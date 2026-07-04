<div class="space-y-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-slate-900">WhatsApp Hesap Ayarları</h1>
        <p class="text-sm text-slate-500 mt-1">Meta WhatsApp Business hesap bağlantı bilgileri</p>
    </div>

    @if(session('wa_success'))
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('wa_success') }}</div>
    @endif

    @if($testResult)
        <div class="rounded-[10px] border {{ str_contains($testResult, 'başarılı') ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} p-4 text-sm">
            {{ $testResult }}
        </div>
    @endif

    <form wire:submit="saveAccount" class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
        {{-- Store seçimi --}}
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Bağlanacak WooCommerce Mağazası <span class="text-red-500">*</span></label>
            @if($accountId > 0 && $storeId)
                <div class="w-full rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    {{ $this->availableStores->firstWhere('id', $storeId)?->store_name ?? 'Bilinmeyen mağaza' }}
                    <span class="text-xs text-slate-400 ml-2">(kaydedildikten sonra değiştirilemez)</span>
                </div>
            @else
                <select wire:model="storeId"
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <option value="">— Mağaza seçin —</option>
                    @foreach($this->availableStores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }} ({{ $store->marketplace }})</option>
                    @endforeach
                </select>
                @error('storeId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">WABA ID <span class="text-red-500">*</span></label>
                <input type="text" wire:model="wabaId"
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
                    placeholder="WhatsApp Business Account ID">
                @error('wabaId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number ID <span class="text-red-500">*</span></label>
                <input type="text" wire:model="phoneNumberId"
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
                    placeholder="Meta Phone Number ID">
                @error('phoneNumberId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Görünen Telefon Numarası</label>
                @if($isConnected && $displayPhoneNumber)
                    <div class="w-full rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        {{ $displayPhoneNumber }}
                    </div>
                @else
                    <div class="w-full rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400 italic">
                        Meta bağlantısından sonra otomatik gelir
                    </div>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Yeni Access Token</label>
                <input type="password" wire:model="newAccessToken"
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
                    placeholder="••••••••••••••••">
                <p class="text-xs text-slate-400 mt-1">Güncellenecekse doldurun. Boş bırakılırsa mevcut token korunur.</p>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="button" wire:click="testConnection" wire:loading.attr="disabled"
                class="rounded-[6px] border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50 transition-colors disabled:opacity-50"
                {{ $accountId === 0 ? 'disabled title="Önce hesabı kaydedin"' : '' }}>
                <span wire:loading.remove wire:target="testConnection">Bağlantıyı Test Et</span>
                <span wire:loading wire:target="testConnection">Test ediliyor...</span>
            </button>
            <button type="submit"
                class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors">
                Kaydet
            </button>
        </div>
    </form>
</div>
