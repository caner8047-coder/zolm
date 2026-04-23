<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnIntakeAnalysis extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'ocr_json' => 'array',
            'classification_json' => 'array',
            'raw_response_json' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeItem::class, 'return_intake_item_id');
    }
}
