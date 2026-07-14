# ZOLM Web Chat Widget API Contract

Bu doküman, ZOLM Canlı Destek Widget'ının harici e-ticaret siteleri (Shopify, Ikas veya Özel altyapılar) ile güvenli iletişim kurması için gereken API protokolünü ve kontrat yapısını tanımlar.

Widget tarayıcı paketi uygulama kökünde `/customer-care-widget.js` adresinden sunulur. `/customer-care` fiziksel bir public klasör olarak kullanılmaz; bu adres AI Müşteri İletişim Merkezi'nin Laravel rotasıdır.

```html
<script src="https://zolm.example/customer-care-widget.js" data-key="PUBLIC_WIDGET_KEY" defer></script>
```

---

## 1. Güvenlik ve Kimlik Doğrulama (HMAC-SHA256)

ZOLM Web Chat API'si imzasız istekleri kabul etmez. Her istek HTTP başlığında (Header) bir HMAC imzası taşımalıdır.

- **İmza Hesaplama:** İstek gövdesi (raw JSON payload) ile mağaza bağlantısına özel paylaşılan gizli anahtar (`webhook_secret`) kullanılarak HMAC-SHA256 ile hesaplanır.
- **Header İsmi:** `X-Zolm-Signature`

### PHP İmza Doğrulama Örneği
```php
$payloadJson = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ZOLM_SIGNATURE'] ?? '';
$secret = $connection->webhook_secret;

$calculated = hash_hmac('sha256', $payloadJson, $secret);
$isValid = hash_equals($calculated, $signature);
```

---

## 2. Inbound Mesaj Gönderme Kontratı (Widget -> ZOLM)

**HTTP Method:** `POST`  
**Endpoint:** `/api/customer-care/web-chat/message`  
**Headers:**
- `Content-Type: application/json`
- `X-Zolm-Signature: CalculatedHmacSignature`

### Request Payload (JSON)
```json
{
  "store_id": 1,
  "session_id": "session_guest_991823",
  "idempotency_key": "chat_msg_uuid_10283",
  "body": "Merhaba, siparişimi sorgulamak istiyorum.",
  "is_online": true,
  "salt": "store_secret_salt"
}
```

### Parametreler
- `store_id` (int, zorunlu): Mesajın ait olduğu ZOLM Mağaza ID'si.
- `session_id` (string, zorunlu): Müşterinin widget oturum ID'si. ZOLM tarafında hash'lenerek saklanır (PII gizliliği).
- `idempotency_key` (string, zorunlu): Tekrarlanan istekleri önlemek için benzersiz mesaj UUID'si.
- `body` (string, zorunlu): Mesaj içeriği.
- `is_online` (boolean, opsiyonel): Müşterinin şu anki bağlantı durumu. `false` ise yanıtların teslimi sıraya alınır (fake sent engellenir).

### Response (HTTP 200 OK)
```json
{
  "success": true,
  "projected": true,
  "conversation_id": 42
}
```

---

## 3. Outbound Mesaj Dağıtımı (ZOLM -> Widget)

ZOLM outbox kuyruğu aracılığıyla temsilci veya AI yanıtı widget'a gönderildiğinde aşağıdaki kontrat uygulanır.

### Callback Payload (JSON)
```json
{
  "event": "message.created",
  "conversation_id": 42,
  "session_hash": "a8f3b29c...hashed_session_id",
  "message": {
    "id": "web_chat_msg_55281abc",
    "body": "Merhaba, siparişinizi kontrol ediyorum. Lütfen bekleyin.",
    "status": "sent"
  }
}
```

- Müşteri offline ise ZOLM callback çağrısı yapmaz ve outbox durumunu `queued` olarak saklar.

---

## 4. Hız Limitleri (Rate Limit) ve CORS

- **CORS:** Yalnızca `IntegrationConnection.webhook_url` veya izin verilmiş site origin'lerinden gelen isteklere (CORS allowlist) izin verilir.
- **Rate Limit:** Her `session_id` için maksimum **dakikada 20 istek** ile sınırlandırılmıştır. Limit aşıldığında `429 Too Many Requests` dönülür.
