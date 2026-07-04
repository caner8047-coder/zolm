<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null, ?int $storeId = null): mixed
    {
        $query = static::where('key', $key);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        } else {
            $query->whereNull('store_id');
        }

        $setting = $query->first();

        return $setting?->value ?? $default;
    }

    public static function set(string $key, mixed $value, ?int $storeId = null): static
    {
        return static::updateOrCreate(
            ['key' => $key, 'store_id' => $storeId],
            ['value' => $value]
        );
    }
}
