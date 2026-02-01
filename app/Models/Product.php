<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ürün Master Modeli
 * 
 * Her ürün için standart desi, parça ve tutar değerlerini saklar.
 * Kargo karşılaştırmasında "beklenen" değerleri hesaplamak için kullanılır.
 * 
 * @property int $id
 * @property string $stok_kodu
 * @property string $urun_adi
 * @property int $parca
 * @property float $desi
 * @property float $tutar
 * @property string|null $kategori
 * @property bool $is_active
 * @property int|null $updated_by
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'stok_kodu',
        'urun_adi',
        'parca',
        'desi',
        'tutar',
        'kategori',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'parca' => 'integer',
        'desi' => 'decimal:2',
        'tutar' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Ürünü son güncelleyen kullanıcı
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Aktif ürünleri getir
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Kategoriye göre filtrele
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('kategori', $category);
    }

    /**
     * Stok kodundan kategori çıkar
     * Örnek: 1BRJZEM00048 -> BRJ (Berjer)
     */
    public static function parseCategoryFromStokKodu(string $stokKodu): ?string
    {
        if (strlen($stokKodu) >= 4) {
            return substr($stokKodu, 1, 3);
        }
        return null;
    }

    /**
     * Kategori kodunun Türkçe karşılığı
     */
    public static function getCategoryName(?string $code): string
    {
        $categories = [
            'BRJ' => 'Berjer',
            'PUF' => 'Puf',
            'KNP' => 'Kanepe',
            'KLT' => 'Koltuk',
            'SND' => 'Sandalye',
            'ÇYS' => 'Çay Seti',
            'MAS' => 'Masa',
            'SEH' => 'Sehpa',
            'YTK' => 'Yatak',
            'DOL' => 'Dolap',
        ];

        return $categories[$code] ?? $code ?? 'Bilinmiyor';
    }

    /**
     * 100 desi kuralına göre minimum parça sayısı
     */
    public function getMinimumParcaSayisi(): int
    {
        if ($this->desi <= 0) {
            return 1;
        }
        return (int) ceil($this->desi / 100);
    }

    /**
     * Belirli adet için toplam beklenen değerleri hesapla
     */
    public function calculateExpectedValues(int $adet = 1): array
    {
        return [
            'parca' => $this->parca * $adet,
            'desi' => $this->desi * $adet,
            'tutar' => $this->tutar * $adet,
        ];
    }

    /**
     * Boot metodunda kategori otomatik parse
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function (Product $product) {
            // Kategori boşsa stok kodundan parse et
            if (empty($product->kategori) && !empty($product->stok_kodu)) {
                $product->kategori = self::parseCategoryFromStokKodu($product->stok_kodu);
            }
        });
    }
}
