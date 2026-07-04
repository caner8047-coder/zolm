<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaAbTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'ab_test_id', 'variant_name', 'sample_size', 'conversions',
        'conversion_rate', 'revenue', 'clicks', 'confidence', 'is_winner',
    ];

    protected function casts(): array
    {
        return [
            'conversion_rate' => 'decimal:4',
            'revenue' => 'decimal:2',
            'confidence' => 'decimal:2',
            'is_winner' => 'boolean',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(WaAbTest::class, 'ab_test_id');
    }
}
