<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Çalışanlar</h1>
            <p class="text-gray-500 mt-1">{{ $employees->total() }} çalışan</p>
        </div>
        <div class="flex space-x-3">
            @if(auth()->user()->hasHrPermission('hr.employees.export'))
                <a href="{{ route('hr.personnel.export') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                    📥 Excel İndir
                </a>
            @endif
            <a href="{{ route('hr.personnel.create') }}" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 text-sm">
                + Yeni Çalışan
            </a>
        </div>
    </div>

    <!-- Filtreler -->
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Ad, soyad, no ara..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-gray-900 focus:border-gray-900">
            </div>
            <div>
                <select wire:model.live="statusFilter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tüm Durumlar</option>
                    <option value="active">Aktif</option>
                    <option value="on_leave">İzinli</option>
                    <option value="suspended">Askıda</option>
                    <option value="terminated">Ayrılmış</option>
                </select>
            </div>
            <div>
                <select wire:model.live="departmentFilter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tüm Departmanlar</option>
                </select>
            </div>
            <div>
                <select wire:model.live="branchFilter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tüm Şubeler</option>
                </select>
            </div>
            <div>
                <button wire:click="resetFilters" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                    Filtreleri Temizle
                </button>
            </div>
        </div>
    </div>

    <!-- Tablo -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Çalışan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pozisyon</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departman</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">İşe Giriş</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kıdem</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($employees as $employee)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-xs font-medium text-gray-600">
                                    {{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $employee->full_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $employee->personal_email ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $employee->activeEmployment?->position?->title ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $employee->activeEmployment?->department?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $employee->activeEmployment?->start_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $employee->tenure ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                bg-{{ $employee->status->color() }}-100 text-{{ $employee->status->color() }}-800">
                                {{ $employee->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('hr.personnel.show', $employee->id) }}" class="text-gray-600 hover:text-gray-900 text-sm">
                                Görüntüle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                            Henüz çalışan bulunmuyor.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Sayfalama -->
    <div class="flex justify-center">
        {{ $employees->links() }}
    </div>
</div>
