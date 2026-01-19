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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

    public function isAdmin(): bool
    {
        return $this->role?->slug === 'admin';
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function canAccessProduction(): bool
    {
        return in_array($this->role?->slug, ['admin', 'uretim_sorumlusu']);
    }

    public function canAccessOperation(): bool
    {
        return in_array($this->role?->slug, ['admin', 'operasyon_sorumlusu']);
    }

    public function canAccessReports(): bool
    {
        return in_array($this->role?->slug, ['admin', 'crm_sorumlusu', 'uretim_sorumlusu', 'operasyon_sorumlusu']);
    }
}
