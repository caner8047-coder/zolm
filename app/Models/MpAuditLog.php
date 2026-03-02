<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpAuditLog extends Model
{
    protected $fillable = [
        'period_id', 'order_id', 'rule_code', 'severity',
        'title', 'description',
        'expected_value', 'actual_value', 'difference',
        'status', 'resolution_note',
    ];

    protected $casts = [
        'expected_value' => 'decimal:2',
        'actual_value'   => 'decimal:2',
        'difference'     => 'decimal:2',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function period(): BelongsTo
    {
        return $this->belongsTo(MpPeriod::class, 'period_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MpOrder::class, 'order_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeWarnings($query)
    {
        return $query->where('severity', 'warning');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByRule($query, string $ruleCode)
    {
        return $query->where('rule_code', $ruleCode);
    }

    // ─── Accessors ──────────────────────────────────────────────

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'warning'  => 'yellow',
            'info'     => 'blue',
            default    => 'gray',
        };
    }

    public function getSeverityIconAttribute(): string
    {
        return match ($this->severity) {
            'critical' => '🔴',
            'warning'  => '🟡',
            'info'     => '🔵',
            default    => '⚪',
        };
    }

    /**
     * Kural kodu → Türkçe etiket
     */
    public function getRuleLabelAttribute(): string
    {
        return match ($this->rule_code) {
            'STOPAJ_HATA'      => 'Stopaj Hatası',
            'BAREM_ASIMI'      => 'Barem Aşımı',
            'KOMISYON_IADE'    => 'Komisyon İadesi Kaybı',
            'YANIK_MALIYET'    => 'Yanık Maliyet',
            'KAMPANYA_KOM'     => 'Kampanya Komisyon Hatası',
            'KDV_ASIMETRI'     => 'KDV Asimetrisi',
            'HAKEDIS_FARK'     => 'Hakediş Farkı',
            'OPERASYONEL_CEZA' => 'Operasyonel Ceza',
            'COKLU_SEPET'      => 'Çoklu Sepet Etkisi',
            'EARSIV_UYARI'     => 'E-Arşiv Uyarısı',
            default            => $this->rule_code,
        };
    }
}
