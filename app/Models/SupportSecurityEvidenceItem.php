<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SupportSecurityEvidenceItem extends Model
{
    protected $fillable = [
        'run_id', 'control_name', 'result', 'evidence_data_encrypted',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportSecurityAuditRun::class, 'run_id');
    }

    /**
     * Kanıt verisini şifreler ve kaydeder. Ham secret/token veya PII kabul etmez.
     */
    public static function createEncrypted(int $runId, string $controlName, string $result, array $safeData): self
    {
        return static::create([
            'run_id'                 => $runId,
            'control_name'           => $controlName,
            'result'                 => $result,
            'evidence_data_encrypted' => Crypt::encryptString(json_encode($safeData, JSON_UNESCAPED_UNICODE)),
        ]);
    }

    public function getEvidenceDataAttribute(): array
    {
        try {
            return json_decode(Crypt::decryptString($this->evidence_data_encrypted), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
