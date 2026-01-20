<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an activity
     */
    public static function log(
        string $action,
        ?string $description = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get action label
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'login' => 'Giriş yaptı',
            'logout' => 'Çıkış yaptı',
            'create_profile' => 'Profil oluşturdu',
            'update_profile' => 'Profil güncelledi',
            'delete_profile' => 'Profil sildi',
            'process_report' => 'Rapor işledi',
            'download_file' => 'Dosya indirdi',
            'create_user' => 'Kullanıcı oluşturdu',
            'update_user' => 'Kullanıcı güncelledi',
            'delete_user' => 'Kullanıcı sildi',
            'reset_password' => 'Şifre sıfırladı',
            default => $this->action,
        };
    }
}
