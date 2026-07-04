<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Denetim Kayıtları</h1>
    </div>

    {{-- Filtreler --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Aksiyon Filtresi</label>
                <input type="text" wire:model.live="actionFilter" placeholder="Örn: campaign, handoff..."
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Varlık Tipi</label>
                <select wire:model.live="entityFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <option value="">Tümü</option>
                    <option value="wa_campaign">Kampanya</option>
                    <option value="wa_account">Hesap</option>
                    <option value="wa_segment">Segment</option>
                    <option value="wa_contact">Müşteri</option>
                    <option value="wa_abandoned_cart">Sepet</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Kayıt Listesi --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70">
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Zaman</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Aksiyon</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 hidden sm:table-cell">Varlık</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 hidden md:table-cell">Kullanıcı</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 hidden lg:table-cell">Detaylar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->logs as $log)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-slate-100 text-slate-700">{{ $log->action }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 hidden sm:table-cell">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600 hidden md:table-cell">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400 hidden lg:table-cell max-w-xs truncate">
                            {{ is_array($log->details) ? json_encode($log->details) : $log->details }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                            Denetim kaydı bulunamadı.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->logs->links() }}
</div>
