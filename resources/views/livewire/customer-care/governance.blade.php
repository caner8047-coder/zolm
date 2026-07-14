<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Enterprise Governance & RBAC</h1>
            <p class="text-sm text-slate-500">Müşteri İletişim Merkezi yetki matrisi ve onay yönetim arayüzü.</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none focus:ring-1 focus:ring-slate-900">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if($errorMessage)
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-[8px] text-sm">
            {{ $errorMessage }}
        </div>
    @endif
    @if($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[8px] text-sm">
            {{ $successMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Role Assignments --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Yeni Rol Ata</h2>
                <form wire:submit.prevent="assignRole" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Kullanıcı</label>
                        <select wire:model="newUserId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm">
                            <option value="">Seçiniz...</option>
                            @foreach($users as $usr)
                                <option value="{{ $usr->id }}">{{ $usr->name }} ({{ $usr->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Rol</label>
                        <select wire:model="newRole" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm">
                            <option value="owner">Owner</option>
                            <option value="admin">Admin</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="agent">Agent</option>
                            <option value="analyst">Analyst</option>
                            <option value="auditor">Auditor</option>
                            <option value="knowledge_manager">Bilgi Yöneticisi</option>
                            <option value="automation_manager">Otomasyon Yöneticisi</option>
                            <option value="read_only">Salt Okunur</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-sm transition shadow-sm">
                        Rolü Güncelle
                    </button>
                </form>
            </div>

            {{-- Role list --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Mevcut Rol Matrisi</h2>
                <div class="divide-y divide-slate-100">
                    @forelse($roleAssignments as $assignment)
                        <div class="py-3 flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $assignment->user->name }}</p>
                                <p class="text-xs text-slate-400">{{ $assignment->user->email }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">
                                {{ $assignment->role }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Bu mağaza için atanmış özel rol bulunmuyor.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right: Approvals & Decisions --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Pending Approvals --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Bekleyen Onay Talepleri</h2>
                <div class="space-y-3">
                    @forelse($pendingApprovals as $req)
                        <div class="p-4 rounded-[8px] border border-slate-100 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 text-xs font-mono rounded bg-amber-100 text-amber-800">
                                        {{ $req->action_type }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $req->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-slate-600 mt-1">İsteyen: <strong class="text-slate-800">{{ $req->requester->name }}</strong></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="approveRequest({{ $req->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium rounded-[6px] transition">
                                    Onayla
                                </button>
                                <button wire:click="rejectRequest({{ $req->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-medium rounded-[6px] transition">
                                    Reddet
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Bekleyen onay talebi bulunmamaktadır.</p>
                    @endforelse
                </div>
            </div>

            {{-- History --}}
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Geçmiş Kararlar</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="pb-3 font-medium">Aksiyon</th>
                                <th class="pb-3 font-medium">Talep Eden</th>
                                <th class="pb-3 font-medium">Karar</th>
                                <th class="pb-3 font-medium">İşleyen</th>
                                <th class="pb-3 font-medium">Tarih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 text-sm">
                            @forelse($decisionHistory as $hist)
                                <tr>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">
                                            {{ $hist->action_type }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-600">{{ $hist->requester->name }}</td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded {{ $hist->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                            {{ $hist->status === 'approved' ? 'Onaylandı' : 'Reddedildi' }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-600">{{ $hist->approver ? $hist->approver->name : '-' }}</td>
                                    <td class="py-3 text-xs text-slate-400">{{ $hist->approved_at?->format('H:i d.m.Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Karar geçmişi temiz.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
