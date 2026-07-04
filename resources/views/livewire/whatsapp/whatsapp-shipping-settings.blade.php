<div class="space-y-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Kargo Bildirimleri</h1>
        <p class="text-sm text-slate-500 mt-1">WhatsApp kargo durum bildirimi ayarları</p>
    </div>

    @if(session('wa_success'))
        <div class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('wa_success') }}</div>
    @endif

    <form wire:submit="saveSettings" class="space-y-6">
        {{-- Ana Toggle --}}
        <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium text-slate-900 text-sm">Kargo Bildirimleri Aktif</div>
                    <div class="text-xs text-slate-500 mt-0.5">Kargo durumu değişikliklerinde WhatsApp mesajı gönderilir</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="shippingEnabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                </label>
            </div>
        </div>

        @if($shippingEnabled)
            {{-- Aşama Seçimi --}}
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
                <div class="font-medium text-slate-900 text-sm">Bildirim Aşamaları</div>
                <div class="space-y-3">
                    @foreach(['shipped' => 'Kargoya Verildi', 'out_for_delivery' => 'Dağıtımda', 'delivered' => 'Teslim Edildi'] as $stage => $label)
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" value="{{ $stage }}"
                                    wire:model="allowedStages"
                                    class="w-4 h-4 text-slate-900 border-gray-300 rounded focus:ring-slate-900">
                                <span class="text-sm text-slate-700">{{ $label }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Template Seçimi --}}
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 space-y-4">
                <div class="font-medium text-slate-900 text-sm">Meta Şablon Seçimi</div>
                <p class="text-xs text-slate-500">Her aşama için onaylanmış bir utility kategorisi şablonu seçin.</p>

                @if($this->availableTemplates->isEmpty())
                    <div class="rounded-[6px] bg-amber-50 border border-amber-200 p-3 text-sm text-amber-700">
                        Henüz onaylanmış şablon bulunmuyor. Önce Şablonlar sayfasından Meta'dan senkronize edin.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @foreach(['shipped' => 'Kargoya Verildi', 'out_for_delivery' => 'Dağıtımda', 'delivered' => 'Teslim Edildi'] as $stage => $label)
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">{{ $label }}</label>
                                <select wire:model="templateIds.{{ $stage }}"
                                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                                    <option value="">— Şablon seçin —</option>
                                    @foreach($this->availableTemplates as $tpl)
                                        <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->language }})</option>
                                    @endforeach
                                </select>
                                @error("templateIds.{$stage}") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Takip Güncelleme --}}
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-slate-900 text-sm">Takip Numarası Güncelleme Mesajı</div>
                        <div class="text-xs text-slate-500 mt-0.5">Takip numarası değiştiğinde düzeltme mesajı gönderilir</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="trackingUpdateEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                    </label>
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit"
                class="rounded-[6px] bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors">
                Kaydet
            </button>
        </div>
    </form>
</div>
