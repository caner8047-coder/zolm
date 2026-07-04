<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Müşteri Profili</h1>
    </div>

    {{-- Telefon Arama --}}
    <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">Telefon ile Ara</label>
                <input type="tel" wire:model="searchPhone" placeholder="+90 5XX XXX XX XX"
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>
            <button wire:click="searchByPhone" class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800">
                Ara
            </button>
        </div>
    </div>

    @if(!empty($profileData['contact']))
        {{-- Müşteri Bilgileri --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
            <div class="font-medium text-slate-900">Müşteri Bilgileri</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><span class="text-slate-500">Ad:</span> <span class="text-slate-900 font-medium">{{ $profileData['contact']['first_name'] ?? '-' }} {{ $profileData['contact']['last_name'] ?? '' }}</span></div>
                <div><span class="text-slate-500">Durum:</span> <span class="text-slate-900 font-medium">{{ $profileData['contact']['status'] }}</span></div>
                <div><span class="text-slate-500">Son Görülme:</span> <span class="text-slate-900 font-medium">{{ $profileData['contact']['last_seen_at'] ?? '-' }}</span></div>
                <div><span class="text-slate-500">Skor:</span> <span class="text-slate-900 font-medium">{{ $profileData['profile']['engagement_score'] ?? '-' }}</span></div>
            </div>
        </div>

        {{-- KPI Kartları --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs text-slate-500 mb-1">Toplam Sipariş</div>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($profileData['profile']['total_orders'] ?? 0) }}</div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs text-slate-500 mb-1">Toplam Gelir</div>
                <div class="text-2xl font-bold text-emerald-600">₺{{ number_format($profileData['profile']['total_revenue'] ?? 0, 2, ',', '.') }}</div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs text-slate-500 mb-1">Gönderilen Mesaj</div>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($profileData['profile']['total_messages_sent'] ?? 0) }}</div>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="text-xs text-slate-500 mb-1">Tıklama</div>
                <div class="text-2xl font-bold text-blue-600">{{ number_format($profileData['profile']['total_clicks'] ?? 0) }}</div>
            </div>
        </div>

        {{-- İzinler --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="font-medium text-slate-900 mb-3">İletişim İzinleri</div>
            <div class="flex flex-wrap gap-2">
                @foreach($profileData['preferences'] ?? [] as $pref)
                    <span class="px-2 py-0.5 text-xs font-medium rounded {{ $pref['status'] === 'granted' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $pref['purpose'] }}: {{ $pref['status'] }}
                    </span>
                @endforeach
                @if(empty($profileData['preferences']))
                    <span class="text-sm text-slate-400">İzin kaydı bulunamadı</span>
                @endif
            </div>
        </div>

        {{-- Son Mesajlar --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="font-medium text-slate-900 mb-3">Son Mesajlar</div>
            @forelse($profileData['recent_messages'] ?? [] as $msg)
                <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                    <div>
                        <span class="text-sm font-medium text-slate-900">{{ $msg['automation_key'] ?? 'Manuel' }}</span>
                        <span class="text-xs text-slate-500 ml-2">{{ $msg['template_name'] ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-xs font-medium rounded {{ $msg['status'] === 'sent' ? 'bg-emerald-100 text-emerald-700' : ($msg['status'] === 'failed' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') }}">
                            {{ $msg['status'] }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $msg['created_at'] }}</span>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-400">Henüz mesaj yok</div>
            @endforelse
        </div>

        {{-- Kuponlar --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="font-medium text-slate-900 mb-3">Kuponlar</div>
            @forelse($profileData['coupons'] ?? [] as $coupon)
                <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                    <span class="text-sm font-mono text-slate-900">{{ $coupon['code'] }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-slate-600">{{ $coupon['discount_type'] === 'percent' ? '%' . $coupon['discount_value'] : '₺' . $coupon['discount_value'] }}</span>
                        <span class="text-xs {{ $coupon['used_at'] ? 'text-emerald-600' : 'text-slate-400' }}">{{ $coupon['used_at'] ? 'Kullanıldı' : 'Kullanılmadı' }}</span>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-400">Henüz kupon yok</div>
            @endforelse
        </div>

    @elseif($contactId > 0)
        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            Müşteri bulunamadı.
        </div>
    @else
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-8 text-center text-slate-400">
            Bir müşteri profili görüntülemek için telefon numarası ile arama yapın veya listeden seçin.
        </div>
    @endif
</div>
