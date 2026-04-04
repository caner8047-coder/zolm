@if($showEditOrderModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data @keydown.escape.window="$wire.closeEditOrderModal()">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeEditOrderModal"></div>
    <div class="relative flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all w-full sm:my-8 sm:max-w-4xl">
            <form wire:submit.prevent="saveOrderEdits">
                <div class="border-b border-gray-100 bg-white px-4 pt-5 sm:px-6">
                    <h3 class="mb-1 text-lg font-semibold text-gray-900">Siparişi Düzenle</h3>
                    <p class="mb-4 text-sm text-gray-500">Sipariş kaydıyla birlikte sipariş içeriğindeki ürün satırlarını da aynı ekranda güncelleyin.</p>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-4 py-5 sm:px-6">
                    <div class="space-y-6">
                        <div>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Sipariş kaydı</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">Temel sipariş bilgileri</p>
                                </div>
                            </div>

                            <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Sipariş No *</label>
                                <input type="text"
                                       wire:model.defer="orderForm.order_number"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="ORD-12345">
                                @error('orderForm.order_number') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Durum *</label>
                                <input type="text"
                                       wire:model.defer="orderForm.order_status"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Created / Onaylandı / Teslim edildi">
                                @error('orderForm.order_status') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Müşteri adı</label>
                                <input type="text"
                                       wire:model.defer="orderForm.customer_name"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Ad Soyad">
                                @error('orderForm.customer_name') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">E-posta</label>
                                <input type="email"
                                       wire:model.defer="orderForm.customer_email"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="musteri@example.com">
                                @error('orderForm.customer_email') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Telefon</label>
                                <input type="text"
                                       wire:model.defer="orderForm.customer_phone"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="05xx xxx xx xx">
                                @error('orderForm.customer_phone') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Sipariş tarihi</label>
                                <input type="datetime-local"
                                       wire:model.defer="orderForm.ordered_at"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                @error('orderForm.ordered_at') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Ticari tip</label>
                                <input type="text"
                                       wire:model.defer="orderForm.commercial_type"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Bireysel / Kurumsal">
                                @error('orderForm.commercial_type') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Fatura adı</label>
                                <input type="text"
                                       wire:model.defer="orderForm.billing_name"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Fatura unvanı / ad soyad">
                                @error('orderForm.billing_name') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Vergi no</label>
                                <input type="text"
                                       wire:model.defer="orderForm.billing_tax_number"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="1234567890">
                                @error('orderForm.billing_tax_number') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Şehir</label>
                                <input type="text"
                                       wire:model.defer="orderForm.shipment_city"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="İstanbul">
                                @error('orderForm.shipment_city') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">İlçe</label>
                                <input type="text"
                                       wire:model.defer="orderForm.shipment_district"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Kadıköy">
                                @error('orderForm.shipment_district') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Sipariş içeriği</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">Ürün satırları</p>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-medium text-slate-600">
                                    {{ count($orderItemsForm) }} satır
                                </span>
                            </div>

                            @if(count($orderItemsForm) > 0)
                                <div class="space-y-3">
                                    @foreach($orderItemsForm as $index => $item)
                                        <div wire:key="order-item-edit-{{ $item['id'] }}"
                                             class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-900">Satır #{{ $index + 1 }}</p>
                                                    <p class="mt-1 text-[11px] text-slate-500">
                                                        @if($item['package_label'])
                                                            Paket {{ $item['package_label'] }}
                                                        @else
                                                            Paketsiz satır
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-medium text-slate-600">
                                                        {{ $item['line_status'] !== '' ? $item['line_status'] : 'Durum yok' }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 gap-4">
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Ürün adı</label>
                                                    <input type="text"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.product_name"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                                           placeholder="Ürün adı">
                                                    @error('orderItemsForm.' . $index . '.product_name') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Barkod</label>
                                                    <input type="text"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.barcode"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                                           placeholder="869...">
                                                    @error('orderItemsForm.' . $index . '.barcode') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Stok kodu</label>
                                                    <input type="text"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.stock_code"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                                           placeholder="SKU-001">
                                                    @error('orderItemsForm.' . $index . '.stock_code') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Satır durumu</label>
                                                    <input type="text"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.line_status"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500"
                                                           placeholder="Created / Delivered">
                                                    @error('orderItemsForm.' . $index . '.line_status') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Adet *</label>
                                                    <input type="number"
                                                           min="1"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.quantity"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                                    @error('orderItemsForm.' . $index . '.quantity') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Birim fiyat</label>
                                                    <input type="number"
                                                           min="0"
                                                           step="0.01"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.unit_price"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                                    @error('orderItemsForm.' . $index . '.unit_price') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Brüt tutar</label>
                                                    <input type="number"
                                                           min="0"
                                                           step="0.01"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.gross_amount"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                                    @error('orderItemsForm.' . $index . '.gross_amount') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">İndirim</label>
                                                    <input type="number"
                                                           min="0"
                                                           step="0.01"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.discount_amount"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                                    @error('orderItemsForm.' . $index . '.discount_amount') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Faturalanacak tutar</label>
                                                    <input type="number"
                                                           min="0"
                                                           step="0.01"
                                                           wire:model.defer="orderItemsForm.{{ $index }}.billable_amount"
                                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                                    @error('orderItemsForm.' . $index . '.billable_amount') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="flex items-end">
                                                    <p class="text-xs leading-5 text-slate-400">
                                                        Bu alanlar sipariş kartı, detay görünümü ve muhasebe eşleşmelerinde görünen satır bilgisini etkiler.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    Bu sipariş için düzenlenebilir ürün satırı bulunamadı.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-slate-50/60 px-4 py-4 sm:px-6">
                    <button type="button"
                            wire:click="closeEditOrderModal"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        İptal
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="saveOrderEdits"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveOrderEdits">Güncelle</span>
                        <span wire:loading wire:target="saveOrderEdits">Kaydediliyor...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
