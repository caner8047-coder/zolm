<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecipeSetting extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    /**
     * Ayar oku (user-scope)
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('user_id', Auth::id())
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Ayar yaz (user-scope)
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['user_id' => Auth::id(), 'key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Varsayılan ayarlar
     */
    public const DEFAULTS = [
        'default_waste_rate'     => '0.10',
        'default_fabric_method'  => 'area_div_width',
        'fabric_rounding_step'   => '0.05',
        'rounding_mode'          => 'none',
    ];
}
