<div class="space-y-6">
    <div class="flex items-center space-x-4">
        <a href="{{ route('hr.personnel.show', $employee->id) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $employee->full_name }} — Düzenle</h1>
        </div>
    </div>

    <form wire:submit="save">
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">Kişisel Bilgiler</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ad <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="first_name" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Soyad <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="last_name" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">İkinci Ad</label>
                    <input type="text" wire:model="middle_name" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cinsiyet</label>
                    <select wire:model="gender" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Seçiniz</option>
                        <option value="male">Erkek</option>
                        <option value="female">Kadın</option>
                        <option value="other">Diğer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Doğum Tarihi</label>
                    <input type="date" wire:model="date_of_birth" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Medeni Durum</label>
                    <select wire:model="marital_status" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Seçiniz</option>
                        <option value="single">Bekar</option>
                        <option value="married">Evli</option>
                        <option value="divorced">Boşanmış</option>
                        <option value="widowed">Dul</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">İletişim</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Telefon</label>
                    <input type="text" wire:model="phone" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">E-posta</label>
                    <input type="email" wire:model="personal_email" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Şehir</label>
                    <input type="text" wire:model="city" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">Acil Durum Kişisi</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                    <input type="text" wire:model="emergency_contact_name" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Telefon</label>
                    <input type="text" wire:model="emergency_contact_phone" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Yakınlık</label>
                    <input type="text" wire:model="emergency_contact_relation" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('hr.personnel.show', $employee->id) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">İptal</a>
            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-800">Güncelle</button>
        </div>
    </form>
</div>
