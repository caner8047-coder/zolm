<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Şablon Yönetimi</h1>
            <p class="text-sm text-slate-500 mt-1">Meta'dan senkronize edilmiş WhatsApp şablonları</p>
        </div>
        <button wire:click="syncFromMeta" wire:loading.attr="disabled"
            class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors disabled:opacity-50">
            <span wire:loading.remove wire:target="syncFromMeta">Senkronize Et</span>
            <span wire:loading wire:target="syncFromMeta">Senkronize ediliyor...</span>
        </button>
    </div>

    @if($syncMessage)
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ $syncMessage }}</div>
    @endif

    @if(session('wa_error'))
        <div class="rounded-[10px] border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('wa_error') }}</div>
    @endif

    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70">
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Şablon Adı</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Dil</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Kategori</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Durum</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Son Senkron</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $template['name'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $template['language'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $template['category'] }}</td>
                        <td class="px-4 py-3">
                            @if($template['status'] === 'approved')
                                <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded">Onaylı</span>
                            @elseif($template['status'] === 'pending')
                                <span class="px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 rounded">Beklemede</span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">{{ $template['status'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">
                            {{ $template['synced_at'] ? \Carbon\Carbon::parse($template['synced_at'])->diffForHumans() : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                            Henüz şablon senkronize edilmemiş. "Senkronize Et" butonuna tıklayın.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
