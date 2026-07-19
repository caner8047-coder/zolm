<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use App\Models\AdImportBatch;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdRecommendation;

class AdsDashboard extends Component
{
    public int $totalAccounts = 0;
    public int $totalImports = 0;
    public int $completedImports = 0;
    public int $totalCampaigns = 0;
    public int $newRecommendations = 0;
    public $recentImports = [];

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $userId = auth()->id();

        $this->totalAccounts = AdAccount::where('user_id', $userId)->count();
        $this->totalImports = AdImportBatch::where('user_id', $userId)->count();
        $this->completedImports = AdImportBatch::where('user_id', $userId)
            ->where('status', 'imported')
            ->count();
        $this->totalCampaigns = AdCampaign::where('user_id', $userId)->count();
        $this->newRecommendations = AdRecommendation::where('user_id', $userId)
            ->where('status', 'new')
            ->count();

        $this->recentImports = AdImportBatch::where('user_id', $userId)
            ->whereIn('status', ['imported', 'failed', 'duplicate'])
            ->latest()
            ->take(5)
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.ads.ads-dashboard')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Genel Bakış']);
    }
}
