<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaAiToolCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_run_id', 'tool_name', 'input_params', 'output_data',
        'status', 'error_message', 'execution_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'input_params' => 'array',
            'output_data' => 'array',
            'execution_time_ms' => 'decimal:2',
        ];
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(WaAiRun::class, 'ai_run_id');
    }
}
