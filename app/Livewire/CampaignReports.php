<?php

namespace App\Livewire;

use App\Models\OptimizationReport;
use Livewire\Component;

class CampaignReports extends Component
{
    public $activeFilter = 'all'; // all, tariff, plus, badge, flash

    public function mount()
    {
        // Require specific permission or just auth
        if (!auth()->check()) {
            return redirect()->route('login');
        }
    }

    public function setFilter($filter)
    {
        $this->activeFilter = $filter;
    }

    public function deleteReport($id)
    {
        $report = OptimizationReport::where('user_id', auth()->id())->find($id);
        
        if ($report) {
            $report->delete();
            // Optional: delete associated files if they exist
            // Storage::disk('local')->delete('reports/' . $report->original_filename);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Rapor başarıyla silindi.'
            ]);
        }
    }


    public function render()
    {
        $query = OptimizationReport::where('user_id', auth()->id());

        if ($this->activeFilter !== 'all') {
            $query->where('campaign_type', $this->activeFilter);
        }

        $reports = $query->orderByDesc('created_at')->get();

        return view('livewire.campaign-reports', [
            'reports' => $reports
        ]);
    }
}
