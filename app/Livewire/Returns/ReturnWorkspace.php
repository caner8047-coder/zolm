<?php

namespace App\Livewire\Returns;

use App\Models\ReturnIntakeItem;
use App\Models\ChannelClaim;
use App\Models\ReturnWhatsappMessage;
use App\Models\ReturnWhatsappThread;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ReturnWorkspace extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = '';

    public function mount(): void
    {
        abort_unless(
            auth()->check() && (
                auth()->user()?->canAccessReturnsIntake()
                || auth()->user()?->canAccessReturnsReview()
            ),
            403
        );

        $this->activeTab = $this->resolveActiveTab($this->activeTab);
    }

    public function showTab(string $tab): void
    {
        $this->activeTab = $this->resolveActiveTab($tab);
    }

    #[Computed]
    public function workspaceStats(): array
    {
        $todayItems = ReturnIntakeItem::query()->whereDate('arrived_at', today());

        return [
            'todayArrivals' => (clone $todayItems)->count(),
            'awaitingDecision' => (clone $todayItems)->whereIn('intake_status', ['ready_for_decision', 'needs_review'])->count(),
            'decisionedToday' => (clone $todayItems)->where('decision_status', '!=', 'pending')->count(),
            'activeThreads' => ReturnWhatsappThread::query()->whereIn('status', ['collecting', 'queued'])->count(),
            'todayMessages' => ReturnWhatsappMessage::query()->where('received_at', '>=', today())->count(),
            'readyForAction' => ReturnIntakeItem::query()->where('intake_status', 'ready_for_decision')->count(),
            'marketplaceClaimsWaiting' => ChannelClaim::query()->whereIn('status', ['pending', 'shipped', 'in_transit', 'delivered'])->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function availableTabs(): array
    {
        $tabs = [];

        if (auth()->user()?->canAccessReturnsIntake()) {
            $tabs[] = 'kabul';
        }

        if (auth()->user()?->canAccessReturnsReview()) {
            $tabs[] = 'pazaryeri';
            $tabs[] = 'havuz';
            $tabs[] = 'whatsapp';
        }

        return $tabs;
    }

    protected function resolveActiveTab(?string $tab): string
    {
        $availableTabs = $this->availableTabs();

        if ($availableTabs === []) {
            return 'kabul';
        }

        return in_array($tab, $availableTabs, true)
            ? $tab
            : $availableTabs[0];
    }

    public function render(): View
    {
        return view('livewire.returns.return-workspace', [
            'workspaceStats' => $this->workspaceStats,
            'canAccessIntake' => auth()->user()?->canAccessReturnsIntake() ?? false,
            'canAccessReview' => auth()->user()?->canAccessReturnsReview() ?? false,
            'availableTabs' => $this->availableTabs(),
        ])->layout('layouts.app', [
            'title' => 'İade Merkezi',
        ]);
    }
}
