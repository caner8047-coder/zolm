<?php

namespace App\Modules\Hr\Core\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class HrAuditService
{
    private array $sensitiveFields = [
        'national_id', 'national_id_encrypted', 'national_id_hash',
        'iban', 'iban_encrypted', 'iban_hash',
        'gross_salary', 'net_pay', 'total_cost', 'base_salary',
        'bank_name', 'bank_account_number',
        'health_data', 'blood_type',
        'password', 'token', 'api_key',
    ];

    public function log(string $action, Model $subject, ?array $old = null, ?array $new = null): void
    {
        $maskedOld = $old ? $this->maskSensitive($old) : null;
        $maskedNew = $new ? $this->maskSensitive($new) : null;

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => get_class($subject),
            'entity_id' => $subject->getKey(),
            'description' => class_basename($subject) . ' ' . $action,
            'metadata' => [
                'module' => 'hr',
                'old_values' => $maskedOld,
                'new_values' => $maskedNew,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function logEvent(string $action, ?string $description = null, ?array $metadata = null): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => null,
            'entity_id' => null,
            'description' => $description,
            'metadata' => array_merge(['module' => 'hr'], $metadata ?? []),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function maskSensitive(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $this->sensitiveFields)) {
                $value = $this->maskValue($key, $value);
            }
        }
        return $data;
    }

    private function maskValue(string $key, mixed $value): string
    {
        return match (true) {
            str_contains($key, 'national_id') => $this->maskNationalId($value),
            str_contains($key, 'iban') => $this->maskIban($value),
            in_array($key, ['gross_salary', 'net_pay', 'total_cost', 'base_salary']) => '[MASKED]',
            str_contains($key, 'health') || $key === 'blood_type' => '[HARIÇ TUTULDU]',
            in_array($key, ['password', 'token', 'api_key']) => '[ASLA LOGLANMAZ]',
            default => '[MASKED]',
        };
    }

    private function maskNationalId(?string $value): string
    {
        if (!$value || !is_string($value)) {
            return 'null';
        }
        return '***' . substr($value, -4);
    }

    private function maskIban(?string $value): string
    {
        if (!$value || !is_string($value)) {
            return 'null';
        }
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 6) . str_repeat('*', $len - 8) . substr($value, -2);
    }
}
