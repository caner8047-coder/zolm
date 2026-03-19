<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRevenueEntry extends Model
{
    use HasFactory;

    public const STATUS_RECORDED = 'recorded';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_NO_OUTPUT = 'no_output';
    public const STATUS_NOTE = 'note';

    protected $fillable = [
        'production_revenue_import_id',
        'work_date',
        'sheet_name',
        'revenue',
        'note',
        'status',
        'meta',
    ];

    protected $casts = [
        'work_date' => 'date',
        'revenue' => 'decimal:2',
        'meta' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductionRevenueImport::class, 'production_revenue_import_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_HOLIDAY => 'Tatil',
            self::STATUS_NO_OUTPUT => 'Çıkış yok',
            self::STATUS_NOTE => 'Not var',
            default => 'Kayıtlı',
        };
    }
}
