<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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

    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }

    public function marketplaceStores(): HasMany
    {
        return $this->hasMany(MarketplaceStore::class);
    }

    // === HR PERMISSION SYSTEM ===

    public function hrPermissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
            ->withPivot('model_type')
            ->wherePivot('model_type', self::class);
    }

    /**
     * Check if user has a specific HR permission via role-based or direct assignment.
     * No blanket admin bypass — permissions must be explicitly assigned.
     */
    public function hasHrPermission(string $permission): bool
    {
        // Check via model_has_roles + role_permission (spatie-like pattern)
        $hasViaModelRole = DB::table('model_has_roles')
            ->join('role_permission', 'role_permission.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', self::class)
            ->where('permissions.name', $permission)
            ->exists();

        if ($hasViaModelRole) {
            return true;
        }

        // Check via role_id FK on users table + role_permission
        $userRoleId = $this->role_id;

        if ($userRoleId) {
            $hasPermission = DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('role_permission.role_id', $userRoleId)
                ->where('permissions.name', $permission)
                ->exists();

            if ($hasPermission) {
                return true;
            }
        }

        // Check via model_has_permissions table (direct permission)
        $hasDirect = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $this->id)
            ->where('model_has_permissions.model_type', self::class)
            ->where('permissions.name', $permission)
            ->exists();

        return $hasDirect;
    }

    /**
     * Sync user's HR roles.
     */
    public function syncHrRoles(array $roleIds): void
    {
        DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->where('model_type', self::class)
            ->delete();

        foreach ($roleIds as $roleId) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_id' => $this->id,
                'model_type' => self::class,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
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

    public function canAccessCrm(): bool
    {
        $role = $this->roleSlug();

        return in_array($role, ['admin', 'manager', 'operator', 'crm_sorumlusu', 'operasyon_sorumlusu'], true);
    }

    public function canAccessCustomMotor(): bool
    {
        return $this->canAccessProduction()
            || $this->canAccessOperation()
            || $this->canAccessReports();
    }

    public function canAccessReturnsIntake(): bool
    {
        $role = $this->roleSlug();

        return in_array($role, ['admin', 'manager', 'operator', 'operasyon_sorumlusu'], true);
    }

    public function canAccessReturnsReview(): bool
    {
        $role = $this->roleSlug();

        return in_array($role, ['admin', 'manager', 'operator', 'operasyon_sorumlusu', 'crm_sorumlusu'], true);
    }

    public function canAccessAds(): bool
    {
        return in_array($this->roleSlug(), ['admin', 'manager', 'uretim_sorumlusu', 'operator'], true);
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
