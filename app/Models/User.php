<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'role',
        'is_active',
        'last_login_at',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // === RELATIONSHIPS ===

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // === ROLE CHECKING (New System) ===

    public function isAdmin(): bool
    {
        // Önce yeni role alanını kontrol et, yoksa eski sisteme bak
        if ($this->role) {
            return $this->role === 'admin';
        }
        return $this->role?->slug === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'operator']);
    }

    public function hasRole(string $slug): bool
    {
        if ($this->role) {
            return $this->role === $slug;
        }
        return $this->role?->slug === $slug;
    }

    // === ACCESS CONTROL ===

    public function canAccessAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canAccessProduction(): bool
    {
        return in_array($this->role, ['admin', 'manager']) || 
               in_array($this->role?->slug, ['admin', 'uretim_sorumlusu']);
    }

    public function canAccessOperation(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'operator']) ||
               in_array($this->role?->slug, ['admin', 'operasyon_sorumlusu']);
    }

    public function canAccessReports(): bool
    {
        return in_array($this->role, ['admin', 'manager']) ||
               in_array($this->role?->slug, ['admin', 'crm_sorumlusu', 'uretim_sorumlusu', 'operasyon_sorumlusu']);
    }

    // === HELPERS ===

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Yönetici',
            'manager' => 'Müdür',
            'operator' => 'Operatör',
            default => 'Bilinmiyor',
        };
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        return $initials;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
        ActivityLog::log('login', 'Sisteme giriş yaptı');
    }
}
