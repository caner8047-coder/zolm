# ZOLM v1 — Proje Mimarisi Haritası

> Freeform board için çekirdek-dal yapısı. Her dal farklı renk ile temsil edilebilir.

---

## 🟢 ÇEKİRDEK (Merkez Daire)

```
┌─────────────────────────────────────────────┐
│              ZOLM v1 ÇEKİRDEK              │
│                                             │
│  Laravel 11 + Livewire 3 + Alpine.js       │
│  MySQL 8 + Tailwind CSS + PhpSpreadsheet   │
│  AI: Gemini API + OpenAI + Groq fallback   │
│  309 Model │ 343 Service │ 128 Component   │
│  262 Migration │ 86 Artisan Command        │
│  193 Route │ 160 Blade View                │
└─────────────────────────────────────────────┘
```

---

## 🔵 DAL 1: PAZARYERİ V2 (83 service, 23 model)

```
Pazaryeri V2
├── Overview (genel bakış)
├── Kâr Merkezi (ProfitCenter)
├── Siparişler (Orders v2)
├── Finans (Finance v2)
├── Ürünler (Products v2)
├── Sorular (Questions — AI destekli)
├── Eşleştirme (Matching Center)
├── Risk Merkezi (Risk Signals)
├── Mutabakat (Settlement Audit)
├── Rapor Digest
├── Entegrasyonlar (10+ connector)
│   ├── TrendyolConnector
│   ├── HepsiburadaConnector
│   ├── N11Connector
│   ├── AmazonConnector
│   ├── ShopifyConnector
│   ├── WooCommerceConnector
│   ├── CiceksepetiConnector
│   ├── KoctasConnector
│   ├── PazaramaConnector
│   └── ShoppyConnector
├── Ayarlar
├── Kampanya Karar Merkezi
├── Simülatör (Pricing + Campaign)
├── Onboarding Wizard
└── Trendyol Booster
    ├── Keyword Intelligence
    ├── Commission Rates
    ├── Bestseller Reports
    ├── Supplier Research
    ├── Store Watch
    └── Cost Presets
```

---

## 🟣 DAL 2: MUHASEBE / ERP (18 component, 18 service)

```
Muhasebe ERP
├── Dashboard
├── Pilot Center
├── Cariler (Parties)
├── Cari Açık Hesap (Party Ledger)
├── Hesap Planı (Chart of Accounts)
├── Journal (Yevmiye)
├── Kasa / Banka
├── Stok
├── Ürünler
├── Satış (Sales)
├── Satın Alma (Purchases)
├── POS
├── e-Documents (e-Fatura)
├── Raporlar
├── Asistan (AI)
├── Marketplace Bridge (pazaryeri-muhasebe köprüsü)
└── Audit Logs
```

---

## 🔴 DAL 3: WHATSAPP BUSINESS (50 service, 52 model, 12 component)

```
WhatsApp Business
├── Genel Bakış (Overview)
├── Hesap Ayarları (Meta Cloud API)
├── Şablonlar (Templates)
├── Kargo Bildirimleri (Shipping)
├── Gelen Kutusu (Inbox)
├── Kampanyalar
│   ├── CampaignSenderService
│   ├── AB Test
│   └── Control Groups
├── Segmentler (SegmentEngine)
├── Müşteri Profili
├── Denetim Kayıtları
├── Otomasyon Ayarları
│   ├── Cart Recovery
│   ├── Stock Alert
│   ├── Birthday Service
│   └── Welcome Onboarding
├── AI Chat (Gemini + Tool Router)
│   ├── OrderStatusTool
│   ├── ProductLookupTool
│   ├── ReturnStatusTool
│   ├── StockAvailabilityTool
│   └── PolicyKnowledgeTool
└── Webhook (Meta + Booster)
```

---

## 🟡 DAL 4: AI MÜŞTERİ MERKEZİ (23 component, 50+ service, 170+ model)

```
AI Müşteri Merkezi (Customer Care)
├── Operasyon
│   ├── Inbox
│   ├── Agent Workspace
│   └── Ayarlar
├── Bilgi ve Kalite
│   ├── Ürün Soruları + Eğitim
│   ├── Bilgi Bankası Önerileri
│   ├── Kalite Denetimi
│   ├── Deney Laboratuvarı
│   └── Yayın Paketleri
├── Pilot ve Üretim
│   ├── Onboarding
│   ├── Pilot Merkezi
│   ├── Lansman Merkezi
│   ├── Canlı Üretim
│   └── Konnektör Sertifikasyonu
├── Yönetim ve Güvenlik
│   ├── Analitik
│   ├── Entegrasyonlar
│   │   ├── TrendyolSupportChannelAdapter
│   │   ├── HepsiburadaSupportChannelAdapter
│   │   ├── N11SupportChannelAdapter
│   │   ├── WhatsAppSupportChannelAdapter
│   │   ├── MetaSocialSupportChannelAdapter
│   │   ├── GoogleBusinessSupportChannelAdapter
│   │   └── WebChatSupportChannelAdapter
│   ├── Organizasyon
│   ├── Enterprise API
│   ├── Ticari Paketler
│   ├── Admin Merkezi
│   ├── Governance
│   ├── Compliance
│   ├── Reliability
│   ├── Ops Center
│   ├── Security
│   ├── Reconciliation
│   └── Customer Success
└── AI Katmanı
    ├── CustomerCareAiOrchestrator
    ├── Confidence Scorer
    ├── Language Service
    ├── Context Builder
    ├── Golden Eval Gate
    └── Evaluation Service
```

---

## 🟠 DAL 5: ÜRETİM / OPERASYON (9 component, 34 service)

```
Üretim / Operasyon
├── Üretim Motoru (ProductionEngine — sabit kurallar)
├── Operasyon Motoru (OperationEngine — sabit kurallar)
├── Özel Motorlar (CustomMotor — AI profilli)
├── Profiller (ProfileWizard + ProfileManager)
│   └── DynamicTransformEngine (AI kuralları)
├── Raporlar
├── Üretim Ciro (ProductionRevenue)
├── Üretim Planlama (ProductionPlanner)
├── Reçete
│   ├── RecipeBuilder
│   ├── RecipeMaterialsManager
│   └── RecipeCalculationService
└── Fabrika (Recipe + Ciro)
```

---

## 🟤 DAL 6: İADE MERKEZİ (5 component, 11 service)

```
İade Merkezi
├── Çalışma Alanı (ReturnWorkspace)
├── İade Kabul (ReturnIntake)
├── İade Havuzu (ReturnIntelligenceCenter)
├── Pazaryeri İadeleri (MarketplaceClaimsCenter)
├── WhatsApp Köprüsü (ReturnWhatsappBridge)
└── Servisler
    ├── ReturnAutoDecisionPolicyService
    ├── ReturnDecisionSuggestionService
    ├── ReturnMatchingService
    ├── ReturnVisionService (AI görsel analiz)
    └── ReturnMediaOptimizationService
```

---

## ⚪ DAL 7: KAMPANYA / TARIFE (6 component)

```
Kampanya / Tarife
├── Kampanya Raporları
├── Karar Merkezi (Decision Center)
├── Ürün Komisyon Tarifeleri
├── Plus Komisyon Tarifeleri
├── Avantajlı Ürün Tarifeleri (Badge Pricing)
├── Flaş Ürünler
├── Sepet İndirimi
└── Simülatör (Campaign + Pricing)
```

---

## ⚫ DAL 8: KARGO / TEDARİK (9 component, 6 service)

```
Kargo / Tedarik
├── Kargo Operasyon (Dashboard)
├── Kargo Checker
├── Teslimat Takibi (DeliveryLookup)
├── Ürün Yöneticisi
├── Rapor Listesi
├── Sevkiyat Defteri (ShipmentLedger)
├── Surat Entegrasyonu (SuratCargoConnector)
├── Surat Rapor Arşivi
├── Tazminat Dashboard (Compensation)
├── Tedarik Raporu
└── WooCommerce Surat Tracking Sync
```

---

## 🔷 DAL 9: REKLAM ZEKASI (9 component, 10 service)

```
Reklam Zekası
├── Dashboard
├── Import Merkezi
├── Ürün Reklamları (Product Ads)
├── Mağaza Reklamları (Store Ads)
├── Influencer Reklamları
├── Kârlılık Analizi (Profitability)
├── AI Aksiyon Merkezi
├── Ayarlar
└── Servisler
    ├── AdImportService
    ├── AdReportService
    ├── ProductAdsService
    ├── ProfitabilityService
    ├── RuleEngine
    └── AdCampaignMatcher
```

---

## 🟫 DAL 10: CRM (2 component, 8 service)

```
CRM
├── Müşteri 360 (CrmWorkspace)
├── Müşteri Cari (CrmCustomerLedger)
└── Servisler
    ├── CrmProjectionService
    ├── CrmCustomerSnapshotService
    ├── CrmAccountingSummaryService
    ├── CrmAlertRuleService
    └── CrmIdentityResolver
```

---

## 🔘 DAL 11: WORDPRESS EKLENTİSİ (ZOLM Booster v1.2.0)

```
WordPress — ZOLM Booster
├── Trendyol Yorumları
│   ├── Widget (ürün sayfası)
│   ├── Badge (yorum rozeti)
│   ├── REST API
│   └── DB (zolm_booster_reviews)
├── WhatsApp Köprüsü
│   ├── Sipariş Bildirimi
│   ├── Müşteri Bildirimi
│   ├── Sepet Bildirimi
│   ├── Stok Bildirimi
│   ├── İzni Bildirimi
│   └── Yüzen WhatsApp Butonu
├── Legacy Migration (eski eklentiden geçiş)
└── Module Manager (aç/kapa)
```

---

## 📊 OLAY AKIŞI (Events → Listeners → Jobs)

```
OrderStatusChanged ──→ ProcessOrderNotificationListener ──→ SendWaMessageJob
ProductStockChanged ──→ ProcessStockAlertListener ──→ ProcessStockAlertJob
ReturnStatusChanged ──→ ProcessReturnNotificationListener ──→ SendWaMessageJob
ShipmentStatusChanged ──→ SendShippingNotificationListener ──→ SendWaMessageJob

Marketplace Sync ──→ SyncMarketplaceDataJob
ERP Push ──→ PushOrderToErpJob
Order Action ──→ RunMarketplaceOrderActionJob
Listing Push ──→ PushMarketplaceListingUpdateJob
Review Sync ──→ TrendyolBoosterReviewSyncJob
```

---

## 🛡️ MIDDLEWARE KATMANI

```
8 Middleware:
├── AdminMiddleware (admin rotaları)
├── AdsAccessMiddleware (reklam erişimi)
├── EnsureWhatsAppFeatureEnabled (WHATSAPP_ENABLED)
├── EnsureCustomerCareFeatureEnabled (CUSTOMER_CARE_ENABLED)
├── EnsureMarketplaceFeatureEnabled (MARKETPLACE_V2_ENABLED)
├── EnsureReturnFeatureEnabled (RETURNS_ENABLED)
├── EnforceCustomerCareTls (HTTPS zorunlu)
└── EnsureLivewireAuthenticatedUnlessPublic (auth kontrolü)
```

---

## 📈 PROJE İSTATİSTİKLERİ

| Metrik | Değer |
|--------|-------|
| Livewire Component | 128 |
| Eloquent Model | 309 |
| Service | 343 |
| Migration | 262 |
| Artisan Command | 86 |
| Route | ~193 |
| Blade View | 160 |
| Job | 15 |
| Event | 4 |
| Listener | 4 |
| Controller | 14 |
| Middleware | 8 |
| Config (custom) | 7 |
| WP Plugin Files | 19 |

---

## 🔗 DALLAR ARASI BAĞLANTILAR

```
Pazaryeri ──── Muhasebe (MarketplaceBridge)
Pazaryeri ──── WhatsApp (OrderNotification, CartRecovery)
Pazaryeri ──── Kargo (ShipmentStatus)
Pazaryeri ──── İade (MarketplaceClaims)
Pazaryeri ──── Reklam (AdCampaign data)

WhatsApp ──── İade (ReturnWhatsappBridge)
WhatsApp ──── Müşteri Merkezi (WhatsAppSupportChannelAdapter)
WhatsApp ──── WP Plugin (BoosterWebhook)

Muhasebe ──── CRM (CrmAccountingSummary)
Muhasebe ──── ERP (ErpIntegrationService)

Üretim ──── Reçete (RecipeCalculation)
Üretim ──── Kâr (ProductionRevenue)

Müşteri Merkezi ──── Tüm Pazaryerileri (SupportChannelAdapters)
Müşteri Merkezi ──── AI (CustomerCareAiOrchestrator)
```

---

> **Freeform Kurulum Notu:**
> 1. Merkeze çekirdek dairesi
> 2. Etrafına 11 dal (farklı renk)
> 3. Her dalın içine alt modüller
> 4. Dallar arası oklar = event/job akışları
> 5. Sol üst köşeye istatistik tablosu
