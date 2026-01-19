<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'input_config',
        'output_config',
        'is_default',
        'ai_prompt',
        'sample_input_path',
        'sample_output_path',
        'ai_generated_rules',
        'is_ai_generated',
        'status',
        'error_message',
    ];

    protected $casts = [
        'input_config' => 'array',
        'output_config' => 'array',
        'ai_generated_rules' => 'array',
        'is_default' => 'boolean',
        'is_ai_generated' => 'boolean',
    ];

    // === İlişkiler ===

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    // === Tip Kontrolleri ===

    public function isProduction(): bool
    {
        return $this->type === 'production';
    }

    public function isOperation(): bool
    {
        return $this->type === 'operation';
    }

    // === AI Durum Kontrolleri ===

    public function isAiGenerated(): bool
    {
        return $this->is_ai_generated;
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isAnalyzing(): bool
    {
        return $this->status === 'analyzing';
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    // === Dönüşüm Kuralları ===

    public function getRules(): array
    {
        // AI tarafından üretilen kurallar varsa onları kullan
        if ($this->is_ai_generated && !empty($this->ai_generated_rules)) {
            return $this->ai_generated_rules;
        }

        // Yoksa input/output config'den kural oluştur (legacy)
        return [
            'version' => '1.0',
            'input' => $this->input_config ?? [],
            'transformations' => [],
            'outputs' => $this->output_config ?? [],
        ];
    }

    // === Scope'lar ===

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeProduction($query)
    {
        return $query->where('type', 'production');
    }

    public function scopeOperation($query)
    {
        return $query->where('type', 'operation');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
