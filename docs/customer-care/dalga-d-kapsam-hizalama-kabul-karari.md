# ZOLM AI Müşteri İletişim Merkezi — Dalga D Kapsam Hizalama Kabul Kararı

Tarih: 2026-07-11  
Karar sahibi: Codex baş mühendis kontrolü  
Kapsam: Dalga D sonrası kapsam hizalama, pilot route güvenliği ve test izolasyonu

## Karar

**Dalga D sonrası kapsam hizalama kabul edildi.**

Bu kabul; Dalga D sonrasında repo içine sızan Dalga E/F parçalarının minimum güvenlik hizalamasını, pilot dashboard route ayrımını ve test izolasyonu düzeltmesini kapsar.

Bu karar **Dalga E/F ürün kabulü**, **pilotun canlı müşteriye açılması** veya **automatic reply production onayı** değildir.

## Bağımsız doğrulama kanıtları

- Hedef test paketi:
  - `tests/Feature/CustomerCare`
  - `tests/Feature/WhatsApp/SupportChannelTest.php`
  - `tests/Feature/MarketplaceQuestionsTest.php`
  - `tests/Feature/MarketplaceReportDigestTest.php`
- Hedef test sonucu: **82 passed, 315 assertions**.
- Full test suite sonucu: **1514 passed, 6252 assertions**.
- `git diff --check`: temiz.
- `npm run build`: başarılı.
- Customer Care migration'ları `Ran` durumunda.
- Route listesinde iki route doğrulandı:
  - `customer-care`
  - `customer-care/pilot`

## Kabul edilen düzeltmeler

1. `/customer-care/pilot` route'u artık `pilot_dashboard_enabled` bayrağıyla ayrı korunuyor.
2. `CUSTOMER_CARE_PILOT_DASHBOARD_ENABLED=false` güvenli varsayılan olarak `.env.example` içine eklendi.
3. `config/customer-care.php` içine `pilot_dashboard_enabled` default kapalı olarak eklendi.
4. `PilotDashboard` kullanıcıya ait mağazalarla başlıyor ve seçilen mağaza için `TenantContext::enforceStoreAccess()` kontrolü yapıyor.
5. AI runs sorgusu `store_id = selectedStoreId` ile sınırlandı.
6. Aktif AI taslak sorgusu `conversation.store_id = selectedStoreId` ile sınırlandı.
7. Yetkisiz store seçimi Livewire negatif testle `assertForbidden()` olarak doğrulandı.
8. Global `tests/TestCase.php` system actor provision yan etkisi kaldırıldı.
9. Customer Care testleri için `CustomerCareTestHelper` trait'i oluşturuldu.
10. Dalga raporları ayrı dosyalara bölündü:
    - `dalga-d-kanit-paketi.md`
    - `dalga-e-kanit-paketi.md`
    - `dalga-f-kanit-paketi.md`

## Not edilen kapsam dışı değişiklik

`tests/Feature/MarketplaceReportDigestTest.php` içinde test verisi temizliği eklenmiş. Bu değişiklik Customer Care dışındadır; ancak full test suite geçişiyle mevcut durumda regresyon yaratmadığı doğrulanmıştır.

Bu dosya, ilgili testteki veri izolasyonu amacıyla kabul edilebilir. Yine de ileride commit öncesi diff review sırasında “neden Customer Care dalgasında değişti?” sorusuna bu gerekçe eklenmelidir.

## Dalga E kalite kapısına taşınacak notlar

1. `PilotDashboard` ham `prompt_raw` ve `response_raw` verilerini gösteriyor. Pilot içi debug için kabul edilebilir; fakat production veya daha geniş kullanıcı erişimi öncesinde PII maskeleme/redaksiyon kararı gerekir.
2. Admin kullanıcının tüm mağazaları görebilmesi mevcut `TenantContext` kararıyla uyumludur; ancak organizasyon/rol modeli netleştiğinde policy testleri genişletilmelidir.
3. Dalga E incelemesi artık `CustomerCareAiOrchestrator`, `CustomerCareContextBuilder`, source-grounded draft kalitesi ve hallucination guard seviyesine odaklanmalıdır.

## Sonuç

Dalga D sonrası kapsam hizalama kalite kapısı **geçti**.

Sıradaki doğru adım: **Dalga E — Copilot AI Motoru ve Kaynaklı Taslak Sistemi kalite incelemesi**.

