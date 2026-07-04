<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Kampanyalar</h1>
            <p class="text-sm text-slate-500 mt-1">Toplu WhatsApp kampanya yönetimi</p>
        </div>
        <a href="{{ route('whatsapp.campaign-create') }}"
            class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors">
            + Yeni Kampanya
        </a>
    </div>

    @if(session('wa_success'))
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('wa_success') }}</div>
    @endif
    @if(session('wa_error'))
        <div class="rounded-[10px] border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('wa_error') }}</div>
    @endif

    {{-- Filtreler --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['all' => 'Tümü', 'draft' => 'Taslak', 'pending_approval' => 'Onay Bekliyor', 'approved' => 'Onaylı', 'scheduled' => 'Zamanlanmış', 'running' => 'Aktif', 'paused' => 'Duraklatılmış', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal'] as $value => $label)
            <button wire:click="setStatusFilter('{{ $value }}')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                    {{ $statusFilter === $value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Kampanya Listesi --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70">
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Kampanya</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Durum</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 hidden sm:table-cell">Segment</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Alıcı</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Gönderilen</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600 hidden md:table-cell">Tıklanan</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600 hidden lg:table-cell">Sipariş</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Aksiyon</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaignStats as $campaign)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $campaign['name'] }}</div>
                            <div class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($campaign['created_at'])->format('d.m.Y H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'draft' => 'bg-slate-100 text-slate-600',
                                    'pending_approval' => 'bg-amber-100 text-amber-700',
                                    'approved' => 'bg-blue-100 text-blue-700',
                                    'scheduled' => 'bg-indigo-100 text-indigo-700',
                                    'running' => 'bg-emerald-100 text-emerald-700',
                                    'paused' => 'bg-orange-100 text-orange-700',
                                    'completed' => 'bg-green-100 text-green-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                ];
                                $statusLabels = [
                                    'draft' => 'Taslak',
                                    'pending_approval' => 'Onay Bekliyor',
                                    'approved' => 'Onaylı',
                                    'scheduled' => 'Zamanlanmış',
                                    'running' => 'Aktif',
                                    'paused' => 'Duraklatılmış',
                                    'completed' => 'Tamamlandı',
                                    'cancelled' => 'İptal',
                                    'failed' => 'Başarısız',
                                ];
                            @endphp
                            <span class="px-2 py-0.5 text-xs font-medium rounded {{ $statusColors[$campaign['status']] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $statusLabels[$campaign['status']] ?? $campaign['status'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 hidden sm:table-cell">{{ $campaign['segment']['name'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-right text-slate-900 font-medium">{{ number_format($campaign['total_recipients']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ number_format($campaign['total_sent']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-600 hidden md:table-cell">{{ number_format($campaign['total_clicked']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-600 hidden lg:table-cell">{{ number_format($campaign['total_converted']) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if(in_array($campaign['status'], ['running']))
                                <button wire:click="pauseCampaign({{ $campaign['id'] }})"
                                    class="text-xs text-orange-600 hover:text-orange-800 mr-2">Duraklat</button>
                            @endif
                            @if($campaign['status'] === 'paused')
                                <button wire:click="resumeCampaign({{ $campaign['id'] }})"
                                    class="text-xs text-emerald-600 hover:text-emerald-800 mr-2">Devam</button>
                            @endif
                            @if(in_array($campaign['status'], ['draft', 'approved', 'scheduled', 'running', 'paused']))
                                <button wire:click="cancelCampaign({{ $campaign['id'] }})"
                                    wire:confirm="Bu kampanyayı iptal etmek istediğinize emin misiniz?"
                                    class="text-xs text-red-600 hover:text-red-800">İptal</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                            Kampanya bulunamadı.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
