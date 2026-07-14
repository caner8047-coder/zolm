# ZOLM Müşteri İletişim Merkezi — Enterprise API Sözleşmesi (v1)

**Tarih:** 2026-07-13  
**Durum:** ✅ Aktif / Kararlı  
**Sürüm:** v1  

---

## 1. Giriş ve Kimlik Doğrulama

Kurumsal müşteriler, ZOLM Müşteri İletişim Merkezi üzerindeki verilerine API aracılığıyla erişebilir.

- **Protokol:** HTTPS  
- **Kimlik Doğrulama:** HTTP `Authorization: Bearer <plain_token>` başlığı ile sağlanır.
- **Güvenlik Politikası:** DB'de düz metin (plain-text) token saklanmaz; yalnızca SHA-256 hash'leri saklanır. IP adresi ve istekler denetlenir.

---

## 2. API Scope Listesi

Her API token'ı belirli yetki sınırlarına (scopes) sahiptir:

| Scope | Açıklama |
|---|---|
| `conversations:read` | Konuşma listesini okuma yetkisi |
| `messages:read` | Konuşma içindeki mesajları ve içeriklerini okuma yetkisi |
| `replies:create` | Müşterilere API üzerinden yanıt gönderme yetkisi |
| `analytics:read` | Kota, bütçe ve AI çalıştırma özetlerini okuma yetkisi |

---

## 3. Rate Limit ve Hata Kodları

- **Rate Limit:** Client bazlı her mağaza için dakikada maksimum 100 istek yapılabilir.
- **Hata Yanıtları:** Standart JSON formatında döner:

| HTTP Kodu | Açıklama |
|---|---|
| `400 Bad Request` | Eksik veya hatalı query parametresi (Örn: `store_id` eksik). |
| `401 Unauthorized` | Eksik, geçersiz veya iptal edilmiş (revoked) API Token. |
| `403 Forbidden` | Yetkisiz mağaza erişimi, yetersiz scope veya entitlement paketi kısıtlaması. |
| `404 Not Found` | Rota veya kaynak bulunamadı (Feature flag kapalıyken tüm endpointler 404 döner). |
| `422 Unprocessable` | Politika ihlali (Policy Engine engellemesi). |

---

## 4. Endpoint Detayları ve Örnek İstekler

### 4.1. Konuşmaları Listele
- **Method & Rota:** `GET /api/customer-care/v1/conversations`
- **Query Parametresi:** `store_id` (Zorunlu)
- **Gerekli Scope:** `conversations:read`
- **Örnek İstek:**
  ```bash
  curl -X GET "https://zolm.test/api/customer-care/v1/conversations?store_id=1" \
       -H "Authorization: Bearer cc_erp_randomtokenstring..."
  ```
- **Örnek Yanıt:**
  ```json
  [
    {
      "id": 12,
      "store_id": 1,
      "status": "open",
      "priority": "high",
      "ai_mode": "assisted",
      "created_at": "2026-07-13T12:00:00.000000Z"
    }
  ]
  ```

### 4.2. Konuşma Mesajlarını Getir
- **Method & Rota:** `GET /api/customer-care/v1/conversations/{id}/messages`
- **Gerekli Scope:** `messages:read`
- **Örnek Yanıt:**
  ```json
  [
    {
      "id": 45,
      "direction": "inbound",
      "sender_type": "customer",
      "message_type": "text",
      "body": "Ürünüm ne zaman kargoya verilir?",
      "sent_at": "2026-07-13T12:05:00.000000Z"
    }
  ]
  ```

### 4.3. Konuşmaya Yanıt Yaz
- **Method & Rota:** `POST /api/customer-care/v1/conversations/{id}/reply`
- **Gerekli Scope:** `replies:create`
- **Örnek İstek:**
  ```json
  {
    "body": "Merhaba, siparişiniz yarın kargoya verilecektir."
  }
  ```
- **Örnek Yanıt (Başarılı):**
  ```json
  {
    "success": true,
    "message_id": 46,
    "status": "pending"
  }
  ```

### 4.4. Analitik Özeti
- **Method & Rota:** `GET /api/customer-care/v1/analytics/summary`
- **Query Parametresi:** `store_id` (Zorunlu)
- **Gerekli Scope:** `analytics:read`
- **Örnek Yanıt:**
  ```json
  {
    "store_id": 1,
    "total_ai_drafts": 142,
    "total_auto_replies": 320,
    "total_costs_usd": 4.25
  }
  ```
