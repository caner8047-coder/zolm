<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'tax_number',
        'tax_office',
        'mersis_number',
        'company_type',
        'phone',
        'email',
        'address',
        'iban',
        'bank_name',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function user(): BelongsTo

    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(LegalEntitySetting::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(MarketplaceStore::class);
    }

    // === HR Relationships ===

    public function hrLicenses(): HasMany
    {
        return $this->hasMany(HrLicense::class);
    }

    public function hrHolidays(): HasMany
    {
        return $this->hasMany(HrHoliday::class);
    }

    public function hasHrModule(string $moduleKey): bool
    {
        return $this->hrLicenses()
            ->where('module_key', $moduleKey)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
