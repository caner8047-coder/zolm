<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategori özelliğinin (attribute) olası değerleri.
 *
 * @property int    $id
 * @property int    $mp_category_attribute_id
 * @property string $platform_value_id
 * @property string $name
 * @property array|null $raw_payload
 */
class MpCategoryAttributeValue extends Model
{
    protected $fillable = [
        'mp_category_attribute_id',
        'platform_value_id',
        'name',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    /**
     * Bu değerin ait olduğu özellik tanımı.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(MpCategoryAttribute::class, 'mp_category_attribute_id');
    }
}
