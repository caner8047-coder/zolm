<div class="-mt-1 space-y-4 lg:-mt-4 lg:space-y-6">
    <x-zolm.section-card
        eyebrow="Pazaryeri Ayarları"
        title="Pazaryeri Ayarları"
        description="Tüm pazaryeri modüllerini etkileyen genel tercihleri tek yerden yönetin. Bu ekran modül bazlı ayarları değil, uygulama geneline yayılan kullanıcı tercihlerini toplar."
        padding="p-4 lg:p-6"
        bodyPadding="px-4 pb-4 pt-0 lg:px-6 lg:pb-6 lg:pt-0"
    >
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(280px,0.7fr)] xl:gap-6">
            <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Arayüz ve Yardım</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900">Bilgilendirici yardım katmanı</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            KPI kartları, tablo başlıkları ve bazı kritik aksiyonlarda görünen açıklama ipuçlarını kontrol eder.
                            Masaüstünde hover, mobilde dokunarak açılır. İsteyen kullanıcı tamamen kapatabilir.
                        </p>
                    </div>

                    <div class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                        {{ $helpTipsEnabled ? 'Aktif' : 'Kapalı' }}
                    </div>
                </div>

                <div class="mt-5 rounded-[8px] border border-slate-200 bg-white p-4">
                    <label class="flex cursor-pointer items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Bilgilendirici yardım ipuçlarını göster</p>
                            <p class="mt-1 text-sm leading-5 text-slate-500">
                                Açıkken kullanıcı kavramların ne olduğunu, verinin nereden geldiğini, ne zaman güncellendiğini ve neyi etkilediğini görebilir.
                            </p>
                        </div>

                        <div class="relative pt-0.5">
                            <input type="checkbox" wire:model.live="helpTipsEnabled" class="sr-only peer">
                            <div class="h-7 w-14 rounded-full bg-slate-300 shadow-inner transition peer-checked:bg-slate-900 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-slate-300 after:absolute after:start-[4px] after:top-[6px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-200 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-7"></div>
                        </div>
                    </label>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        wire:click="saveSettings"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full items-center justify-center rounded-[8px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="saveSettings">Ayarları kaydet</span>
                        <span wire:loading wire:target="saveSettings">Kaydediliyor...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="resetUiSettings"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full items-center justify-center rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    >
                        Varsayılanı geri yükle
                    </button>
                </div>

                @if (session('settings_success'))
                    <div class="mt-4 rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('settings_success') }}
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Etkilenen modüller</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($helpTipCoverage as $moduleLabel)
                            <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                {{ $moduleLabel }}
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-4 text-sm leading-6 text-slate-500">
                        Bu tercih aynı anda tüm pazaryeri sayfalarına uygulanır; her ekran için ayrı ayrı ayar yapmanız gerekmez.
                    </p>
                </div>

                <div class="rounded-[10px] border border-dashed border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Yol haritası</p>
                    <div class="mt-4 grid grid-cols-1 gap-3">
                        <div class="rounded-[8px] border border-slate-200 bg-white px-4 py-3">
                            <p class="text-sm font-medium text-slate-900">Kompakt görünüm</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Kart ve tablo boşluklarını daraltan ikinci bir yoğunluk modu burada yönetilecek.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-white px-4 py-3">
                            <p class="text-sm font-medium text-slate-900">Tablo yoğunluğu</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Siparişler, ürünler ve finans için daha sıkı ya da daha rahat satır yoğunluğu seçilebilecek.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-white px-4 py-3">
                            <p class="text-sm font-medium text-slate-900">Varsayılan kolon setleri</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Kullanıcının tercih ettiği kolon görünümü modül bazında burada kalıcı hale getirilecek.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-zolm.section-card>

    <x-zolm.section-card
        eyebrow="Çıktı Çalışma Alanı"
        title="Kargo Barkod ve Etiket Çıktıları"
        description="Sipariş aksiyon menüsünden ve toplu seçimden indirilecek kargo etiketleri ile irsaliyelerin görünümünü buradan yönetebilirsiniz. Firma bilgisi varsa onu, yoksa aşağıdaki fallback gönderici bilgisini kullanır."
        padding="p-4 lg:p-6"
        bodyPadding="px-4 pb-4 pt-0 lg:px-6 lg:pb-6 lg:pt-0"
    >
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.22fr)_minmax(300px,0.78fr)] xl:gap-6">
            <div class="space-y-4">
                <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Etiket Çıktısı</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Kargo etiketi şablonu</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Tekil siparişte veya toplu seçimde oluşturulan PDF etiketleri bu kuralları kullanır.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                {{ $labelTemplateOptions[$labelPrintSettings['template'] ?? 'courier'] ?? 'Kurye standart' }}
                            </span>
                            <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                {{ $labelPaperOptions[$labelPrintSettings['paper'] ?? 'thermal_100x150'] ?? 'Termal 100x150' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Etiket şablonu</label>
                            <select wire:model.live="labelPrintSettings.template" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($labelTemplateOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Kağıt boyutu</label>
                            <select wire:model.live="labelPrintSettings.paper" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($labelPaperOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Barkod yüksekliği</label>
                            <input type="number" min="32" max="96" step="1" wire:model.live="labelPrintSettings.barcode_height" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_sender" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Gönderici alanı</span>
                                <span class="mt-1 block text-xs text-slate-500">Firma veya fallback gönderici kartını göster.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_tracking_number" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Takip numarası</span>
                                <span class="mt-1 block text-xs text-slate-500">Kargo takip numarasını başlıkta sabitle.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_marketplace" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Mağaza bilgisi</span>
                                <span class="mt-1 block text-xs text-slate-500">Pazaryeri mağaza adını etiket üzerinde göster.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_customer_phone" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Müşteri telefonu</span>
                                <span class="mt-1 block text-xs text-slate-500">Alıcı kartında telefon bilgisini ekle.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_items" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Ürün satırları</span>
                                <span class="mt-1 block text-xs text-slate-500">Detaylı ürün listesini göster.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_barcode_text" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Barkod metni</span>
                                <span class="mt-1 block text-xs text-slate-500">Barkodun altında okunabilir metni yazdır.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="labelPrintSettings.show_item_summary" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Kompakt ürün özeti</span>
                                <span class="mt-1 block text-xs text-slate-500">İlk ürünleri kısa rozet olarak ekler.</span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Etiket dip notu</label>
                        <textarea wire:model.live="labelPrintSettings.footer_note" rows="2" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="Örn. Teslimat sırasında paket hasarını kontrol edin."></textarea>
                    </div>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">İrsaliye Çıktısı</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Sevk ve irsaliye şablonu</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Paket bazlı veya sipariş bazlı irsaliye PDF’leri bu alanı kullanır.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                {{ $dispatchTemplateOptions[$dispatchPrintSettings['template'] ?? 'classic'] ?? 'Klasik irsaliye' }}
                            </span>
                            <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                {{ $dispatchPaperOptions[$dispatchPrintSettings['paper'] ?? 'a4'] ?? 'A4 dikey' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">İrsaliye şablonu</label>
                            <select wire:model.live="dispatchPrintSettings.template" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($dispatchTemplateOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Kağıt boyutu</label>
                            <select wire:model.live="dispatchPrintSettings.paper" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                                @foreach($dispatchPaperOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Barkod yüksekliği</label>
                            <input type="number" min="32" max="96" step="1" wire:model.live="dispatchPrintSettings.barcode_height" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm">
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_sender" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Gönderici kartı</span>
                                <span class="mt-1 block text-xs text-slate-500">Sevk belgesinin üstünde firma alanını göster.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_customer_phone" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Telefon alanı</span>
                                <span class="mt-1 block text-xs text-slate-500">Alıcı telefon bilgisini ekle.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_billing_info" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Fatura bilgisi</span>
                                <span class="mt-1 block text-xs text-slate-500">Vergi no ve unvan bilgisini görünür tut.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_items" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Ürün tablosu</span>
                                <span class="mt-1 block text-xs text-slate-500">Adet, barkod ve stok kodu satırlarını yazdır.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_barcode" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Paket barkodu</span>
                                <span class="mt-1 block text-xs text-slate-500">Üst blokta kargo barkodunu göster.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_barcode_text" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Barkod metni</span>
                                <span class="mt-1 block text-xs text-slate-500">İrsaliyede barkod altı metnini görünür tut.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[8px] border border-slate-200 bg-white p-3">
                            <input type="checkbox" wire:model.live="dispatchPrintSettings.show_signature_area" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">İmza alanı</span>
                                <span class="mt-1 block text-xs text-slate-500">Belgenin altında teslim/imza kutusu bırak.</span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-slate-500">İrsaliye dip notu</label>
                        <textarea wire:model.live="dispatchPrintSettings.footer_note" rows="2" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="Örn. Ürün tesliminde hasar ve eksik kontrolü yapılmıştır."></textarea>
                    </div>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Gönderici Fallback Bilgisi</p>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Siparişte bağlı firma kaydı yoksa etiket ve irsaliye bu bilgileri kullanır.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Firma adı</label>
                            <input type="text" wire:model.live="companyForm.name" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="ZOLM Tekstil">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Telefon</label>
                            <input type="text" wire:model.live="companyForm.phone" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="0212 000 00 00">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Vergi numarası</label>
                            <input type="text" wire:model.live="companyForm.tax_number" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="1234567890">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Adres</label>
                            <textarea wire:model.live="companyForm.address" rows="3" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-900 sm:text-sm" placeholder="Mahalle, cadde, no, ilçe / il"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        wire:click="saveDocumentSettings"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full items-center justify-center rounded-[8px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="saveDocumentSettings">Çıktı ayarlarını kaydet</span>
                        <span wire:loading wire:target="saveDocumentSettings">Kaydediliyor...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="resetDocumentSettings"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full items-center justify-center rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    >
                        Çıktı varsayılanlarını geri yükle
                    </button>
                </div>

                @if (session('document_settings_success'))
                    <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('document_settings_success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-[8px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $errors->first() }}
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Canlı kullanım</p>
                    <div class="mt-4 grid grid-cols-1 gap-3">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-sm font-medium text-slate-900">Sipariş aksiyon menüsü</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Her siparişte tekil olarak `Kargo etiketi indir` ve `İrsaliye indir` aksiyonu görünür.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-sm font-medium text-slate-900">Toplu seçim akışı</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Sipariş veya paket seçip toplu PDF oluşturabilirsiniz. Paket seçimi yalnızca seçilen paketleri yazdırır.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-sm font-medium text-slate-900">Barkod katmanı</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Kargo barkodu sipariş, paket veya raw payload içinden çözülür; bulunamazsa sipariş numarası fallback olarak kullanılır.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Çıktı alanları</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Alıcı adı</span>
                        <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Adres</span>
                        <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Kargo barkodu</span>
                        <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Takip no</span>
                        <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Paket ürünleri</span>
                        <span class="inline-flex min-h-[32px] items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Gönderici</span>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-slate-500">
                        Farklı kağıt boyutlarında aynı veri yüzeyini koruyoruz; sadece yoğunluk ve blok yerleşimi değişiyor.
                    </p>
                </div>

                <div class="rounded-[10px] border border-dashed border-slate-200 bg-slate-50/60 p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Not</p>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Buradaki çıktı şablonları iç PDF üretimi içindir. Pazaryerinden gelen ortak barkod servisleri ayrı operasyon aksiyonu olarak korunur; yerel PDF çıktısı bu servislere bağımlı değildir.
                    </p>
                </div>
            </div>
        </div>
    </x-zolm.section-card>
</div>
