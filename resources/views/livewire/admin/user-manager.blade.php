<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Kullanıcı Yönetimi</h1>
            <p class="text-gray-500 mt-1">Sistem kullanıcılarını yönetin</p>
        </div>
        <button wire:click="openCreateModal" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">
            + Yeni Kullanıcı
        </button>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center space-x-4">
            <div class="flex-1">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="İsim veya email ara..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900"
                >
            </div>
            <select wire:model.live="roleFilter" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">Tüm Roller</option>
                <option value="admin">Yönetici</option>
                <option value="manager">Müdür</option>
                <option value="operator">Operatör</option>
            </select>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kullanıcı</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Son Giriş</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                <tr class="{{ !$user->is_active ? 'bg-gray-50 opacity-60' : '' }}">
                    <td class="px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center font-medium text-gray-600">
                                {{ $user->initials }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $user->name }}</p>
                                <p class="text-sm text-gray-500">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full
                            {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-700' : 
                               ($user->role === 'manager' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') }}">
                            {{ $user->role_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <button 
                            wire:click="toggleActive({{ $user->id }})"
                            class="px-2 py-1 text-xs rounded-full {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}"
                            {{ $user->id === auth()->id() ? 'disabled' : '' }}
                        >
                            {{ $user->is_active ? 'Aktif' : 'Deaktif' }}
                        </button>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $user->last_login_at?->format('d.m.Y H:i') ?? '-' }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end space-x-2">
                            <button 
                                wire:click="openEditModal({{ $user->id }})"
                                class="px-3 py-1 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
                            >
                                Düzenle
                            </button>
                            <button 
                                wire:click="openPasswordModal({{ $user->id }})"
                                class="px-3 py-1 text-sm text-orange-600 border border-orange-300 rounded hover:bg-orange-50"
                            >
                                Şifre
                            </button>
                            @if($user->id !== auth()->id())
                            <button 
                                wire:click="delete({{ $user->id }})"
                                wire:confirm="Bu kullanıcıyı silmek istediğinize emin misiniz?"
                                class="px-3 py-1 text-sm text-red-600 border border-red-300 rounded hover:bg-red-50"
                            >
                                Sil
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        Kullanıcı bulunamadı
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                {{ $editingId ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı' }}
            </h3>
            
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">İsim</label>
                    <input type="text" wire:model="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" wire:model="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Şifre {{ $editingId ? '(boş bırakırsan değişmez)' : '' }}
                    </label>
                    <input type="password" wire:model="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Şifre Tekrar</label>
                    <input type="password" wire:model="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                    <select wire:model="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="operator">Operatör</option>
                        <option value="manager">Müdür</option>
                        <option value="admin">Yönetici</option>
                    </select>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300">
                    <label for="is_active" class="ml-2 text-sm text-gray-700">Aktif</label>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        İptal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                        {{ $editingId ? 'Güncelle' : 'Oluştur' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Password Reset Modal -->
    @if($showPasswordModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Şifre Sıfırla</h3>
            
            <form wire:submit="resetPassword" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Yeni Şifre</label>
                    <input type="password" wire:model="newPassword" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    @error('newPassword') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Şifre Tekrar</label>
                    <input type="password" wire:model="newPassword_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        İptal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                        Şifreyi Sıfırla
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
