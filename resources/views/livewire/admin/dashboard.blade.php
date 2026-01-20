<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-500 mt-1">Sistem durumu ve istatistikler</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.users') }}" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                Kullanıcı Yönetimi
            </a>
            <a href="{{ route('admin.logs') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Aktivite Logları
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Users -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Kullanıcı</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_users'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ $stats['active_users'] }} aktif</p>
        </div>

        <!-- Profiles -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Profil</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_profiles'] }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ $stats['ai_profiles'] }} AI profili</p>
        </div>

        <!-- Reports -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Rapor</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_reports'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Bugün {{ $stats['today_reports'] }} rapor</p>
        </div>

        <!-- Disk Usage -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Disk Kullanımı</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['disk_usage'] }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Raporlar klasörü</p>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activities -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-medium text-gray-900">Son Aktiviteler</h3>
            </div>
            <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                @forelse($recentActivities as $activity)
                <div class="px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-xs font-medium">
                            {{ $activity->user?->initials ?? '?' }}
                        </div>
                        <div>
                            <p class="text-sm text-gray-900">{{ $activity->user?->name ?? 'Sistem' }}</p>
                            <p class="text-xs text-gray-500">{{ $activity->action_label }}</p>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</span>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-gray-500">
                    Henüz aktivite yok
                </div>
                @endforelse
            </div>
            <div class="px-6 py-3 border-t border-gray-100">
                <a href="{{ route('admin.logs') }}" class="text-sm text-gray-600 hover:text-gray-900">Tümünü Gör →</a>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-medium text-gray-900">Son Raporlar</h3>
            </div>
            <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                @forelse($recentReports as $report)
                <div class="px-6 py-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-900">{{ $report->original_filename }}</p>
                            <p class="text-xs text-gray-500">{{ $report->user?->name }} • {{ $report->profile?->name }}</p>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 text-xs rounded {{ $report->status === 'success' ? 'bg-green-100 text-green-700' : ($report->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                {{ $report->status }}
                            </span>
                            <p class="text-xs text-gray-400 mt-1">{{ $report->created_at->format('d.m H:i') }}</p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-gray-500">
                    Henüz rapor yok
                </div>
                @endforelse
            </div>
            <div class="px-6 py-3 border-t border-gray-100">
                <a href="{{ route('report-history') }}" class="text-sm text-gray-600 hover:text-gray-900">Tümünü Gör →</a>
            </div>
        </div>
    </div>
</div>
