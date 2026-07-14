<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportReplyMacro extends Model
{
    protected $fillable = [
        'store_id',
        'title',
        'body',
        'category',
        'channel_scope',
        'language',
        'is_active',
        'variables_schema',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'variables_schema' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SupportReplyMacroVersion::class, 'macro_id');
    }
}
