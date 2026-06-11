<?php

namespace App\Livewire\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\LegalEntity;
use App\Services\Cargo\SuratCargoConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SuratIntegrationSettings extends Component
{
    public ?int $editingAccountId = null;
    public string $message = '';
    public string $messageTone = 'info';

    public array $form = [
        'legal_entity_id' => '',
        'environment' => 'live',
        'account_name' => '',
        'customer_code' => '',
        'sender_username' => '',
        'sender_password' => '',
        'query_password' => '',
        'cod_username' => '',
        'cod_password' => '',
        'api_base_url' => '',
        'query_base_url' => '',
        'branch_code' => '',
        'origin_city' => 'Denizli',
        'origin_district' => '',
        'origin_address' => '',
        'contact_name' => '',
        'contact_phone' => '',
        'is_default' => true,
        'is_active' => true,
        'test_endpoint' => '',
        'test_reference' => '',
        'create_endpoint' => '',
        'track_endpoint' => '',
        'cancel_endpoint' => '',
        'recall_endpoint' => '',
        'invoice_endpoint' => '',
        'soap_wsdl_url' => '',
    ];

    protected array $rules = [
        'form.legal_entity_id' => 'nullable|integer',
        'form.environment' => 'required|in:live,test',
        'form.account_name' => 'nullable|string|max:150',
        'form.customer_code' => 'required|string|max:80',
        'form.sender_username' => 'nullable|string|max:120',
        'form.sender_password' => 'nullable|string|max:255',
        'form.query_password' => 'nullable|string|max:255',
        'form.cod_username' => 'nullable|string|max:120',
        'form.cod_password' => 'nullable|string|max:255',
        'form.api_base_url' => 'nullable|string|max:255',
        'form.query_base_url' => 'nullable|string|max:255',
        'form.branch_code' => 'nullable|string|max:80',
        'form.origin_city' => 'nullable|string|max:120',
        'form.origin_district' => 'nullable|string|max:120',
        'form.origin_address' => 'nullable|string|max:2000',
        'form.contact_name' => 'nullable|string|max:150',
        'form.contact_phone' => 'nullable|string|max:40',
        'form.is_default' => 'boolean',
        'form.is_active' => 'boolean',
        'form.test_endpoint' => 'nullable|string|max:255',
        'form.test_reference' => 'nullable|string|max:120',
        'form.create_endpoint' => 'nullable|string|max:255',
        'form.track_endpoint' => 'nullable|string|max:255',
        'form.cancel_endpoint' => 'nullable|string|max:255',
        'form.recall_endpoint' => 'nullable|string|max:255',
        'form.invoice_endpoint' => 'nullable|string|max:255',
        'form.soap_wsdl_url' => 'nullable|string|max:255',
    ];

    public function mount(): void
    {
        $this->applySuratDefaults('live', false);
    }

    #[Computed]
    public function tableReady(): bool
    {
        return Schema::hasTable('cargo_carrier_accounts');
    }

    #[Computed]
    public function accounts()
    {
        if (!$this->tableReady) {
            return collect();
        }

        return CargoCarrierAccount::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->latest('is_default')
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function legalEntities()
    {
        if (!Schema::hasTable('legal_entities')) {
            return collect();
        }

        return LegalEntity::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function editAccount(int $accountId): void
    {
        $account = CargoCarrierAccount::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->findOrFail($accountId);

        $this->editingAccountId = $account->id;
        $environment = (string) data_get($account->settings_json, 'environment', 'live');
        $defaults = $this->suratDefaults($environment === 'test' ? 'test' : 'live');

        $this->form = array_merge($this->form, [
            'legal_entity_id' => (string) ($account->legal_entity_id ?? ''),
            'environment' => $environment === 'test' ? 'test' : 'live',
            'account_name' => (string) ($account->account_name ?? ''),
            'customer_code' => (string) ($account->customer_code ?? ''),
            'sender_username' => (string) ($account->sender_username ?? ''),
            'sender_password' => '',
            'query_password' => '',
            'cod_username' => (string) ($account->cod_username ?? ''),
            'cod_password' => '',
            'api_base_url' => (string) ($account->api_base_url ?: $defaults['api_base_url']),
            'query_base_url' => (string) ($account->query_base_url ?: $defaults['query_base_url']),
            'branch_code' => (string) ($account->branch_code ?? ''),
            'origin_city' => (string) ($account->origin_city ?? 'Denizli'),
            'origin_district' => (string) ($account->origin_district ?? ''),
            'origin_address' => (string) ($account->origin_address ?? ''),
            'contact_name' => (string) ($account->contact_name ?? ''),
            'contact_phone' => (string) ($account->contact_phone ?? ''),
            'is_default' => (bool) $account->is_default,
            'is_active' => (bool) $account->is_active,
            'test_endpoint' => (string) (data_get($account->settings_json, 'endpoints.test_connection') ?: $defaults['test_endpoint']),
            'test_reference' => (string) data_get($account->settings_json, 'test_reference', ''),
            'create_endpoint' => (string) (data_get($account->settings_json, 'endpoints.create_shipment') ?: $defaults['create_endpoint']),
            'track_endpoint' => (string) (data_get($account->settings_json, 'endpoints.track_shipment') ?: $defaults['track_endpoint']),
            'cancel_endpoint' => (string) (data_get($account->settings_json, 'endpoints.cancel_shipment') ?: $defaults['cancel_endpoint']),
            'recall_endpoint' => (string) (data_get($account->settings_json, 'endpoints.recall_shipment') ?: $defaults['recall_endpoint']),
            'invoice_endpoint' => (string) data_get($account->settings_json, 'endpoints.invoice_lines', ''),
            'soap_wsdl_url' => (string) (data_get($account->settings_json, 'soap_wsdl_url') ?: $defaults['soap_wsdl_url']),
        ]);
    }

    public function newAccount(): void
    {
        $this->editingAccountId = null;
        $this->reset('message');
        $this->form = array_merge($this->form, [
            'legal_entity_id' => '',
            'environment' => 'live',
            'account_name' => '',
            'customer_code' => '',
            'sender_username' => '',
            'sender_password' => '',
            'query_password' => '',
            'cod_username' => '',
            'cod_password' => '',
            'api_base_url' => '',
            'query_base_url' => '',
            'branch_code' => '',
            'origin_city' => 'Denizli',
            'origin_district' => '',
            'origin_address' => '',
            'contact_name' => '',
            'contact_phone' => '',
            'is_default' => true,
            'is_active' => true,
            'test_endpoint' => '',
            'test_reference' => '',
            'create_endpoint' => '',
            'track_endpoint' => '',
            'cancel_endpoint' => '',
            'recall_endpoint' => '',
            'invoice_endpoint' => '',
            'soap_wsdl_url' => '',
        ]);

        $this->applySuratDefaults('live', false);
    }

    public function applyLiveDefaults(): void
    {
        $this->applySuratDefaults('live');
    }

    public function applyTestDefaults(): void
    {
        $this->applySuratDefaults('test');
    }

    public function updatedFormEnvironment(string $environment): void
    {
        $this->applySuratDefaults($environment);
    }

    protected function applySuratDefaults(string $environment, bool $showMessage = true): void
    {
        $environment = $environment === 'test' ? 'test' : 'live';
        $defaults = $this->suratDefaults($environment);

        $this->form['environment'] = $environment;
        $this->form['api_base_url'] = $defaults['api_base_url'];
        $this->form['query_base_url'] = $defaults['query_base_url'];
        $this->form['test_endpoint'] = $defaults['test_endpoint'];
        $this->form['create_endpoint'] = $defaults['create_endpoint'];
        $this->form['track_endpoint'] = $defaults['track_endpoint'];
        $this->form['cancel_endpoint'] = $defaults['cancel_endpoint'];
        $this->form['recall_endpoint'] = $defaults['recall_endpoint'];
        $this->form['soap_wsdl_url'] = $defaults['soap_wsdl_url'];

        if ($showMessage) {
            $this->showMessage($environment === 'test' ? 'Sürat prova ortamı endpointleri dolduruldu.' : 'Sürat canlı ortam endpointleri dolduruldu.', 'info');
        }
    }

    protected function suratDefaults(string $environment): array
    {
        if ($environment === 'test') {
            return [
                'api_base_url' => 'https://api02.suratkargo.com.tr',
                'query_base_url' => 'https://api02.suratkargo.com.tr',
                'soap_wsdl_url' => 'https://prova.suratkargo.com.tr/services.asmx?WSDL',
                'test_endpoint' => '/api/KargoTakipHareketDetayi',
                'create_endpoint' => '/api/GonderiyiKargoyaGonder',
                'track_endpoint' => '/api/KargoTakipHareketDetayi',
                'cancel_endpoint' => '/api/GonderiSil',
                'recall_endpoint' => '/api/GonderiGeriCek',
            ];
        }

        return [
            'api_base_url' => 'https://api01.suratkargo.com.tr',
            'query_base_url' => 'https://api01.suratkargo.com.tr',
            'soap_wsdl_url' => 'https://webservices.suratkargo.com.tr/services.asmx?WSDL',
            'test_endpoint' => '/api/KargoTakipHareketDetayi',
            'create_endpoint' => '/api/GonderiyiKargoyaGonder',
            'track_endpoint' => '/api/KargoTakipHareketDetayi',
            'cancel_endpoint' => '/api/GonderiSil',
            'recall_endpoint' => '/api/GonderiGeriCek',
        ];
    }

    public function save(): void
    {
        if (!$this->tableReady) {
            $this->showMessage('Sürat entegrasyon tabloları henüz hazır değil. Migration çalıştırılmalı.', 'warning');
            return;
        }

        if (($this->form['legal_entity_id'] ?? '') === '') {
            $this->form['legal_entity_id'] = null;
        }

        $validated = $this->validate()['form'];
        $userId = auth()->id();

        DB::transaction(function () use ($validated, $userId) {
            $account = $this->editingAccountId
                ? CargoCarrierAccount::query()->where('user_id', $userId)->findOrFail($this->editingAccountId)
                : new CargoCarrierAccount(['user_id' => $userId, 'carrier_code' => 'surat']);

            if ((bool) $validated['is_default']) {
                CargoCarrierAccount::query()
                    ->where('user_id', $userId)
                    ->where('carrier_code', 'surat')
                    ->when($account->exists, fn ($query) => $query->whereKeyNot($account->id))
                    ->update(['is_default' => false]);
            }

            $account->fill([
                'legal_entity_id' => filled($validated['legal_entity_id'] ?? null) ? (int) $validated['legal_entity_id'] : null,
                'carrier_code' => 'surat',
                'carrier_name' => 'Sürat Kargo',
                'account_name' => $validated['account_name'] ?: 'Sürat Kargo',
                'customer_code' => $validated['customer_code'],
                'sender_username' => $validated['sender_username'] ?: $validated['customer_code'],
                'cod_username' => $validated['cod_username'] ?: null,
                'api_base_url' => $validated['api_base_url'] ?: null,
                'query_base_url' => $validated['query_base_url'] ?: null,
                'branch_code' => $validated['branch_code'] ?: null,
                'origin_city' => $validated['origin_city'] ?: null,
                'origin_district' => $validated['origin_district'] ?: null,
                'origin_address' => $validated['origin_address'] ?: null,
                'contact_name' => $validated['contact_name'] ?: null,
                'contact_phone' => $validated['contact_phone'] ?: null,
                'is_default' => (bool) $validated['is_default'],
                'is_active' => (bool) $validated['is_active'],
                'status' => (bool) $validated['is_active'] ? 'configured' : 'inactive',
                'settings_json' => [
                    'environment' => $validated['environment'],
                    'soap_wsdl_url' => $validated['soap_wsdl_url'] ?: null,
                    'test_reference' => $validated['test_reference'] ?: null,
                    'endpoints' => array_filter([
                        'test_connection' => $validated['test_endpoint'] ?: null,
                        'create_shipment' => $validated['create_endpoint'] ?: null,
                        'track_shipment' => $validated['track_endpoint'] ?: null,
                        'cancel_shipment' => $validated['cancel_endpoint'] ?: null,
                        'recall_shipment' => $validated['recall_endpoint'] ?: null,
                        'invoice_lines' => $validated['invoice_endpoint'] ?: null,
                    ]),
                ],
            ]);

            if (filled($validated['sender_password'] ?? null)) {
                $account->sender_password_encrypted = $validated['sender_password'];
            }

            if (filled($validated['query_password'] ?? null)) {
                $account->query_password_encrypted = $validated['query_password'];
            }

            if (filled($validated['cod_password'] ?? null)) {
                $account->cod_password_encrypted = $validated['cod_password'];
            }

            $account->save();
            $this->editingAccountId = $account->id;
        });

        $this->form['sender_password'] = '';
        $this->form['query_password'] = '';
        $this->form['cod_password'] = '';
        $this->showMessage('Sürat hesap bilgileri kaydedildi.', 'success');
    }

    public function testConnection(int $accountId): void
    {
        $account = CargoCarrierAccount::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->findOrFail($accountId);

        try {
            $result = app(SuratCargoConnector::class)->testConnection($account);
            $account->forceFill([
                'last_verified_at' => ($result['success'] ?? false) ? now() : null,
                'status' => ($result['success'] ?? false) ? 'verified' : $account->status,
                'last_error' => ($result['success'] ?? false) ? null : ($result['message'] ?? 'Bağlantı testi tamamlanamadı.'),
            ])->save();

            $this->showMessage($result['message'] ?? 'Bağlantı testi tamamlandı.', ($result['success'] ?? false) ? 'success' : 'warning');
        } catch (\Throwable $exception) {
            $account->forceFill([
                'last_error' => $exception->getMessage(),
            ])->save();

            $this->showMessage($exception->getMessage(), 'warning');
        }
    }

    protected function showMessage(string $message, string $tone = 'info'): void
    {
        $this->message = $message;
        $this->messageTone = $tone;
    }

    public function render()
    {
        return view('livewire.cargo.surat-integration-settings');
    }
}
