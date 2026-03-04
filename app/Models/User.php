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

    public function roleSlug(): ?string
    {
        // Eğer role bir nesneyse (ilişki yüklendiyse veya Role modeli ise)
        if ($this->role instanceof Role) {
            return $this->role->slug;
        }

        // Eğer role alanı string ise (DB'deki 'role' sütunu)
        if (is_string($this->role)) {
            return $this->role;
        }

        // Eğer hiçbiriyse ve bir ilişki varsa onu deneyelim (ama sonsuz döngüye girmemeye dikkat)
        // Eğer role_id varsa ve ilişki henüz yüklenmediyse slug'ı ordan alabiliriz
        // Ancak genellikle $this->role string geliyorsa o tercih ediliyor demektir.
        
        return null;
    }

    public function isAdmin(): bool
    {
        return $this->roleSlug() === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->roleSlug(), ['admin', 'manager']);
    }

    public function isOperator(): bool
    {
        return in_array($this->roleSlug(), ['admin', 'manager', 'operator']);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roleSlug() === $slug;
    }

    // === ACCESS CONTROL ===

    public function canAccessAdmin(): bool
    {
        return $this->roleSlug() === 'admin';
    }

    public function canAccessProduction(): bool
    {
        $role = $this->roleSlug();
        return in_array($role, ['admin', 'manager', 'uretim_sorumlusu']);
    }

    public function canAccessOperation(): bool
    {
        $role = $this->roleSlug();
        return in_array($role, ['admin', 'manager', 'operator', 'operasyon_sorumlusu']);
    }

    public function canAccessReports(): bool
    {
        $role = $this->roleSlug();
        return in_array($role, ['admin', 'manager', 'crm_sorumlusu', 'uretim_sorumlusu', 'operasyon_sorumlusu']);
    }

    // === HELPERS ===

    public function getRoleLabelAttribute(): string
    {
        return match ($this->roleSlug()) {
            'admin' => 'Yönetici',
            'manager' => 'Müdür',
            'operator' => 'Operatör',
            'uretim_sorumlusu' => 'Üretim Sorumlusu',
            'operasyon_sorumlusu' => 'Operasyon Sorumlusu',
            'crm_sorumlusu' => 'CRM Sorumlusu',
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
