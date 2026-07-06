<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Veri İçe Aktarma
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Reklam Verisi İçe Aktar</h1>
                <p class="mt-1 text-sm text-slate-500">Trendyol reklam raporlarınızı Excel/CSV olarak yükleyin.</p>
            </div>
        </div>
    </section>

    {{-- Durum Mesajı --}}
    @if($statusMessage)
        <section class="rounded-2xl border {{ str_contains($statusMessage, 'Hata') ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
            <p class="text-sm {{ str_contains($statusMessage, 'Hata') ? 'text-rose-700' : 'text-emerald-700' }}">{{ $statusMessage }}</p>
        </section>
    @endif

    {{-- Import Formu --}}
    @if(!$showPreview)
        <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 lg:gap-6">
                <div class="min-w-0 xl:col-span-7 xl:py-1">
                    {{-- Dosya Yükleme --}}
                    <label class="group flex cursor-pointer rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-slate-400 hover:bg-white">
                        <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="hidden">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $file ? $file->getClientOriginalName() : 'Reklam raporunu seçin (.xlsx, .xls, .csv)' }}</p>
                                <p class="mt-1 text-xs sm:text-sm text-slate-500">Trendyol'dan dışa aktardığınız Excel dosyasını yükleyin.</p>
                            </div>
                        </div>
                    </label>

                    @error('file')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    {{-- Form Alanları --}}
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                        {{-- Reklam Hesabı --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Reklam Hesabı</label>
                            <div class="mt-1 flex gap-2">
                                <select wire:model="selectedAccountId" class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                                    <option value="">Seçin</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account['id'] }}">{{ $account['account_name'] }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="openNewAccountModal" class="shrink-0 px-3 py-2 text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                    + Yeni
                                </button>
                            </div>
                            @error('selectedAccountId')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Import Türü --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Rapor Türü</label>
                            <select wire:model="importType" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                                <option value="">Seçin</option>
                                @foreach($importTypes as $type)
                                    <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                                @endforeach
                            </select>
                            @error('importType')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Rapor Başlangıç --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Rapor Başlangıç Tarihi</label>
                            <input type="date" wire:model="reportPeriodStart" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                            @error('reportPeriodStart')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Rapor Bitiş --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Rapor Bitiş Tarihi</label>
                            <input type="date" wire:model="reportPeriodEnd" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                            @error('reportPeriodEnd')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Export Tarihi --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Export Tarihi (Opsiyonel)</label>
                            <input type="datetime-local" wire:model="exportedAt" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                        </div>

                        {{-- Kampanya Seçimi --}}
                        @if(\App\Enums\AdImportType::tryFrom($importType)?->requiresCampaignContext())
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Kampanya <span class="text-rose-500">*</span></label>
                                <select wire:model="selectedCampaignId" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                                    <option value="">Kampanya seçin</option>
                                    {{-- Kampanya adayları burada listelenecek --}}
                                </select>
                                @error('selectedCampaignId')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    {{-- Yükle Butonu --}}
                    <div class="mt-4">
                        <button
                            type="button"
                            wire:click="uploadAndPreview"
                            wire:loading.attr="disabled"
                            wire:target="uploadAndPreview,file"
                            class="w-full sm:w-auto px-6 py-3 text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 transition-colors"
                        >
                            <span wire:loading.remove wire:target="uploadAndPreview">Yükle ve Önizle</span>
                            <span wire:loading wire:target="uploadAndPreview">İşleniyor...</span>
                        </button>
                    </div>
                </div>

                {{-- Sağ Panel --}}
                <div class="xl:col-span-5 space-y-4">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Desteklenen Raporlar</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Ürün Reklamları Genel Rapor
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Ürün Reklamları Kampanya-Ürün Rapor
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Mağaza Reklamları Kelime Raporu
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Influencer Raporu
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Notlar</p>
                        <ul class="mt-3 space-y-2 text-xs text-slate-500">
                            <li>Aynı dosya tekrar yüklendiğinde otomatik olarak engellenir.</li>
                            <li>Kampanya-Ürün, Kelime ve Influencer raporlarında kampanya seçimi zorunludur.</li>
                            <li>Genel rapor birden fazla kampanya içerebilir.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Önizleme --}}
    @if($showPreview)
        <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Önizleme</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Toplam {{ $batchStats['total'] }} satır:
                        <span class="text-emerald-600 font-medium">{{ $batchStats['valid'] }} geçerli</span>,
                        <span class="text-rose-600 font-medium">{{ $batchStats['invalid'] }} hatalı</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="cancelImport" class="px-4 py-2 text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                        İptal
                    </button>
                    <button
                        wire:click="confirmImport"
                        wire:loading.attr="disabled"
                        wire:target="confirmImport"
                        class="px-4 py-2 text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 transition-colors"
                    >
                        <span wire:loading.remove wire:target="confirmImport">İçe Aktar</span>
                        <span wire:loading wire:target="confirmImport">Aktarılıyor...</span>
                    </button>
                </div>
            </div>

            {{-- Önizleme Tablosu --}}
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Satır</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Durum</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Veri</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewRows as $row)
                            <tr class="border-b border-slate-100 {{ $row['status'] === 'error' ? 'bg-rose-50' : '' }}">
                                <td class="px-3 py-2 text-slate-600">{{ $row['row_number'] }}</td>
                                <td class="px-3 py-2">
                                    @if($row['status'] === 'valid')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Geçerli</span>
                                    @elseif($row['status'] === 'error')
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Hatalı</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Bekliyor</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600 max-w-xs truncate">
                                    @if($row['errors'])
                                        <span class="text-rose-600 text-xs">{{ implode(', ', $row['errors']) }}</span>
                                    @elseif($row['normalized'])
                                        {{ json_encode(array_slice($row['normalized'], 0, 3)) }}
                                    @else
                                        {{ json_encode(array_slice($row['raw'], 0, 3)) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Import Geçmişi --}}
    @if(count($importHistory) > 0 && !$showPreview)
        <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Import Geçmişi</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Dosya</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Tür</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Dönem</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Durum</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($importHistory as $import)
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2 text-slate-900 font-medium max-w-xs truncate">{{ $import['source_filename'] }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ \App\Enums\AdImportType::tryFrom($import['import_type'])?->label() ?? $import['import_type'] }}</td>
                                <td class="px-3 py-2 text-slate-600 text-xs">{{ $import['report_period_start'] }} — {{ $import['report_period_end'] }}</td>
                                <td class="px-3 py-2">
                                    @php $status = \App\Enums\AdImportStatus::tryFrom($import['status']); @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $status?->color() ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $status?->label() ?? $import['status'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-500 text-xs">{{ $import['created_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Yeni Reklam Hesabı Modalı --}}
    @if($showNewAccountModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeNewAccountModal"></div>
                <div class="relative inline-block transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold text-slate-900" id="modal-title">Yeni Reklam Hesabı Ekle</h3>
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
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Pazaryeri</label>
                                    <input type="text" value="Trendyol" disabled class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base sm:text-sm text-slate-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Para Birimi</label>
                                    <input type="text" value="TRY" disabled class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base sm:text-sm text-slate-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button wire:click="createNewAccount" type="button" class="inline-flex w-full justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:ml-3">
                            Kaydet
                        </button>
                        <button wire:click="closeNewAccountModal" type="button" class="mt-3 inline-flex w-full justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                            İptal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
