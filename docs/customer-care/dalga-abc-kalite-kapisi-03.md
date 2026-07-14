# ZOLM AI Müşteri İletişim Merkezi — Dalga A/B/C Kalite Kapısı 03

## Karar

**YELLOW — ALTYAPI REVİZYONU KISMİ KABUL, PİLOT HAZIR DEĞİL**

Dalga A/B/C Kalite Kapısı 02 sonrası yapılan düzeltmeler kod seviyesinde önemli ilerleme sağlamıştır. WhatsApp adapter store-bound hale getirilmiş, outbox claim koşulları güçlendirilmiş, worker bütünlük kontrolleri eklenmiş, AI otomatik yanıt matrisi yazılmış ve Customer Care test paketi genişlemiştir.

Ancak Antigravity'nin "tüm P0 ve P1 eksiksiz tamamlandı" iddiası doğru değildir. Kalan P1/Pilot blokerleri nedeniyle gerçek müşteri mesajı gönderimi ve automatic mode açılmamalıdır.

## Bağımsız Doğrulama

```text
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php --no-coverage

Tests: 1 risky, 46 passed (162 assertions)
```

```text
git diff --check
# CLEAN
```

```text
./vendor/bin/sail artisan route:list --name=customer-care --env=testing
# GET|HEAD customer-care customer-care.home
```

```text
./vendor/bin/sail artisan schedule:list
# support-process-outbox her dakika listeleniyor
```

## Kabul Edilen İyileştirmeler

1. WhatsApp adapter `^wa_(\d+)$` parse ve `store_id` sınırıyla çalışıyor.
2. WhatsApp cross-tenant negatif testi eklendi ve geçiyor.
3. Outbox claim update artık `attempt_count` ve `retry_at` uygunluğunu claim anında tekrar kontrol ediyor.
4. Final state (`sent`, `accepted`, `cancelled`, `exhausted`) yeniden gönderim engeli eklendi.
5. Worker dispatch bütünlük kontrolü eklendi.
6. AI automatic reply için `auto_reply_enabled`, `ai_mode`, `ownership_status` ve lifecycle kontrolleri eklendi.
7. UI metni "hazır" iddiasından "hazırlık/doğrulama devam ediyor" seviyesine çekildi.
8. ADR-007 ile AI shadow/golden/ledger yönü belgelendi.

## Kalan Blokerler

### 1. Teslim raporu yanlış dosyada

Antigravity raporu `walkthrough.md` dosyasına yazdığını belirtti; repo kökündeki `walkthrough.md` hâlâ eski "Kurulum ve Çalıştırma Rehberi" içeriğidir. Dosya-test eşleşmesi, migration/rollback, build ve diff kanıtları bu dosyada yoktur.

Bu bir kod blokeri değildir; fakat teslimat kanıt paketi güvenilir değildir.

### 2. Knowledge/Brand Voice validasyon ve audit tamamlanmadı

`KnowledgeBaseService` ve `BrandVoiceService` actor fallback eklemiş; fakat uzunluk limiti, alan validasyonu, prompt-injection sınırı, değişiklik audit'i ve kaynak sürümü hâlâ yoktur.

Bu alanlar automatic mode veya müşteri-facing cevap kaynakları için pilot hazır değildir.

### 3. Actor fallback tasarımı net değil

Auth/user yoksa store sahibi otomatik aktör seçiliyor. Bu worker akışını çalıştırır; fakat servis sınırında "çağıran kim" bilgisini bulanıklaştırır. Explicit system actor veya job payload actor kararı ADR/service contract ile netleşmelidir.

### 4. Projection yaşam döngüsü hâlâ manuel

`SupportProjectionService` idempotent başlangıç sunuyor; ancak event/job/backfill/cursor/recovery veya bunun pilot dışı bırakıldığına dair açık karar yoktur.

### 5. Dispatch audit retention hâlâ ADR ile çelişiyor

`support_dispatch_attempts` append-only audit hedefiyle tanımlanıyor; fakat migration'da `support_dispatch_attempts.support_dispatch_id` cascade delete ile siliniyor. KVKK/retention kararı netleşmeden pilot kabulü verilmemeli.

### 6. AI ledger/golden yalnız ADR seviyesinde

ADR-007 doğru yönde; ancak `support_ai_runs` migration/model/job yok. Bu aşamada automatic mode kapalı kalmalıdır. AI tarafı yalnız copilot/shadow mimarisine hazırlanmış kabul edilir.

## Sonuç

- Dalga A/B/C kod revizyonu, **altyapı prototipi ve güvenlik sertleştirme ilerlemesi** olarak kabul edilebilir.
- **Pilot kabulü verilmedi.**
- `CUSTOMER_CARE_AUTO_REPLY_ENABLED=false` kalmalıdır.
- Gerçek müşteri mesajı gönderen otomatik akış açılmamalıdır.
- Bir sonraki çalışma, yeni özellik eklemek yerine kalan P1/pilot blokerlerini kapatmalıdır.

## Antigravity İçin Kısa Revizyon Talimatı

Şunları tamamlayıp yeniden kanıt paketi ver:

1. Gerçek revizyon raporunu `docs/customer-care/dalga-abc-revizyon-02-kanit-paketi.md` olarak yaz.
2. Knowledge/Brand Voice validasyon, audit ve prompt güvenliği sınırını uygula veya pilot dışı ADR kararı yaz.
3. Actor fallback yerine explicit system actor/job actor sözleşmesini netleştir.
4. Projection event/job/backfill/cursor kapsamını uygula veya pilot dışı kararını yaz.
5. `support_dispatch_attempts` audit retention kararını migration/ADR ile düzelt.
6. AI `support_ai_runs` için en az migration/model ya da net faz dışı kararı ver.
7. Testleri tekrar çalıştır: CustomerCare, WhatsApp SupportChannel ve ilgili MarketplaceQuestion regresyonları.

Commit, push ve branch değişikliği yapılmayacaktır.
