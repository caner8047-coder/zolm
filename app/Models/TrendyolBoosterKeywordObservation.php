<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendyolBoosterKeywordObservation extends Model
{
    protected $fillable = [
        'trendyol_booster_keyword_id',
        'observed_rank',
        'result_count',
        'checked_result_count',
        'visibility_status',
    ];

    public $timestamps = false;

    protected $casts = [
        'observed_rank' => 'integer',
        'result_count' => 'integer',
        'checked_result_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function keyword()
    {
        return $this->belongsTo(TrendyolBoosterKeyword::class, 'trendyol_booster_keyword_id');
    }
}
