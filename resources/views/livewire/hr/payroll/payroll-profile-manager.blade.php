<div class="space-y-4 p-4 lg:space-y-6 lg:p-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Bordro kontrol merkezi</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900 lg:text-2xl">Çalışan Bordro Profilleri</h1>
                <p class="mt-1 text-sm text-slate-500">Ödeme, SGK ve teşvik parametrelerini tarihçeli ve çift kontrollü yönetin.</p>
            </div>
            <a href="{{ route('hr.payroll') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 sm:w-auto sm:py-2">Bordro alanına dön</a>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs text-slate-500">Aktif çalışan</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $employees->count() }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs text-slate-500">Onaylı profili olan</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $profiledCount }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs text-slate-500">Onay bekleyen sürüm</p>
                <p class="mt-1 text-xl font-semibold {{ $pendingCount ? 'text-amber-700' : 'text-slate-900' }}">{{ $pendingCount }}</p>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(280px,0.8fr)_minmax(0,2.2fr)]">
            <aside class="border-b border-slate-200 bg-slate-50/60 p-4 xl:border-b-0 xl:border-r lg:p-6">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Çalışan seçimi</h2>
                    <p class="mt-1 text-xs text-slate-500">İlk 100 aktif çalışan gösterilir.</p>
                </div>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Ad, sicil veya e-posta ara…" class="mt-4 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:text-sm">

                <div class="mt-3 max-h-[34rem] space-y-2 overflow-y-auto pr-1">
                    @forelse($employees as $employee)
                        @php
                            $approvedProfile = $employee->payrollProfiles->firstWhere('status', 'approved');
                            $pendingProfile = $employee->payrollProfiles->firstWhere('status', 'pending_approval');
                        @endphp
                        <button type="button" wire:click="selectEmployee({{ $employee->id }})" class="w-full min-w-0 rounded-[8px] border p-3 text-left transition {{ $selectedEmployeeId === $employee->id ? 'border-slate-900 bg-white shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                            <span class="flex items-start justify-between gap-2">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-900">{{ $employee->full_name }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $employee->employee_number }}</span>
                                </span>
                                @if($pendingProfile)
                                    <span class="shrink-0 rounded bg-amber-50 px-2 py-0.5 font-mono text-xs text-amber-700">Onayda</span>
                                @elseif($approvedProfile)
                                    <span class="shrink-0 rounded bg-emerald-50 px-2 py-0.5 font-mono text-xs text-emerald-700">Aktif</span>
                                @else
                                    <span class="shrink-0 rounded bg-red-50 px-2 py-0.5 font-mono text-xs text-red-700">Eksik</span>
                                @endif
                            </span>
                        </button>
                    @empty
                        <p class="rounded-[8px] border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">Aramayla eşleşen aktif çalışan yok.</p>
                    @endforelse
                </div>
            </aside>

            <div class="min-w-0 p-4 lg:p-6">
                @if($selectedEmployee)
                    @php
                        $latestApproved = $selectedEmployee->payrollProfiles->sortByDesc('version')->firstWhere('status', 'approved');
                        $pendingProfiles = $selectedEmployee->payrollProfiles->where('status', 'pending_approval')->sortByDesc('version');
                    @endphp
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-slate-900">{{ $selectedEmployee->full_name }}</h2>
                            <p class="text-sm text-slate-500">{{ $selectedEmployee->employee_number }} · Yeni profil sürümü</p>
                        </div>
                        @if($latestApproved)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs text-slate-600">
                                <span class="font-medium text-slate-900">Aktif v{{ $latestApproved->version }}</span>
                                · {{ $latestApproved->effective_from->format('d.m.Y') }}
                                · {{ $latestApproved->payment_method === 'bank' ? ($latestApproved->maskedIban() ?: 'Banka') : 'Nakit' }}
                            </div>
                        @endif
                    </div>

                    @if($pendingProfiles->isNotEmpty())
                        <div class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50/70 p-4">
                            <h3 class="text-sm font-semibold text-amber-900">Onay bekleyen profil sürümleri</h3>
                            <div class="mt-2 space-y-2">
                                @foreach($pendingProfiles as $profile)
                                    <div class="flex flex-col gap-2 rounded-[6px] border border-amber-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 text-xs text-slate-600">
                                            <p class="font-medium text-slate-900">v{{ $profile->version }} · {{ $profile->effective_from->format('d.m.Y') }} · {{ $profile->payment_method === 'bank' ? ($profile->maskedIban() ?: 'Banka') : 'Nakit' }}</p>
                                            <p class="mt-0.5 truncate">{{ $profile->change_reason }}</p>
                                        </div>
                                        @if($profile->created_by !== auth()->id())
                                            <button wire:click="approve({{ $profile->id }})" wire:confirm="Bu bordro profili onaylansın mı?" class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white sm:w-auto sm:py-2">Profili onayla</button>
                                        @else
                                            <span class="text-xs text-amber-800">Bağımsız onaycı bekleniyor</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form wire:submit="save" class="mt-5 space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Geçerlilik ve ödeme</h3>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <label class="text-sm text-slate-700">Başlangıç tarihi *<input wire:model.defer="effectiveFrom" type="date" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                <label class="text-sm text-slate-700">Bitiş tarihi<input wire:model.defer="effectiveUntil" type="date" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                <label class="text-sm text-slate-700">Bordro grubu<input wire:model.defer="payrollGroupCode" placeholder="Örn. BEYAZ-YAKA" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                <label class="text-sm text-slate-700">Ödeme yöntemi *<select wire:model.live="paymentMethod" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm"><option value="bank">Banka</option><option value="cash">Nakit</option></select></label>
                                @if($paymentMethod === 'bank')
                                    <label class="text-sm text-slate-700 sm:col-span-2">IBAN *<input wire:model.defer="iban" autocomplete="off" placeholder="TR__ ____ ____ ____ ____ ____ __" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 font-mono text-base uppercase sm:text-sm"><span class="mt-1 block text-xs text-slate-500">Güvenlik nedeniyle mevcut IBAN gösterilmez; yeni sürüm için tekrar girilir.</span></label>
                                    <label class="text-sm text-slate-700">Banka adı<input wire:model.defer="bankName" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                    <label class="text-sm text-slate-700 sm:col-span-2">Hesap sahibi<input wire:model.defer="bankAccountHolder" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                @endif
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-5">
                            <h3 class="text-sm font-semibold text-slate-900">SGK, teşvik ve istisnalar</h3>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <label class="text-sm text-slate-700">Sosyal güvenlik statüsü *<select wire:model.defer="socialSecurityStatus" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm"><option value="standard">Standart</option><option value="retired">Emekli çalışan</option><option value="apprentice">Çırak</option><option value="intern">Stajyer</option><option value="foreign">Yabancı çalışan</option></select></label>
                                <label class="text-sm text-slate-700">Sigorta kolu kodu<input wire:model.defer="insuranceBranchCode" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                <label class="text-sm text-slate-700">Teşvik kanun kodu<input wire:model.defer="incentiveLawCode" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                <label class="text-sm text-slate-700">Varsayılan eksik gün kodu<input wire:model.defer="missingDayDefaultCode" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></label>
                                <label class="text-sm text-slate-700">Engellilik derecesi<select wire:model.defer="disabilityDegree" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm"><option value="">Yok</option><option value="1">1. derece</option><option value="2">2. derece</option><option value="3">3. derece</option></select></label>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <label class="flex min-h-11 items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 text-sm"><input wire:model.defer="isRetired" type="checkbox"> Emekli</label>
                                <label class="flex min-h-11 items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 text-sm"><input wire:model.defer="isRdEmployee" type="checkbox"> Ar-Ge çalışanı</label>
                                <label class="flex min-h-11 items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 text-sm"><input wire:model.defer="isTechnoparkEmployee" type="checkbox"> Teknokent çalışanı</label>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-5">
                            <label class="text-sm text-slate-700">Değişiklik gerekçesi *<textarea wire:model.defer="changeReason" rows="3" placeholder="Profilin neden oluşturulduğunu veya değiştirildiğini açıklayın." class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm"></textarea></label>
                            @if($errors->any())
                                <div class="mt-3 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                            @endif
                            <div class="mt-4 flex justify-end">
                                <button wire:loading.attr="disabled" wire:target="save" class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-60 sm:w-auto sm:py-2">
                                    <span wire:loading.remove wire:target="save">Onaya gönder</span><span wire:loading wire:target="save">Kaydediliyor…</span>
                                </button>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="flex min-h-80 items-center justify-center rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-6 text-center">
                        <div><p class="text-sm font-medium text-slate-900">Bordro profili için çalışan seçin</p><p class="mt-1 text-sm text-slate-500">Ödeme ve SGK parametreleri burada tarihçeli olarak yönetilir.</p></div>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
