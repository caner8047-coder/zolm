<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Organizasyon Yönetimi</h1>
            <p class="text-sm text-slate-500">Legal Entity ve çoklu mağaza sınırları, üyelikler ve System Actor durumları.</p>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="selectedOrgId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($organizations as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>
            <button wire:click="runDiagnostics" id="btn-run-diagnostics"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                Teşhis Raporu
            </button>
        </div>
    </div>

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 rounded-[8px] p-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-sm text-emerald-700">{{ $successMessage }}</div>
    @endif

    {{-- Teşhis Çıktısı --}}
    @if($diagnosticsOutput)
        <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
            <h2 class="text-base font-semibold text-slate-900 mb-3">Teşhis Raporu</h2>
            <pre class="text-xs bg-slate-50 p-4 rounded-[6px] border border-slate-200 overflow-x-auto whitespace-pre-wrap">{{ $diagnosticsOutput }}</pre>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Üyeler Paneli --}}
        <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Organizasyon Üyeleri</h2>

            <div class="space-y-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-3">
                    <input wire:model="newMemberEmail" type="email" id="input-member-email" placeholder="Kullanıcı e-posta adresi..."
                        class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
                    <select wire:model="newMemberRole" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                        <option value="member">Üye</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="admin">Yönetici</option>
                    </select>
                    <button wire:click="addMember" id="btn-add-member"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                        Ekle
                    </button>
                </div>
            </div>

            @if($members->isEmpty())
                <p class="text-sm text-slate-400 text-center py-4">Üye bulunmuyor.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($members as $m)
                        <div class="py-2.5 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $m->user->name ?? 'Bilinmeyen Kullanıcı' }}</p>
                                <p class="text-xs text-slate-500">{{ $m->user->email ?? '' }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-600">
                                {{ strtoupper($m->role) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Servis Hesapları Paneli --}}
        <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Servis Hesapları (API)</h2>

            <div class="space-y-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-3">
                    <input wire:model="newSaName" type="text" id="input-sa-name" placeholder="Servis adı..."
                        class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
                    <input wire:model="newSaEmail" type="email" id="input-sa-email" placeholder="E-posta..."
                        class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
                    <button wire:click="addServiceAccount" id="btn-add-sa"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                        Ekle
                    </button>
                </div>
            </div>

            @if($serviceAccounts->isEmpty())
                <p class="text-sm text-slate-400 text-center py-4">Servis hesabı bulunmuyor.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($serviceAccounts as $sa)
                        <div class="py-2.5 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $sa->name }}</p>
                                <p class="text-xs text-slate-500">{{ $sa->email }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-100 text-emerald-800">
                                AKTİF
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
