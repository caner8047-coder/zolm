<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaTemplate extends Model
{
    use HasFactory;

    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'wa_account_id',
        'name',
        'language',
        'category',
        'status',
        'components_json',
        'variable_schema_json',
        'rejection_reason',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components_json' => 'array',
            'variable_schema_json' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForAccount($query, WaAccount $account)
    {
        return $query->where('wa_account_id', $account->id);
    }
}
