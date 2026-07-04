<?php

namespace App\Livewire\WhatsApp;

use App\Models\MarketplaceStore;
use App\Models\WaAccount;
use App\Models\WaSetting;
use App\Services\WhatsApp\AuditLogService;
use App\Services\WhatsApp\MetaCloudApiService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppAccountSettings extends Component
{
    public int $accountId = 0;
    public string $wabaId = '';
    public string $phoneNumberId = '';
    public string $displayPhoneNumber = '';
    public string $newAccessToken = '';
    public ?int $storeId = null;
    public string $status = 'active';
    public bool $isConnected = false;
    public string $testResult = '';
    public bool $testing = false;

    /** Token hiçbir zaman Livewire state'te tutulmaz */
    private bool $tokenHydrated = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadAccount();
    }

    public function loadAccount(): void
    {
        $account = WaAccount::first();

        if ($account) {
            $this->accountId = $account->id;
            $this->wabaId = $account->waba_id;
            $this->phoneNumberId = $account->phone_number_id;
            $this->displayPhoneNumber = $account->display_phone_number;
            $this->storeId = $account->store_id;
            $this->status = $account->status;
            $this->isConnected = !empty($account->display_phone_number);
        } else {
            // Yeni hesap — tüm alanlar boş
            $this->accountId = 0;
            $this->wabaId = '';
            $this->phoneNumberId = '';
            $this->displayPhoneNumber = '';
            $this->storeId = null;
            $this->status = 'active';
            $this->isConnected = false;
        }

        // Token ASLA state'te tutulmaz
        $this->newAccessToken = '';
        $this->tokenHydrated = true;
    }

    public function getAvailableStoresProperty()
    {
        return MarketplaceStore::where('marketplace', 'woocommerce')
            ->where('is_active', true)
            ->orderBy('store_name')
            ->get();
    }

    public function saveAccount(): void
    {
        $validated = $this->validate([
            'wabaId' => 'required|string|max:80',
            'phoneNumberId' => 'required|string|max:80',
            'storeId' => 'required|integer|exists:marketplace_stores,id',
        ]);

        // WC mağazası olduğunu doğrula
        $store = MarketplaceStore::where('id', $validated['storeId'])
            ->where('marketplace', 'woocommerce')
            ->first();

        if (!$store) {
            $this->addError('storeId', 'Sadece WooCommerce mağazaları seçilebilir.');
            return;
        }

        // Aynı mağaza için zaten aktif hesap var mı?
        $existingForStore = WaAccount::where('store_id', $validated['storeId'])
            ->when($this->accountId > 0, fn ($q) => $q->where('id', '!=', $this->accountId))
            ->first();

        if ($existingForStore) {
            $this->addError('storeId', 'Bu mağaza için zaten bir WhatsApp hesabı tanımlı.');
            return;
        }

        if ($this->accountId > 0) {
            $account = WaAccount::findOrFail($this->accountId);
            $updates = [
                'waba_id' => $validated['wabaId'],
                'phone_number_id' => $validated['phoneNumberId'],
                'store_id' => $validated['storeId'],
            ];

            // Token yalnızca girildiyse güncelle
            if ($this->newAccessToken !== '') {
                $updates['access_token_encrypted'] = $this->newAccessToken;
            }

            $account->update($updates);
        } else {
            $account = WaAccount::create([
                'store_id' => $validated['storeId'],
                'waba_id' => $validated['wabaId'],
                'phone_number_id' => $validated['phoneNumberId'],
                'display_phone_number' => '',
                'access_token_encrypted' => $this->newAccessToken ?: 'pending',
                'status' => 'active',
                'is_active' => true,
            ]);
            $this->accountId = $account->id;
        }

        // Tokenı hemen temizle — hiçbir yerde kalmasın
        $this->newAccessToken = '';

        app(AuditLogService::class)->log(
            'whatsapp_account_updated',
            'wa_account',
            $account->id,
            ['waba_id' => $validated['wabaId'], 'store_id' => $validated['storeId']],
        );

        session()->flash('wa_success', 'WhatsApp hesabı kaydedildi. Token alanı temizlendi.');
        $this->loadAccount();
    }

    public function testConnection(): void
    {
        if ($this->accountId === 0) {
            $this->testResult = 'Önce hesabı kaydedin.';
            return;
        }

        $account = WaAccount::find($this->accountId);
        if (!$account) {
            $this->testResult = 'Hesap bulunamadı.';
            return;
        }

        $this->testing = true;
        $this->testResult = '';

        try {
            $metaApi = app(MetaCloudApiService::class);
            $profile = $metaApi->getBusinessProfile($account);

            // display_phone_number'ı Meta'dan al ve güncelle
            $phone = $profile['display_phone_number'] ?? null;
            if ($phone) {
                $account->update([
                    'display_phone_number' => $phone,
                    'status' => 'active',
                ]);
                $this->displayPhoneNumber = $phone;
                $this->isConnected = true;
            }

            $this->testResult = 'Bağlantı başarılı! Telefon: ' . ($phone ?: 'bilinmiyor');
            $this->testing = false;
        } catch (\Throwable $e) {
            // Hassas hata içeriğini gösterme
            $this->testResult = 'Bağlantı başarısız. Token ve WABA bilgilerinizi kontrol edin.';
            $this->testing = false;
        }
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-account-settings');
    }
}
