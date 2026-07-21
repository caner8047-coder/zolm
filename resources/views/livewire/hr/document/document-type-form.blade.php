<div class="space-y-6">
    <div class="flex items-center space-x-4">
        <a href="{{ route('hr.settings.document-types') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $isEdit ? 'Belge Türü Düzenle' : 'Yeni Belge Türü' }}</h1>
    </div>
    @if(session('error'))<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm">{{ session('error') }}</div>@endif
    <form wire:submit="save">
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700">Ad <span class="text-red-500">*</span></label><input type="text" wire:model="name" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">@error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror</div>
                <div><label class="block text-sm font-medium text-gray-700">Kod <span class="text-red-500">*</span></label><input type="text" wire:model="code" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">@error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror</div>
                <div><label class="block text-sm font-medium text-gray-700">Kategori <span class="text-red-500">*</span></label>
                    <select wire:model="category" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="identity">Kimlik</option><option value="contract">Sözleşme</option><option value="education">Eğitim</option>
                        <option value="residence">İkamet</option><option value="criminal_record">Sabıka</option><option value="health">Sağlık</option>
                        <option value="certificate">Sertifika</option><option value="kvkk">KVKK</option><option value="occupational_safety">İSG</option>
                        <option value="payroll">Bordro</option><option value="termination">İşten Çıkış</option><option value="other">Diğer</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">Hassasiyet <span class="text-red-500">*</span></label>
                    <select wire:model="sensitivity" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="standard">Standart</option><option value="confidential">Gizli</option><option value="highly_sensitive">Çok Hassas</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">Sıralama</label><input type="number" wire:model="sort_order" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <label class="flex items-center space-x-2"><input type="checkbox" wire:model="requires_expiry_date" class="rounded border-gray-300"><span class="text-sm">Son kullanma tarihi gerekli</span></label>
                <label class="flex items-center space-x-2"><input type="checkbox" wire:model="requires_issue_date" class="rounded border-gray-300"><span class="text-sm">Düzenleme tarihi gerekli</span></label>
                <label class="flex items-center space-x-2"><input type="checkbox" wire:model="requires_document_number" class="rounded border-gray-300"><span class="text-sm">Belge numarası gerekli</span></label>
                <label class="flex items-center space-x-2"><input type="checkbox" wire:model="is_mandatory" class="rounded border-gray-300"><span class="text-sm">Zorunlu belge</span></label>
                <label class="flex items-center space-x-2"><input type="checkbox" wire:model="employee_can_upload" class="rounded border-gray-300"><span class="text-sm">Çalışan yükleyebilir</span></label>
                <label class="flex items-center space-x-2"><input type="checkbox" wire:model="is_active" class="rounded border-gray-300"><span class="text-sm">Aktif</span></label>
            </div>
        </div>
        <div class="flex justify-end space-x-3 mt-4">
            <a href="{{ route('hr.settings.document-types') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">İptal</a>
            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-800">{{ $isEdit ? 'Güncelle' : 'Oluştur' }}</button>
        </div>
    </form>
</div>
