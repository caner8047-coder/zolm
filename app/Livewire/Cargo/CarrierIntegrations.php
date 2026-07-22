<?php

namespace App\Livewire\Cargo;

use App\Models\CargoCarrierAccount;
use App\Services\Cargo\CargoCarrierManager;
use App\Services\Cargo\CargoCarrierRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CarrierIntegrations extends Component
{
    public ?string $selectedCarrierCode = null;

    public ?int $editingAccountId = null;

    public array $form = [];

    public ?string $feedback = null;

    public string $feedbackTone = 'success';

    #[Computed]
    public function carriers(): array
    {
        $accounts = Schema::hasTable('cargo_carrier_accounts') && auth()->id()
            ? CargoCarrierAccount::query()
                ->where('user_id', auth()->id())
                ->get()
                ->groupBy('carrier_code')
            : collect();

        return collect(app(CargoCarrierRegistry::class)->all())
            ->map(function (array $carrier, string $code) use ($accounts) {
                $carrierAccounts = $accounts->get($code, collect());
                $connected = $carrierAccounts->whereIn('status', ['connected', 'verified'])->count();
                $carrier['account_count'] = $carrierAccounts->count();
                $carrier['connected_count'] = $connected;
                $carrier['has_error'] = $carrierAccounts->where('status', 'error')->isNotEmpty();

                return $carrier;
            })
            ->all();
    }

    #[Computed]
    public function selectedCarrier(): ?array
    {
        return $this->selectedCarrierCode
            ? app(CargoCarrierRegistry::class)->find($this->selectedCarrierCode)
            : null;
    }

    #[Computed]
    public function legalEntities()
    {
        return auth()->user()?->legalEntities()->active()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function carrierAccounts()
    {
        if (! $this->selectedCarrierCode || ! auth()->id()) {
            return collect();
        }

        return CargoCarrierAccount::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', $this->selectedCarrierCode)
            ->latest('is_default')
            ->latest('id')
            ->get();
    }

    public function openSetup(string $carrierCode, ?int $accountId = null): mixed
    {
        $carrier = app(CargoCarrierRegistry::class)->get($carrierCode);

        if (($carrier['integration_status'] ?? null) === 'marketplace_managed') {
            return $this->redirectRoute('mp.integrations', navigate: true);
        }

        if (blank($carrier['connector'] ?? null)) {
            $this->feedbackTone = 'warning';
            $this->feedback = "{$carrier['name']} şu anda ZOLM'de yalnızca rapor ve takip bağlantısı olarak kullanılabilir.";

            return null;
        }

        $this->selectedCarrierCode = $carrier['key'];
        $this->editingAccountId = $accountId;
        $this->feedback = null;
        $account = $accountId
            ? CargoCarrierAccount::query()
                ->where('user_id', auth()->id())
                ->where('carrier_code', $carrier['key'])
                ->findOrFail($accountId)
            : null;
        $credentials = $this->accountCredentialValues($account, $carrier);
        $this->form = [
            'legal_entity_id' => $account?->legal_entity_id,
            'account_name' => $account?->account_name ?: $carrier['name'].' Hesabı',
            'customer_code' => $account?->customer_code,
            'environment' => data_get($credentials, 'environment', $carrier['key'] === 'surat' ? 'live' : 'test'),
            'origin_city' => $account?->origin_city,
            'origin_district' => $account?->origin_district,
            'origin_address' => $account?->origin_address,
            'contact_name' => $account?->contact_name,
            'contact_phone' => $account?->contact_phone,
            'is_default' => $account?->is_default ?? true,
            'is_active' => $account?->is_active ?? true,
            'credentials' => [],
        ];

        foreach ($carrier['setup_fields'] ?? [] as $field) {
            $key = $field['key'];
            $this->form['credentials'][$key] = ($field['secret'] ?? false)
                ? ''
                : data_get($credentials, $key, $field['default'] ?? '');
        }

        return null;
    }

    public function updatedFormEnvironment(string $environment): void
    {
        if ($this->selectedCarrierCode !== 'surat') {
            return;
        }

        foreach ($this->suratDefaults($environment) as $key => $value) {
            data_set($this->form, "credentials.{$key}", $value);
        }
    }

    public function closeSetup(): void
    {
        $this->reset(['selectedCarrierCode', 'editingAccountId', 'form']);
        $this->resetValidation();
    }

    public function saveAccount(): void
    {
        $carrier = $this->selectedCarrier;
        abort_unless($carrier && auth()->id(), 403);

        $this->validate([
            'form.legal_entity_id' => [
                'nullable',
                'integer',
                Rule::exists('legal_entities', 'id')->where('user_id', auth()->id()),
            ],
            'form.account_name' => ['required', 'string', 'max:150'],
            'form.customer_code' => ['nullable', 'string', 'max:80'],
            'form.environment' => ['required', 'in:test,live'],
            'form.origin_city' => ['nullable', 'string', 'max:120'],
            'form.origin_district' => ['nullable', 'string', 'max:120'],
            'form.origin_address' => ['nullable', 'string', 'max:1000'],
            'form.contact_name' => ['nullable', 'string', 'max:150'],
            'form.contact_phone' => ['nullable', 'string', 'max:40'],
            'form.is_default' => ['boolean'],
            'form.is_active' => ['boolean'],
        ]);

        $account = $this->editingAccountId
            ? CargoCarrierAccount::query()
                ->where('user_id', auth()->id())
                ->where('carrier_code', $carrier['key'])
                ->findOrFail($this->editingAccountId)
            : new CargoCarrierAccount(['user_id' => auth()->id(), 'carrier_code' => $carrier['key']]);
        $originalCredentials = $this->accountCredentialValues($account, $carrier);
        $credentials = $originalCredentials;
        $errors = [];

        foreach ($carrier['setup_fields'] ?? [] as $field) {
            $key = $field['key'];
            if (($field['type'] ?? null) === 'checkbox') {
                data_set($credentials, $key, (bool) data_get($this->form, "credentials.{$key}", false));

                continue;
            }
            $value = trim((string) data_get($this->form, "credentials.{$key}", ''));
            if ($value !== '') {
                data_set($credentials, $key, $value);
            }
            if (($field['required'] ?? false) && blank(data_get($credentials, $key))) {
                $errors["form.credentials.{$key}"] = "{$field['label']} alanı zorunludur.";
            }
        }

        if (($carrier['key'] ?? null) === 'ptt') {
            foreach (['customer_id' => '/^\d{9,10}$/', 'barcode_start' => '/^\d{12}$/', 'barcode_end' => '/^\d{12}$/'] as $key => $pattern) {
                if (! preg_match($pattern, (string) data_get($credentials, $key, ''))) {
                    $errors["form.credentials.{$key}"] = match ($key) {
                        'customer_id' => 'PTT müşteri numarası 9 veya 10 haneli olmalıdır.',
                        default => 'PTT barkod aralığı 12 haneli olmalıdır.',
                    };
                }
            }
            $postalCheque = (string) data_get($credentials, 'postal_cheque_number', '');
            if ($postalCheque !== '' && ! preg_match('/^\d{8}$/', $postalCheque)) {
                $errors['form.credentials.postal_cheque_number'] = 'Posta Çeki No 8 haneli olmalıdır.';
            }
            if ((int) data_get($credentials, 'barcode_start', 0) > (int) data_get($credentials, 'barcode_end', 0)) {
                $errors['form.credentials.barcode_end'] = 'Son barkod aralığı ilk değerden küçük olamaz.';
            }
            if (data_get($originalCredentials, 'barcode_start') !== data_get($credentials, 'barcode_start')
                || data_get($originalCredentials, 'barcode_end') !== data_get($credentials, 'barcode_end')) {
                data_forget($credentials, 'next_barcode');
            }
        }

        if (($carrier['key'] ?? null) === 'surat' && blank($this->form['customer_code'] ?? null)) {
            $errors['form.customer_code'] = 'Sürat müşteri kodu zorunludur.';
        }

        if ($errors !== []) {
            foreach ($errors as $key => $message) {
                $this->addError($key, $message);
            }

            return;
        }

        data_set($credentials, 'environment', $this->form['environment']);

        DB::transaction(function () use ($account, $carrier, $credentials) {
            if ($this->form['is_default']) {
                CargoCarrierAccount::query()
                    ->where('user_id', auth()->id())
                    ->where('carrier_code', $carrier['key'])
                    ->when($account->exists, fn ($query) => $query->whereKeyNot($account->getKey()))
                    ->update(['is_default' => false]);
            }

            $isSurat = $carrier['key'] === 'surat';
            $attributes = [
                'legal_entity_id' => $this->form['legal_entity_id'] ?: null,
                'carrier_name' => $carrier['name'],
                'account_name' => $this->form['account_name'],
                'customer_code' => $this->form['customer_code'] ?: data_get($credentials, 'customer_id'),
                'origin_city' => $this->form['origin_city'] ?: null,
                'origin_district' => $this->form['origin_district'] ?: null,
                'origin_address' => $this->form['origin_address'] ?: null,
                'contact_name' => $this->form['contact_name'] ?: null,
                'contact_phone' => $this->form['contact_phone'] ?: null,
                'is_default' => (bool) $this->form['is_default'],
                'is_active' => (bool) $this->form['is_active'],
                'status' => 'saved',
                'last_verified_at' => null,
                'last_error' => null,
            ];

            if ($isSurat) {
                $settings = $account->settings_json ?? [];
                data_set($settings, 'environment', $this->form['environment']);
                data_set($settings, 'test_reference', data_get($credentials, 'test_reference') ?: null);
                data_set($settings, 'soap_wsdl_url', data_get($credentials, 'soap_wsdl_url') ?: null);
                $attributes = array_merge($attributes, [
                    'sender_username' => data_get($credentials, 'sender_username') ?: $this->form['customer_code'],
                    'cod_username' => data_get($credentials, 'cod_username') ?: null,
                    'api_base_url' => data_get($credentials, 'api_base_url') ?: null,
                    'query_base_url' => data_get($credentials, 'query_base_url') ?: null,
                    'branch_code' => data_get($credentials, 'branch_code') ?: null,
                    'settings_json' => $settings,
                ]);
            } else {
                $attributes['credentials_encrypted'] = $credentials;
            }

            $account->fill($attributes);

            if ($isSurat) {
                foreach ([
                    'sender_password' => 'sender_password_encrypted',
                    'query_password' => 'query_password_encrypted',
                    'cod_password' => 'cod_password_encrypted',
                ] as $formKey => $column) {
                    $value = trim((string) data_get($this->form, "credentials.{$formKey}", ''));
                    if ($value !== '') {
                        $account->{$column} = $value;
                    }
                }
            }

            $account->save();
        });

        $this->editingAccountId = $account->id;
        $this->feedbackTone = 'success';
        $this->feedback = 'Hesap bilgileri şifrelenerek kaydedildi. Şimdi bağlantıyı test edebilirsiniz.';
        unset($this->carriers, $this->carrierAccounts);
    }

    /**
     * @return array<string, mixed>
     */
    protected function accountCredentialValues(?CargoCarrierAccount $account, array $carrier): array
    {
        if (($carrier['key'] ?? null) !== 'surat') {
            return $account?->credentials_encrypted ?? [];
        }

        $environment = (string) data_get($account?->settings_json, 'environment', 'live');
        $defaults = $this->suratDefaults($environment);

        return [
            'environment' => $environment,
            'sender_username' => (string) ($account?->sender_username ?? ''),
            'sender_password' => (string) ($account?->sender_password_encrypted ?? ''),
            'query_password' => (string) ($account?->query_password_encrypted ?? ''),
            'cod_username' => (string) ($account?->cod_username ?? ''),
            'cod_password' => (string) ($account?->cod_password_encrypted ?? ''),
            'branch_code' => (string) ($account?->branch_code ?? ''),
            'test_reference' => (string) data_get($account?->settings_json, 'test_reference', ''),
            'api_base_url' => (string) ($account?->api_base_url ?: $defaults['api_base_url']),
            'query_base_url' => (string) ($account?->query_base_url ?: $defaults['query_base_url']),
            'soap_wsdl_url' => (string) (data_get($account?->settings_json, 'soap_wsdl_url') ?: $defaults['soap_wsdl_url']),
        ];
    }

    /**
     * @return array{api_base_url:string, query_base_url:string, soap_wsdl_url:string}
     */
    protected function suratDefaults(string $environment): array
    {
        $test = $environment === 'test';

        return [
            'api_base_url' => (string) config($test ? 'cargo.integrations.surat.test_base_url' : 'cargo.integrations.surat.base_url'),
            'query_base_url' => (string) config($test ? 'cargo.integrations.surat.test_base_url' : 'cargo.integrations.surat.query_base_url'),
            'soap_wsdl_url' => (string) config($test ? 'cargo.integrations.surat.test_soap_wsdl_url' : 'cargo.integrations.surat.soap_wsdl_url'),
        ];
    }

    public function testAccount(int $accountId, CargoCarrierManager $manager): void
    {
        $account = CargoCarrierAccount::query()->where('user_id', auth()->id())->findOrFail($accountId);

        try {
            $result = $manager->forAccount($account)->testConnection($account);
            $success = (bool) ($result['success'] ?? false);
            $account->forceFill([
                'status' => $success ? 'connected' : 'error',
                'last_verified_at' => $success ? now() : null,
                'last_error' => $success ? null : ($result['message'] ?? 'Bağlantı doğrulanamadı.'),
            ])->save();
            $this->feedbackTone = $success ? 'success' : 'danger';
            $this->feedback = (string) ($result['message'] ?? ($success ? 'Bağlantı başarılı.' : 'Bağlantı doğrulanamadı.'));
        } catch (\Throwable $exception) {
            report($exception);
            $account->forceFill(['status' => 'error', 'last_error' => $exception->getMessage()])->save();
            $this->feedbackTone = 'danger';
            $this->feedback = 'Bağlantı hatası: '.$exception->getMessage();
        }

        unset($this->carriers, $this->carrierAccounts);
    }

    public function render()
    {
        return view('livewire.cargo.carrier-integrations');
    }
}
