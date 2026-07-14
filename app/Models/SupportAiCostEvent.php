<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAiCostEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'support_ai_run_id', 'model', 'provider', 'input_tokens', 'output_tokens', 'cost_estimate',
    ];

    protected $casts = [
        'support_ai_run_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_estimate' => 'decimal:6',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(SupportAiRun::class, 'support_ai_run_id');
    }
}
