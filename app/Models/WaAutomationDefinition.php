<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaAutomationDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'key', 'name', 'status', 'priority',
        'config_json', 'template_id', 'version', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['config_json' => 'array'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(WaAutomationEnrollment::class, 'automation_id');
    }
}
