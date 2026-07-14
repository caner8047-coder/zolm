# ZOLM AI Müşteri İletişim Merkezi — Dalga F Kabul Kararı

Tarih: 2026-07-11  
Karar: **Dalga F kabul edildi**

## Baş mühendis özeti

Dalga F Kalite Kapısı 01 revizyonunda önceki P0 kabul engeli giderildi. `CustomerCareAutomationGate`, gerçek otomatik gönderim yolu olan `SupportReplyService::sendAiReply()` içine fail-closed şekilde bağlandı.

Artık AI otomatik yanıtı dış kanala çıkmadan önce şu kapılardan geçmek zorunda:

- Customer Care master switch
- `auto_reply_enabled`
- pilot store allowlist
- confidence threshold
- golden dataset eval gate
- human ownership lock
- conversation automation mode
- conversation lifecycle
- channel enabled kontrolü

Confidence skoru eksikse sistem fail-closed davranıyor ve hiçbir outbound AI mesajı oluşturmuyor. Bu, kontrollü pilot için gerekli ana güvenlik çizgisini karşılıyor.

## Kabul edilen ana düzeltmeler

### 1. Otomatik gönderim yolu gate arkasına alındı

Dosya:

```text
app/Services/Support/SupportReplyService.php
```

`sendAiReply()` imzası confidence skorunu alacak şekilde güncellendi:

```php
sendAiReply(SupportConversation $conversation, string $message, ?int $confidenceScore = null)
```

`$confidenceScore === null` durumunda kayıt oluşturmadan fail-closed dönüyor.

Gate reddederse:

- `support_messages` kaydı oluşmuyor.
- Outbox/dispatch akışı başlamıyor.
- Red nedeni response mesajında görülüyor.

### 2. Pilot allowlist explicit config oldu

Dosyalar:

```text
.env.example
config/customer-care.php
```

Eklenen env:

```text
CUSTOMER_CARE_PILOT_STORE_ALLOWLIST=
```

Varsayılan boş değer fail-closed davranıyor.

### 3. PII maskeleme merkezi servise taşındı

Yeni servis:

```text
app/Services/Support/Security/PiiRedactor.php
```

`PilotDashboard::maskPii()` artık bu servisi kullanıyor. E-posta, telefon ve T.C. Kimlik No maskeleme davranışı korunuyor.

### 4. Gerçek gönderim yolu entegrasyon testleri eklendi

Dosya:

```text
tests/Feature/CustomerCare/CustomerCarePilotGateTest.php
```

Yeni test kapsamı:

- Store allowlist dışında `sendAiReply()` engelleniyor.
- Golden eval başarısızsa `sendAiReply()` engelleniyor.
- Confidence düşükse `sendAiReply()` engelleniyor.
- Confidence eksikse `sendAiReply()` fail-closed dönüyor.
- Tüm kapılar geçilirse AI outbound gönderim başarılı çalışıyor.

## Bağımsız doğrulama kanıtları

### Hedef paket

Komut:

```text
git diff --check
npm run build
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
```

Sonuç:

```text
git diff --check: temiz
npm run build: başarılı
Tests: 86 passed (296 assertions)
Duration: 2.72s
```

### Full suite

Komut:

```text
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuç:

```text
Tests: 1522 passed (6282 assertions)
Duration: 79.28s
```

## Kalan P2 notları

Bunlar Dalga F kabulünü engellemez; sonraki sertleştirme dalgasında değerlendirilebilir:

1. Gate başarısızlığı testlerinde `support_messages` yokluğu kanıtlanıyor. `support_dispatches` yokluğu da ayrıca açık assertion olarak eklenirse kanıt paketi daha keskin olur.
2. `CUSTOMER_CARE_PILOT_STORE_ALLOWLIST` parse işlemi şu an comma-separated string'i array'e çeviriyor. Trim + integer cast ile daha net hale getirilebilir.
3. `PiiRedactor` MVP için yeterli; pilot dashboard dışı export/ledger yüzeyleri büyürse IBAN, adres ve farklı telefon formatları için kapsam genişletilmeli.

## Son karar

Dalga F, kontrollü pilot ve otomasyon kapısı hedefini artık karşılıyor.

**Dalga F kabul edildi. Dalga G'ye geçilebilir.**
