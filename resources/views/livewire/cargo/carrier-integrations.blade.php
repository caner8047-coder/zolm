@php
    $carriers = $this->carriers;
@endphp

<div class="flex flex-col gap-4 sm:flex-row lg:gap-6">
    <section class="min-w-0 flex-1 rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <h2 class="text-xl font-semibold text-slate-900 lg:text-2xl">Kargo taşıyıcıları</h2>
            <p class="mt-1 text-sm text-slate-500">Taşıyıcı hesabınızı ekleyin, bağlantıyı doğrulayın ve gönderi operasyonunda kullanmaya başlayın.</p>
        </div>

        @if($feedback && !$selectedCarrierCode)
            <div class="mx-4 mt-4 rounded-[8px] border p-3 text-sm lg:mx-6 {{ $feedbackTone === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : ($feedbackTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-700') }}">
                {{ $feedback }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:gap-4 lg:p-6 xl:grid-cols-3">
            @foreach($carriers as $code => $carrier)
                @php
                    $marketplaceManaged = ($carrier['integration_status'] ?? null) === 'marketplace_managed';
                    $available = filled($carrier['connector'] ?? null);
                    $connected = ($carrier['connected_count'] ?? 0) > 0;
                    $saved = ($carrier['account_count'] ?? 0) > 0;
                    $hasError = (bool) ($carrier['has_error'] ?? false);
                    $status = match (true) {
                        $marketplaceManaged => ['label' => 'Mağaza üzerinden', 'tone' => 'border-violet-200 bg-violet-50 text-violet-700', 'detail' => 'Trendyol Express gönderileri bağlı Trendyol mağazası ve paketleri üzerinden otomatik yönetilir.'],
                        $connected => ['label' => 'Bağlı', 'tone' => 'border-emerald-200 bg-emerald-50 text-emerald-700', 'detail' => 'Hesap doğrulandı; gönderi oluşturma ve takip akışında kullanılabilir.'],
                        $hasError => ['label' => 'Kontrol gerekli', 'tone' => 'border-red-200 bg-red-50 text-red-700', 'detail' => 'Hesap kaydedildi ancak son bağlantı testi başarısız oldu. Bilgileri kontrol edin.'],
                        $available && $saved => ['label' => 'Test bekliyor', 'tone' => 'border-amber-200 bg-amber-50 text-amber-800', 'detail' => 'Hesap kaydedildi. Kullanıma açmak için bağlantı testini tamamlayın.'],
                        $available => ['label' => 'Kuruluma hazır', 'tone' => 'border-blue-200 bg-blue-50 text-blue-700', 'detail' => 'Kargo firmasının verdiği hesap bilgilerini girerek kurulumu tamamlayabilirsiniz.'],
                        default => ['label' => 'Rapor / takip', 'tone' => 'border-slate-200 bg-slate-50 text-slate-600', 'detail' => 'Bu taşıyıcı ZOLM’de rapor eşleştirme ve genel takip bağlantısı için kullanılabilir.'],
                    };
                @endphp

                <article class="flex min-w-0 flex-col rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold text-slate-900">{{ $carrier['name'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ collect($carrier['capabilities'] ?? [])->map(fn($capability) => ['create' => 'Gönderi', 'cancel' => 'İptal', 'track' => 'Takip', 'invoice' => 'Fatura'][$capability] ?? $capability)->implode(' · ') ?: 'Rapor · Takip bağlantısı' }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-[6px] border px-2 py-0.5 text-xs font-medium {{ $status['tone'] }}">{{ $status['label'] }}</span>
                    </div>

                    <p class="mt-3 flex-1 text-sm leading-5 text-slate-600">{{ $status['detail'] }}</p>

                    <div class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-xs text-slate-500">{{ $carrier['account_count'] }} hesap</span>
                        @if($marketplaceManaged)
                            <button type="button" wire:click="openSetup('{{ $code }}')" class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                                Trendyol mağazasını bağla
                            </button>
                        @elseif($available)
                            <button type="button" wire:click="openSetup('{{ $code }}')" class="w-full min-h-[44px] rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:py-2">
                                {{ $saved ? 'Hesapları yönet' : 'Hesap ekle' }}
                            </button>
                        @else
                            <span class="text-xs font-medium text-slate-600">API hesabı kullanılmıyor</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @if($selectedCarrierCode && $this->selectedCarrier)
        @php
            $selected = $this->selectedCarrier;
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/30 p-0 sm:items-center sm:p-4" wire:keydown.escape.window="closeSetup">
            <section class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-t-[10px] border border-slate-200 bg-white shadow-xl sm:rounded-[10px]">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-200 bg-white p-4 lg:p-6">
                    <div class="min-w-0">
                        <h3 class="text-xl font-semibold text-slate-900">{{ $selected['name'] }} hesabı</h3>
                        <p class="mt-1 text-sm text-slate-500">Firmanın size verdiği bilgileri girin. Parolalar şifrelenmiş olarak saklanır.</p>
                    </div>
                    <button type="button" wire:click="closeSetup" class="min-h-[44px] shrink-0 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 sm:py-2">Kapat</button>
                </div>

                <div class="space-y-4 p-4 lg:space-y-6 lg:p-6">
                    @if($feedback)
                        <div class="rounded-[8px] border p-3 text-sm {{ $feedbackTone === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                            {{ $feedback }}
                        </div>
                    @endif

                    @if($this->carrierAccounts->isNotEmpty())
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach($this->carrierAccounts as $account)
                                    <div class="flex min-w-0 items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-white p-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $account->account_name }}</p>
                                            <p class="mt-0.5 text-xs {{ $account->status === 'connected' ? 'text-emerald-700' : ($account->status === 'error' ? 'text-red-700' : 'text-slate-500') }}">
                                                {{ $account->status === 'connected' ? 'Bağlı' : ($account->status === 'error' ? 'Bağlantı hatası' : 'Test bekliyor') }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 gap-2">
                                            <button type="button" wire:click="openSetup('{{ $selectedCarrierCode }}', {{ $account->id }})" class="min-h-[44px] rounded-[6px] border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700">Düzenle</button>
                                            <button type="button" wire:click="testAccount({{ $account->id }})" wire:loading.attr="disabled" wire:target="testAccount({{ $account->id }})" class="min-h-[44px] rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white disabled:opacity-50">Test et</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form wire:submit="saveAccount" autocomplete="off" data-form-type="other" class="space-y-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:gap-4">
                            <label class="block min-w-0">
                                <span class="text-sm font-medium text-slate-700">Hesap adı</span>
                                <input type="text" wire:model.defer="form.account_name" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm">
                                @error('form.account_name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block min-w-0">
                                <span class="text-sm font-medium text-slate-700">Firma / tüzel kişilik</span>
                                <select wire:model.defer="form.legal_entity_id" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm">
                                    <option value="">Genel hesap</option>
                                    @foreach($this->legalEntities as $entity)
                                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block min-w-0">
                                <span class="text-sm font-medium text-slate-700">Müşteri / cari kodu</span>
                                <input type="text" wire:model.defer="form.customer_code" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm">
                            </label>
                            <label class="block min-w-0">
                                <span class="text-sm font-medium text-slate-700">Ortam</span>
                                <select wire:model.live="form.environment" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm">
                                    <option value="test">Test</option>
                                    <option value="live">Canlı</option>
                                </select>
                            </label>

                            @foreach($selected['setup_fields'] ?? [] as $field)
                                <label class="block min-w-0">
                                    @if(($field['type'] ?? null) === 'checkbox')
                                        <span class="mt-6 flex min-h-[44px] items-center gap-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" wire:model.defer="form.credentials.{{ $field['key'] }}" class="rounded border-slate-300">
                                            {{ $field['label'] }}
                                        </span>
                                    @else
                                        <span class="text-sm font-medium text-slate-700">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-red-500">*</span>@endif</span>
                                        <input
                                            id="cargo-credential-{{ $selectedCarrierCode }}-{{ $field['key'] }}"
                                            name="cargo_{{ $selectedCarrierCode }}_{{ $field['key'] }}"
                                            type="{{ $field['type'] ?? 'text' }}"
                                            wire:model.defer="form.credentials.{{ $field['key'] }}"
                                            wire:key="cargo-credential-{{ $selectedCarrierCode }}-{{ $field['key'] }}"
                                            autocomplete="{{ ($field['secret'] ?? false) ? 'new-password' : 'off' }}"
                                            autocapitalize="none"
                                            spellcheck="false"
                                            data-1p-ignore
                                            data-lpignore="true"
                                            data-form-type="other"
                                            @if(($field['secret'] ?? false) && $editingAccountId) placeholder="Değiştirmek istemiyorsanız boş bırakın" @endif
                                            class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm"
                                        >
                                    @endif
                                    @if($field['help'] ?? null)<span class="mt-1 block text-xs text-slate-500">{{ $field['help'] }}</span>@endif
                                    @error('form.credentials.'.$field['key']) <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Gönderici bilgileri</h4>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:gap-4">
                                <input type="text" wire:model.defer="form.contact_name" placeholder="Yetkili adı" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                                <input type="text" wire:model.defer="form.contact_phone" placeholder="Telefon" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                                <input type="text" wire:model.defer="form.origin_city" placeholder="Çıkış ili" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                                <input type="text" wire:model.defer="form.origin_district" placeholder="Çıkış ilçesi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm">
                                <textarea wire:model.defer="form.origin_address" rows="3" placeholder="Gönderici adresi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:col-span-2 sm:text-sm"></textarea>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                                <label class="flex items-center gap-2"><input type="checkbox" wire:model.defer="form.is_default" class="rounded border-slate-300"> Varsayılan hesap</label>
                                <label class="flex items-center gap-2"><input type="checkbox" wire:model.defer="form.is_active" class="rounded border-slate-300"> Aktif</label>
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveAccount" class="w-full min-h-[44px] rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-50 sm:w-auto sm:py-2">
                                {{ $editingAccountId ? 'Değişiklikleri kaydet' : 'Hesabı kaydet' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    @endif
</div>
