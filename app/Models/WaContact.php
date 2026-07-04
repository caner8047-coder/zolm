<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class WaContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'wc_customer_id',
        'zolm_customer_id',
        'phone_e164_encrypted',
        'phone_hash',
        'first_name',
        'last_name',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'phone_e164_encrypted' => 'encrypted',
            'last_seen_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(WaContactPreference::class, 'contact_id');
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(WaConsentEvent::class, 'contact_id');
    }

    public function suppressions(): HasMany
    {
        return $this->hasMany(WaSuppression::class, 'contact_id');
    }

    public function outboxMessages(): HasMany
    {
        return $this->hasMany(WaOutbox::class, 'contact_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WaConversation::class, 'contact_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEligibleForMessaging($query)
    {
        return $query->active()
            ->whereNotNull('phone_hash')
            ->where('phone_hash', '!=', '')
            ->whereDoesntHave('suppressions', function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
            });
    }

    public static function hashPhone(string $phone): string
    {
        $pepper = config('app.key', '');
        return hash_hmac('sha256', $phone, $pepper);
    }
}
