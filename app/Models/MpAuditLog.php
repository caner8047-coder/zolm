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
        // İtiraz alanları
        'dispute_status', 'disputed_at', 'dispute_note',
        'dispute_resolution', 'dispute_resolved_at',
    ];

    protected $casts = [
        'expected_value'       => 'decimal:2',
        'actual_value'         => 'decimal:2',
        'difference'           => 'decimal:2',
        'disputed_at'          => 'datetime',
        'dispute_resolved_at'  => 'datetime',
    ];

    /** İtiraz durumları */
    const DISPUTE_PENDING  = 'pending';
    const DISPUTE_ACCEPTED = 'accepted';
    const DISPUTE_REJECTED = 'rejected';

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

    /** İtiraz edilmemiş kayıtlar */
    public function scopeNotDisputed($query)
    {
        return $query->whereNull('dispute_status');
    }

    /** İtiraz edilmiş (herhangi bir durumda) */
    public function scopeDisputed($query)
    {
        return $query->whereNotNull('dispute_status');
    }

    /** Beklemede olan itirazlar */
    public function scopePendingDispute($query)
    {
        return $query->where('dispute_status', self::DISPUTE_PENDING);
    }

    // ─── İtiraz Aksiyon Metodları ────────────────────────────────

    /**
     * Bu kaydın itiraz edilebilir olup olmadığını kontrol eder.
     * Sadece KAYIP_ODEME ve KARGO_MALIYET_ASIMI kritik kurallar itiraza açık.
     */
    public function canBeDisputed(): bool
    {
        if ($this->dispute_status !== null) {
            return false; // Zaten itiraz edilmiş
        }

        return in_array($this->rule_code, [
            'KAYIP_ODEME',
            'KARGO_MALIYET_ASIMI',
            'HAKEDIS_FARK',
            'KOMISYON_TUTARSIZLIGI',
            'CARI_UYUMSUZLUK',
        ], true);
    }

    /**
     * Kaydı itiraz edildi olarak işaretler.
     */
    public function markAsDisputed(string $note = ''): bool
    {
        if (! $this->canBeDisputed()) {
            return false;
        }

        return $this->update([
            'dispute_status' => self::DISPUTE_PENDING,
            'disputed_at'    => now(),
            'dispute_note'   => $note,
        ]);
    }

    /**
     * İtirazı çözüme kavuşturur.
     */
    public function resolveDispute(string $status, string $resolution = ''): bool
    {
        if (! in_array($status, [self::DISPUTE_ACCEPTED, self::DISPUTE_REJECTED], true)) {
            return false;
        }

        return $this->update([
            'dispute_status'       => $status,
            'dispute_resolution'   => $resolution,
            'dispute_resolved_at'  => now(),
            'status'               => $status === self::DISPUTE_ACCEPTED ? 'resolved' : 'open',
        ]);
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
        $meta = \App\Services\AuditEngine::getMetaByCode($this->rule_code);
        if ($meta && !empty($meta['title'])) {
            return $meta['title'];
        }

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
