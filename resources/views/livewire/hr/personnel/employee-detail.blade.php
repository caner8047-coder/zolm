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
                    <span class="text-xl font-bold text-gray-500">{{ substr($employee->first_name, 0, 1 }}{{ substr($employee->last_name, 0, 1) }}</span>
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

    @if($activeTab === 'history')
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <p class="text-sm text-gray-500">İşlem geçmişi yakında eklenecek.</p>
        </div>
    @endif
</div>
