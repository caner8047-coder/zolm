<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterActionAudit extends Model
{
    protected $fillable = ['owner_user_id', 'actor_user_id', 'fingerprint', 'event', 'from_value', 'to_value', 'context_json', 'occurred_at'];

    protected function casts(): array
    {
        return ['context_json' => 'array', 'occurred_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
