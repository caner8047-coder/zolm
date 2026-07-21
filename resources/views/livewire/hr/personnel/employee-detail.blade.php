<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="{{ route('hr.personnel') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <!-- Fotoğraf veya Avatar -->
            <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-200 flex items-center justify-center flex-shrink-0">
                @if($employee->photo)
                    <img src="{{ $employee->photo->disk_path }}" alt="{{ $employee->full_name }}" class="w-full h-full object-cover">
                @else
                    <span class="text-xl font-bold text-gray-500">{{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}</span>
                @endif
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $employee->full_name }}</h1>
                <p class="text-gray-500">{{ $employee->employee_number }} — {{ $employee->activeEmployment?->position?->title ?? 'Pozisyon tanımlı değil' }}</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                bg-{{ $employee->status->color() }}-100 text-{{ $employee->status->color() }}-800">
                {{ $employee->status->label() }}
            </span>
            <a href="{{ route('hr.personnel.edit', $employee->id) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                Düzenle
            </a>
        </div>
    </div>

    <!-- Sekmeler -->
    <div class="border-b border-gray-200">
        <nav class="flex space-x-8">
            <button wire:click="$set('activeTab', 'overview')"
                class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'overview' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Özet
            </button>
            <button wire:click="$set('activeTab', 'personal')"
                class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'personal' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Kişisel Bilgiler
            </button>
            <button wire:click="$set('activeTab', 'employment')"
                class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'employment' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Çalışma Bilgileri
            </button>
            <button wire:click="$set('activeTab', 'documents')"
                class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'documents' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Belgeler
                @if($mandatoryCount > 0)
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $mandatoryCount }}</span>
                @endif
            </button>
            <button wire:click="$set('activeTab', 'leaves')"
                class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'leaves' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                İzinler
            </button>
            <button wire:click="$set('activeTab', 'history')"
                class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'history' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                İşlem Geçmişi
            </button>
        </nav>
    </div>

    <!-- Sekme İçerikleri -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Kişisel Bilgiler</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Ad Soyad</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->full_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">TC Kimlik</dt>
                        <dd class="text-sm text-gray-900">***{{ $employee->national_id_last_four }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Cinsiyet</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->gender ? ucfirst($employee->gender) : '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Doğum Tarihi</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->date_of_birth?->format('d.m.Y') ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Telefon</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->phone ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Çalışma Bilgileri</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Pozisyon</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->activeEmployment?->position?->title ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Departman</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->activeEmployment?->department?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Şube</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->activeEmployment?->branch?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Yönetici</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->activeEmployment?->manager?->full_name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">İşe Giriş</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->activeEmployment?->start_date?->format('d.m.Y') ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Kıdem</dt>
                        <dd class="text-sm text-gray-900">{{ $employee->tenure ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @endif

    @if($activeTab === 'personal')
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">İletişim ve Adres</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-gray-500">E-posta</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->personal_email ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Telefon</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->phone ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Adres</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->address ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Şehir</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->city ?? '-' }}</dd>
                </div>
            </dl>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Acil Durum Kişisi</h3>
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm text-gray-500">Ad</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->emergency_contact_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Telefon</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->emergency_contact_phone ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Yakınlık</dt>
                    <dd class="text-sm text-gray-900">{{ $employee->emergency_contact_relation ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    @endif

    @if($activeTab === 'employment')
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-medium text-gray-900">Çalışma Kayıtları</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Başlangıç</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bitiş</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pozisyon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departman</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($employee->employmentRecords as $record)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $record->start_date->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $record->end_date?->format('d.m.Y') ?? 'Devam ediyor' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $record->position?->title ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $record->department?->name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $record->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $record->status === 'active' ? 'Aktif' : 'Tamamlanmış' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($activeTab === 'documents')
        <div class="space-y-4">
            @if(session('document_success'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('document_success') }}</div>
            @endif

            <!-- Belge Özeti -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Eksik Zorunlu</p>
                    <p class="text-2xl font-bold {{ $mandatoryCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $mandatoryCount }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Doğrulama Bekleyen</p>
                    <p class="text-2xl font-bold {{ $pendingVerification > 0 ? 'text-yellow-600' : 'text-gray-900' }}">{{ $pendingVerification }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Aktif Belge</p>
                    <p class="text-2xl font-bold text-green-600">{{ $activeCount }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Süresi Yaklaşan</p>
                    <p class="text-2xl font-bold {{ $expiringSoon > 0 ? 'text-orange-600' : 'text-gray-900' }}">{{ $expiringSoon }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Süresi Dolmuş</p>
                    <p class="text-2xl font-bold {{ $expiredCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $expiredCount }}</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Bekleyen Talep</p>
                    <p class="text-2xl font-bold {{ $pendingRequests->count() > 0 ? 'text-blue-600' : 'text-gray-900' }}">{{ $pendingRequests->count() }}</p>
                </div>
            </div>

            <!-- Eksik Zorunlu Belgeler -->
            @if($missingMandatoryTypes->isNotEmpty())
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200"><h3 class="font-medium text-gray-900">Eksik Zorunlu Belgeler</h3></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($missingMandatoryTypes as $type)
                            <li class="px-4 py-3 text-sm text-gray-700 flex items-center justify-between">
                                <span>{{ $type->name }} <span class="text-gray-400">({{ $type->category->label() }})</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Bekleyen Belge Talepleri -->
            @if($pendingRequests->isNotEmpty())
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200"><h3 class="font-medium text-gray-900">Bekleyen Belge Talepleri</h3></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($pendingRequests as $request)
                            <li class="px-4 py-3 text-sm text-gray-700 flex items-center justify-between">
                                <span>{{ $request->documentType?->name ?? '-' }} — Son tarih: {{ $request->due_date?->format('d.m.Y') ?? '-' }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $request->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">{{ $request->status === 'overdue' ? 'Gecikti' : 'Bekliyor' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($newVersionDocId && auth()->user()?->hasHrPermission('hr.documents.create'))
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="font-medium text-gray-900 mb-3">Yeni Sürüm Yükle</h3>
                    <div class="flex items-center gap-3">
                        <input type="file" wire:model="newVersionFile" class="text-sm">
                        <button wire:click="uploadNewVersion" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Yükle</button>
                        <button wire:click="$set('newVersionDocId', null)" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">İptal</button>
                    </div>
                    @error('newVersionFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            @if($rejectDocId)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="font-medium text-gray-900 mb-3">Ret Gerekçesi</h3>
                    <textarea wire:model="rejectReason" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ret nedeni (zorunlu)"></textarea>
                    @error('rejectReason') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3 mt-3">
                        <button wire:click="rejectDocument" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">Reddet</button>
                        <button wire:click="$set('rejectDocId', null)" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">İptal</button>
                    </div>
                </div>
            @endif

            <!-- Belge Listesi -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="font-medium text-gray-900">Çalışan Belgeleri</h3>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Belge Türü</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Doğrulama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Son Kullanma</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versiyon</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($documents as $doc)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $doc->documentType?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $doc->documentType?->category?->label() ?? '-' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $doc->status->color() }}-100 text-{{ $doc->status->color() }}-800">{{ $doc->status->label() }}</span></td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $doc->verification_status->color() }}-100 text-{{ $doc->verification_status->color() }}-800">{{ $doc->verification_status->label() }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $doc->expiry_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">v{{ $doc->version_number }}</td>
                                <td class="px-4 py-3 text-right text-sm space-x-2 whitespace-nowrap">
                                    @if(auth()->user()?->hasHrPermission('hr.documents.download') && $doc->current_file_id)
                                        <a href="{{ route('hr.files.download', $doc->current_file_id) }}" class="text-gray-600 hover:text-gray-900">İndir</a>
                                    @endif
                                    @if(auth()->user()?->hasHrPermission('hr.documents.create'))
                                        <button wire:click="startNewVersion({{ $doc->id }})" class="text-blue-600 hover:text-blue-800">Yeni Sürüm</button>
                                    @endif
                                    @if(auth()->user()?->hasHrPermission('hr.documents.verify') && $doc->verification_status->value === 'pending')
                                        <button wire:click="verifyDocument({{ $doc->id }})" class="text-green-600 hover:text-green-800">Doğrula</button>
                                        <button wire:click="startReject({{ $doc->id }})" class="text-red-600 hover:text-red-800">Reddet</button>
                                    @endif
                                    @if(auth()->user()?->hasHrPermission('hr.documents.archive') && $doc->status->value !== 'archived')
                                        <button wire:click="archiveDocument({{ $doc->id }})" class="text-gray-600 hover:text-gray-900">Arşivle</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Henüz belge bulunmuyor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'leaves')
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h3 class="text-lg font-medium text-gray-900">{{ now()->year }} İzin Bakiyeleri</h3>
                @if(auth()->user()?->hasHrPermission('hr.leaves.create'))<a href="{{ route('hr.leaves.create', ['employee' => $employee->id]) }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-lg bg-gray-900 text-white text-sm text-center">+ İzin Talebi</a>@endif
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                @forelse($leaveBalances as $balance)<div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4"><p class="text-sm text-gray-500">{{ $balance->leaveType?->name }}</p><p class="mt-1 text-2xl font-bold {{ $balance->remaining_amount < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $balance->remaining_amount }}</p><p class="mt-1 text-xs text-gray-500">Hak ediş {{ $balance->entitled_amount }} · Kullanım {{ $balance->used_amount }}</p></div>@empty<div class="sm:col-span-2 xl:col-span-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-500">Bu dönem için bakiye hareketi bulunmuyor.</div>@endforelse
            </div>
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden"><div class="px-4 py-3 border-b border-gray-200"><h3 class="font-medium text-gray-900">İzin Talepleri</h3></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tür</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Süre</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($leaveRequests as $request)<tr><td class="px-4 py-3 text-sm text-gray-900">{{ $request->leaveType?->name }}</td><td class="px-4 py-3 text-sm text-gray-500">{{ $request->start_date->format('d.m.Y') }} — {{ $request->end_date->format('d.m.Y') }}</td><td class="px-4 py-3 text-sm text-gray-500">{{ $request->requested_amount }} {{ $request->unit->label() }}</td><td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-mono rounded bg-gray-100 text-gray-700">{{ $request->status->label() }}</span></td></tr>@empty<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Henüz izin talebi bulunmuyor.</td></tr>@endforelse</tbody></table></div></div>
        </div>
    @endif

    @if($activeTab === 'history')
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <p class="text-sm text-gray-500">İşlem geçmişi yakında eklenecek.</p>
        </div>
    @endif
</div>
