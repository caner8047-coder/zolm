@php
    $accounts = $this->accounts;
    $legalEntities = $this->legalEntities;
@endphp

<div class="space-y-4 lg:space-y-6">
    @if(!$this->tableReady)
        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Sürat entegrasyon tabloları henüz hazır değil. Migration çalıştıktan sonra hesap bilgileri kaydedilebilir.
        </div>
    @endif

    @if($message)
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($messageTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-slate-50 text-slate-700') }}">
            {{ $message }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4 lg:gap-6">
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Sürat Kargo hesabı</h2>
                        <p class="mt-1 text-sm text-slate-500">Gönderim şifresi ve sorgulama/web servis şifresi ayrı tutulur; tüm hassas alanlar encrypted kaydedilir.</p>
                    </div>
                    <button type="button" wire:click="newAccount" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                        Yeni Hesap
                    </button>
                </div>
            </div>

            <form wire:submit.prevent="save" class="space-y-4 p-4 lg:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Cari / tüzel kişi</label>
                        <select wire:model.defer="form.legal_entity_id" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                            <option value="">Genel hesap</option>
                            @foreach($legalEntities as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Ortam</label>
                        <select wire:model.live="form.environment" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                            <option value="live">Canlı</option>
                            <option value="test">Prova / test</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Hesap adı</label>
                        <input type="text" wire:model.defer="form.account_name" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm" placeholder="Sürat Kargo">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Müşteri kodu</label>
                        <input type="text" wire:model.defer="form.customer_code" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm" placeholder="Müşteri / mağaza kodu">
                        @error('form.customer_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Gönderim kullanıcı adı</label>
                        <input type="text" wire:model.defer="form.sender_username" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Gönderim şifresi</label>
                        <input type="password" wire:model.defer="form.sender_password" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm" placeholder="{{ $editingAccountId ? 'Değişmeyecekse boş bırakın' : '' }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Sorgulama/Web servis şifresi</label>
                        <input type="password" wire:model.defer="form.query_password" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm" placeholder="{{ $editingAccountId ? 'Değişmeyecekse boş bırakın' : '' }}">
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Sürat servis adresleri</h3>
                            <p class="mt-1 text-xs text-slate-500">Canlı/prova adresleri Sürat dokümantasyonuna göre hazır gelir; özel şube adresi verilirse bu alanlardan değiştirilebilir.</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" wire:click="applyLiveDefaults" class="inline-flex min-h-[40px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                Canlı varsayılan
                            </button>
                            <button type="button" wire:click="applyTestDefaults" class="inline-flex min-h-[40px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                Prova varsayılan
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-700">API Base URL</label>
                            <input type="text" wire:model.defer="form.api_base_url" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm" placeholder="Şubeden alınan canlı/test adres">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Sorgulama Base URL</label>
                            <input type="text" wire:model.defer="form.query_base_url" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Şube kodu</label>
                            <input type="text" wire:model.defer="form.branch_code" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Bağlantı test endpoint</label>
                            <input type="text" wire:model.defer="form.test_endpoint" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Test referansı</label>
                            <input type="text" wire:model.defer="form.test_reference" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm" placeholder="Opsiyonel WebSiparisKodu">
                            <p class="mt-1 text-xs text-slate-500">Boş bırakılabilir. Gerçek takip testi için Sürat'e gönderilmiş paket/sipariş referansı yazılır.</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Gönderi oluştur endpoint</label>
                            <input type="text" wire:model.defer="form.create_endpoint" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Takip endpoint</label>
                            <input type="text" wire:model.defer="form.track_endpoint" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">İptal endpoint</label>
                            <input type="text" wire:model.defer="form.cancel_endpoint" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Geri çek endpoint</label>
                            <input type="text" wire:model.defer="form.recall_endpoint" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Fatura endpoint</label>
                            <input type="text" wire:model.defer="form.invoice_endpoint" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                        <div class="sm:col-span-2 xl:col-span-3">
                            <label class="text-sm font-medium text-slate-700">SOAP WSDL</label>
                            <input type="text" wire:model.defer="form.soap_wsdl_url" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Çıkış ili</label>
                        <input type="text" wire:model.defer="form.origin_city" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Çıkış ilçesi</label>
                        <input type="text" wire:model.defer="form.origin_district" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Telefon</label>
                        <input type="text" wire:model.defer="form.contact_phone" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm">
                    </div>
                    <div class="sm:col-span-2 xl:col-span-3">
                        <label class="text-sm font-medium text-slate-700">Çıkış adresi</label>
                        <textarea wire:model.defer="form.origin_address" rows="3" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm"></textarea>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-200 pt-4">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model.defer="form.is_default" class="rounded border-slate-300 text-slate-900">
                            Varsayılan hesap
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model.defer="form.is_active" class="rounded border-slate-300 text-slate-900">
                            Aktif
                        </label>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                        Kaydet
                    </button>
                </div>
            </form>

        </section>

        <aside class="space-y-4">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Kayıtlı Sürat hesapları</h3>
                <div class="mt-3 space-y-2">
                    @forelse($accounts as $account)
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $account->account_name ?: 'Sürat Kargo' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $account->customer_code ?: 'Müşteri kodu yok' }}</p>
                                </div>
                                @if($account->is_default)
                                    <span class="rounded-[6px] bg-slate-900 px-2 py-0.5 text-[10px] font-semibold text-white">Varsayılan</span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs {{ $account->last_error ? 'text-amber-700' : 'text-slate-500' }}">
                                {{ $account->last_error ?: ($account->last_verified_at ? 'Son test: ' . $account->last_verified_at->format('d.m.Y H:i') : 'Test bekliyor') }}
                            </p>
                            <div class="mt-3 flex gap-2">
                                <button type="button" wire:click="editAccount({{ $account->id }})" class="inline-flex min-h-[36px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">Düzenle</button>
                                <button type="button" wire:click="testConnection({{ $account->id }})" class="inline-flex min-h-[36px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white">Test</button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            Henüz Sürat hesabı yok.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[10px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Uygulama notu</h3>
                <p class="mt-2 text-sm text-slate-500">Sürat fatura API vermediği için kargo bedeli takip servisindeki Tutar + OlcumTutar toplamından alınır. Gönderi oluşturma, takip, pasife alma ve geri çekme REST servisleriyle çalışır.</p>
            </section>
        </aside>
    </div>
</div>
