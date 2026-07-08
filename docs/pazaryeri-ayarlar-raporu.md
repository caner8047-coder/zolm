# ZOLM Pazaryeri Modülü — Ayarlar Raporu

**Tarih:** 7 Temmuz 2026  
**Kapsam:** Pazaryeri modülünün baştan sona taranması, mevcut ayarların listelenmesi ve eksik/kullanılabilir ayar önerileri  
**Amaç:** Kullanıcının uygulamayı kendi firmasına ve kendi isteklerine göre özelleştirebilmesi

---

## BÖLÜM 1: MEVCUT DURUM ÖZETİ

### 1.1 Depolama Mimarisi

ZOLM'de ayarlar tek bir tabloda değil, **7 ayrı model + 8 statik config dosyası** üzerinden dağıtılmış durumda:

| Model | Tablo | Yapı | Kapsam |
|---|---|---|---|
| `MpAccountingSetting` | `mp_accounting_settings` | user_id + JSON blob | Kullanıcıya özel |
| `MpErpSetting` | `mp_erp_settings` | user_id + sütunlar | Kullanıcıya özel |
| `RecipeSetting` | `recipe_settings` | user_id + key/value | Kullanıcıya özel |
| `WaSetting` | `wa_settings` | store_id (nullable) + key/value | Mağaza bazlı veya global |
| `WaAutomationConfig` | `wa_automation_configs` | store_id + key/value | Mağaza bazlı |
| `ReturnBridgeSetting` | `return_bridge_settings` | Tek satır, sütunlar | Sistem geneli |
| `LegalEntitySetting` | `legal_entity_settings` | legal_entity_id + JSON | Tüzel kişilik bazlı |

**Config dosyaları:** `config/marketplace.php`, `config/cargo.php`, `config/whatsapp.php`, `config/ai.php`, `config/returns.php`, `config/version.php`

**Servis katmanı:** `MpSettingsService` — dot-notation erişim, 1 saat cache, typed getter'lar, 300+ satırlık varsayılan değer dizisi.

### 1.2 Mevcut Ayarlar Sayfası (Pazaryeri Ayarları)

**Dosya:** `MarketplaceSettings.php` + `marketplace-settings.blade.php`

Şu anda **2 ana bölüm** var:

**Bölüm 1 — Genel (Arayüz ve Ürün Hesabı):**
- Yardım ipuçları aç/kapalı
- Varsayılan kâr hesaplama pazaryeri (mağaza ortalaması / en düşük / spesifik pazaryeri)
- WooCommerce komisyon oranı (%)
- Reçete maliyetini stok kartına işleme toggle'ı

**Bölüm 2 — Çıktı (Kargo Etiketi ve İrsaliye):**
- Kargo etiketi: şablon, kağıt tipi, barkod yüksekliği, 7 alan toggle'ı, dip notu
- Sevk irsaliyesi: şablon, kağıt tipi, barkod yüksekliği, 8 alan toggle'ı, dip notu
- Gönderici fallback bilgisi (firma adı, telefon, vergi no, adres)

### 1.3 Muhasebe Ayarları Paneli (Ayrı Panel)

**Dosya:** `mp-settings-panel.blade.php` — 6 accordion bölüm:

| Bölüm | İçerik |
|---|---|
| Firma Profili | Ad, vergi no, vergi dairesi, telefon, email, adres, IBAN, banka, şube, yetkili, MERSİS |
| Kârlılık Hedefleri | Hedef kâr marjı %, minimum kâr eşiği %, varsayılan ambalaj maliyeti |
| Vergi & KDV | Stopaj oranı, ürün KDV, gider KDV, Net KDV toggle, teorik stopaj toggle |
| Kargo & Barem | Pazaryeri seçimi, barem limiti, varsayılan kargo firması, kendi kargo toggle, kargo firması listesi (ekle/çıkar), ağır kargo ceza tablosu |
| Desi Fiyat Tablosu | Kargo firması bazında 9 desi aralığı × fiyat |
| Barem Fiyat Tablosu | Kargo firması bazında 2 barem aralığı × fiyat |
| Denetim Limitleri | 30+ tolerans eşiği, kural aç/kapalı, bilgi logu toggle |

---

## BÖLÜM 2: MEVCUT AYARLAR — EKSİK ALANLAR VE ÖNERİLER

Aşağıda her bir pazaryeri alt modülü için **mevcut ayarların yetmediği, kodda hardcode kalan veya kullanıcının firmasına göre özelleştirmesi gereken** alanlar listelenmektedir.

---

### 2.1 SİPARİŞLER (MarketplaceOrders)

**Mevcut ayar desteği:** Sütun görünürlüğü (`ui.visible_columns`) kaydediliyor.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| S-1 | **Sayfa başına sipariş sayısı** | ✅ DÜZELTİLDİ — `ui.orders_per_page` (varsayılan 20), persistent | Kullanıcı tercihine göre değişir | — |
| S-2 | **Varsayılan tarih aralığı (Siparişler)** | ✅ DÜZELTİLDİ — `ui.orders_default_date_range_days` (varsayılan 0=Tüm zamanlar), persistent; query-string korunuyor | Kullanıcı bazen 7 gün, bazen 90 gün bakmak ister | — |
| S-3 | **Varsayılan sıralama** | 4 preset var ama hangisinin aktif olacağı sabitlenmemiş | Kullanıcı "tarihe göre eski" yerine "tutar büyüklüğüne göre" görmek isteyebilir | `orders.default_sort` |
| S-4 | **Toplu işlem limiti** | Hardcoded 25 (MarketplaceOverview) | Büyük firmalar toplu işlerde daha fazla işlem yapmak ister | `orders.bulk_action_limit` |
| S-5 | **Dosya yükleme boyut limiti** | Hardcoded 51200 KB | Firma büyük Excel dosyaları yükleyebilir | `orders.max_upload_size_kb` |
| S-6 | **Import timeout süresi** | Hardcoded 300 saniye | Büyük veri setleri için uzatılabilir | `orders.import_timeout_seconds` |
| S-7 | **Diyagnostik geriye dönük pencere** | Hardcoded 168 saat | Kullanıcı 1 hafta değil, 1 ay geriye bakmak isteyebilir | `orders.diagnostics_lookback_hours` |
| S-8 | **Varsayılan renk etiketi filtresi** | Renk etiketleri var ama filtre tercihi kaydedilmiyor | Kullanıcı sürekli aynı etikete göre filtreler | `orders.default_color_label` |
| S-9 | **Sipariş durumu filtresi tercihi** | Varsayılan açık durumlar | Kullanıcı bazen "kargoya verildi" filtresiyle açmak isteyebilir | `orders.default_status_filter` |
| S-10 | **Trendyol zaman damgası ofseti** | Hardcoded 10800 saniye (3 saat), store timezone'u override ediyor | Her firma farklı timezone'da olabilir (AB'de satış yapan firmalar) | `orders.trendyol_timestamp_offset_seconds` |

---

### 2.2 ÜRÜNLER (MpProductsManager)

**Mevcut ayar desteği:** Reçete maliyet senkronizasyonu toggle'ı, kâr hesabı pazaryeri seçimi.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| P-1 | **Varsayılan KDV oranı (ürün formu)** | ✅ DÜZELTİLDİ — `resetForm()` artık `MpSettingsService::getDefaultProductVatRate()` kullanıyor | Vergi ayarı ile tutarsızlık giderildi | — |
| P-2 | **Varsayılan parça sayısı** | Hardcoded 1 | Kullanıcı sürekli 3'lü paket satıyorsa bu tercihi kaydetmeli | `products.default_piece_count` |
| P-3 | **Sayfa başına ürün sayısı** | ✅ DÜZELTİLDİ — `ui.products_per_page` (varsayılan 25), persistent | Persistent hale getirildi | — |
| P-4 | **ÖTV oranı görünürluğu** | `otv_rate` alanı modelde var ama UI'da yok | Özel tüketim vergisi olan ürünler için gerekli | `products.show_otv_field` |
| P-5 | **Stok uyarı eşiği** | Ürün kartında stok alertı yok | Düşük stok uyarısı kritik | `products.low_stock_threshold` |
| P-6 | **Toplu fiyat düzeltme oranı limiti** | Yüzde düzeltme için üst sınır yok | Yanlış girilen büyük oranları engeller | `products.max_bulk_price_adjust_pct` |
| P-7 | **Ürün eşleştirme otomatik önerme eşiği** | Hardcoded >=100 puan | Kullanıcı daha muhafazakar veya daha agresif olabilir | `products.auto_match_threshold` |
| P-8 | **Eşleştirme aday puanlama ağırlıkları** | Hardcoded: barkod=120, stok kodu=100, model kodu=90, marka=12, kategori=8, başlık=30 | Her firma farklı eşleme stratejisi ister | `products.match_weights` (nested) |

---

### 2.3 KÂR HESAPLAMA (MarketplaceProfitCenter)

**Mevcut ayar desteği:** Kâr görünümü (mağaza ortalaması/en düşük), WooCommerce komisyon oranı, hedef kâr marjı, minimum kâr eşiği, ambalaj maliyeti.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| K-1 | **Sağlık skoru ağırlıkları** | Hardcoded: finans=%30, snapshot=%25, maliyet=%25, ödeme=%25 | Her firma farklı boyuta öncelik verebilir | `profit.health_score_weights` |
| K-2 | **Aksiyon sayfası boyutu** | Hardcoded 8 | Büyük firmalar daha fazla aksiyon görmek ister | `profit.action_page_size` |
| K-3 | **Kampanya simülasyonu limiti** | Hardcoded limit(100) | Portföy büyüklüğüne göre artırılmalı | `profit.campaign_simulation_limit` |
| K-4 | **Fiyat simülasyonu KDV oranları** | Varsayılan MtSettingsService'ten geliyor ama kampanya simülasyonu hardcoded %20 | Tutarlılık için birleştirilmeli | Zaten mevcut, tutarsızlık düzeltilmeli |
| K-5 | **Kâr hesaplama yöntemi** | Sadece "mağaza ortalaması" ve "en düşük" var | "En yüksek kâr", "medyan", "ağırlıklı ortalama" gibi seçenekler eklenebilir | `profit.calculation_method` |
| K-6 | **Paketleme maliyeti kalemi** | Sadece tek bir ambalaj maliyeti var | Firmalar kutu, dolgu, etiket gibi ayrı maliyet kalemeleri tutmak isteyebilir | `profit.packaging_cost_breakdown` |

---

### 2.4 KARGO (Cargo modülleri)

**Mevcut ayar desteği:** Barem limiti, kargo firmaları listesi, ağır kargo cezaları, desi fiyat tablosu, barem fiyat tablosu, kendi kargo toggle.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| C-1 | **Desi aralıkları dinamik** | 9 sabit aralık var (0-2, 3, 4, 5, 10, 15, 20, 25, 30), aralık eklenemiyor | Firma kendi kargo anlaşmasına göre farklı aralıklar kullanabilir | `cargo.desi_ranges` (dinamik array) |
| C-2 | **Barem aralıkları dinamik** | Sadece 0-150 ve 150-300 var, eklenemiyor | Bazı firmalarda barem 0-200, 200-500 gibi aralıklar kullanılır | `cargo.barem_ranges` (dinamik array) |
| C-3 | **Varsayılan kargo firması (gönderici)** | Sürat ayarlarında Denizli hardcoded | Firma konumu settings'den gelmeli | `cargo.default_origin_city` |
| C-4 | **Kargo desi üst sınırı** | Hardcoded 30 desi | Büyük mobilya firmaları 50+ desi gönderir | `cargo.max_desi_limit` |
| C-5 | **Kargo raporu varsayılan periyodu** | Hardcoded 30 gün | Kullanıcı tercihini kaydetmeli | `cargo.default_report_period` |
| C-6 | **Kargo firması bazlı senkronizasyon ayarları** | Her kargo firması için ayrı senkronizasyon tercihi yok | Firma X kargosunu her gün, Y kargosunu haftada bir kontrol etmek isteyebilir | `cargo.company_sync_settings` |
| C-7 | **Sürat Kargo entegrasyon ayarları** | Sürat ayarları ayrı sayfada, settings'e taşınabilir | Tek yerden yönetim | Zaten `SuratIntegrationSettings` component'inde mevcut |

---

### 2.5 ENTEGRASYONLAR (MarketplaceIntegrations)

**Mevcut ayar desteği:** Senkronizasyon profilleri (per-marketplace), fiyat/stok push toggle'ları, auto-match toggle, webhook ayarları.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| E-1 | **Senkronizasyon poll aralıkları üst sınırı** | Doğrulama: orders max 1440, products max 10080 dk | API kotası yüksek firmalar daha sık senkronize etmek isteyebilir | `integrations.max_poll_intervals` |
| E-2 | **Maksimum paralel iş** | Doğrulama max 5 | Daha fazla kaynak ayıran firmalar artırabilir | `integrations.max_parallel_jobs_limit` |
| E-3 | **Smoke test parametreleri** | Hardcoded `--hours=24 --preview=2` | Firma test süresini ayarlamak isteyebilir | `integrations.smoke_test_hours`, `integrations.smoke_test_preview` |
| E-4 | **Pazaryeri bazlı bildirim tercihleri** | Bildirim ayarları genel, per-marketplace değil | Firmalar sadece kritik pazaryerinden bildirim almak isteyebilir | `integrations.notification_preferences` |
| E-5 | **API hata eşikleri** | Circuit breaker hardcoded 5 hata, 300 sn kurtarma | Firma daha hassas veya daha toleranslı olabilir | `integrations.circuit_breaker_threshold`, `integrations.circuit_breaker_recovery_seconds` |
| E-6 | **Rate limiter ayarları** | Hardcoded max 5 retry, 60 sn backoff | API kotasına göre özelleştirilmeli | `integrations.rate_limit_*` |

---

### 2.6 MUHASEBE (MarketplaceAccounting)

**Mevcut ayar desteği:** Firma profili (tam), stopaj/KDV oranları, denetim toleransları (30+), audit kuralları aç/kapalı, reconciliation eşikleri, desi/barem fiyat tabloları.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| M-1 | **Para birimi seçimi** | ✅ DÜZELTİLDİ — Varsayılan para birimi ayarı eklendi; kur dönüşümü kapsam dışı | Yurtdışı satış yapan firmalar EUR/USD kullanabilir | — |
| M-2 | **Dönem oluşturma varsayılanı** | Yeni dönem 'Trendyol' olarak oluşturuluyor | Kullanıcı farklı pazaryerinden başlayabilir | `accounting.default_period_marketplace` |
| M-3 | **Dönem kilitleme otomatik** | Manuel kilitleme var, otomatik dönem kapanışı yok | Firmalar belirli günde otomatik kapanış isteyebilir | `accounting.auto_close_day` |
| M-4 | **Reconciliation durum renkleri** | Hardcoded renk kodları | Firmalar kendi renk şemalarını belirleyebilir | `accounting.reconciliation_colors` |
| M-5 | **ERP webhook ayarları** | `MpErpSetting` ayrı modelde, settings panelinden erişilmiyor | Tek yerden yönetim | Mevcut, ayarlar paneline taşınmalı |
| M-6 | **Varsayılan Excel import şablonu** | Herhangi bir şablon tercihi yok | Firma kendi Excel düzenini tanımlayabilir | `accounting.import_template` |
| M-7 | **Stopaj matrah hesaplama yöntemi** | Tek yöntem var | Firma KDV dahil veya hariç matrah kullanabilir | `accounting.stopaj_base_method` |
| M-8 | **Dönem kapatma toleransı** | Dönem kapatma için bakiye 0 olmalı | Firmalar belirli bir farkı kabul edebilir | `accounting.period_close_tolerance` |

---

### 2.7 EŞLEŞTİRME (MarketplaceMatchingCenter)

**Mevcut ayar desteği:** Yok (tümü hardcode).

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| ES-1 | **Aday puanlama ağırlıkları** | ✅ DÜZELTİLDİ — `matching.weights` (8 sinyal, her biri 0–500), persistent | Her firma farklı eşleme stratejisi ister | — |
| ES-2 | **Otomatik önerme eşiği** | ✅ DÜZELTİLDİ — `matching.auto_recommend_threshold` (varsayılan 100, aralık 1–500), persistent | Daha muhafazakar veya agresif olabilir | — |
| ES-3 | **Arama duraklama kelimeleri** | ✅ DÜZELTİLDİ — `matching.stop_words` (17 varsayılan, virgül/yeni satır girişi), persistent | Firma domain-specific kelimeler ekleyebilir | — |
| ES-4 | **Aday arama limiti** | ✅ DÜZELTİLDİ — `matching.candidate_search_limit` (1–100, vars. 12) + `candidate_result_limit` (1–50, vars. 8), persistent | Büyük ürün kataloğunda daha fazla aranabilir | — |
| ES-5 | **Eşleme otomatik çalıştırma** | Manuel tetikleme var | Firmalar her senkronizasyondan sonra otomatik eşleme isteyebilir | `matching.auto_run_on_sync` |

---

### 2.8 FİNANS (MarketplaceFinance)

**Mevcut ayar desteği:** Reconciliation toleransları (MpSettingsService üzerinden).

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| F-1 | **Varsayılan tarih aralığı (Finans)** | ✅ DÜZELTİLDİ — `ui.finance_default_date_range_days` (varsayılan 30 gün), persistent; query-string korunuyor | Her ziyarette resetleniyor sorunu giderildi | — |
| F-2 | **Diyagnostik kategori filtresi** | Hardcoded 3 kategori | Firmalar farklı diyagnostik kategorileri görmek isteyebilir | `finance.diagnostics_categories` |
| F-3 | **Risk rehberi eşiği** | Hardcoded | Firmalar risk algılama hassasiyetini ayarlamalı | `finance.risk_threshold` |

---

### 2.9 SORULAR (MarketplaceQuestions)

**Mevcut ayar desteği:** Yok.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| Q-1 | **Varsayılan soru durumu filtresi** | Hardcoded 'open' | Kullanıcı her zaman 'cevaplanmamış' ile başlamak isteyebilir | `questions.default_status` |
| Q-2 | **Maks cevap uzunluğu** | Hardcoded 5000 karakter | Bazı pazaryerleri daha uzun/kısa cevap kabul eder | `questions.max_answer_length` |
| Q-3 | **Otomatik cevap kuralları önceliği aralığı** | Hardcoded varsayılan 100 | Kullanıcı öncelik aralığını belirleyebilir | `questions.default_rule_priority` |
| Q-4 | **AI cevap üretimi toggle'ı** | AI cevap var ama settings'ten açılıp kapatılamıyor | Firmalar AI kullanmak istemeyebilir | `questions.ai_answer_enabled` |
| Q-5 | **Şablon değişkenleri** | Basit string replacement | Firmalar kendi değişkenlerini ekleyebilir | `questions.template_variables` |
| Q-6 | **Bildirim tercihleri** | Yeni soru bildirimi ayarı yok | Firmalar bildirim almak istemeyebilir | `questions.notification_enabled` |

---

### 2.10 İADELER & RİSK MERKEZİ

**Mevcut ayar desteği:** Yok.

**Eklenmesi gereken ayarlar:**

| # | Ayar | Açıklama | Neden Gerekli | Önerilen Key |
|---|---|---|---|---|
| I-1 | **İade iş istasyonu varsayılan sekmesi** | Rol bazlı erişim var ama tercih yok | Kullanıcı belirli sekmeyle başlamak isteyebilir | `returns.default_tab` |
| I-2 | **İade istatistik penceresi** | Hardcoded bugün | Kullanıcı 7 günlük görmek isteyebilir | `returns.stats_window_days` |
| I-3 | **Risk sinyali uyuma süresi** | Blade'de tanımlı, ayarlanamıyor | Firmalar farklı uyuma süreleri isteyebilir | `risk.snooze_options` |

---

## BÖLÜM 3: YENİ AYARLAR BÖLÜMÜ ÖNERİLERİ

Mevcut ayarlar sayfasına **yeni bölümler** olarak eklenebilecek kategoriler:

### 3.1 Bildirim Tercihleri (Yeni Bölüm)

```
notifications.orders_new            → Yeni sipariş bildirimi
notifications.orders_critical       → Kritik durum bildirimi (eksik ödeme vs.)
notifications.stock_low             → Düşük stok uyarısı
notifications.questions_new         → Yeni soru bildirimi
notifications.claims_new            → Yeni claim bildirimi
notifications.reports_digest        → Rapor bülteni bildirimi
notifications.channels              → Bildirim kanalları (e-posta, Telegram, push)
notifications.quiet_hours_start     → Sessiz saat başlangıcı
notifications.quiet_hours_end       → Sessiz saat bitişi
```

### 3.2 Arayüz Tercihleri (Genişletilmiş)

```
ui.language                         → Dil seçimi (tr/en)
ui.theme                            → Tema (light/dark/system)
ui.default_date_range               → Genel varsayılan tarih aralığı
ui.per_page_*                       → Her modül için sayfa başına kayıt
ui.compact_mode                     → Kompakt görünüm
ui.show_advanced_features           → İleri seviye özellikleri göster
ui.dashboard_widgets                → Dashboard widget sıralaması
```

### 3.3 İş Akışı Tercihleri (Yeni Bölüm)

```
workflow.auto_confirm_orders        → Siparişleri otomatik onaylama
workflow.auto_ship_label            → Etiket otomatik yazdırma
workflow.auto_match_products        → Ürün eşleştirmeyi otomatik çalıştır
workflow.auto_close_periods         → Dönemleri otomatik kapatma
workflow.require_approval_above     → Belirli tutar üstü onay gerektirme
workflow.default_order_action       → Varsayılan sipariş aksiyonu
```

### 3.4 Raporlama Tercihleri (Yeni Bölüm)

```
reports.currency_display            → Rapor para birimi gösterimi
reports.decimal_places              → Ondalık basamak sayısı
reports.date_format                 → Tarih formatı
reports.export_format               → Varsayılan dışa aktarım formatı (xlsx/csv/pdf)
reports.auto_refresh_interval       → Otomatik yenileme süresi
```

---

## BÖLÜM 4: ÖNCELİK SIRASI

### Yüksek Öncelik (Hemen Eklenmeli)

1. ~~**S-1, P-3** — Sayfa başına kayıt sayısı~~ ✅ Tamamlandı
2. ~~**S-2, F-1** — Varsayılan tarih aralığı~~ ✅ Tamamlandı
3. ~~**P-1** — Ürün formu KDV oranı~~ ✅ Tamamlandı
4. ~~**M-1** — Para birimi seçimi~~ ✅ Tamamlandı
5. ~~**ES-1** — Eşleştirme ağırlıkları~~ ✅ Tamamlandı
6. **S-10** — Trendyol zaman damgası offset'i (AB firmaları için kritik)
7. **C-1, C-2** — Dinamik desi/barem aralıkları (şirketler kendi anlaşmalarına göre değiştirir)

### Orta Öncelik (Yakın Zamanda)

8. **K-1** — Kâr sağlık skoru ağırlıkları
9. **Q-4** — AI cevap toggle'ı
10. **C-4** — Desi üst sınırı
11. **P-5** — Düşük stok uyarısı
12. **S-5, S-6** — Dosya yükleme ve timeout limitleri
13. **Bölüm 3.1** — Bildirim tercihleri
14. **Bölüm 3.2** — Genişletilmiş arayüz tercihleri

### Düşük Öncelik (Planlanabilir)

15. **K-5, K-6** — Alternatif kâr hesaplama yöntemleri, ambalaj maliyeti kalemleri
16. **Bölüm 3.3** — İş akışı otomasyonları
17. **Bölüm 3.4** — Raporlama tercihleri
18. **P-8** — Eşleştirme puanlama özelleştirme (ileri seviye)

---

## BÖLÜM 5: TEKNİK NOTLAR

### Uyulması Gereken Kurallar

1. **Mevcut hiçbir ayar silinmeyecek** — Sadece genişletme yapılacak
2. Tüm yeni ayarlar `MpSettingsService::getDefaults()` içine eklenecek
3. `mp_accounting_settings` tablosu JSON blob yapısı korunacak (yeni key'ler eklenecek)
4. Backward-uyumluluk: Eski key'ler çalışmaya devam edecek
5. Her yeni ayar section'ı "Varsayılan" butonuna sahip olacak
6. Etkilenen modüller badge'i her bölüm için gösterilecek

### Dosya Haritası

```
app/Livewire/MarketplaceSettings.php          → Ana ayarlar component'i (genişletilecek)
app/Livewire/MarketplaceAccounting.php         → Muhasebe ayarları (genişletilecek)
app/Services/MpSettingsService.php             → Merkezi ayar servisi (yeni key'ler)
resources/views/livewire/marketplace-settings.blade.php → Ana ayarlar view'u (yeni bölümler)
resources/views/livewire/mp-settings-panel.blade.php    → Muhasebe ayarları view'u
config/marketplace.php                         → Statik config (gerekirse güncellenecek)
database/migrations/                           → Yeni tablo gerekirse (varsa)
```

---

## BÖLÜM 6: ÖZET İSTATİSTİKLER

| Metrik | Değer |
|---|---|
| Mevcut ayar sayısı (MpSettingsService) | ~75 key |
| Mevcut UI ayar sayısı (Pazaryeri Ayarları sayfası) | 12 alan |
| Mevcut UI ayar sayısı (Muhasebe paneli) | ~45 alan |
| **Önerilen yeni ayar sayısı** | **~65 key** |
| **Toplam ayar (önerilen dahil)** | **~140 key** |
| Yeni önerilen bölüm | 4 (Bildirim, Arayüz, İş Akışı, Raporlama) |
| Hardcoded değer (düzeltilmesi gereken) | ~20 kritik nokta |

---

*Rapor sonu. Hiçbir dosya değiştirilmemiştir, sadece analiz yapılmıştır.*
