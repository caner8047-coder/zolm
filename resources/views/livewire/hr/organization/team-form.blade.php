<div class="space-y-6">
    <div class="flex items-center space-x-4">
        <a href="{{ route('hr.settings.teams') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $isEdit ? 'Ekip Düzenle' : 'Yeni Ekip' }}</h1>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm">{{ session('error') }}</div>
    @endif

    <form wire:submit="save">
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ekip Adı <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Birim <span class="text-red-500">*</span></label>
                    <select wire:model="unit_id" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Seçiniz</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->department?->name ?? '' }})</option>
                        @endforeach
                    </select>
                    @error('unit_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sıralama</label>
                    <input type="number" wire:model="sort_order" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300">
                <label for="is_active" class="text-sm text-gray-700">Aktif</label>
            </div>
        </div>
        <div class="flex justify-end space-x-3 mt-4">
            <a href="{{ route('hr.settings.teams') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">İptal</a>
            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-800">{{ $isEdit ? 'Güncelle' : 'Oluştur' }}</button>
        </div>
    </form>
</div>
