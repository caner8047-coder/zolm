<?php

namespace App\Services\WhatsApp;

use App\Models\WaAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
    ): WaAuditLog {
        return WaAuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
