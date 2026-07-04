<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaAccount;
use App\Models\WaOutbox;
use App\Models\WaMessageDelivery;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppOverview extends Component
{
    public int $todaySent = 0;
    public int $todayDelivered = 0;
    public int $todayRead = 0;
    public int $todayFailed = 0;
    public int $totalQueued = 0;
    public bool $accountActive = false;
    public bool $testMode = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadData();
    }

    public function loadData(): void
    {
        $today = now()->startOfDay();

        $this->todaySent = WaOutbox::where('status', 'sent')
            ->where('created_at', '>=', $today)->count();

        $this->todayDelivered = WaMessageDelivery::where('status', 'delivered')
            ->where('delivered_at', '>=', $today)->count();

        $this->todayRead = WaMessageDelivery::where('status', 'read')
            ->where('read_at', '>=', $today)->count();

        $this->todayFailed = WaOutbox::where('status', 'failed')
            ->where('created_at', '>=', $today)->count();

        $this->totalQueued = WaOutbox::where('status', 'queued')->count();

        $this->accountActive = WaAccount::active()->exists();
        $this->testMode = config('whatsapp.features.test_mode', true);
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-overview');
    }
}
