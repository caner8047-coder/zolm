@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    POS / Perakende Satış
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Hızlı Satış (POS) Kasası</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Terminal ve vardiya açılışlarını yapın, sepet arayüzü ile hızlı barkodlu perakende satış yapın ve nakit/kart tahsilatları ile faturaları anında kapatın.
                </p>
            </div>
            <div class="shrink-0 flex gap-2">
                <button wire:click="$toggle('showTerminalForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Yeni Terminal Tanımla
                </button>
            </div>
        </div>
    </section>

    {{-- Yeni Terminal Formu --}}
    @if($showTerminalForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Yeni Satış Terminali Tanımla</h3>
            <form wire:submit.prevent="createTerminal" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Terminal Adı</label>
                    <input type="text" wire:model="terminalName" placeholder="Örn: 1 Nolu Kasa, POS-A" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('terminalName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showTerminalForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Terminali Aç</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Ana Arayüz: İki Kolon --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Sol Kısım: Terminal, Vardiya & Geçmiş (1 Kolon) --}}
        <div class="space-y-6">
            {{-- Terminal Seçimi & Vardiya --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
                <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">Kasa / Terminal Seçimi</h3>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Aktif Terminal</label>
                    <select wire:change="selectTerminal($event.target.value)" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Terminal Seçin...</option>
                        @foreach($this->terminals as $term)
                            <option value="{{ $term->id }}" {{ $selectedTerminalId === $term->id ? 'selected' : '' }}>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if($this->activeTerminal)
                    <div class="pt-2 space-y-3">
                        <div class="text-sm font-medium text-slate-800">Vardiya Durumu:</div>
                        @if($this->activeShift)
                            {{-- Vardiya Açık --}}
                            <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-xs space-y-1.5 text-emerald-800">
                                <div>Vardiya ID: <span class="font-semibold">#{{ $this->activeShift->id }}</span></div>
                                <div>Açılış Zamanı: <span class="font-semibold">{{ $this->activeShift->opened_at->format('d.m.Y H:i') }}</span></div>
                                <div>Açılış Kasası: <span class="font-semibold">{{ $formatMoney($this->activeShift->opening_balance) }}</span></div>
                                <div>Satış Toplamı: <span class="font-bold">{{ $formatMoney($this->recentSales->sum('amount')) }}</span></div>
                            </div>
                            <button wire:click="$set('showShiftCloseForm', true)" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 transition-colors min-h-[44px]">
                                Vardiyayı Kapat (Gün Sonu)
                            </button>
                        @else
                            {{-- Vardiya Kapalı --}}
                            <div class="bg-slate-50 border border-slate-200 rounded-[8px] p-3 text-xs text-slate-500 text-center">
                                Bu terminal için açık bir vardiya bulunmuyor. Satış yapmak için önce vardiya açmalısınız.
                            </div>
                            <button wire:click="$set('showShiftOpenForm', true)" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                                Yeni Vardiya Aç
                            </button>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Vardiya Açma Modali --}}
            @if($showShiftOpenForm)
                <div class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-[10px] border border-slate-200 max-w-sm w-full p-6 shadow-xl space-y-4">
                        <h3 class="text-base font-semibold text-slate-900">Vardiyayı Başlat</h3>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açılış Kasa Nakit Tutarı (TRY)</label>
                            <input type="number" step="0.01" wire:model="shiftOpeningBalance" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                            <button type="button" wire:click="$set('showShiftOpenForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">Vazgeç</button>
                            <button type="button" wire:click="openShift" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-[6px] hover:bg-emerald-700 min-h-[44px]">Vardiyayı Aç</button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Vardiya Kapatma Modali --}}
            @if($showShiftCloseForm)
                <div class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-[10px] border border-slate-200 max-w-sm w-full p-6 shadow-xl space-y-4">
                        <h3 class="text-base font-semibold text-slate-900">Vardiyayı Kapat (Gün Sonu Raporu)</h3>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kapanış Kasa Tutar Sayımı (TRY)</label>
                            <input type="number" step="0.01" wire:model="shiftClosingBalance" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                            <button type="button" wire:click="$set('showShiftCloseForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">Vazgeç</button>
                            <button type="button" wire:click="closeShift" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 min-h-[44px]">Vardiyayı Kapat</button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Vardiya İçi Son Satışlar --}}
            @if($this->activeShift)
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-slate-900">Vardiyadaki Son Satışlar</h3>
                    <div class="divide-y divide-slate-100 max-h-[300px] overflow-y-auto">
                        @forelse($this->recentSales as $sale)
                            <div class="py-2.5 flex justify-between items-center text-xs">
                                <div>
                                    <div class="font-semibold text-slate-900">#{{ $sale->salesOrder->document_number }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $sale->payment_method === 'cash' ? 'Nakit' : 'Kredi Kartı' }}</div>
                                </div>
                                <div class="text-right font-mono font-bold text-slate-900">
                                    {{ $formatMoney($sale->amount) }}
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 py-6 text-center">Bu vardiyada henüz satış yapılmadı.</div>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>

        {{-- Sağ Kısım: POS Kasa Satış Arayüzü (2 Kolon) --}}
        <div class="lg:col-span-2 space-y-6">
            @if($this->activeShift)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    {{-- Ürün Seçim Alanı (1/3 genişlik) --}}
                    <div class="md:col-span-1 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                        <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Ürün Listesi</h3>
                        <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                            @foreach($this->products as $p)
                                <div wire:click="addToCart('{{ $p->stock_code }}')" class="p-2 border border-slate-100 rounded-lg hover:border-slate-300 bg-slate-50/50 cursor-pointer transition-all flex flex-col justify-between">
                                    <div class="font-medium text-slate-800 text-xs truncate">{{ $p->product_name }}</div>
                                    <div class="flex justify-between items-center mt-1.5 font-mono text-[10px]">
                                        <span class="text-slate-400">{{ $p->stock_code }}</span>
                                        <span class="font-bold text-slate-900">{{ $formatMoney($p->sale_price ?? $p->sales_price_try ?? 10.00) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Sepet & Ödeme Alanı (2/3 genişlik) --}}
                    <div class="md:col-span-2 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm flex flex-col justify-between space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Alışveriş Sepeti</h3>

                            {{-- Sepet Listesi --}}
                            <div class="divide-y divide-slate-100 overflow-y-auto max-h-[350px] pr-1">
                                @forelse($cart as $index => $item)
                                    <div class="py-3 flex justify-between items-center text-xs gap-3">
                                        <div class="flex-1">
                                            <div class="font-semibold text-slate-900">{{ $item['name'] }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $item['stock_code'] }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="number" value="{{ $item['quantity'] }}" wire:change="updateQuantity({{ $index }}, $event.target.value)" class="w-12 rounded-[6px] border border-slate-200 px-2 py-1 text-center font-mono focus:border-slate-500 focus:outline-none min-h-[36px]" />
                                            <span class="text-slate-400">x</span>
                                            <input type="number" step="0.01" value="{{ $item['unit_price'] }}" wire:change="updateUnitPrice({{ $index }}, $event.target.value)" class="w-20 rounded-[6px] border border-slate-200 px-2 py-1 text-right font-mono focus:border-slate-500 focus:outline-none min-h-[36px]" />
                                        </div>
                                        <div class="text-right font-mono font-bold text-slate-900 w-24">
                                            {{ $formatMoney($item['quantity'] * $item['unit_price']) }}
                                        </div>
                                        <button wire:click="removeFromCart({{ $index }})" class="text-rose-600 hover:text-rose-800 p-1 rounded hover:bg-rose-50 flex items-center justify-center min-w-[32px] min-h-[32px]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-sm text-slate-400 py-12 text-center">Sepetiniz boş. Satış yapmak için sol taraftan ürün seçin.</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Sepet Toplamları & Tahsilat --}}
                        <div class="border-t border-slate-100 pt-4 space-y-4">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Ödeme Yöntemi</label>
                                    <div class="mt-1 flex gap-2">
                                        <button type="button" wire:click="$set('paymentMethod', 'cash')" class="px-4 py-2 text-xs font-semibold rounded-[6px] border transition-colors min-h-[40px] {{ $paymentMethod === 'cash' ? 'bg-slate-900 text-white border-slate-950' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                                            Nakit
                                        </button>
                                        <button type="button" wire:click="$set('paymentMethod', 'credit_card')" class="px-4 py-2 text-xs font-semibold rounded-[6px] border transition-colors min-h-[40px] {{ $paymentMethod === 'credit_card' ? 'bg-slate-900 text-white border-slate-950' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                                            Kredi Kartı
                                        </button>
                                    </div>
                                </div>

                                <div class="text-right font-mono space-y-1 text-xs text-slate-500">
                                    <div>Ara Toplam: <span class="font-bold text-slate-700">{{ $formatMoney($this->subtotal) }}</span></div>
                                    <div>KDV Toplam: <span class="font-bold text-slate-600">{{ $formatMoney($this->vatTotal) }}</span></div>
                                    <div class="text-lg font-bold text-slate-950 mt-1">Ödenecek Tutar: {{ $formatMoney($this->total) }}</div>
                                </div>
                            </div>

                            <button type="button" wire:click="checkout" class="w-full inline-flex items-center justify-center px-4 py-3 text-base font-bold text-white bg-emerald-600 rounded-[6px] hover:bg-emerald-700 transition-colors min-h-[48px]">
                                Satışı Tamamla (Fatura Kes)
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <section class="rounded-[10px] border border-slate-200 bg-white p-8 shadow-sm text-center text-slate-400">
                    <div class="flex flex-col items-center justify-center space-y-2">
                        <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span class="text-sm">Satış yapabilmek için lütfen sol menüden terminal seçimi yapıp vardiya başlatın.</span>
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
