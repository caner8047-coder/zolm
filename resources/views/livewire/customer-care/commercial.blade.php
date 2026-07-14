<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Ticari Paket Yönetimi & Limitler</h1>
            <p class="text-sm text-slate-500">Abonelik planları, entitlement (özellik hakkı) denetimleri ve faturalama raporlama.</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 rounded-[8px] p-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-sm text-emerald-700">{{ $successMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Aktif Abonelik ve Plan Değiştirme --}}
        <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6 lg:col-span-1 space-y-6">
            <div>
                <h2 class="text-base font-semibold text-slate-900 mb-3">Aktif Plan Durumu</h2>
                @if($subscription)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs text-slate-500 mb-1">Abonelik Planı</p>
                        <p class="text-xl font-bold text-slate-900">{{ strtoupper($subscription->plan->name ?? '') }}</p>
                        <span class="inline-block px-2 py-0.5 text-xs font-mono rounded mt-2
                            {{ $subscription->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($subscription->status) }}
                        </span>
                        <p class="text-xs text-slate-400 mt-2">Bitiş Tarihi: {{ $subscription->ends_at?->format('d.m.Y H:i') ?? 'Sınırsız' }}</p>
                    </div>
                @else
                    <div class="rounded-[8px] border border-slate-200 bg-red-50 p-4 text-sm text-red-700">
                        ⚠ Aktif abonelik bulunmuyor. Sistem kısıtlı fail-closed modundadır.
                    </div>
                @endif
            </div>

            <div>
                <h2 class="text-base font-semibold text-slate-900 mb-3">Plan Değiştir</h2>
                <div class="space-y-3">
                    <select wire:model="newPlanId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="0">Plan seçin...</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    <button wire:click="requestPlanChange" id="btn-change-plan"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                        Plana Geç
                    </button>
                </div>
            </div>
        </div>

        {{-- Loglar ve Dışa Aktarma --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- billing readiness export --}}
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-3">Billing Readiness Raporu</h2>
                <p class="text-xs text-slate-500 mb-4">Seçili aya ait özellik kullanım/blokaj olaylarını faturalama entegrasyonu için dışa aktarın.</p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input wire:model="exportMonth" type="text" id="input-export-month" placeholder="YYYY-MM"
                        class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
                    <button wire:click="exportBillingData" id="btn-export-billing"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                        Faturalama Raporunu İndir
                    </button>
                </div>
            </div>

            {{-- son entitlement olayları --}}
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4">Son Özellik Kullanım Denetimleri</h2>
                @if($events->isEmpty())
                    <p class="text-sm text-slate-400 text-center py-4">Kullanım geçmişi bulunamadı.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($events as $ev)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-mono text-slate-600 mr-2">{{ $ev->feature }}</span>
                                    <span class="text-slate-400">({{ $ev->context['reason'] ?? 'Normal' }})</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded font-mono
                                        {{ $ev->status === 'blocked' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ strtoupper($ev->status) }}
                                    </span>
                                    <span class="text-slate-400">{{ $ev->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
