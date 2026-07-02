<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterShippingRate extends Model
{
    /**
     * @var string
     */
    protected $table = 'trendyol_booster_shipping_rates';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'cargo_company',
        'desi',
        'price',
        'marketplace',
        'source',
        'imported_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'desi' => 'integer',
        'price' => 'decimal:2',
        'imported_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
