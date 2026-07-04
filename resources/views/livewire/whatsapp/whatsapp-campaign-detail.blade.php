<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">{{ $campaign['name'] ?? 'Kampanya Detayı' }}</h1>
            @if(!empty($campaign['status']))
                <span class="px-2 py-0.5 text-xs font-medium rounded mt-1 inline-block
                    {{ match($campaign['status']) { 'running' => 'bg-emerald-100 text-emerald-700', 'completed' => 'bg-blue-100 text-blue-700', 'paused' => 'bg-amber-100 text-amber-700', 'cancelled' => 'bg-red-100 text-red-700', default => 'bg-slate-100 text-slate-600' } }}">
                    {{ $campaign['status'] }}
                </span>
            @endif
        </div>
        <a href="{{ route('whatsapp.campaigns') }}" class="text-sm text-slate-500 hover:text-slate-700">← Listeye Dön</a>
    </div>

    {{-- Kampanya Bilgileri --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div><span class="text-slate-500">Segment:</span> <span class="text-slate-900 font-medium">{{ $campaign['segment']['name'] ?? '-' }}</span></div>
            <div><span class="text-slate-500">Şablon:</span> <span class="text-slate-900 font-medium">{{ $campaign['template']['name'] ?? '-' }}</span></div>
            <div><span class="text-slate-500">Oluşturulma:</span> <span class="text-slate-900 font-medium">{{ $campaign['created_at'] ?? '-' }}</span></div>
            <div><span class="text-slate-500">Başlangıç:</span> <span class="text-slate-900 font-medium">{{ $campaign['started_at'] ?? '-' }}</span></div>
        </div>
    </div>

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
            <div class="text-xs text-slate-500">Alıcı</div>
            <div class="text-2xl font-bold text-slate-900">{{ number_format($campaign['total_recipients'] ?? 0) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
            <div class="text-xs text-slate-500">Gönderilen</div>
            <div class="text-2xl font-bold text-emerald-600">{{ number_format($campaign['total_sent'] ?? 0) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
            <div class="text-xs text-slate-500">Tıklanan</div>
            <div class="text-2xl font-bold text-blue-600">{{ number_format($campaign['total_clicked'] ?? 0) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
            <div class="text-xs text-slate-500">Sipariş</div>
            <div class="text-2xl font-bold text-emerald-600">{{ number_format($campaign['total_converted'] ?? 0) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
            <div class="text-xs text-slate-500">Gelir</div>
            <div class="text-2xl font-bold text-slate-900">₺{{ number_format($campaign['total_revenue'] ?? 0, 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Alıcı Dağılımı --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="font-medium text-slate-900 mb-3">Alıcı Dağılımı</div>
        <div class="flex flex-wrap gap-2">
            @foreach($audienceSummary as $status => $count)
                <span class="px-3 py-1 text-sm font-medium rounded-lg {{ match($status) { 'eligible' => 'bg-blue-100 text-blue-700', 'queued' => 'bg-indigo-100 text-indigo-700', 'sent' => 'bg-emerald-100 text-emerald-700', 'converted' => 'bg-green-100 text-green-700', 'failed' => 'bg-red-100 text-red-700', 'skipped' => 'bg-slate-100 text-slate-600', default => 'bg-slate-100 text-slate-600' } }}">
                    {{ $status }}: {{ number_format($count) }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- Günlük Metrikler --}}
    @if(!empty($metrics))
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="font-medium text-slate-900 mb-3">Günlük Metrikler</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70">
                        <th class="text-left px-3 py-2 font-medium text-slate-600">Tarih</th>
                        <th class="text-right px-3 py-2 font-medium text-slate-600">Gönderilen</th>
                        <th class="text-right px-3 py-2 font-medium text-slate-600">Teslim</th>
                        <th class="text-right px-3 py-2 font-medium text-slate-600">Tıklanan</th>
                        <th class="text-right px-3 py-2 font-medium text-slate-600">Dönüşüm</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($metrics as $m)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-slate-900">{{ $m['metric_date'] }}</td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ $m['recipients_sent'] }}</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $m['recipients_delivered'] }}</td>
                            <td class="px-3 py-2 text-right text-blue-600">{{ $m['recipients_clicked'] }}</td>
                            <td class="px-3 py-2 text-right text-green-600">{{ $m['recipients_converted'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
