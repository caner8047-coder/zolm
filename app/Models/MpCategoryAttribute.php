<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pazaryeri kategori özelliği (attribute) sözlük modeli.
 *
 * Tenant'a bağlı değildir — referans/sözlük verisi olarak tüm kullanıcılara açıktır.
 * MpCategory ile `platform_category_id + marketplace` üzerinden ilişkilendirilebilir.
 *
 * @property int    $id
 * @property string $marketplace
 * @property string $platform_category_id
 * @property string $platform_attribute_id
 * @property string $name
 * @property bool   $is_required
 * @property bool   $is_variant
 * @property bool   $is_multi_select
 * @property string|null $data_type
 * @property array|null  $raw_payload
 * @property \Carbon\Carbon|null $last_synced_at
 */
class MpCategoryAttribute extends Model
{
    protected $fillable = [
        'marketplace',
        'platform_category_id',
        'platform_attribute_id',
        'name',
        'is_required',
        'is_variant',
        'is_multi_select',
        'data_type',
        'raw_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'is_required'     => 'boolean',
        'is_variant'      => 'boolean',
        'is_multi_select' => 'boolean',
        'raw_payload'     => 'array',
        'last_synced_at'  => 'datetime',
    ];

    /**
     * Bu özelliğe ait değer listesi.
     */
    public function values(): HasMany
    {
        return $this->hasMany(MpCategoryAttributeValue::class, 'mp_category_attribute_id');
    }
}
