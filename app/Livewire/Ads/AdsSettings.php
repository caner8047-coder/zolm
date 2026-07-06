<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use App\Models\AdAccount;
use App\Enums\AdChannelCode;

class AdsSettings extends Component
{
    public array $accounts = [];
    public bool $showAddModal = false;
    public string $newAccountName = '';
    public string $newAccountExternalId = '';
    public string $newAccountMarketplace = 'trendyol';

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $this->loadAccounts();
    }

    public function loadAccounts(): void
    {
        $this->accounts = AdAccount::where('user_id', auth()->id())
            ->withCount('adCampaigns')
            ->get()
            ->toArray();
    }

    public function openAddModal(): void
    {
        $this->showAddModal = true;
        $this->newAccountName = '';
        $this->newAccountExternalId = '';
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function addAccount(): void
    {
        $this->validate([
            'newAccountName' => 'required|string|max:255',
            'newAccountExternalId' => 'nullable|string|max:100',
        ]);

        AdAccount::create([
            'user_id' => auth()->id(),
            'marketplace' => $this->newAccountMarketplace,
            'account_name' => $this->newAccountName,
            'external_account_id' => $this->newAccountExternalId ?: null,
            'currency_code' => 'TRY',
            'timezone' => 'Europe/Istanbul',
            'is_active' => true,
        ]);

        $this->loadAccounts();
        $this->showAddModal = false;
    }

    public function toggleAccountStatus(int $accountId): void
    {
        $account = AdAccount::where('user_id', auth()->id())
            ->findOrFail($accountId);

        $account->update(['is_active' => !$account->is_active]);

        $this->loadAccounts();
    }

    public function deleteAccount(int $accountId): void
    {
        $account = AdAccount::where('user_id', auth()->id())
            ->findOrFail($accountId);

        if ($account->adCampaigns()->count() > 0) {
            session()->flash('error', 'Bu hesaba bağlı kampanyalar bulunduğu için silinemez.');
            return;
        }

        $account->delete();
        $this->loadAccounts();
    }

    public function render()
    {
        return view('livewire.ads.ads-settings')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Ayarlar']);
    }
}
