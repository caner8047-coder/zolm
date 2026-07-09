<?php

namespace App\Console\Commands;

use App\Models\CrmContact;
use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\PartyRole;
use App\Services\Crm\PartyIdentityResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillPartiesFromCrmCommand extends Command
{
    protected $signature = 'party:backfill-from-crm
        {--user-id= : Sadece belirli kullanıcı için çalıştır}
        {--limit= : Maksimum işlenecek crm_contacts kaydı}
        {--chunk=100 : Chunk başına işlenecek kayıt sayısı}
        {--dry-run : Hiçbir veri yazma, sadece raporla}
        {--force : party_core_enabled kapalıyken gerçek yazmaya izin ver}';

    protected $description = 'Mevcut crm_contacts kayıtlarını parties katmanına güvenli, idempotent şekilde bağlar.';

    public function handle(PartyIdentityResolver $resolver): int
    {
        if (!$this->tablesReady()) {
            $this->error('CRM veya party tabloları hazır değil. Önce migration çalıştırın.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $enabled = $resolver->isEnabled();
        $canWrite = $dryRun || $enabled || $force;

        if (!$canWrite) {
            $this->error('party_core_enabled kapalı. Gerçek backfill için --force verin veya --dry-run ile önizleyin.');

            return self::FAILURE;
        }

        // --force ile config geçici olarak true set edilir; finally içinde eski değere restore edilir.
        $originalEnabled = $enabled;
        $mutatedConfig = false;

        if (!$dryRun && !$enabled && $force) {
            $this->warn('--force ile party_core_enabled kapalıyken gerçek yazma yapılıyor.');
            config()->set('marketplace.features.party_core_enabled', true);
            $mutatedConfig = true;
        }

        try {
            $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
            $limit = max(0, (int) $this->option('limit'));
            $chunkSize = max(1, min(1000, (int) $this->option('chunk')));

            $baseQuery = $this->candidateQuery($userId);
            $totalCandidates = (clone $baseQuery)->count();
            $candidateCount = $limit > 0 ? min($totalCandidates, $limit) : $totalCandidates;

            $this->table(
                ['Alan', 'Değer'],
                [
                    ['Kullanıcı filtresi', $userId !== null ? (string) $userId : '-'],
                    ['Dry run', $dryRun ? 'evet' : 'hayır'],
                    ['party_core_enabled', $originalEnabled ? 'açık' : 'kapalı'],
                    ['--force', $force ? 'evet' : 'hayır'],
                    ['Yazma izni', $canWrite ? 'evet' : 'hayır'],
                    ['Aday contact', (string) $candidateCount],
                    ['Toplam eşleşen', (string) $totalCandidates],
                    ['Limit', $limit > 0 ? (string) $limit : '-'],
                    ['Chunk', (string) $chunkSize],
                ]
            );

            if ($candidateCount === 0) {
                $this->components->info('Backfill için aday contact bulunamadı (party_id boş kayıt yok).');

                return self::SUCCESS;
            }

            $summary = $this->processContacts($resolver, $baseQuery, $limit, $chunkSize, $dryRun);

            $this->table(
                ['Sonuç', 'Değer'],
                [
                    ['İşlenen contact', (string) $summary['processed']],
                    ['Oluşturulan/eşleşen party', (string) $summary['parties']],
                    ['Oluşturulan customer rolü', (string) $summary['roles']],
                    ['Aktarılan identity', (string) $summary['identities']],
                    ['Atlanan (zaten bağlı)', (string) $summary['skipped']],
                    ['Dry run', $dryRun ? 'evet (veri yazılmadı)' : 'hayır'],
                ]
            );

            return self::SUCCESS;
        } finally {
            // --force ile config mutate edildiyse eski değerine restore et.
            if ($mutatedConfig) {
                config()->set('marketplace.features.party_core_enabled', $originalEnabled);
            }
        }
    }
    /**
     * CRM ve party tablolarının hazır olup olmadığını kontrol eder.
     */
    protected function tablesReady(): bool
    {
        return Schema::hasTable('crm_contacts')
            && Schema::hasTable('parties')
            && Schema::hasTable('party_roles')
            && Schema::hasTable('party_identities')
            && Schema::hasColumn('crm_contacts', 'party_id');
    }

    /**
     * party_id boş crm_contacts adaylarını döndürür (tenant izolasyonlu).
     */
    protected function candidateQuery(?int $userId): Builder
    {
        return CrmContact::query()
            ->whereNull('party_id')
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId));
    }

    /**
     * @return array{processed:int, parties:int, roles:int, identities:int, skipped:int}
     */
    protected function processContacts(
        PartyIdentityResolver $resolver,
        Builder $baseQuery,
        int $limit,
        int $chunkSize,
        bool $dryRun,
    ): array {
        $summary = ['processed' => 0, 'parties' => 0, 'roles' => 0, 'identities' => 0, 'skipped' => 0];
        $processed = 0;

        $callback = function ($contacts) use ($resolver, $dryRun, &$summary, &$processed, $limit) {
            foreach ($contacts as $contact) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                $processed++;
                $summary['processed']++;

                if ($dryRun) {
                    $this->line("  [dry-run] contact #{$contact->id}: {$contact->display_name}");
                    continue;
                }

                // Her contact işlemini DB::transaction içine al: party/role/identity
                // yazımları atomik olsun; bir contact'ta hata olursa diğerleri etkilenmesin.
                $result = DB::transaction(fn () => $this->processContact($resolver, $contact));
                $summary['parties'] += $result['party'] ? 1 : 0;
                $summary['roles'] += $result['role'];
                $summary['identities'] += $result['identities'];
            }

            return true;
        };

        // Default chunkById signature: chunkById($count, $callback). Model üzerinden
        // çağrıldığı için kolon/alias belirtmeye gerek yok; Laravel primary key kullanır.
        (clone $baseQuery)
            ->with('identities')
            ->orderBy('crm_contacts.id')
            ->chunkById($chunkSize, $callback);

        return $summary;
    }

    /**
     * Bir contact için önce crm_contact_identities içindeki güçlü identity
     * (external_customer_id + store_id) ile mevcut party bulmaya çalış; bulunursa
     * mevcut party'ye bağla, bulunmazsa PartyIdentityResolver ile yeni party oluştur.
     * Sonra customer rolü upsert ve identity aktar.
     *
     * @return array{party:bool, role:int, identities:int}
     */
    protected function processContact(PartyIdentityResolver $resolver, CrmContact $contact): array
    {
        if (!$contact->relationLoaded('identities')) {
            $contact->load('identities');
        }

        // Önce güçlü identity (external_customer_id + store_id) ile mevcut party ara.
        $existingParty = $this->findExistingPartyFromIdentities($contact);

        if ($existingParty) {
            $party = $existingParty;
        } else {
            $party = $resolver->resolve([
                'user_id' => $contact->user_id,
                'source_type' => 'crm',
                'name' => $contact->display_name,
                'email' => $contact->primary_email,
                'phone' => $contact->primary_phone,
                'tax_number' => $contact->billing_tax_number,
                'city' => $contact->city,
                'district' => $contact->district,
            ]);
        }

        if (!$party) {
            return ['party' => false, 'role' => 0, 'identities' => 0];
        }

        $contact->party_id = $party->id;
        $contact->save();

        $roleCreated = $this->upsertCustomerRole($party, $contact->user_id);
        $identities = $this->transferContactIdentities($party, $contact);

        return ['party' => true, 'role' => $roleCreated ? 1 : 0, 'identities' => $identities];
    }

    /**
     * crm_contact_identities içindeki external_customer_id + store_id kombinasyonuyla
     * mevcut bir party_identity varsa, ilgili party'yi döndür. Böylece resolve öncesi
     * güçlü eşleşme yapılmış olur; yeni party gereksiz yere oluşturulmaz.
     */
    protected function findExistingPartyFromIdentities(CrmContact $contact): ?Party
    {
        foreach ($contact->identities as $identity) {
            $externalId = trim((string) ($identity->external_customer_id ?? ''));
            $storeId = $identity->store_id ? (int) $identity->store_id : null;
            $sourceType = trim((string) ($identity->source_type ?? ''));
            if ($sourceType === '') {
                $sourceType = 'crm';
            }

            if ($externalId === '' || $storeId === null) {
                continue;
            }

            $partyIdentity = PartyIdentity::query()
                ->with('party')
                ->where('user_id', $contact->user_id)
                ->where('source_type', $sourceType)
                ->where('store_id', $storeId)
                ->where('identity_kind', 'external_customer_id')
                ->where('identity_value', $externalId)
                ->first();

            if ($partyIdentity?->party) {
                return $partyIdentity->party;
            }
        }

        return null;
    }

    /**
     * Party için customer rolü upsert eder (unique user_id+party_id+role ile idempotent).
     */
    protected function upsertCustomerRole(Party $party, int $userId): bool
    {
        $role = PartyRole::firstOrCreate(
            [
                'user_id' => $userId,
                'party_id' => $party->id,
                'role' => 'customer',
            ],
            [
                'status' => 'active',
            ],
        );

        return $role->wasRecentlyCreated;
    }

    /**
     * crm_contact_identities içindeki email/phone/external_customer_id/tax_number
     * kimliklerini party_identities'e idempotent şekilde aktarır.
     */
    protected function transferContactIdentities(Party $party, CrmContact $contact): int
    {
        if (!$contact->relationLoaded('identities')) {
            $contact->load('identities');
        }

        $count = 0;

        foreach ($contact->identities as $identity) {
            $count += $this->transferSingleIdentity($party, $identity);
        }

        return $count;
    }

    /**
     * Tek bir crm_contact_identity satırındaki DOLU tüm kimlik alanlarını
     * (external_customer_id, normalized_phone, email, tax_number) party_identity'ye
     * aktarır. İlk bulduğunda return etmez; bir satırda birden fazla dolu alan varsa
     * hepsini aktarır. Aktarılan identity sayısını döndürür.
     */
    protected function transferSingleIdentity(Party $party, $identity): int
    {
        $storeId = $identity->store_id ? (int) $identity->store_id : null;
        $sourceType = trim((string) ($identity->source_type ?? ''));
        if ($sourceType === '') {
            $sourceType = 'crm';
        }

        $count = 0;
        $confidence = (float) ($identity->confidence ?? 0);

        // Sayaç yalnızca yeni kayıt oluşunca artsın; duplicate denemelerde rapor şişmesin.
        // external_customer_id (pazaryeri kaynak kimliği)
        if (!empty($identity->external_customer_id)) {
            $created = PartyIdentity::firstOrCreate(
                [
                    'user_id' => $party->user_id,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'external_customer_id',
                    'identity_value' => $identity->external_customer_id,
                ],
                [
                    'party_id' => $party->id,
                    'external_id' => $identity->external_customer_id,
                    'confidence' => $confidence,
                ],
            );
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        // phone
        if (!empty($identity->normalized_phone)) {
            $created = PartyIdentity::firstOrCreate(
                [
                    'user_id' => $party->user_id,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'phone',
                    'identity_value' => $identity->normalized_phone,
                ],
                [
                    'party_id' => $party->id,
                    'confidence' => $confidence,
                ],
            );
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        // email
        if (!empty($identity->email)) {
            $created = PartyIdentity::firstOrCreate(
                [
                    'user_id' => $party->user_id,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'email',
                    'identity_value' => $identity->email,
                ],
                [
                    'party_id' => $party->id,
                    'confidence' => $confidence,
                ],
            );
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        // tax_number
        if (!empty($identity->tax_number)) {
            $created = PartyIdentity::firstOrCreate(
                [
                    'user_id' => $party->user_id,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'tax_number',
                    'identity_value' => $identity->tax_number,
                ],
                [
                    'party_id' => $party->id,
                    'confidence' => $confidence,
                ],
            );
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }
}

