<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Birimler</h1>
            <p class="text-gray-500 mt-1">{{ $units->total() }} birim</p>
        </div>
        <a href="{{ route('hr.settings.units.create') }}" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 text-sm">+ Yeni Birim</a>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Ad veya kod ara..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <select wire:model.live="departmentFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tüm Departmanlar</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tüm Durumlar</option>
                <option value="active">Aktif</option>
                <option value="inactive">Pasif</option>
            </select>
            <button wire:click="resetFilters" class="border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">Temizle</button>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kod</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ad</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departman</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($units as $unit)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $unit->code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $unit->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $unit->department?->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $unit->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $unit->is_active ? 'Aktif' : 'Pasif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('hr.settings.units.edit', $unit->id) }}" class="text-gray-600 hover:text-gray-900 text-sm">Düzenle</a>
                            <button wire:click="toggleActive({{ $unit->id }})" class="text-sm {{ $unit->is_active ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800' }}">
                                {{ $unit->is_active ? 'Pasifleştir' : 'Aktifleştir' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Henüz birim bulunmuyor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex justify-center">{{ $units->links() }}</div>
</div>
