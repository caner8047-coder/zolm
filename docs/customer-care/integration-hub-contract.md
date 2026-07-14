# ZOLM Enterprise Integration Hub Contract Specification

This document details the webhook integration protocol, payloads, HMAC signature schemas, retry mechanics, and idempotency guarantees for outbound and inbound integrations.

---

## 1. Webhook Signature Verification (HMAC-SHA256)

To secure webhook consumption, ZOLM signs every outbound JSON payload with the configured HMAC secret key. Developers MUST verify this signature on receipt.

### Headers Sent
- `X-Zolm-Event-Id`: Unique event UUID.
- `X-Zolm-Timestamp`: POSIX epoch timestamp of transmission.
- `X-Zolm-Signature`: Calculated SHA256 HMAC of signature source string.
- `X-Zolm-Idempotency-Key`: Canonical idempotency token.

### Signature Formula
The signature signature payload string is generated as:
```text
[X-Zolm-Timestamp].[Raw-JSON-Body]
```
Example calculation in PHP:
```php
$signingPayload = $timestamp . '.' . $rawJsonBody;
$computedSignature = hash_hmac('sha256', $signingPayload, $webhookSecret);

if (hash_equals($computedSignature, $receivedSignature)) {
    // Signature is valid
}
```

---

## 2. Event Payloads (Schema v1.0)

All webhook events adhere to a common structural envelope containing metadata and the actual redacted event data.

### Envelope Structure
```json
{
  "event_id": "97e68cfb-6f8f-4318-97fb-faad0b4f8cb6",
  "store_id": 1,
  "occurred_at": "2026-07-13T10:45:24Z",
  "schema_version": "1.0",
  "data": {},
  "idempotency_key": "idemp_abc123xyz"
}
```

### Event: `conversation.created`
Fired when a customer initiates a new support thread.
```json
{
  "event_id": "97e68cfb-6f8f-4318-97fb-faad0b4f8cb6",
  "store_id": 1,
  "occurred_at": "2026-07-13T10:45:24Z",
  "schema_version": "1.0",
  "data": {
    "conversation_id": 45,
    "channel_type": "whatsapp",
    "external_customer_id": "[KVKK-SİLİNDİ]"
  },
  "idempotency_key": "idemp_abc123xyz"
}
```

### Event: `message.received`
Fired on inbound message projection.
```json
{
  "event_id": "ccbe5233-ff1a-471a-9696-bebc7fb56254",
  "store_id": 1,
  "occurred_at": "2026-07-13T10:45:26Z",
  "schema_version": "1.0",
  "data": {
    "conversation_id": 45,
    "message_id": 204,
    "direction": "inbound",
    "body": "[KVKK-SİLİNDİ]"
  },
  "idempotency_key": "idemp_msg204"
}
```

---

## 3. Retries, Backoffs, and Dead-Letter Queue (DLQ)

If the webhook endpoint does not return a successful response status code (HTTP 2xx):
1. **Attempts:** ZOLM attempts delivery a maximum of **3 times**.
2. **Backoff:** Deliveries are enqueued with retries.
3. **Dead-Letter State:** If all 3 attempts fail, status transitions to `dead_letter` and delivery stops to prevent infinite loops.
4. **Monitoring:** Operators can manually review DLQ items in the integrations control panel and trigger a manual retry.
