<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SupplyOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'siparis_no',
        'kayit_tarihi',
        'musteri_adi',
        'telefon',
        'adres',
        'ilce',
        'il',
        'urun_adi',
        'kategori',
        'adet',
        'soz_tarihi',
        'renk_etiketi',
        'durum',
        'sebebiyet',
        'gonderim_tarihi',
        'notlar',
    ];

    protected $casts = [
        'kayit_tarihi' => 'date',
        'soz_tarihi' => 'date',
        'gonderim_tarihi' => 'date',
        'adet' => 'integer',
    ];

    /**
     * Durum seçenekleri
     */
    public const DURUM_OPTIONS = [
        'bekliyor' => ['label' => 'Bekliyor', 'color' => 'gray', 'icon' => '⏳'],
        'uretim' => ['label' => 'Üretim', 'color' => 'blue', 'icon' => '🔨'],
        'paketleme' => ['label' => 'Paketleme', 'color' => 'yellow', 'icon' => '📦'],
        'kargo' => ['label' => 'Kargo', 'color' => 'orange', 'icon' => '🚚'],
        'gonderildi' => ['label' => 'Gönderildi', 'color' => 'green', 'icon' => '✅'],
    ];

    /**
     * Sebebiyet seçenekleri
     */
    public const SEBEBIYET_OPTIONS = [
        'yok' => ['label' => '-', 'color' => 'gray'],
        'uretim' => ['label' => 'Üretim', 'color' => 'blue'],
        'paketleme' => ['label' => 'Paketleme', 'color' => 'yellow'],
        'kargo' => ['label' => 'Kargo', 'color' => 'orange'],
    ];

    /**
     * Durum label'ını getir
     */
    public function getDurumLabelAttribute(): string
    {
        return self::DURUM_OPTIONS[$this->durum]['label'] ?? $this->durum;
    }

    /**
     * Durum rengini getir
     */
    public function getDurumColorAttribute(): string
    {
        return self::DURUM_OPTIONS[$this->durum]['color'] ?? 'gray';
    }

    /**
     * Sebebiyet label'ını getir
     */
    public function getSebebiyetLabelAttribute(): string
    {
        return self::SEBEBIYET_OPTIONS[$this->sebebiyet]['label'] ?? $this->sebebiyet;
    }

    /**
     * Gecikmiş mi?
     */
    public function getIsGecikmiAttribute(): bool
    {
        // Kargo veya gönderildi ise gecikmiş sayılmaz
        if (in_array($this->durum, ['kargo', 'gonderildi'])) {
            return false;
        }
        
        if (!$this->soz_tarihi) {
            return false;
        }
        
        return $this->soz_tarihi->lt(Carbon::today());
    }

    /**
     * Tam adres
     */
    public function getTamAdresAttribute(): string
    {
        $parts = array_filter([
            $this->adres,
            $this->ilce,
            $this->il,
        ]);
        
        return implode(', ', $parts);
    }

    // ==================== SCOPES ====================

    /**
     * Bekleyen siparişler (kargoya verilmemiş ve gönderilmemiş)
     */
    public function scopeBekleyen($query)
    {
        return $query->whereNotIn('durum', ['kargo', 'gonderildi']);
    }

    /**
     * Bugün gönderilen
     */
    public function scopeBugünGonderilen($query)
    {
        return $query->where('durum', 'gonderildi')
                     ->whereDate('gonderim_tarihi', Carbon::today());
    }

    /**
     * Gecikmiş siparişler (kargoya verilmemiş ve gönderilmemiş, söz tarihi geçmiş)
     */
    public function scopeGecikmis($query)
    {
        return $query->whereNotIn('durum', ['kargo', 'gonderildi'])
                     ->whereNotNull('soz_tarihi')
                     ->whereDate('soz_tarihi', '<', Carbon::today());
    }

    /**
     * Duruma göre filtrele
     */
    public function scopeDurumFiltre($query, ?string $durum)
    {
        if ($durum && $durum !== 'hepsi') {
            return $query->where('durum', $durum);
        }
        return $query;
    }

    /**
     * Sebebiyete göre filtrele
     */
    public function scopeSebebiyetFiltre($query, ?string $sebebiyet)
    {
        if ($sebebiyet && $sebebiyet !== 'hepsi') {
            return $query->where('sebebiyet', $sebebiyet);
        }
        return $query;
    }

    /**
     * Arama
     */
    public function scopeArama($query, ?string $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('musteri_adi', 'like', "%{$search}%")
                  ->orWhere('siparis_no', 'like', "%{$search}%")
                  ->orWhere('urun_adi', 'like', "%{$search}%")
                  ->orWhere('telefon', 'like', "%{$search}%");
            });
        }
        return $query;
    }
}
