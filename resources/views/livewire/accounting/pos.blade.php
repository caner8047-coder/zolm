@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $statusLabel = fn ($s) => match ($s) {
        'posted' => ['Aktif',   'bg-emerald-50 text-emerald-700'],
        'voided' => ['İptal',   'bg-rose-50 text-rose-700'],
        default  => [$s,        'bg-slate-100 text-slate-700'],
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Workspace & Özet Kartı --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    POS Hızlı Satış Sistemi
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Perakende Checkout</h1>
                <p class="mt-2 text-sm text-slate-500">
                    POS terminallerini yönetin, kasa vardiyaları açın ve hızlı sepet onaylama/tahsilat akışları ile perakende satış işlemlerinizi gerçekleştirin.
                </p>
            </div>
            <div class="shrink-0 flex gap-2">
                <button
                    wire:click="$toggle('showTerminalForm')"
                    id="btn-toggle-terminal-form"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]"
                >
                    {{ $showTerminalForm ? 'Kapat' : 'Yeni Terminal' }}
                </button>
            </div>
        </div>

        {{-- Terminal Oluşturma Formu --}}
        @if($showTerminalForm)
            <div class="mt-4 pt-4 border-t border-slate-200 space-y-4">
                <h3 class="text-sm font-semibold text-slate-900">Satış Terminali Oluştur</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Terminal Adı <span class="text-rose-500">*</span></label>
                        <input wire:model="terminalName" id="input-terminal-name" type="text" placeholder="Kasa-1" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        @error('terminalName') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Varsayılan Depo</label>
                        <select wire:model="terminalWarehouseId" id="select-terminal-warehouse" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            <option value="">Depo Seçin...</option>
                            @foreach($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Varsayılan Kasa/Banka</label>
                        <select wire:model="terminalAccountId" id="select-terminal-account" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            <option value="">Hesap Seçin...</option>
                            @foreach($this->accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->type === 'cash' ? 'Kasa' : 'Banka' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Varsayılan Şirket</label>
                        <select wire:model="terminalLegalEntityId" id="select-terminal-legal-entity" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            <option value="">Şirket Seçin...</option>
                            @foreach($this->legalEntities as $le)
                                <option value="{{ $le->id }}">{{ $le->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button wire:click="createTerminal" id="btn-submit-terminal" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">Terminal Kaydet</button>
                </div>
            </div>
        @endif
    </section>

    {{-- Terminal ve Vardiya Kontrolü --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Terminal Seçimi --}}
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Aktif Terminal Seçimi</h3>
            <div class="space-y-2">
                @forelse($this->terminals as $term)
                    <button
                        wire:click="selectTerminal({{ $term->id }})"
                        id="btn-select-terminal-{{ $term->id }}"
                        class="w-full text-left px-4 py-3 rounded-[8px] border transition-all flex items-center justify-between {{ $selectedTerminalId === $term->id ? 'border-slate-900 bg-slate-50 font-medium text-slate-950' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50/50' }} min-h-[44px]"
                    >
                        <span>{{ $term->name }}</span>
                        @if($selectedTerminalId === $term->id)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-900 text-white font-mono">SEÇİLİ</span>
                        @endif
                    </button>
                @empty
                    <p class="text-xs text-slate-400">Kayıtlı terminal bulunamadı. Lütfen yeni bir terminal oluşturun.</p>
                @endforelse
            </div>
        </div>

        {{-- Vardiya Durumu --}}
        <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm md:col-span-2 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-semibold text-slate-900">Kasa Vardiyası Kontrolü</h3>
                <div>
                    @if($activeShiftId)
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 font-mono">VARDİYA AÇIK (ID: {{ $activeShiftId }})</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10 font-mono">VARDİYA KAPALI</span>
                    @endif
                </div>
            </div>

            @if($selectedTerminalId)
                @if(!$activeShiftId)
                    {{-- Vardiya Açma Formu --}}
                    <div class="space-y-4">
                        <p class="text-xs text-slate-500">Bu terminalde satış işlemlerini başlatmak için yeni bir kasa vardiyası (shift) açmalısınız.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açılış Nakit Bakiyesi</label>
                                <input wire:model="shiftOpeningBalance" id="input-shift-opening" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Vardiya Kasa/Banka</label>
                                <select wire:model="shiftAccountId" id="select-shift-account" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                    <option value="">Varsayılan Terminal Kasa</option>
                                    @foreach($this->accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Şirket</label>
                                <select wire:model="shiftLegalEntityId" id="select-shift-legal-entity" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                    <option value="">Varsayılan Şirket</option>
                                    @foreach($this->legalEntities as $le)
                                        <option value="{{ $le->id }}">{{ $le->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button wire:click="openShift" id="btn-open-shift" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 rounded-[6px] min-h-[44px]">Vardiyayı Aç (Satışa Başla)</button>
                    </div>
                @else
                    {{-- Vardiya Kapatma Formu --}}
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 text-center">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Açılış</span>
                                <span class="text-sm font-bold text-slate-700 font-mono">{{ $formatMoney($this->activeShift->opening_balance) }}</span>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 text-center">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Nakit Satış</span>
                                <span class="text-sm font-bold text-slate-700 font-mono">{{ $formatMoney($this->kpis['cashSales']) }}</span>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 text-center">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Kart/Banka</span>
                                <span class="text-sm font-bold text-slate-700 font-mono">{{ $formatMoney($this->kpis['bankSales']) }}</span>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 text-center">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Beklenen Kasa Nakit</span>
                                <span class="text-sm font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['expected']) }}</span>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4 flex flex-col sm:flex-row items-end gap-4">
                            <div class="flex-1 w-full">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kasadaki Sayılan Nakit</label>
                                <input wire:model="shiftClosingBalance" id="input-shift-closing" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            </div>
                            <button wire:click="closeShift" id="btn-close-shift" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-[6px] min-h-[44px]">Vardiyayı Kapat (Gün Sonu Al)</button>
                        </div>
                    </div>
                @endif
            @else
                <p class="text-xs text-slate-400">Lütfen vardiya işlemlerini başlatmak için soldan bir terminal seçin.</p>
            @endif
        </div>
    </section>

    @if($activeShiftId)
        {{-- Sepet & Checkout & Ürün Arama Arayüzü --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- Sol Taraf: Ürün Arama & Ürünler Listesi --}}
            <div class="lg:col-span-7 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-semibold text-slate-900">Ürün Barkod / Arama</h3>
                </div>
                <div>
                    <input
                        wire:model.live.debounce.250ms="cartSearch"
                        id="input-cart-search"
                        type="text"
                        placeholder="Barkod okutun veya ürün adı/stok kodu yazın..."
                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]"
                    >
                </div>

                {{-- Ürün Kartları / Izgara --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto max-h-[350px] pr-1">
                    @forelse($this->products as $product)
                        <button
                            wire:click="addToCart('{{ $product->stock_code }}')"
                            id="btn-add-product-{{ $product->stock_code }}"
                            class="rounded-[8px] border border-slate-200 bg-slate-50/50 hover:bg-slate-50 p-3 text-left space-y-2 transition-all flex flex-col justify-between"
                        >
                            <div>
                                <span class="block text-xs font-semibold text-slate-500 font-mono">{{ $product->stock_code }}</span>
                                <span class="block text-sm font-bold text-slate-800 leading-tight mt-1">{{ $product->product_name }}</span>
                            </div>
                            <div class="flex items-center justify-between w-full mt-2 pt-2 border-t border-slate-100">
                                <span class="text-xs text-slate-400">Birim Fiyat</span>
                                <span class="text-sm font-extrabold text-slate-950 font-mono">{{ $formatMoney($product->sale_price ?? $product->sales_price_try ?? 10.00) }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full py-12 text-center text-sm text-slate-400">Aramayla eşleşen ürün bulunamadı.</div>
                    @endforelse
                </div>
            </div>

            {{-- Sağ Taraf: Sepet & Checkout Kontrol Yüzeyi --}}
            <div class="lg:col-span-5 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-4 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">Satış Sepeti</h3>

                    {{-- Sepet Ürünleri --}}
                    <div class="space-y-3 mt-4 max-h-[280px] overflow-y-auto pr-1">
                        @forelse($cart as $idx => $item)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/40 p-3 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-xs text-slate-400 font-mono block">{{ $item['stock_code'] }}</span>
                                        <span class="text-sm font-semibold text-slate-800 leading-snug">{{ $item['name'] }}</span>
                                    </div>
                                    <button wire:click="removeFromCart({{ $idx }})" class="text-xs text-rose-500 font-semibold hover:underline">Kaldır</button>
                                </div>
                                <div class="grid grid-cols-3 gap-2 items-center">
                                    {{-- Miktar --}}
                                    <div>
                                        <label class="block text-[10px] text-slate-400 uppercase font-semibold">Miktar</label>
                                        <input
                                            type="number"
                                            value="{{ $item['quantity'] }}"
                                            wire:change="updateQuantity({{ $idx }}, $event.target.value)"
                                            class="w-full text-center rounded-[6px] border border-slate-200 bg-white py-1 px-2 text-xs focus:outline-none min-h-[30px]"
                                            min="1"
                                        >
                                    </div>
                                    {{-- Birim Fiyat --}}
                                    <div>
                                        <label class="block text-[10px] text-slate-400 uppercase font-semibold">B. Fiyat</label>
                                        <input
                                            type="number"
                                            value="{{ $item['unit_price'] }}"
                                            wire:change="updateUnitPrice({{ $idx }}, $event.target.value)"
                                            step="0.01"
                                            class="w-full text-right rounded-[6px] border border-slate-200 bg-white py-1 px-2 text-xs focus:outline-none min-h-[30px]"
                                            min="0"
                                        >
                                    </div>
                                    {{-- İskonto --}}
                                    <div>
                                        <label class="block text-[10px] text-slate-400 uppercase font-semibold">İsk %</label>
                                        <input
                                            type="number"
                                            value="{{ $item['discount_rate'] }}"
                                            wire:change="updateDiscountRate({{ $idx }}, $event.target.value)"
                                            step="0.01"
                                            class="w-full text-right rounded-[6px] border border-slate-200 bg-white py-1 px-2 text-xs focus:outline-none min-h-[30px]"
                                            min="0"
                                            max="100"
                                        >
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 py-12 text-center">Sepete ürün ekleyin.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Ödeme & Cari & Depo Ayarları --}}
                <div class="border-t border-slate-200 pt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Cari / Müşteri</label>
                            <select wire:model="selectedPartyId" id="select-cart-party" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-xs focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="">Perakende Müşteri</option>
                                @foreach($this->parties as $p)
                                    <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo</label>
                            <select wire:model="selectedWarehouseId" id="select-cart-warehouse" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-xs focus:border-slate-500 focus:outline-none min-h-[44px]">
                                @foreach($this->warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Ödeme Tipi</label>
                            <select wire:change="updatePaymentMethod($event.target.value)" id="select-cart-payment-method" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-xs focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="cash">Nakit (Kasa)</option>
                                <option value="card">Kredi Kartı (Banka)</option>
                                <option value="bank_transfer">Havale / EFT (Banka)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap</label>
                            <select wire:model="selectedAccountId" id="select-cart-account" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-xs focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="">Hesap Seçin...</option>
                                @foreach($this->accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Toplam & Checkout --}}
                    <div class="bg-slate-50 border border-slate-200 rounded-[8px] p-3 space-y-1.5 font-mono text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span>Ara Toplam:</span>
                            <span>{{ $formatMoney($this->subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-rose-600">
                            <span>İskonto Toplam:</span>
                            <span>-{{ $formatMoney($this->discountTotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>KDV:</span>
                            <span>{{ $formatMoney($this->vatTotal) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 mt-1 font-bold text-slate-900 text-sm">
                            <span>Genel Toplam:</span>
                            <span>{{ $formatMoney($this->total) }}</span>
                        </div>
                    </div>

                    <button
                        wire:click="checkout"
                        id="btn-checkout"
                        class="w-full inline-flex items-center justify-center px-4 py-3 text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 rounded-[6px] transition-all min-h-[44px]"
                    >
                        Satışı Onayla & Fiş Kes
                    </button>
                </div>
            </div>
        </section>
    @endif

    {{-- Son Satışlar Tablosu --}}
    @if($selectedTerminalId)
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h3 class="text-base font-semibold text-slate-900">Vardiyadaki Son Satışlar</h3>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" id="btn-column-selector" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                        Kolonlar ({{ count($visibleColumns) }})
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-20 p-2 space-y-1">
                        @foreach($this->columnDefs as $colKey => $colLabel)
                            <label class="flex items-center px-2 py-1.5 hover:bg-slate-50 rounded cursor-pointer text-sm">
                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 mr-2">
                                <span class="text-slate-700">{{ $colLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Desktop Tablo --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            @if(in_array('id', $visibleColumns, true))
                                <th wire:click="sortTable('id')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                    No @if($sortColumn === 'id') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                                </th>
                            @endif
                            @if(in_array('reference_number', $visibleColumns, true))
                                <th wire:click="sortTable('reference_number')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                    Belge No @if($sortColumn === 'reference_number') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                                </th>
                            @endif
                            @if(in_array('party', $visibleColumns, true))
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider select-none">Müşteri</th>
                            @endif
                            @if(in_array('payment_method', $visibleColumns, true))
                                <th wire:click="sortTable('payment_method')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                    Ödeme Yöntemi @if($sortColumn === 'payment_method') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                                </th>
                            @endif
                            @if(in_array('amount', $visibleColumns, true))
                                <th wire:click="sortTable('amount')" class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                    Tutar @if($sortColumn === 'amount') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                                </th>
                            @endif
                            @if(in_array('status', $visibleColumns, true))
                                <th wire:click="sortTable('status')" class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                    Durum @if($sortColumn === 'status') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                                </th>
                            @endif
                            @if(in_array('action', $visibleColumns, true))
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider select-none">İşlem</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($this->recentSales as $sale)
                            @php [$statusText, $statusClass] = $statusLabel($sale->status); @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                @if(in_array('id', $visibleColumns, true))
                                    <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ $sale->id }}</td>
                                @endif
                                @if(in_array('reference_number', $visibleColumns, true))
                                    <td class="px-4 py-3 font-mono text-sm text-slate-900 whitespace-nowrap">{{ $sale->reference_number }}</td>
                                @endif
                                @if(in_array('party', $visibleColumns, true))
                                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $sale->party?->display_name ?? $sale->salesOrder?->party?->display_name ?? 'Perakende Müşteri' }}</td>
                                @endif
                                @if(in_array('payment_method', $visibleColumns, true))
                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                        {{ $sale->payment_method === 'cash' ? 'Nakit' : ($sale->payment_method === 'card' ? 'Kredi Kartı' : 'Havale') }}
                                    </td>
                                @endif
                                @if(in_array('amount', $visibleColumns, true))
                                    <td class="px-4 py-3 text-right font-mono text-slate-900 font-medium whitespace-nowrap">{{ $formatMoney($sale->amount) }}</td>
                                @endif
                                @if(in_array('status', $visibleColumns, true))
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded font-mono {{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
                                @endif
                                @if(in_array('action', $visibleColumns, true))
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if($sale->status === 'posted' && $activeShiftId)
                                            <button
                                                wire:click="confirmCancel({{ $sale->id }})"
                                                id="btn-cancel-sale-{{ $sale->id }}"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-[6px] transition-colors"
                                            >
                                                İptal Et
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($visibleColumns) }}" class="px-4 py-12 text-center text-sm text-slate-400">Bu vardiyada henüz satış yapılmadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobil Kart Görünümü --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse($this->recentSales as $sale)
                    @php [$statusText, $statusClass] = $statusLabel($sale->status); @endphp
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-sm font-semibold text-slate-900">{{ $sale->reference_number }}</span>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded font-mono {{ $statusClass }}">{{ $statusText }}</span>
                        </div>
                        <div class="text-xs text-slate-500">Müşteri: {{ $sale->party?->display_name ?? 'Perakende Müşteri' }}</div>
                        <div class="flex items-center justify-between text-xs text-slate-500 font-mono">
                            <span>{{ $sale->payment_method === 'cash' ? 'Nakit' : 'Kredi Kartı/Banka' }}</span>
                            <span class="font-bold text-slate-900">{{ $formatMoney($sale->amount) }}</span>
                        </div>
                        @if($sale->status === 'posted' && $activeShiftId)
                            <div class="pt-2">
                                <button wire:click="confirmCancel({{ $sale->id }})" id="btn-mob-cancel-{{ $sale->id }}" class="w-full py-2 text-sm font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-[6px] text-center">İptal Et</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Bu vardiyada henüz satış yapılmadı.</div>
                @endforelse
            </div>
        </section>
    @endif

    {{-- İptal Gerekçesi Modalı --}}
    @if($showCancelModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-base font-semibold text-slate-900">POS Satışını İptal Et</h3>
                <p class="text-xs text-slate-500 font-mono">
                    Bu işlemi onayladığınızda tahsilat kaydı void edilecek, stoklar depoya iade edilecek ve yevmiye fişleri ters kayıtla sıfırlanacaktır.
                </p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">İptal Gerekçesi</label>
                    <input wire:model="cancelReason" id="input-cancel-reason" type="text" placeholder="İade alındı / Hatalı işlem..." class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="cancelSale" id="btn-submit-cancel" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-[6px] transition-colors min-h-[44px]">Satışı İptal Et</button>
                    <button wire:click="$set('showCancelModal', false)" id="btn-close-cancel-modal" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">Vazgeç</button>
                </div>
            </div>
        </div>
    @endif
</div>
