<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Ayarlar
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Reklam Hesapları</h1>
                <p class="mt-1 text-sm text-slate-500">Reklam hesaplarınızı yönetin.</p>
            </div>
            <button wire:click="openAddModal" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
                + Yeni Hesap Ekle
            </button>
        </div>
    </section>

    {{-- Durum Mesajı --}}
    @if(session('error'))
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-sm text-rose-700">{{ session('error') }}</p>
        </section>
    @endif

    {{-- Hesap Listesi --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Mevcut Hesaplar</h2>

        @if(count($accounts) > 0)
            <div class="mt-4 space-y-3">
                @foreach($accounts as $account)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium text-slate-900">{{ $account['account_name'] }}</p>
                                    @if($account['is_active'])
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Pasif</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ ucfirst($account['marketplace']) }}
                                    · {{ $account['currency_code'] }}
                                    · {{ $account['ad_campaigns_count'] }} kampanya
                                </p>
                                @if($account['external_account_id'])
                                    <p class="mt-1 text-xs text-slate-500">Harici ID: {{ $account['external_account_id'] }}</p>
                                @endif
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button wire:click="toggleAccountStatus({{ $account['id'] }})"
                                    class="px-3 py-1.5 text-xs font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                    {{ $account['is_active'] ? 'Pasifleştir' : 'Aktifleştir' }}
                                </button>
                                @if($account['ad_campaigns_count'] == 0)
                                    <button wire:click="deleteAccount({{ $account['id'] }})"
                                        wire:confirm="Bu hesabi silmek istediğinize emin misiniz?"
                                        class="px-3 py-1.5 text-xs font-medium border border-rose-200 bg-white text-rose-700 rounded-lg hover:bg-rose-50 transition-colors">
                                        Sil
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-4 text-center py-8">
                <p class="text-sm text-slate-500">Henüz reklam hesabı eklenmemiş.</p>
                <button wire:click="openAddModal" class="mt-3 text-sm font-medium text-slate-700 hover:text-slate-900">
                    İlk hesabınızı ekleyin →
                </button>
            </div>
        @endif
    </section>

    {{-- Yeni Hesap Modalı --}}
    @if($showAddModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeAddModal"></div>
                <div class="relative inline-block transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold text-slate-900">Yeni Reklam Hesabı Ekle</h3>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Hesap Adı <span class="text-rose-500">*</span></label>
                                <input type="text" wire:model="newAccountName" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none" placeholder="Örn: Mağazam Trendyol">
                                @error('newAccountName')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Harici Hesap ID (Opsiyonel)</label>
                                <input type="text" wire:model="newAccountExternalId" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none" placeholder="Trendyol Mağaza ID">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Pazaryeri</label>
                                <select wire:model="newAccountMarketplace" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                                    <option value="trendyol">Trendyol</option>
                                    <option value="hepsiburada">Hepsiburada</option>
                                    <option value="n11">N11</option>
                                    <option value="amazon">Amazon</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button wire:click="addAccount" type="button" class="inline-flex w-full justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:ml-3">
                            Kaydet
                        </button>
                        <button wire:click="closeAddModal" type="button" class="mt-3 inline-flex w-full justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                            İptal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
