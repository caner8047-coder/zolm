<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Otomasyon Ayarları</h1>
    </div>

    @if(session('wa_success'))
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('wa_success') }}</div>
    @endif

    <form wire:submit="saveSettings" class="space-y-6">

        {{-- Sepet Kurtarma --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">Sepet Kurtarma</div>
                    <div class="text-xs text-slate-500">Terk edilen sepet hatırlatmaları</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="cartRecovery.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
            @if(!empty($cartRecovery['enabled']))
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Aşama 1 Gecikmesi (dk)</label>
                        <input type="number" wire:model="cartRecovery.0.delay_minutes" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Aşama 2 Gecikmesi (saat)</label>
                        <input type="number" wire:model="cartRecovery.1.delay_hours" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Aşama 3 Gecikmesi (saat)</label>
                        <input type="number" wire:model="cartRecovery.2.delay_hours" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                </div>
            @endif
        </div>

        {{-- Stok Hatırlatıcı --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">Stok Hatırlatıcı</div>
                    <div class="text-xs text-slate-500">Stoğa düşünce bildirim</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="stockAlert.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        {{-- Sipariş Onayı --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">Sipariş Onayı</div>
                    <div class="text-xs text-slate-500">Yeni sipariş bildirimi</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="orderConfirmation.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        {{-- İade Bildirimleri --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">İade Bildirimleri</div>
                    <div class="text-xs text-slate-500">İade durum güncellemeleri</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="returnNotifications.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        {{-- Doğum Günü --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">Doğum Günü</div>
                    <div class="text-xs text-slate-500">Yıllık doğum günü mesajları</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="birthday.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        {{-- Karşılama Akışı --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">Hoş Geldin Akışı</div>
                    <div class="text-xs text-slate-500">Yeni üye karşılama mesajları</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="welcomeOnboarding.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        {{-- İlk Alışveriş --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="font-medium text-slate-900">İlk Alışveriş Teşviki</div>
                    <div class="text-xs text-slate-500">Gün 3/7/14 serisi</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="firstPurchase.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        {{-- Frekans Limitleri --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="font-medium text-slate-900 mb-3">Frekans Limitleri</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Max/24 saat</label>
                    <input type="number" wire:model="frequencyCap.marketing_max_per_24h" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Max/7 gün</label>
                    <input type="number" wire:model="frequencyCap.marketing_max_per_7d" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Max/30 gün</label>
                    <input type="number" wire:model="frequencyCap.marketing_max_per_30d" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-[6px] bg-slate-900 text-white px-6 py-2.5 text-sm font-medium hover:bg-slate-800 transition-colors">
                Tüm Ayarları Kaydet
            </button>
        </div>
    </form>
</div>
