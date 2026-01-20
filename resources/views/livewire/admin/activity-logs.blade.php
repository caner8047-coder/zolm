<div class="space-y-4 lg:space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Aktivite Logları</h1>
            <p class="text-gray-500 mt-1 text-sm">Sistem aktivitelerini görüntüleyin</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
            ← Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-200 p-3 lg:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="sm:col-span-2 lg:col-span-1">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Kullanıcı veya açıklama ara..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                >
            </div>
            <select wire:model.live="actionFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Tüm İşlemler</option>
                @foreach($actions as $action)
                <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="dateFrom" class="flex-1 px-2 py-2 border border-gray-300 rounded-lg text-xs">
                <span class="text-gray-400">-</span>
                <input type="date" wire:model.live="dateTo" class="flex-1 px-2 py-2 border border-gray-300 rounded-lg text-xs">
            </div>
            <button wire:click="clearFilters" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                Temizle
            </button>
        </div>
    </div>

    <!-- Logs - Card view on mobile, table on desktop -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <!-- Desktop Table (hidden on mobile) -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kullanıcı</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Açıklama</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                            {{ $log->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-2">
                                <div class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center text-xs font-medium">
                                    {{ $log->user?->initials ?? '?' }}
                                </div>
                                <span class="text-sm text-gray-900">{{ $log->user?->name ?? 'Sistem' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                {{ $log->action_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                            {{ $log->description ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">
                            {{ $log->ip_address ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                            Log bulunamadı
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="lg:hidden divide-y divide-gray-100">
            @forelse($logs as $log)
            <div class="p-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center text-xs font-medium">
                            {{ $log->user?->initials ?? '?' }}
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ $log->user?->name ?? 'Sistem' }}</span>
                    </div>
                    <span class="text-xs text-gray-400">{{ $log->created_at->format('d.m H:i') }}</span>
                </div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700">
                        {{ $log->action_label }}
                    </span>
                </div>
                @if($log->description)
                <p class="text-xs text-gray-500 truncate">{{ $log->description }}</p>
                @endif
            </div>
            @empty
            <div class="p-8 text-center text-gray-500">
                Log bulunamadı
            </div>
            @endforelse
        </div>
        
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
