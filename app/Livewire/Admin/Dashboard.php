<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'stats' => $this->getStats(),
            'recentActivities' => $this->getRecentActivities(),
            'recentReports' => $this->getRecentReports(),
        ])->layout('layouts.app');
    }

    protected function getStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_profiles' => Profile::count(),
            'ai_profiles' => Profile::where('is_ai_generated', true)->count(),
            'total_reports' => Report::count(),
            'today_reports' => Report::whereDate('created_at', today())->count(),
            'disk_usage' => $this->getDiskUsage(),
        ];
    }

    protected function getDiskUsage(): string
    {
        $totalSize = 0;
        $path = storage_path('app/private/reports');
        
        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $totalSize += $file->getSize();
            }
        }

        if ($totalSize > 1073741824) {
            return round($totalSize / 1073741824, 2) . ' GB';
        } elseif ($totalSize > 1048576) {
            return round($totalSize / 1048576, 2) . ' MB';
        } else {
            return round($totalSize / 1024, 2) . ' KB';
        }
    }

    protected function getRecentActivities()
    {
        return ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    protected function getRecentReports()
    {
        return Report::with(['user', 'profile'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }
}
