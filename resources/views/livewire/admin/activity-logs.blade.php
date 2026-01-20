<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Aktivite Logları</h1>
            <p class="text-gray-500 mt-1">Sistem aktivitelerini görüntüleyin</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
            ← Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Kullanıcı veya açıklama ara..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                >
            </div>
            <select wire:model.live="actionFilter" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">Tüm İşlemler</option>
                @foreach($actions as $action)
                <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
            <div class="flex items-center space-x-2">
                <input type="date" wire:model.live="dateFrom" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <span class="text-gray-400">-</span>
                <input type="date" wire:model.live="dateTo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button wire:click="clearFilters" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                Temizle
            </button>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kullanıcı</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Açıklama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $log->created_at->format('d.m.Y H:i:s') }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-xs font-medium">
                                {{ $log->user?->initials ?? '?' }}
                            </div>
                            <span class="text-sm text-gray-900">{{ $log->user?->name ?? 'Sistem' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                            {{ $log->action_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                        {{ $log->description ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-400">
                        {{ $log->ip_address ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        Log bulunamadı
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($logs->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
