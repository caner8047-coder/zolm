<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class MpPeriod extends Model
{
    protected $fillable = [
        'user_id', 'seller_id', 'year', 'month', 'marketplace',
        'status', 'import_files', 'summary_cache', 'notes',
        'total_orders', 'total_returns', 'total_cancellations', 'total_audit_errors',
        'is_locked',
    ];

    protected $casts = [
        'year'              => 'integer',
        'month'             => 'integer',
        'import_files'      => 'array',
        'summary_cache'     => 'array',
        'total_orders'      => 'integer',
        'total_returns'     => 'integer',
        'total_cancellations' => 'integer',
        'total_audit_errors'  => 'integer',
        'is_locked'         => 'boolean',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MpOrder::class, 'period_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MpTransaction::class, 'period_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MpInvoice::class, 'period_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MpAuditLog::class, 'period_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /**
     * Sadece aktif kullanıcının (Tenant) dönemlerini getirir. (Güvenlik / İzolasyon)
     */
    public function scopeUser($query)
    {
        return $query->where('user_id', Auth::id());
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeByMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeByMarketplace($query, string $marketplace)
    {
        return $query->where('marketplace', $marketplace);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ─── Accessors ──────────────────────────────────────────────

    /**
     * Dönem adı: "Ocak 2025" formatında
     */
    public function getPeriodNameAttribute(): string
    {
        $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];

        return ($months[$this->month] ?? $this->month) . ' ' . $this->year;
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Dönem istatistiklerini yeniden hesapla
     */
    public function recalculateStats(): void
    {
        $this->update([
            'total_orders'       => $this->orders()->count(),
            'total_returns'      => $this->orders()->where('status', 'İade Edildi')->count(),
            'total_cancellations' => $this->orders()->where('status', 'İptal Edildi')->count(),
            'total_audit_errors' => $this->auditLogs()->where('status', 'open')->count(),
        ]);
    }
}
