<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kargo Rapor Satırı Modeli
 * 
 * Her karşılaştırma satırı için detaylı bilgi saklar.
 * Beklenen vs gerçek değerler, farklar ve hata tipleri.
 * 
 * @property int $id
 * @property int $cargo_report_id
 * @property \Carbon\Carbon|null $tarih
 * @property string $musteri_adi
 * @property string|null $takip_kodu
 * @property string|null $stok_kodu
 * @property string|null $urun_adi
 * @property int $adet
 * @property int $beklenen_parca
 * @property float $beklenen_desi
 * @property float $beklenen_tutar
 * @property int $gercek_parca
 * @property float $gercek_desi
 * @property float $gercek_tutar
 * @property int $parca_fark
 * @property float $desi_fark
 * @property float $tutar_fark
 * @property string $error_type
 * @property bool $has_error
 * @property bool $is_matched
 */
class CargoReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cargo_report_id',
        'tarih',
        'musteri_adi',
        'takip_kodu',
        'stok_kodu',
        'urun_adi',
        'adet',
        'beklenen_parca',
        'beklenen_desi',
        'beklenen_tutar',
        'gercek_parca',
        'gercek_desi',
        'gercek_tutar',
        'parca_fark',
        'desi_fark',
        'tutar_fark',
        'error_type',
        'has_error',
        'is_matched',
        'pazaryeri',
        'magaza',
        'siparis_no',
        'siparis_detay',
        'cikis_il',
        'is_iade',
        'is_parca_gonderi',
    ];

    protected $casts = [
        'tarih' => 'date',
        'adet' => 'integer',
        'beklenen_parca' => 'integer',
        'beklenen_desi' => 'decimal:2',
        'beklenen_tutar' => 'decimal:2',
        'gercek_parca' => 'integer',
        'gercek_desi' => 'decimal:2',
        'gercek_tutar' => 'decimal:2',
        'parca_fark' => 'integer',
        'desi_fark' => 'decimal:2',
        'tutar_fark' => 'decimal:2',
        'has_error' => 'boolean',
        'is_matched' => 'boolean',
        'is_iade' => 'boolean',
        'is_parca_gonderi' => 'boolean',
        'siparis_detay' => 'array',
    ];

    /**
     * Hata tipleri ve açıklamaları
     */
    public const ERROR_TYPES = [
        'none' => ['label' => 'Hata Yok', 'color' => 'green', 'icon' => '✓'],
        'desi_eksik' => ['label' => 'Desi Eksik', 'color' => 'yellow', 'icon' => '↓'],
        'desi_fazla' => ['label' => 'Desi Fazla', 'color' => 'red', 'icon' => '↑'],
        'tutar_eksik' => ['label' => 'Tutar Eksik', 'color' => 'yellow', 'icon' => '↓'],
        'tutar_fazla' => ['label' => 'Tutar Fazla', 'color' => 'red', 'icon' => '↑'],
        'parca_eksik' => ['label' => 'Parça Eksik', 'color' => 'red', 'icon' => '⚠'],
        'parca_fazla' => ['label' => 'Parça Fazla', 'color' => 'orange', 'icon' => '+'],
        'eslesmedi' => ['label' => 'Eşleşmedi', 'color' => 'gray', 'icon' => '?'],
    ];

    /**
     * Ana rapor
     */
    public function cargoReport(): BelongsTo
    {
        return $this->belongsTo(CargoReport::class);
    }

    /**
     * Ana rapor (alias)
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(CargoReport::class, 'cargo_report_id');
    }

    /**
     * İlişkili ürün
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'stok_kodu', 'stok_kodu');
    }

    /**
     * Tazmin talebi (varsa)
     */
    public function compensation(): HasOne
    {
        return $this->hasOne(Compensation::class);
    }

    /**
     * Hatalı satırlar
     */
    public function scopeWithErrors($query)
    {
        return $query->where('has_error', true);
    }

    /**
     * Belirli hata tipine göre filtre
     */
    public function scopeByErrorType($query, string $type)
    {
        return $query->where('error_type', $type);
    }

    /**
     * Eşleşmiş satırlar
     */
    public function scopeMatched($query)
    {
        return $query->where('is_matched', true);
    }

    /**
     * İade olmayan satırlar
     */
    public function scopeNotIade($query)
    {
        return $query->where('is_iade', false);
    }

    /**
     * Hata tipi bilgisi
     */
    public function getErrorInfoAttribute(): array
    {
        return self::ERROR_TYPES[$this->error_type] ?? self::ERROR_TYPES['none'];
    }

    /**
     * Kargo takip linki
     */
    public function getTrackingUrlAttribute(): ?string
    {
        if (empty($this->takip_kodu)) {
            return null;
        }

        $cargoCompany = $this->cargoReport?->cargo_company;

        $urls = [
            'Sürat Kargo' => 'https://suratkargo.com.tr/Default/_KargoTakip?kargotakipno=' . $this->takip_kodu,
            'MNG Kargo' => 'https://www.mngkargo.com.tr/gonderi-takip?q=' . $this->takip_kodu,
            'Yurtiçi Kargo' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $this->takip_kodu,
            'Aras Kargo' => 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code=' . $this->takip_kodu,
            'PTT Kargo' => 'https://gonderitakip.ptt.gov.tr/?q=' . $this->takip_kodu,
        ];

        return $urls[$cargoCompany] ?? null;
    }

    /**
     * Aleyhimize mi? (Fazla faturalama)
     */
    public function isAgainstUs(): bool
    {
        return in_array($this->error_type, ['desi_fazla', 'tutar_fazla']);
    }

    /**
     * Lehimize mi? (Eksik faturalama)
     */
    public function isInOurFavor(): bool
    {
        return in_array($this->error_type, ['desi_eksik', 'tutar_eksik']);
    }

    /**
     * Kritik hata mı?
     */
    public function isCritical(): bool
    {
        return in_array($this->error_type, ['parca_eksik', 'eslesmedi']);
    }

    /**
     * Boot metodunda hata tespiti
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function (CargoReportItem $item) {
            // Farkları hesapla
            $item->parca_fark = $item->gercek_parca - $item->beklenen_parca;
            $item->desi_fark = $item->gercek_desi - $item->beklenen_desi;
            $item->tutar_fark = $item->gercek_tutar - $item->beklenen_tutar;

            // Hata tipini belirle
            $item->error_type = $item->determineErrorType();
            $item->has_error = $item->error_type !== 'none';
        });
    }

    /**
     * Hata tipini belirle
     */
    public function determineErrorType(): string
    {
        // Öncelik sırası: Eşleşmeme > Parça > Desi > Tutar

        if (!$this->is_matched) {
            return 'eslesmedi';
        }

        // Parça kontrolü
        $parcaFark = $this->gercek_parca - $this->beklenen_parca;
        if ($parcaFark < 0) {
            return 'parca_eksik';
        }
        if ($parcaFark > 0) {
            return 'parca_fazla';
        }

        // Desi kontrolü (tolerans: ±2 desi)
        $desiFark = $this->gercek_desi - $this->beklenen_desi;
        if ($desiFark < -2) {
            return 'desi_eksik';
        }
        if ($desiFark > 2) {
            return 'desi_fazla';
        }

        // Tutar kontrolü (tolerans: ±5 TL)
        $tutarFark = $this->gercek_tutar - $this->beklenen_tutar;
        if ($tutarFark < -5) {
            return 'tutar_eksik';
        }
        if ($tutarFark > 5) {
            return 'tutar_fazla';
        }

        return 'none';
    }
}
