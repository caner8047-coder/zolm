<?php

namespace App\Services\Crm;

use App\Models\Party;
use App\Models\PartyIdentity;
use Illuminate\Support\Str;

/**
 * Party bazlı kimlik çözümleyici iskeleti.
 *
 * Mevcut CrmIdentityResolver'a dokunmadan, paralel çalışan party katmanı
 * kimlik çözümleyicisidir. Sıralı eşleştirme mantığını CrmIdentityResolver'dan
 * miras alır: kaynak kimliği + mağaza → telefon → e-posta → vergi no → ad + şehir.
 *
 * Bu servis yalnızca party_core_enabled feature flag açıkça true olmadığında
 * hiçbir canlı akışı etkilemez. Mevcut CRM projection davranışı değişmez.
 */
class PartyIdentityResolver
{
    /**
     * Party core feature flag'inin açık olup olmadığını döndürür.
     */
    public function isEnabled(): bool
    {
        return (bool) config('marketplace.features.party_core_enabled', false);
    }

    /**
     * @param array{
     *     user_id:int,
     *     store_id?:int|null,
     *     source_type:string,
     *     external_customer_id?:string|null,
     *     name?:string|null,
     *     email?:string|null,
     *     phone?:string|null,
     *     tax_number?:string|null,
     *     city?:string|null,
     *     district?:string|null,
     *     confidence?:float|int|null,
     *     raw_payload?:array|null
     * } $data
     */
    public function resolve(array $data): ?Party
    {
        // Feature flag kapalıysa hiçbir party/identity oluşturmaz; canlı akış bozulmaz.
        if (!$this->isEnabled()) {
            return null;
        }

        $userId = (int) $data['user_id'];
        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;
        $sourceType = trim((string) ($data['source_type'] ?? 'manual'));
        // Boş string gelirse manual kabul edilir.
        if ($sourceType === '') {
            $sourceType = 'manual';
        }
        $externalId = $this->cleanNullable($data['external_customer_id'] ?? null);
        $name = $this->cleanNullable($data['name'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $phone = $this->cleanNullable($data['phone'] ?? null);
        $normalizedPhone = $this->normalizePhone($phone);
        $taxNumber = $this->normalizeTaxNumber($data['tax_number'] ?? null);
        $city = $this->cleanNullable($data['city'] ?? null);
        $normalizedName = $this->normalizeName($name);

        $party = $this->findParty(
            $userId,
            $storeId,
            $sourceType,
            $externalId,
            $email,
            $normalizedPhone,
            $taxNumber,
            $normalizedName,
            $city,
        );

        if (!$party) {
            $party = $this->createParty([
                'user_id' => $userId,
                'display_name' => $name ?: 'İsimsiz Müşteri',
                'normalized_name' => $normalizedName,
                'primary_email' => $email,
                'primary_phone' => $phone,
                'normalized_phone' => $normalizedPhone,
                'tax_number' => $taxNumber,
                'city' => $city,
            ]);
        } else {
            $party->fill(array_filter([
                'display_name' => $party->display_name === 'İsimsiz Müşteri' && $name ? $name : null,
                'normalized_name' => $party->normalized_name ?: $normalizedName,
                'primary_email' => $party->primary_email ?: $email,
                'primary_phone' => $party->primary_phone ?: $phone,
                'normalized_phone' => $party->normalized_phone ?: $normalizedPhone,
                'tax_number' => $party->tax_number ?: $taxNumber,
                'city' => $party->city ?: $city,
            ], fn ($value) => $value !== null && $value !== ''));

            if ($party->isDirty()) {
                $party->save();
            }
        }

        $this->upsertIdentity($party, [
            'user_id' => $userId,
            'store_id' => $storeId,
            'source_type' => $sourceType,
            'external_id' => $externalId,
            'email' => $email,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'name' => $name,
            'tax_number' => $taxNumber,
            'city' => $city,
            'confidence' => (float) ($data['confidence'] ?? 0),
            'raw_payload' => $data['raw_payload'] ?? null,
        ]);

        return $party;
    }
    /**
     * Sıralı eşleştirmeyle mevcut party bulur (bulamazsa null).
     */
    public function findParty(
        int $userId,
        ?int $storeId,
        string $sourceType,
        ?string $externalId,
        ?string $email,
        ?string $normalizedPhone,
        ?string $taxNumber,
        ?string $normalizedName,
        ?string $city,
    ): ?Party {
        if ($externalId && $storeId) {
            $identity = $this->findIdentity($userId, $sourceType, $storeId, 'external_customer_id', $externalId);
            if ($identity?->party) {
                return $identity->party;
            }
        }

        if ($normalizedPhone) {
            $identity = $this->findIdentity($userId, $sourceType, null, 'phone', $normalizedPhone);
            if ($identity?->party) {
                return $identity->party;
            }

            $party = Party::query()
                ->where('user_id', $userId)
                ->where('normalized_phone', $normalizedPhone)
                ->first();

            if ($party) {
                return $party;
            }
        }

        if ($email) {
            $identity = $this->findIdentity($userId, $sourceType, null, 'email', $email);
            if ($identity?->party) {
                return $identity->party;
            }

            $party = Party::query()
                ->where('user_id', $userId)
                ->where('primary_email', $email)
                ->first();

            if ($party) {
                return $party;
            }
        }

        if ($taxNumber) {
            $party = Party::query()
                ->where('user_id', $userId)
                ->where('tax_number', $taxNumber)
                ->first();

            if ($party) {
                return $party;
            }
        }

        if ($normalizedName && $city) {
            return Party::query()
                ->where('user_id', $userId)
                ->where('normalized_name', $normalizedName)
                ->where('city', $city)
                ->first();
        }

        return null;
    }

    /**
     * Party identity kaydını (kaynak+mağaza+tip+değer) ile arar.
     */
    public function findIdentity(
        int $userId,
        string $sourceType,
        ?int $storeId,
        string $identityKind,
        string $identityValue,
    ): ?PartyIdentity {
        // store_id null ise açıkça whereNull('store_id') ile sınırla;
        // store'a bağlı identity'yi yanlışlıkla bulmayı önler.
        // store_id doluysa where('store_id', $storeId) ile sınırla.
        return PartyIdentity::query()
            ->with('party')
            ->where('user_id', $userId)
            ->where('source_type', $sourceType)
            ->when(
                $storeId === null,
                fn ($q) => $q->whereNull('store_id'),
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->where('identity_kind', $identityKind)
            ->where('identity_value', $identityValue)
            ->first();
    }

    /**
     * Yeni party kaydı oluşturur. İlk faz default party_type=unknown.
     */
    public function createParty(array $attributes): Party
    {
        return Party::create(array_merge([
            'party_type' => 'unknown',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Çözülen party için kaynak kimlik kaydını upsert eder.
     */
    public function upsertIdentity(Party $party, array $attributes): PartyIdentity
    {
        $userId = (int) $attributes['user_id'];
        $storeId = isset($attributes['store_id']) ? (int) $attributes['store_id'] : null;
        $sourceType = trim((string) ($attributes['source_type'] ?? 'manual'));
        $confidence = (float) ($attributes['confidence'] ?? 0);
        $rawPayload = $attributes['raw_payload'] ?? null;

        if (!empty($attributes['external_id']) && $storeId) {
            return PartyIdentity::updateOrCreate(
                [
                    'user_id' => $userId,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'external_customer_id',
                    'identity_value' => $attributes['external_id'],
                ],
                [
                    'party_id' => $party->id,
                    'external_id' => $attributes['external_id'],
                    'confidence' => $confidence,
                    'raw_payload' => $rawPayload,
                ],
            );
        }

        if (!empty($attributes['normalized_phone'])) {
            return PartyIdentity::updateOrCreate(
                [
                    'user_id' => $userId,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'phone',
                    'identity_value' => $attributes['normalized_phone'],
                ],
                [
                    'party_id' => $party->id,
                    'confidence' => $confidence,
                    'raw_payload' => $rawPayload,
                ],
            );
        }

        if (!empty($attributes['email'])) {
            return PartyIdentity::updateOrCreate(
                [
                    'user_id' => $userId,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'email',
                    'identity_value' => $attributes['email'],
                ],
                [
                    'party_id' => $party->id,
                    'confidence' => $confidence,
                    'raw_payload' => $rawPayload,
                ],
            );
        }

        return $this->upsertFallbackIdentity($party, $attributes, $userId, $storeId, $sourceType, $confidence, $rawPayload);
    }

    /**
     * En zayıf sinyaller (tax_number, name) için identity upsert. Sıralı
     * eşleştirmede isim tek başına güvenilir değildir; yalnızca son çare olarak yazılır.
     */
    protected function upsertFallbackIdentity(
        Party $party,
        array $attributes,
        int $userId,
        ?int $storeId,
        string $sourceType,
        float $confidence,
        ?array $rawPayload,
    ): PartyIdentity {
        if (!empty($attributes['tax_number'])) {
            return PartyIdentity::updateOrCreate(
                [
                    'user_id' => $userId,
                    'source_type' => $sourceType,
                    'store_id' => $storeId,
                    'identity_kind' => 'tax_number',
                    'identity_value' => $attributes['tax_number'],
                ],
                [
                    'party_id' => $party->id,
                    'confidence' => $confidence,
                    'raw_payload' => $rawPayload,
                ],
            );
        }

        $nameValue = $this->normalizeName($attributes['name'] ?? null) ?? 'unknown';

        return PartyIdentity::updateOrCreate(
            [
                'user_id' => $userId,
                'source_type' => $sourceType,
                'store_id' => $storeId,
                'identity_kind' => 'name',
                'identity_value' => $nameValue,
            ],
            [
                'party_id' => $party->id,
                'confidence' => $confidence,
                'raw_payload' => $rawPayload,
            ],
        );
    }

    protected function normalizeEmail(?string $email): ?string
    {
        $clean = $this->cleanNullable($email);

        return $clean ? Str::lower($clean) : null;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $clean = $this->cleanNullable($phone);

        if ($clean === null) {
            return null;
        }

        return preg_replace('/\D+/', '', $clean);
    }

    protected function normalizeName(?string $name): ?string
    {
        $clean = $this->cleanNullable($name);

        if ($clean === null) {
            return null;
        }

        return Str::slug($clean, ' ');
    }

    protected function normalizeTaxNumber(?string $taxNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $taxNumber);

        return $digits !== '' ? $digits : null;
    }

    protected function cleanNullable(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}

