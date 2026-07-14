<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportWebLead extends Model
{
    protected $fillable = [
        'store_id', 'support_widget_session_id', 'idempotency_key_hash', 'conversation_id', 'crm_contact_id',
        'name_encrypted', 'email_encrypted', 'phone_encrypted', 'purpose_encrypted',
        'lead_source', 'campaign', 'conversation_summary_encrypted', 'consent_basis',
        'marketing_consent_granted', 'privacy_notice_version', 'consented_at', 'marketing_consented_at', 'status',
    ];

    protected $casts = [
        'name_encrypted' => 'encrypted',
        'email_encrypted' => 'encrypted',
        'phone_encrypted' => 'encrypted',
        'purpose_encrypted' => 'encrypted',
        'conversation_summary_encrypted' => 'encrypted',
        'marketing_consent_granted' => 'boolean',
        'consented_at' => 'datetime',
        'marketing_consented_at' => 'datetime',
    ];

    public function session(): BelongsTo { return $this->belongsTo(SupportWidgetSession::class, 'support_widget_session_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(CrmContact::class, 'crm_contact_id'); }
}
