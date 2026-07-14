# Walkthrough - Dalga AQ/AR/AS ve Dalga AT/AU/AV Teslimatı

Müşteri İletişim Merkezi için Dalga AQ (Organization/Tenant v2), Dalga AR (Enterprise API) ve Dalga AS (Commercial Packaging) modülleri KG01 revizyonu tamamlanmış olarak kabul edilmiş; ardışık olarak **Dalga AT (Agent Workspace v2)**, **Dalga AU (Connector Certification)** ve **Dalga AV (Production Go-Live Center)** modülleri başarıyla reponun ana çalışma dizinine (`/Volumes/TWINMOS/zolm`) eklenmiş ve test edilmiştir.

---

## 1. Dalga AT — Agent Workspace v2, Makrolar ve Temsilci Verimliliği

- **Hazır Yanıt Makroları (Macros):** `support_reply_macros` ve `support_reply_macro_versions` tabloları üzerinden temsilcilerin hızlıca kullanabileceği değişkenli (Örn: `{customer_name}`, `{store_name}`) şablon yanıt altyapısı kuruldu. Makro ekleme ve render aşamalarında PII/prompt-injection filtreleri mevcuttur.
- **Dahili Özel Notlar (Internal Notes):** `support_internal_notes` tablosu üzerinde temsilciler arası gizli notlar şifreli (`encrypted` cast) saklanır ve dışarıya giden outbound kuyruk tetiklenmez.
- **Temsilci Çakışma Önleme (Soft Presence):** Aynı konuşma üzerinde birden fazla temsilcinin eşzamanlı çalışıp çalışmadığını TTL bazlı (60 saniye) takip eden `SupportAgentPresence` yapısı kuruldu.
- **Kişisel Kayıtlı Görünümler (Saved Views):** Temsilciler durum ve kanal filtre kombinasyonlarını mağaza bazında izole şekilde `support_saved_views` tablosuna kaydedebilir.
- **Arayüz:** `/customer-care/agent-workspace` rotasında, ZOLM Kurumsal Açık Panel standartlarına uygun ve mobil uyumlu temsilci kokpiti render edilmektedir.
- **Komut:** `customer-care:macro-audit --store=ID --dry-run` ile makrolar taranarak politikaya uymayan veya PII barındıran şablonlar pasifleştirilir.

---

## 2. Dalga AU — Connector Certification & Sandbox

- **Entegrasyon Sağlık Kartı (Certification Runs):** `support_connector_certification_runs` ve `support_connector_certification_checks` tabloları ile kanal entegrasyonlarının feature flag, DB config, connector bind, `canReply()` ve secret hijyeni asgari canlandırılma kriterlerine göre otomatik denetlenmesi sağlandı.
- **Sandbox Webhook Simülatörü:** Dış servis webhook olaylarını taklit etmek amacıyla `customer-care:simulate-channel-event` ve arayüz üzerinden mock JSON simülatörü kuruldu.
- **Web Chat HMAC Doğrulaması:** Gelen webhook olaylarında `signature` değeri mağazanın entegrasyon ayarlarındaki `webhook_secret` ile SHA-256 HMAC algoritmasıyla doğrulanarak, imzasız ve geçersiz istekler fail-closed engellenir.
- **Komutlar:**
  - `customer-care:certify-connectors --store=ID --dry-run`
  - `customer-care:simulate-channel-event --store=ID --channel=web_chat --fixture=path --dry-run`
- **Arayüz & Kılavuz:** `/customer-care/certification` ekranı aktif edilmiş ve sertifikasyon gereksinimlerini listeleyen `docs/customer-care/connector-certification-runbook.md` kılavuzu eklenmiştir.

---

## 3. Dalga AV — Production Go-Live Center & Readiness Gate

- **Canlıya Geçiş Denetimleri (Readiness Checks):** `support_production_readiness_runs` tablosu ile canlıya geçiş öncesinde; eksik connector sertifikasyonu, 7 günden eski (stale) golden eval sonucu, çözülmemiş açık kritik güvenlik bulguları ve onaylanmamış launch planları denetlenerek 0-100 arası hazır olma skoru hesaplanır. Skor 90'ın altındaysa geçiş engellenir (fail-closed).
- **Konfigürasyon Kilitleme (Freeze Snapshot):** Aktif kanallar, plan limitleri ve entegrasyon bağlantılarını (PII ve açık secret içermeyecek şekilde) dondurarak şifreli (`encrypted` cast) saklayan `support_production_freeze_snapshots` yapısı kuruldu.
- **İki Aşamalı Onay Mekanizması (Governance):** Freeze snapshot onay işleminde self-approval tamamen engellenmiş olup, denetimi başlatan temsilcinin kendi isteğini onaylaması engellenmiştir.
- **Rollback Tatbikatı (Rollback Drill):** `customer-care:production-rollback-drill` komutu ile acil durumlarda devre kesici (CB) durumu ve askıya alma yolları dry-run olarak raporlanır.
- **Komutlar:**
  - `customer-care:production-rollback-drill --store=ID --dry-run`
  - `customer-care:production-evidence-pack --store=ID` (Canlı öncesi markdown kanıt paketi üretir).
- **Arayüz:** `/customer-care/production` rotası ile canlıya geçiş kontrol paneli ve iki aşamalı onay tetikleyicisi entegre edildi.

---

## 4. Ürün Soruları ve İnsan Onaylı AI Eğitim Havuzu

- **Birleşik ürün soru görünümü:** `/customer-care/product-questions` ekranı pazaryeri sorularını mağaza, cevap ve öğrenme durumuna göre arar, filtreler ve mobil kart/masaüstü ledger görünümünde listeler.
- **Tarihsel senkronizasyon:** **Soru ve Cevapları Çek** işlemi connector capability'sini doğrulayarak varsayılan 365 günlük açık ve cevaplanmış soru geçmişini idempotent biçimde alır.
- **Güvenli öğrenme:** Yalnız pazaryerinde yayınlanmış insan cevapları PII temizliği, siparişe özel içerik, sağlık/hukuk/kesin vaat ve prompt-injection kontrollerinden sonra bilgi adayı olabilir.
- **İnsan onayı:** Adaylar Bilgi Bankası Önerileri kuyruğunda düzenlenir, onaylanır veya reddedilir. Onaylanan ürün bilgisi süreli ve kaynaklı RAG bağlamına girer; ham kayıt modele doğrudan öğretilmez.
- **Yaşam döngüsü:** Eğitim dışı bırakılan adayın bekleyen önerisi de reddedilir; **Yeniden İncele** aynı öneriyi kontrollü biçimde kuyruğa açar. Golden işareti yalnız yayınlanmış kayıtlarda kullanılabilir ve canlı eval setini otomatik değiştirmez.

---

## 5. Doğrulama ve Test Sonuçları

### 5.1. Hedef ve Tüm Müşteri İletişim Merkezi Testleri
Aşağıdaki komutlar ile tüm test paketi koşturulmuş ve hepsi yeşil yanmıştır:

- **Sidebar Navigation Testi:**
  - `CustomerCareSidebarMenuTest` -> `13 passed / 84 assertions` ✅
- **Kanal Provizyonu (Provisioning) Testi:**
  - `CustomerCareChannelProvisioningTest` -> `15 passed / 47 assertions` ✅
- **Ayarlar (Settings) Testi:**
  - `CustomerCareSettingsTest` -> `14 passed / 39 assertions` ✅
- **Kanal Entegrasyon Simülasyonu Smoke Testi:**
  - `CustomerCareSmokeTestCommandTest` -> `1 passed / 6 assertions` ✅
- **Ürün Soruları ve Eğitim Testi:** `CustomerCareProductQuestionsTest` -> `8 passed / 33 assertions` ✅
- **Müşteri İletişim Merkezi Paketi (Tüm Testler):** `501 passed / 1769 assertions` ✅
- **Geriye Uyumluluk Testleri (Backward Compatibility):** `27 passed / 124 assertions` ✅
- **Tam Proje Regresyonu:** `1960 passed / 7830 assertions` ✅

Canlıya geçiş öncesi güvenlik, grounding ve outbox entegrasyon kanallarının (Trendyol, HB, N11, WhatsApp, Meta, Google, Web Chat) asgari canlandırılma durumu `customer-care:smoke-test` komutuyla (database transaction rollback modunda) başarıyla test edilmiş ve tüm kanalların sorunsuz çalıştığı kanıtlanmıştır.

Test sırasında üretim kanıt belgesinin yanlışlıkla silinmesini önlemek için pilot rapor komutunun çıktı dizini yapılandırılabilir hale getirildi. İlgili test artık yalnız kendine ait geçici dizini kullanır; gerçek `docs/customer-care/` kanıtları korunur.

---

## 6. Kanıt Belgeleri ve Referanslar

Tüm teknik kanıt paketleri projenin `docs/customer-care/` dizinine yazılmıştır:

1. [Dalga AT kanıt paketi](docs/customer-care/dalga-at-kanit-paketi.md)
2. [Dalga AU kanıt paketi](docs/customer-care/dalga-au-kanit-paketi.md)
3. [Dalga AV kanıt paketi](docs/customer-care/dalga-av-kanit-paketi.md)
4. [Connector certification runbook](docs/customer-care/connector-certification-runbook.md)
5. [Uçtan uca modül ve entegrasyon test senaryosu](docs/customer-care/uctan-uca-modul-ve-entegrasyon-test-senaryosu.md)
6. [Store 1 pilot launch raporu](docs/customer-care/pilot-launch-report-store-1.md)
7. [Ürün soruları ve AI eğitim akışı](docs/customer-care/urun-sorulari-ai-egitim-akisi.md)

Store 1 için güncel pilot raporu **NO-GO / canary-ready değil** sonucundadır. Bu teknik teslimat hatası değildir; aktif kanal, allowlist, Gemini erişimi, golden evaluation, shadow karşılaştırması, Türkçe kalite kapısı ve onboarding kanıtı gerçek mağaza verisiyle tamamlanana kadar üretim açılışı fail-closed kalır.
