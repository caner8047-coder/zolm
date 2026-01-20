<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UserManager extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $showPasswordModal = false;
    public ?int $editingId = null;
    
    // Form fields
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'operator';
    public bool $is_active = true;

    // Password reset
    public ?int $passwordUserId = null;
    public string $newPassword = '';
    public string $newPassword_confirmation = '';

    // Search
    public string $search = '';
    public string $roleFilter = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->editingId,
            'role' => 'required|in:admin,manager,operator',
            'is_active' => 'boolean',
        ];
    }

    public function openCreateModal()
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'password_confirmation', 'role', 'is_active']);
        $this->is_active = true;
        $this->role = 'operator';
        $this->showModal = true;
    }

    public function openEditModal($userId)
    {
        $user = User::findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role ?? 'operator';
        $this->is_active = $user->is_active ?? true;
        $this->password = '';
        $this->password_confirmation = '';
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            
            // Şifre değişikliği
            if (!empty($this->password)) {
                $this->validate([
                    'password' => 'required|min:8|confirmed',
                ]);
                $data['password'] = Hash::make($this->password);
            }
            
            $user->update($data);
            ActivityLog::log('update_user', "'{$user->name}' kullanıcısı güncellendi", 'User', $user->id);
            session()->flash('success', 'Kullanıcı başarıyla güncellendi.');
        } else {
            $this->validate([
                'password' => 'required|min:8|confirmed',
            ]);
            $data['password'] = Hash::make($this->password);
            
            $user = User::create($data);
            ActivityLog::log('create_user', "'{$user->name}' kullanıcısı oluşturuldu", 'User', $user->id);
            session()->flash('success', 'Kullanıcı başarıyla oluşturuldu.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'email', 'password', 'password_confirmation']);
    }

    public function openPasswordModal($userId)
    {
        $this->passwordUserId = $userId;
        $this->newPassword = '';
        $this->newPassword_confirmation = '';
        $this->showPasswordModal = true;
    }

    public function resetPassword()
    {
        $this->validate([
            'newPassword' => 'required|min:8|confirmed',
        ], [
            'newPassword.required' => 'Yeni şifre gerekli.',
            'newPassword.min' => 'Şifre en az 8 karakter olmalı.',
            'newPassword.confirmed' => 'Şifreler eşleşmiyor.',
        ]);

        $user = User::findOrFail($this->passwordUserId);
        $user->update(['password' => Hash::make($this->newPassword)]);
        
        ActivityLog::log('reset_password', "'{$user->name}' kullanıcısının şifresi sıfırlandı", 'User', $user->id);

        $this->showPasswordModal = false;
        $this->reset(['passwordUserId', 'newPassword', 'newPassword_confirmation']);
        session()->flash('success', 'Şifre başarıyla sıfırlandı.');
    }

    public function toggleActive($userId)
    {
        $user = User::findOrFail($userId);
        
        // Kendini deaktif edemez
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Kendi hesabınızı deaktif edemezsiniz.');
            return;
        }
        
        $user->update(['is_active' => !$user->is_active]);
        
        $action = $user->is_active ? 'aktif edildi' : 'deaktif edildi';
        ActivityLog::log('update_user', "'{$user->name}' kullanıcısı {$action}", 'User', $user->id);
    }

    public function delete($userId)
    {
        $user = User::findOrFail($userId);
        
        // Kendini silemez
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Kendi hesabınızı silemezsiniz.');
            return;
        }
        
        ActivityLog::log('delete_user', "'{$user->name}' kullanıcısı silindi", 'User', $user->id);
        $user->delete();
        
        session()->flash('success', 'Kullanıcı silindi.');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showPasswordModal = false;
    }

    public function render()
    {
        $query = User::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        $users = $query->orderBy('name')->paginate(10);

        return view('livewire.admin.user-manager', [
            'users' => $users,
        ])->layout('layouts.app');
    }
}
