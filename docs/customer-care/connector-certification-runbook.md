# Connector Certification Runbook

Tüm entegrasyon kanallarının (Trendyol, Hepsiburada, N11, WhatsApp, Meta Social, Google Reviews, Web Chat ve Enterprise API) canlıya (production) alınabilmesi için geçmesi gereken asgari kriterler bu belgede tanımlanmıştır.

---

## 1. Sertifikasyon Kriterleri Listesi

Her bir kanal için aşağıdaki denetim maddeleri başarılı olmalıdır:

### 1.1. Feature Flag Durumu
- İlgili kanalın özellik bayrağı (Örn: `meta_social_enabled`) config dosyasında `true` tanımlı olmalıdır.
- Devre dışı bırakılmış kanalların API veya outbox üzerinden mesaj göndermesi engellenmelidir.

### 1.2. Kanal Yapılandırması (Channel Config)
- `support_channels` tablosunda ilgili mağaza (`store_id`) için kanal kaydı bulunmalı ve `is_enabled` bayrağı `true` olmalıdır.

### 1.3. Entegrasyon Hub Bağlantısı (Connector Bind)
- `integration_connections` tablosunda ilgili sağlayıcı (`provider`) için geçerli bir bağlantı olmalıdır.
- Bağlantı durumu (`status`) `configured` veya `active` olmalıdır.

### 1.4. Gönderim Kapasitesi Kontrolü (`canReply`)
- Kanal adaptörünün `canReply()` fonksiyonu çağrıldığında `true` dönmelidir.
- Geçersiz formatta alıcı veya kapatılmış oturumlar için gönderimler fail-closed engellenmelidir.

### 1.5. Gizli Bilgi Hijyeni (Secret Hygiene)
- Webhook secret, app secret ve access token değerleri veritabanında kesinlikle ham (plain-text) olarak loglanmamalıdır.
- API çağrılarında veya hata kayıtlarında `cc_` veya `plain_` önekli açık sırlar sızdırılmamalı, otomatik maskelenmelidir.

---

## 2. Sandbox Simülasyon Prosedürleri

- Canlı API entegrasyonu kurulmadan önce, sandbox ortamında mock/fixture JSON dosyaları kullanılarak olay simülasyonu yapılmalıdır.
- **Web Chat HMAC Doğrulaması:** Site içi sohbet bileşeninde gelen her webhook isteğinin imzası, mağazaya özel `webhook_secret` kullanılarak SHA-256 HMAC algoritmasıyla doğrulanmalı; imzasız veya geçersiz istekler fail-closed olarak bloklanmalıdır.
- **Meta ve Google GBP Güvenliği:** Yorum ve mesajlara otomatik yanıt veren bot ayarları, entegrasyon bağı koparıldığında (unbind) anında kesilmeli ve sessizce sonlanmalıdır (fail-closed).
