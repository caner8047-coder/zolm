<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kargo Karşılaştırma Raporu Modeli
 * 
 * Her karşılaştırma işlemi için bir rapor kaydı oluşturulur.
 * Özet istatistikler ve metadata bu modelde saklanır.
 * 
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $cargo_company
 * @property \Carbon\Carbon $report_date
 * @property int $total_orders
 * @property int $matched_orders
 * @property int $unmatched_orders
 * @property int $error_count
 * @property float $total_expected_desi
 * @property float $total_actual_desi
 * @property float $total_desi_diff
 * @property float $total_expected_tutar
 * @property float $total_actual_tutar
 * @property float $total_tutar_diff
 * @property string $status
 */
class CargoReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'cargo_company',
        'report_date',
        'total_orders',
        'matched_orders',
        'unmatched_orders',
        'error_count',
        'total_expected_desi',
        'total_actual_desi',
        'total_desi_diff',
        'total_expected_tutar',
        'total_actual_tutar',
        'total_tutar_diff',
        'cargo_file_name',
        'order_file_name',
        'status',
        'notes',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_orders' => 'integer',
        'matched_orders' => 'integer',
        'unmatched_orders' => 'integer',
        'error_count' => 'integer',
        'total_expected_desi' => 'decimal:2',
        'total_actual_desi' => 'decimal:2',
        'total_desi_diff' => 'decimal:2',
        'total_expected_tutar' => 'decimal:2',
        'total_actual_tutar' => 'decimal:2',
        'total_tutar_diff' => 'decimal:2',
    ];

    /**
     * Raporu oluşturan kullanıcı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rapor satırları
     */
    public function items(): HasMany
    {
        return $this->hasMany(CargoReportItem::class);
    }

    /**
     * Sadece hatalı satırlar
     */
    public function errorItems(): HasMany
    {
        return $this->hasMany(CargoReportItem::class)->where('has_error', true);
    }

    /**
     * Tamamlanmış raporlar
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Belirli kargo firmasına ait raporlar
     */
    public function scopeByCargoCompany($query, string $company)
    {
        return $query->where('cargo_company', $company);
    }

    /**
     * Tarih aralığına göre filtre
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('report_date', [$startDate, $endDate]);
    }

    /**
     * Hata yüzdesi
     */
    public function getErrorPercentageAttribute(): float
    {
        if ($this->total_orders <= 0) {
            return 0;
        }
        return round(($this->error_count / $this->total_orders) * 100, 2);
    }

    /**
     * Eşleşme yüzdesi
     */
    public function getMatchPercentageAttribute(): float
    {
        if ($this->total_orders <= 0) {
            return 0;
        }
        return round(($this->matched_orders / $this->total_orders) * 100, 2);
    }

    /**
     * Aleyhimize olan toplam fark (tazmin talep edilecek miktar)
     */
    public function getTotalClaimableAttribute(): float
    {
        return $this->items()
            ->where('tutar_fark', '>', 0)
            ->sum('tutar_fark');
    }

    /**
     * Kargo firması logosu
     */
    public function getCargoLogoAttribute(): string
    {
        $logos = [
            'Sürat Kargo' => 'surat.png',
            'MNG Kargo' => 'mng.png',
            'Yurtiçi Kargo' => 'yurtici.png',
            'Aras Kargo' => 'aras.png',
            'PTT Kargo' => 'ptt.png',
        ];

        return $logos[$this->cargo_company] ?? 'default.png';
    }

    /**
     * İstatistikleri yeniden hesapla
     */
    public function recalculateStats(): void
    {
        $items = $this->items;

        $this->total_orders = $items->count();
        $this->matched_orders = $items->where('is_matched', true)->count();
        $this->unmatched_orders = $items->where('is_matched', false)->count();
        $this->error_count = $items->where('has_error', true)->count();

        $this->total_expected_desi = $items->sum('beklenen_desi');
        $this->total_actual_desi = $items->sum('gercek_desi');
        $this->total_desi_diff = $items->sum('desi_fark');

        $this->total_expected_tutar = $items->sum('beklenen_tutar');
        $this->total_actual_tutar = $items->sum('gercek_tutar');
        $this->total_tutar_diff = $items->sum('tutar_fark');

        $this->save();
    }
}
