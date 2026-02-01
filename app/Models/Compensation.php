<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tazmin Talebi Modeli
 * 
 * Kargo firmasından talep edilecek tazminatları takip eder.
 * Kayıp ürün, hasarlı ürün, fazla faturalama vb. durumlar için.
 * 
 * @property int $id
 * @property int $user_id
 * @property int|null $cargo_report_item_id
 * @property \Carbon\Carbon $tarih
 * @property string $musteri_adi
 * @property string|null $takip_kodu
 * @property string|null $urun_adi
 * @property string|null $stok_kodu
 * @property string|null $cargo_company
 * @property string $sebep
 * @property string|null $aciklama
 * @property float $talep_tutari
 * @property float $onaylanan_tutar
 * @property string $durum
 */
class Compensation extends Model
{
    use HasFactory;

    protected $table = 'compensations';

    protected $fillable = [
        'user_id',
        'cargo_report_item_id',
        'tarih',
        'musteri_adi',
        'takip_kodu',
        'urun_adi',
        'stok_kodu',
        'cargo_company',
        'sebep',
        'aciklama',
        'talep_tutari',
        'onaylanan_tutar',
        'durum',
        'kargo_referans_no',
        'attachments',
        'talep_tarihi',
        'sonuc_tarihi',
        'dilekce_icerigi',
    ];

    protected $casts = [
        'tarih' => 'date',
        'talep_tarihi' => 'date',
        'sonuc_tarihi' => 'date',
        'talep_tutari' => 'decimal:2',
        'onaylanan_tutar' => 'decimal:2',
        'attachments' => 'array',
    ];

    /**
     * Tazmin sebepleri ve açıklamaları
     */
    public const SEBEPLER = [
        'kayip_urun' => ['label' => 'Kayıp Ürün', 'icon' => '📦', 'color' => 'red'],
        'hasarli_urun' => ['label' => 'Hasarlı Ürün', 'icon' => '💔', 'color' => 'orange'],
        'desi_fazla' => ['label' => 'Fazla Desi Faturalama', 'icon' => '📊', 'color' => 'yellow'],
        'tutar_fazla' => ['label' => 'Fazla Tutar Faturalama', 'icon' => '💰', 'color' => 'yellow'],
        'gecikme' => ['label' => 'Teslimat Gecikmesi', 'icon' => '⏰', 'color' => 'blue'],
        'yanlis_teslim' => ['label' => 'Yanlış Adrese Teslim', 'icon' => '📍', 'color' => 'purple'],
        'iade_kayip' => ['label' => 'İade Sürecinde Kayıp', 'icon' => '🔄', 'color' => 'red'],
        'diger' => ['label' => 'Diğer', 'icon' => '📋', 'color' => 'gray'],
    ];

    /**
     * Durum bilgileri
     */
    public const DURUMLAR = [
        'beklemede' => ['label' => 'Beklemede', 'color' => 'gray', 'icon' => '⏳'],
        'talep_edildi' => ['label' => 'Talep Edildi', 'color' => 'blue', 'icon' => '📤'],
        'inceleniyor' => ['label' => 'İnceleniyor', 'color' => 'yellow', 'icon' => '🔍'],
        'onaylandi' => ['label' => 'Onaylandı', 'color' => 'green', 'icon' => '✓'],
        'kismi_onay' => ['label' => 'Kısmi Onay', 'color' => 'orange', 'icon' => '½'],
        'reddedildi' => ['label' => 'Reddedildi', 'color' => 'red', 'icon' => '✗'],
        'odendi' => ['label' => 'Ödendi', 'color' => 'green', 'icon' => '💵'],
        'kapandi' => ['label' => 'Kapandı', 'color' => 'gray', 'icon' => '🔒'],
    ];

    /**
     * Tazmin talebini oluşturan kullanıcı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * İlişkili kargo rapor satırı
     */
    public function cargoReportItem(): BelongsTo
    {
        return $this->belongsTo(CargoReportItem::class);
    }

    /**
     * Bekleyen tazminler
     */
    public function scopePending($query)
    {
        return $query->whereIn('durum', ['beklemede', 'talep_edildi', 'inceleniyor']);
    }

    /**
     * Tamamlanmış tazminler
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('durum', ['onaylandi', 'kismi_onay', 'odendi', 'kapandi']);
    }

    /**
     * Belirli kargo firmasına ait
     */
    public function scopeByCargoCompany($query, string $company)
    {
        return $query->where('cargo_company', $company);
    }

    /**
     * Sebep bilgisi
     */
    public function getSebepInfoAttribute(): array
    {
        return self::SEBEPLER[$this->sebep] ?? self::SEBEPLER['diger'];
    }

    /**
     * Durum bilgisi
     */
    public function getDurumInfoAttribute(): array
    {
        return self::DURUMLAR[$this->durum] ?? self::DURUMLAR['beklemede'];
    }

    /**
     * Kargo takip linki
     */
    public function getTrackingUrlAttribute(): ?string
    {
        if (empty($this->takip_kodu)) {
            return null;
        }

        $urls = [
            'Sürat Kargo' => 'https://suratkargo.com.tr/Default/_KargoTakip?kargo-takipno=' . $this->takip_kodu,
            'MNG Kargo' => 'https://www.mngkargo.com.tr/wps/portal/takip?takipNo=' . $this->takip_kodu,
            'Yurtiçi Kargo' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $this->takip_kodu,
            'Aras Kargo' => 'https://www.araskargo.com.tr/trmweb/kargoIzleme.aspx?code=' . $this->takip_kodu,
            'PTT Kargo' => 'https://gonderitakip.ptt.gov.tr/Track/Verify?q=' . $this->takip_kodu,
        ];

        return $urls[$this->cargo_company] ?? null;
    }

    /**
     * Aktif mi? (Bekleyen veya işlemde)
     */
    public function isActive(): bool
    {
        return in_array($this->durum, ['beklemede', 'talep_edildi', 'inceleniyor']);
    }

    /**
     * Sonuçlandı mı?
     */
    public function isResolved(): bool
    {
        return in_array($this->durum, ['onaylandi', 'kismi_onay', 'reddedildi', 'odendi', 'kapandi']);
    }

    /**
     * Başarılı mı?
     */
    public function isSuccessful(): bool
    {
        return in_array($this->durum, ['onaylandi', 'kismi_onay', 'odendi']);
    }

    /**
     * Fark tutarı (Talep - Onaylanan)
     */
    public function getDifferenceAttribute(): float
    {
        return $this->talep_tutari - $this->onaylanan_tutar;
    }

    /**
     * Onay oranı (%)
     */
    public function getApprovalRateAttribute(): float
    {
        if ($this->talep_tutari <= 0) {
            return 0;
        }
        return round(($this->onaylanan_tutar / $this->talep_tutari) * 100, 2);
    }

    /**
     * Kargo rapor itemından tazmin oluştur
     */
    public static function createFromCargoReportItem(CargoReportItem $item, string $sebep, ?string $aciklama = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'cargo_report_item_id' => $item->id,
            'tarih' => $item->tarih ?? now(),
            'musteri_adi' => $item->musteri_adi,
            'takip_kodu' => $item->takip_kodu,
            'urun_adi' => $item->urun_adi,
            'stok_kodu' => $item->stok_kodu,
            'cargo_company' => $item->cargoReport?->cargo_company,
            'sebep' => $sebep,
            'aciklama' => $aciklama,
            'talep_tutari' => abs($item->tutar_fark),
            'durum' => 'beklemede',
        ]);
    }
}
