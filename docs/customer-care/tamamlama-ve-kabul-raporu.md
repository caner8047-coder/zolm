# ZOLM AI Müşteri İletişim Merkezi — Tamamlama ve Kabul Raporu

**Tarih:** 14 Temmuz 2026  
**Kapsam:** `ZCC-001`–`ZCC-018`  
**Teknik sonuç:** KABUL — UYGULAMA, MIGRATION VE GÜNCEL TAM REGRESYON TAMAMLANDI  
**Customer Care regresyonu:** 501 test, 1.769 assertion, 0 hata  
**Güncel tam proje regresyonu:** 1.960 test, 7.830 assertion, 0 hata  
**Veritabanı doğrulaması:** Customer Care migration'larının tamamı yerel MySQL 8 üzerinde uygulandı; durumları `Ran`  
**Not:** Canlı mağaza aktivasyonu aşağıdaki credential, connector, kalite, yönetişim ve hukuk kapılarına ayrıca bağlıdır.

Manuel UAT, sandbox connector ve uçtan uca kabul koşuları için `docs/customer-care/uctan-uca-modul-ve-entegrasyon-test-senaryosu.md` kullanılmalıdır.

## Karar

Ürün şartnamesindeki 18 gereksinimin uygulama, veri modeli, güvenlik kapısı, operasyon yüzeyi ve otomatik test karşılıkları tamamlanmıştır. Otomatik cevap özelliği güvenli varsayılan olarak kapalıdır; mağaza bazlı canlıya geçiş ancak gerçek credential, connector sertifikasyonu, Türkçe kalite, shadow/canary ve yönetişim kapıları geçildiğinde yapılabilir.

“Teknik olarak tamamlandı” aşağıdaki dış gerçeklerin tamamlandığı anlamına gelmez:

- Meta/Google/WhatsApp ve pazaryeri üretim hesaplarının credential veya app-review süreci,
- mağazaya özel gerçek veriyle connector sertifikasyonu,
- KVKK pazarlama ifadesi için hukuk/DPO onayı,
- `45+ dil` gibi bir pazarlama iddiası için dil başına kalite kapısı.

Kod bu dış bağımlılıkları tamamlanmış varsaymaz; eksik olduklarında fail-closed kalır.

## Son üretim sertleştirmeleri

- Golden kalite kararı tek fail-closed serviste birleştirildi; en güncel tamamlanmış koşu, gerçek vaka satırları, minimum örneklem, skor bütünlüğü, kaynak doğruluğu ve sıfır kritik hata birlikte doğrulanır.
- Otomatik gönderim; gerçek queue kanıtı, açık reliability merkezi ve `closed` circuit-breaker olmadan çalışmaz. Bilinmeyen/ölçülmemiş durum sağlıklı kabul edilmez.
- Prompt injection son kullanıcı girdisi sağlayıcıya gönderilmeden durdurulur ve başarısız AI ledger kaydıyla insan devrine alınır.
- Analytics ve operasyon yüzeyleri örneklem yokken sıfır başarı/maliyet/latency uydurmaz; `Ölçüm yok` ve gerçek örnek sayısı gösterir.
- CRM/ERP ve webhook çıkışları HTTPS, DNS çözümleme, public-IP doğrulama, DNS pinning, redirect kapatma ve idempotency kurallarıyla SSRF/rebinding saldırılarına karşı sertleştirildi.
- Master kill-switch; enterprise API, inbound webhook, widget, integration outbox ve zamanlanmış Customer Care işlerinde bağlayıcıdır.
- MySQL migration'ları foreign-key taşıyan indeks değişimleri, 64 karakterlik constraint adı sınırı ve yarıda kalmış DDL sonrası tekrar çalıştırma açısından sertleştirildi.
- Müşteri bazlı DSR silme; konuşma/mesaj/AI ham verisine ek olarak şifreli attachment, web lead, widget metadata, WhatsApp kişi kaydı, consent ve yalnız güvenle ilişkilendirilebilen CRM kimliğini kapsar.
- DSR export’u paketlenmiş yönetişim onayına bağlı, satır kilitli ve tek kullanımlı hale getirildi; konuşma, web lead, widget, consent ve WhatsApp verilerini XML-safe/UTF-8 biçimde üretir.
- Deney servisi gerçek vaka ölçümü olmadan `0 ihlal`, `marka sesi geçti` veya kazanan varyant yazmaz. Sonuçlar artifact/varyant kimliğine bağlı, güncel eval kanıtı gerektirir.
- Release preflight artık sabit policy başarısı üretmez; cevap şablonlarını gerçek kanal validatorlarıyla denetler. Yayın ve rollback iki kişili onaya, gerçek onaylayana ve paketle bağlı artifact sürümüne dayanır.
- Daha yeni artifact sürümü aktifse eski paketin rollback’i durdurulur; başka yayının sürümü yanlışlıkla geri alınmaz.
- Connector `--dry-run` gerçek adapter, credential, `canReply` ve health kontrollerini çalıştırır; kalıcı sertifikasyon/health kaydı oluşturmaz ve sahte `OK` göstermez.
- Golden eval yazımı tenant/RBAC aktörüne bağlandı; manuel eval seed üretim ortamında kapatıldı.
- Entitlement ve billing servislerinde aktörsüz tenant bypass’ı kapatıldı; billing dönemi doğrulandı ve CSV formül enjeksiyonu engellendi.
- Pazaryeri ürün soru-cevapları mağaza bazlı Customer Care konuşmalarına yansıtıldı; PII, siparişe özel içerik, yüksek risk ve prompt-injection kapılarından geçmeyen kayıtlar eğitim havuzuna alınmıyor. Adayı hariç tutma ve yeniden inceleme işlemleri bilgi önerisinin durumuyla atomik biçimde eşitleniyor.

## 18 gereksinim kabul matrisi

| Gereksinim | Durum | Başlıca uygulama kanıtı | Otomatik test kanıtı |
|---|---|---|---|
| ZCC-001 Katalog/sipariş | Tamam | `CustomerCareContextBuilder`, `CustomerCareCustomerSummaryService`, canlı Shipment ve güncellik kaynakları | `CustomerCareCustomerSummaryTest`, `CustomerCareKnowledgeGroundingTest` |
| ZCC-002 Kaynak/iddia defteri | Tamam | `SupportAiRun`, `CustomerCareSourceLedgerService`, Inbox kaynak görünümü | `CustomerCareAiOrchestratorTest`, `SupportOutboxTest` |
| ZCC-003 Güven ve insan devri | Tamam | bileşik confidence, `CustomerCareHandoffService`, atomik human lock | `CustomerCareAiOrchestratorTest`, `SupportConversationStateMachineTest` |
| ZCC-004 Üç yanıt modu | Tamam | tenant/store/channel/intent/conversation daraltması, auditli mod değişimi | `CustomerCareSettingsTest`, `CustomerCarePilotGateTest` |
| ZCC-005 Kanal politika motoru | Tamam | sürümlü `SupportPolicyEngine`, WhatsApp pencere/consent ve kanal validatorları | `SupportPolicyEngineTest`, kanal adapter testleri |
| ZCC-006 İnsan onaylı öğrenme | Tamam | kümelenmiş suggestion, draft/onay/düzenleme/ret, suppression, pazaryeri ürün soru-cevap havuzu ve sürümlü yayın | `KnowledgeSuggestionsTest`, `KnowledgeAndVoiceTest`, `CustomerCareProductQuestionsTest` |
| ZCC-007 Marka sesi | Tamam | dil bazlı marka profili, response validator ve insan onaylı örnekler | `KnowledgeAndVoiceTest`, `CustomerCareSettingsTest` |
| ZCC-008 Site widget | Tamam | imzalı tenant/domain, CORS/rate limit, ayrı pazarlama izni, şifreli dosya ve handoff | `WebChatWidgetApiTest`, `WebChatSupportChannelAdapterTest` |
| ZCC-009 Birleşik kanallar | Tamam | capability adapterları, idempotent projection/webhook ve Inbox kanal sağlık yüzeyi | `SupportChannelAdapterContractTest`, `CustomerCareInboundWebhookTest` |
| ZCC-010 Satış danışmanlığı | Tamam | doğrulanmış güncel katalog adayları, seçenek limiti, beden/uyumluluk guardı ve attribution | `CustomerCareSalesAssistTest` |
| ZCC-011 Kalite/operasyon analitiği | Tamam | gerçek pay/payda, dönem, min örneklem, maliyet/latency/queue/policy metrikleri | `CustomerCareAnalyticsTest`, `CustomerCareOpsTest`, `CustomerCareQualityTest` |
| ZCC-012 Site asistanı/lead | Tamam | idempotent lead, source/campaign/izin/şifreli özet ve CRM aktarım sözleşmesi | `WebChatWidgetApiTest`, `CustomerCareExternalConnectorTest` |
| ZCC-013 İş sonucu ölçümü | Tamam | intent tekrarı, mesai içi/dışı yanıt, çözüm tipi ve güvenilir satış attributionı | `CustomerCareAnalyticsTest`, `CustomerCareSalesAssistTest` |
| ZCC-014 Onboarding | Tamam | gerçek health/capability, katalog-sipariş-soru dry-run, ilk doğrulanmış taslak süresi ve destek paketi | `CustomerCareOnboardingTest` |
| ZCC-015 CRM/ERP/iç sistem | Tamam | adapter contractı, HTTPS/SSRF guardı, imza/replay/idempotency, inbound receipt, asenkron outbox/DLQ ve auditli public API | `CustomerCareIntegrationHubTest`, `CustomerCareExternalConnectorTest`, `CustomerCareEnterpriseApiTest` |
| ZCC-016 Güvenlik/KVKK/RBAC | Tamam | TLS/HSTS, şifreli PII/credential, tenant scope, rol matrisi, consent/DSR/retention/anonymization/audit | `CustomerCareSecurityTest`, `CustomerCareComplianceTest`, `CustomerCareTenantSecurityTest`, `CustomerCareAnonymizationTest` |
| ZCC-017 Yanlış cevap/düzeltme | Tamam | retract capability, düzeltme mesajı/görevi, kritik kill switch, insan onaylı regression kaydı | `CustomerCareCorrectionLifecycleTest`, `CanaryCircuitBreakerTest` |
| ZCC-018 Türkçe/çok dil | Tamam | dil tespiti, dil seçimi, Türkçe typo/argo/kısaltma golden seti, dil bazlı kaynak/kritik hata kapısı ve fallback | `SupportAiEvalLedgerTest`, `CustomerCareQualityTest`, `CustomerCarePilotReadinessTest` |

## Güvenilir gönderim kararı

Manuel ve AI outbound cevaplar HTTP/Livewire isteği içinde haricî kanala senkron gönderilmez. Önce kalıcı, idempotent `support_dispatches` outbox kaydı oluşturulur. Worker atomik claim, policy/automation gate'i yeniden doğrulama, retry/backoff ve terminal `exhausted` davranışıyla gönderir. Teslim kabul edilmeden mesaj `sent` sayılmaz; teslim sonucu ayrıca audit edilir.

Kuyruğa alınmış AI mesajları saatlik limitte rezervasyon olarak sayılır. Bu, worker çalışmadan önce çok sayıda mesajın kuyruğa sokulup limiti aşmasını engeller.

## Veri güvenliği kararı

- Konuşma müşteri kimliği, mesaj içeriği, AI ham bağlamı, widget lead/özetleri, compliance müşteri kimlikleri ve connector secretları şifreli veya geri döndürülemez hash ile korunur.
- Ham inbound webhook gövdesi mesaj kayıtlarına taşınmaz; receipt yalnız olay kimliği/hash ve normalize edilmiş sonuç tutar.
- Customer Care HTTP trafiği üretimde TLS'e zorlanır; güvenli yanıtlarda HSTS bulunur.
- Credential değişiklikleri RBAC `secret_rotate` yetkisi ve secretsız fingerprint audit kaydı gerektirir.
- Anonimleştirme web lead, CRM identity, widget metadata ve şifreli attachment dosyalarını da kapsar; legal hold varsa silme engellenir.

## Canlıya geçiş kapısı

Her üretim mağazası için sırayla:

1. Kanal ve AI credentiallarını secret yönetimiyle tanımla.
2. `customer-care:certify-connectors --store=ID` çalıştır ve bütün zorunlu kontrolleri geçir.
3. Onboarding dry-run ile gerçek katalog güncellik kanıtını ve ilk doğrulanmış taslağı üret.
4. Türkçe golden kalite kapısını, shadow karşılaştırmasını ve production readiness kontrolünü geçir.
5. İlk kapsamı `copilot`/`shadow` aç; sonra düşük riskli intentlerde canary uygula.
6. Hukuk onayı olmadan “KVKK ile tam uyumlu”, dil başı kapı olmadan “45+ dil” iddiası yayımlama.

## Doğrulama komutları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage
./vendor/bin/sail artisan test tests/Feature/MarketplaceQuestionsTest.php \
  tests/Feature/MarketplaceReportDigestTest.php \
  tests/Feature/WhatsApp/SupportChannelTest.php --no-coverage
./vendor/bin/sail artisan test --no-coverage
./vendor/bin/sail artisan migrate --pretend --no-interaction
./vendor/bin/sail artisan route:list --path=customer-care
./vendor/bin/sail artisan schedule:list
```

14 Temmuz 2026 doğrulama sonucu:

- Customer Care: `501 passed (1769 assertions)`
- Tam proje: `1960 passed (7830 assertions)`
- Customer Care route kaydı: `41`
- Customer Care zamanlanmış iş kaydı: `5`
- Blade compile/cache: başarılı
- PHP syntax ve `git diff --check`: başarılı
- Yerel MySQL migration durumu: bekleyen Customer Care migration'ı yok

Tam proje koşusunda Customer Care kapsamı dışında kalan bazı testlerde PHPUnit 12'de kaldırılacak doc-comment metadata kullanımı için deprecation uyarıları bulunmaktadır. Bunlar mevcut PHPUnit sürümünde test başarısını veya Customer Care kabulünü etkilemez; proje genelinde ayrı teknik borç olarak izlenmelidir.

Nihai teknik kabul koşulu sağlanmıştır. Canlı aktivasyon ayrıca mağaza bazlı dış bağımlılık ve ölçüm kapılarına bağlıdır.
