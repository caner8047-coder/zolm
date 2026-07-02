# ZOLM Rakip Odakli Gelistirme Plani - 16.06.2026

Bu dokuman Melontik ve SellerIQ incelemesinden cikan firsatlari ZOLM'un mevcut kod tabaniyla esleyerek hazirlanmis uctan uca gelistirme planidir.

Ana hedef yeni bir tema veya kopuk modul yapmak degil; ZOLM'un mevcut pazaryeri, finans, urun, kargo, kampanya, iade ve raporlama omurgasini tek bir kar karar merkezi deneyimine donusturmektir.

## 1. Urun Vizyonu

ZOLM, "pazaryeri veri okuyucu" olmaktan daha ileri bir noktada durmali:

- Saticiya bugun gercekten ne kadar kazandigini gostermeli.
- Hangi siparis, urun, kanal, kampanya, kargo, iade veya finans hareketinin kari erittigini netlestirmeli.
- Eksik maliyet, eksik finans, hatali desi, komisyon farki, hizmet bedeli, stopaj, KDV ve reklam etkisini tek karar yuzeyinde gostermeli.
- Trendyol ile baslayip Hepsiburada, N11, Pazarama, Ciceksepeti, Koctas, WooCommerce ve Shopify'a genisleyen coklu pazaryeri avantajini korumali.
- Excel fallback ile API tabanli canli akis ayni dogruluk motoruna akmali.

Onerilen urun adi:

**ZOLM Kar Merkezi**

Alt yuzeyler:

1. **Kar Kokpiti**
2. **Urun Fiyatlandirma ve Kar Simulasyonu**
3. **Hakediş, Desi ve Kesinti Kontrolu**
4. **Kampanya Karar Merkezi**
5. **Risk ve Uyari Merkezi**
6. **Otomatik Raporlar ve Gunluk Ozetler**
7. **Onboarding ve Veri Hazirlik Rehberi**

## 2. Mevcut Durum Haritasi

### 2.1. Ana Teknoloji

- Laravel 11 + Livewire 3.
- TALL mantigi; Alpine.js + Tailwind CDN.
- MySQL 8 / Sail.
- Excel icin PhpSpreadsheet.
- AI tarafinda Gemini destekli profil ve iade analizleri.

### 2.2. Eski Pazaryeri Muhasebe Katmani

Mevcut dosyalar:

- `app/Livewire/MarketplaceAccounting.php`
- `app/Services/MarketplaceImportService.php`
- `app/Services/MarketplaceExportService.php`
- `app/Services/AuditEngine.php`
- `app/Services/UnitEconomicsService.php`
- `app/Models/MpPeriod.php`
- `app/Models/MpOrder.php`
- `app/Models/MpTransaction.php`
- `app/Models/MpInvoice.php`
- `app/Models/MpSettlement.php`
- `app/Models/MpAuditLog.php`

Güçlü yanlar:

- Trendyol Excel import akisi olgun.
- Siparis, cari hareket, fatura, stopaj, settlement importu var.
- `AuditEngine` cok zengin denetim kurallari tasiyor.
- `UnitEconomicsService` hakediş, COGS, ambalaj, kendi kargo, KDV etkisi ve stopajla net kar hesapliyor.
- Excel export kurallari mevcut ve proje standardina uygun.

Zayif yanlar:

- Eski `mp_*` katmani tek pazaryeri ve donem mantigina daha yakin.
- Dashboard ve denetim degeri kullaniciya modern V2 yuzeylerle yeterince birlesik gorunmuyor.
- Reklam, ceza, erken odeme, diger fatura gibi kalemler V2 profit snapshot tarafinda henuz tam kategori standardina baglanmamis.

### 2.3. Yeni Coklu Pazaryeri V2 Katmani

Mevcut dosyalar:

- `app/Livewire/MarketplaceOverview.php`
- `app/Livewire/MarketplaceIntegrations.php`
- `app/Livewire/MarketplaceOrders.php`
- `app/Livewire/MarketplaceFinance.php`
- `app/Livewire/MpProductsManager.php`
- `app/Livewire/MarketplaceMatchingCenter.php`
- `app/Services/Marketplace/*`
- `app/Models/LegalEntity.php`
- `app/Models/MarketplaceStore.php`
- `app/Models/IntegrationConnection.php`
- `app/Models/IntegrationSyncProfile.php`
- `app/Models/IntegrationSyncRun.php`
- `app/Models/IntegrationWebhookEvent.php`
- `app/Models/ChannelProduct.php`
- `app/Models/ChannelListing.php`
- `app/Models/ChannelOrder.php`
- `app/Models/ChannelOrderPackage.php`
- `app/Models/ChannelOrderItem.php`
- `app/Models/OrderFinancialEvent.php`
- `app/Models/OrderProfitSnapshot.php`

Güçlü yanlar:

- Cok firmali ve cok magazali veri modeli hazir.
- Provider registry Trendyol, Hepsiburada, N11, Koctas, Pazarama, Amazon, Ciceksepeti, WooCommerce ve Shopify'i taniyor.
- Siparis, urun, finans, soru, iade sync tipleri var.
- Webhook, polling, debounce, retry, diagnostics, safe profile ve smoke test omurgasi var.
- `MarketplaceProfitSnapshotService` siparis bazli tahmini/kesin kar snapshot'i uretiyor.
- `MarketplaceReconciliationQueryService` finans mutabakat farklarini hesapliyor.
- `MarketplaceFinance` net alacak, kesinti, kesin kar, mutabakat, bekleyen finans ve snapshot eksiklerini gosteriyor.

Zayif yanlar:

- Kar odakli tek ust kokpit yok.
- V2 `vat_effect` simdilik 0; KDV etkisi eski `UnitEconomicsService` kadar derin degil.
- V2 finans event tipi taksonomisi Melontik'teki masraf kirilimini tam yansitacak kadar genisletilmemis.
- Reklam, ceza, erken odeme, mikro ihracat ve diger fatura etkisi tek grafik/kokpit seviyesinde urunlesmemis.
- Uyari/risk sinyalleri mevcut diagnostics ve notification katmanina dagilmis; "satici bugun neye bakmali" seklinde tek merkezde degil.

### 2.4. Urun ve Maliyet Katmani

Mevcut dosyalar:

- `app/Livewire/MpProductsManager.php`
- `app/Services/MpProductImportService.php`
- `app/Services/ProductCompositionResolver.php`
- `app/Services/RecipeProductCostSyncService.php`
- `app/Services/MpProductChangeLogger.php`
- `app/Models/MpProduct.php`
- `app/Models/ProductSet.php`
- `app/Models/ProductSetItem.php`
- `app/Models/Recipe.php`

Mevcut alanlar:

- COGS
- Ambalaj maliyeti
- Kargo maliyeti
- Desi
- Parca adedi
- Stok
- KDV / maliyet KDV
- Komisyon
- Kar komisyon override
- Ek sabit gider
- Ek yuzdesel gider
- Iade orani
- Teslimat tipi
- Kanal listing iliskileri
- Set / bundle / recete baglantilari

Sonuc:

Rakiplerde urun ayarlari ekraninda gordugumuz cekirdek alanlar ZOLM'de buyuk olcude mevcut. Ana ihtiyac yeni veri alani degil; bu alanlari fiyat simulasyonu, risk, toplu aksiyon ve kokpit metriklerine daha guclu baglamak.

### 2.5. Kampanya Katmani

Mevcut dosyalar:

- `app/Livewire/TariffOptimizer.php`
- `app/Livewire/PlusCommission.php`
- `app/Livewire/BadgePricing.php`
- `app/Livewire/FlashProducts.php`
- `app/Livewire/BasketDiscountCampaign.php`
- `app/Services/CampaignAnalysisService.php`
- `app/Services/TariffOptimizerService.php`
- `app/Services/PlusCommissionService.php`
- `app/Services/BadgePricingService.php`
- `app/Services/FlashProductsService.php`
- `app/Services/BasketDiscountCampaignService.php`

Güçlü yanlar:

- Trendyol urun komisyon tarifesi analizi var.
- Plus komisyon analizi var.
- Avantajli urun etiketi analizi var.
- Flash urun analizi var.
- Sepet indirimi analizi var.
- Senaryo, onerilen tarife, net kar ve kar farki hesaplari mevcut.
- Export akisi var.

Zayif yanlar:

- Kampanya modulleri ayri ayri duruyor; tek "kampanya karar merkezi" algisi zayif.
- Kokpit, urun karlilik listesi ve kampanya onerileri arasinda karar yolu daha belirgin olmali.
- Hesap formulu V2 finans snapshot ve urun maliyet detaylariyla daha tutarli hale getirilmeli.

### 2.6. Kargo ve Desi Katmani

Mevcut dosyalar:

- `app/Livewire/CargoReports.php`
- `resources/views/livewire/cargo/*`
- `app/Services/CargoComparisonEngine.php`
- `app/Services/Cargo/CargoShipmentService.php`
- `app/Services/Cargo/SuratCargoConnector.php`
- `app/Services/Cargo/SuratReportArchiveService.php`
- `app/Services/Cargo/WooCommerceSuratTrackingSyncService.php`
- `app/Models/Shipment.php`
- `app/Models/ShipmentCost.php`
- `app/Models/CargoInvoiceLine.php`
- `app/Models/CargoReportRun.php`
- `app/Models/CargoReportLine.php`

Güçlü yanlar:

- Sürat Kargo hesap ve takip entegrasyonu mevcut.
- Gonderi, takip, fatura satiri, beklenen maliyet ve gercek maliyet baglantisi var.
- Desi/tutar farki toleranslari var.
- Kargo sonucu profit snapshot yeniden hesaplamaya baglanabiliyor.

Zayif yanlar:

- Melontik/SellerIQ gibi "hatalı desi/hakediş kontrolu" mesajı ana pazaryeri finans akisi icinde daha net gorunmeli.
- Kargo farklari finans dashboard, urun dashboard ve risk merkezine otomatik sinyal olarak akmali.

### 2.7. Iade Katmani

Mevcut dosyalar:

- `app/Livewire/Returns/ReturnWorkspace.php`
- `app/Livewire/Returns/MarketplaceClaimsCenter.php`
- `app/Livewire/Returns/ReturnIntake.php`
- `app/Livewire/Returns/ReturnIntelligenceCenter.php`
- `app/Livewire/Returns/ReturnWhatsappBridge.php`
- `app/Services/Marketplace/MarketplaceClaimSyncService.php`
- `app/Services/Marketplace/MarketplaceClaimActionService.php`
- `app/Jobs/AnalyzeReturnIntakeItemJob.php`

Güçlü yanlar:

- Marketplace claim sync var.
- Claim approve/reject aksiyon kabiliyetleri var.
- Iade kabul, gorsel analiz, karar, WhatsApp bridge ve gunluk rapor altyapisi var.
- ZOLM bu alanda rakiplerden daha genis.

Plan icin not:

Iade finans etkisi Kar Kokpiti'ne "iade maliyeti", "iade kargo zarari", "iade oranı", "riskli urunler" olarak akmali.

## 3. Rakiplerden Cikan Firsatlar

### 3.1. Melontik'ten Alinacak Dersler

Public ve demo incelemesinde gorulenler:

- Net kar ve brut kar ayrimi.
- Masraf kalemlerini cok gorunur gosteren dashboard.
- Urun, siparis, kategori, iade, reklam ve kampanya raporlari.
- Urun ayarlari + maliyet + desi + iade orani + ekstra gider.
- Kar marji listesi.
- Hedef kar ile fiyat onerisi.
- Hakediş ve desi kontrolu.
- Uyari listesi.
- Gunluk/aylik rapor mailleri.
- Mikro ihracat bolge filtreleri.

ZOLM uyarlamasi:

- Dashboard daha kurumsal ve daha az daginik olmali.
- Masraf kirilimi ZOLM'un V2 finans event taksonomisine baglanmali.
- Urun ayarlari zaten var; karar aksiyonlari guclendirilmeli.
- Kampanya modulleri tek karar merkezinde birlestirilmeli.

### 3.2. SellerIQ'dan Alinacak Dersler

Public incelemede gorulenler:

- Cok net mesaj: "Gercek karini saniyeler icinde gor."
- Zarar eden urunu kirmizi bayrakla goster.
- Kargo/desi surpizlerini tespit et.
- Basit fiyatlandirma hesaplayici.
- Kredi karti gerektirmeyen, hizli onboarding.
- Trendyol kar hesaplama sayfasi SEO ve kullanici kazanimi icin guclu.

ZOLM uyarlamasi:

- Public veya yari-public "Trendyol Kar Hesaplama" araci yapilmali.
- ZOLM icinde ayni hesaplayici urun master verisiyle daha guclu calismali.
- Kokpitte "zarar eden siparis/urun" cok hizli fark edilmeli.
- Onboarding kullaniciya ilk 15 dakikada deger gostermeli.

## 4. Hedef Mimari

### 4.1. Yeni Servis Katmani

Yeni servisler:

1. `MarketplaceProfitCenterQueryService`
   - Kar kokpiti icin tum ozetleri tek servis altinda toplar.
   - Kaynaklar: `channel_orders`, `channel_order_items`, `order_financial_events`, `order_profit_snapshots`, `mp_products`, `shipments`, `channel_claims`.
   - Tarih, pazaryeri, magaza, firma ve ulke filtrelerini standartlastirir.

2. `MarketplaceCostBreakdownService`
   - Masraf kalemlerini standart kategoriye cevirir.
   - Kategoriler: COGS, ambalaj, komisyon, kargo, hizmet bedeli, stopaj, KDV, reklam, ceza, erken odeme, iade etkisi, diger.
   - Eski `mp_*` ve yeni V2 event tiplerini ayni etiket setine map eder.

3. `MarketplaceVatEffectService`
   - V2 icin KDV etkisini hesaplar.
   - Kaynaklar: urun KDV, maliyet KDV, komisyon/kargo/hizmet KDV oranlari, legal entity ayarlari.
   - `OrderProfitSnapshot.vat_effect` alanini doldurur.

4. `MarketplacePricingSimulationService`
   - Urun fiyatlandirma ve hedef kar simulasyonu yapar.
   - Input: maliyet, KDV, komisyon, kargo, hizmet bedeli, stopaj, mikro ihracat, teslimat tipi, reklam payi, hedef kar.
   - Output: net kar, kar marji, maliyet ustu getiri, zarar/uyari, onerilen fiyat.

5. `MarketplaceRiskSignalService`
   - Audit, finance, product, cargo, return ve campaign kaynakli riskleri tek sinyal modeline cevirir.
   - AppNotification veya yeni `marketplace_risk_signals` tablosuna akitilabilir.

6. `MarketplaceReportDigestService`
   - Gunluk/haftalik/aylik e-posta raporlari icin veri paketleri uretir.
   - Dashboard metrikleri ile ayni hesaplama servislerini kullanir.

7. `MarketplaceSnapshotBackfillService`
   - Eksik veya eski snapshot'lari toplu yeniden hesaplar.
   - Urun maliyeti degistiginde etkilenen siparisleri yeniden hesaplatir.

### 4.2. Yeni veya Genisletilecek Veri Modeli

Ilk fazda minimum migration tercih edilmeli.

Gerekli genisletmeler:

1. `order_financial_events.event_type` taksonomisi genisletilecek.
   - `advertising`
   - `penalty`
   - `early_payment_fee`
   - `other_invoice`
   - `return_cargo`
   - `international_service_fee`
   - `international_operation_fee`
   - `campaign_discount`
   - `marketplace_discount`

2. `order_profit_snapshots` kullanilmaya devam edilecek.
   - `vat_effect` alaninin gercek hesapla doldurulmasi P0.
   - Gerekirse ileride `advertising_total`, `penalty_total` gibi kolonlar eklenebilir; ilk fazda `deduction` agregasyonu ile cozulmeli.

3. Yeni tablo onerisi: `marketplace_pricing_scenarios`
   - Authenticated fiyat simulasyonlarini kaydetmek icin.
   - Alanlar: `user_id`, `mp_product_id`, `channel_listing_id`, `marketplace`, `input_json`, `result_json`, `status`, `created_by`, `created_at`.
   - Public hesaplayici ilk fazda DB'ye yazmayabilir.

4. Yeni tablo onerisi: `marketplace_report_subscriptions`
   - Gunluk/haftalik rapor tercihleri icin.
   - Alanlar: `user_id`, `store_id`, `frequency`, `channels_json`, `filters_json`, `enabled`, `last_sent_at`.
   - Alternatif: `user_notification_preferences` genisletilebilir.

5. Yeni tablo opsiyonel: `marketplace_risk_signals`
   - Riskleri kalici izlemek, kapatmak, atamak, tekrar acmak icin.
   - Ilk fazda AppNotification + computed guidance yeterliyse ertelenebilir.

### 4.3. Veri Akisi

Ana akis:

1. API veya Excel siparis verisi gelir.
2. `ChannelOrder`, `ChannelOrderPackage`, `ChannelOrderItem` guncellenir.
3. Urun/listing eslesmesi yapilir.
4. Finans olaylari `OrderFinancialEvent` olarak yazilir.
5. `MarketplaceProfitSnapshotService` snapshot hesaplar.
6. Yeni `MarketplaceVatEffectService` KDV etkisini snapshot'a dahil eder.
7. `MarketplaceReconciliationQueryService` tahmini/kesin farklari hesaplar.
8. `MarketplaceRiskSignalService` riskleri uretir.
9. `MarketplaceProfitCenterQueryService` kokpit metriklerini sunar.
10. Rapor, bildirim ve aksiyonlar ayni servislerden beslenir.

Legacy akis:

1. `MarketplaceImportService` veya `DetailedOrderImportService` Excel verisini alir.
2. Mevcut legacy projection komutlari V2 tablolara aktarir.
3. Snapshot ve risk akisi yeni V2 akisi ile ayni devam eder.

Urun maliyeti degisiklik akisi:

1. Kullanici `MpProductsManager` icinden veya Excel import ile maliyet/desi/komisyon/iade gunceller.
2. `MpProductChangeLogger` degisikligi kaydeder.
3. Yeni job: `RecalculateProfitSnapshotsForProductJob`.
4. Ilgili son X gun/aktif donem siparisleri yeniden hesaplanir.
5. Kokpit ve risk sinyalleri yenilenir.

## 5. Yeni Moduller ve UI Planlari

Tum yeni ekranlar ZOLM Kurumsal Acik Panel Sistemi ile yapilacak.

### 5.1. Kar Kokpiti

Onerilen route:

- `GET /marketplace-profit-center`
- Livewire: `App\Livewire\MarketplaceProfitCenter`
- View: `resources/views/livewire/marketplace-profit-center.blade.php`

Ana bolumler:

1. Ust workspace
   - Tarih araligi
   - Pazaryeri
   - Magaza
   - Firma
   - Finans durumu
   - Ulke/bolge
   - "Bugun neye bakmali?" primary guidance

2. KPI satiri
   - Toplam ciro
   - Net alacak
   - Tahmini kar
   - Kesin kar
   - Net kar marji
   - Maliyet tanimli ciro
   - Finans bekleyen siparis
   - Zarar eden siparis

3. Masraf kalemleri ledger'i
   - Urun maliyeti
   - Ambalaj
   - Komisyon
   - Kargo
   - Hizmet bedeli
   - Stopaj
   - Net KDV
   - Reklam
   - Ceza
   - Iade etkisi
   - Diger

4. Kar performansi
   - Gunluk trend
   - Tahmini/kesin ayrimi
   - Ciro - kesinti - maliyet - kar funnel'i

5. Risk panelleri
   - Zarar eden urunler
   - Eksik maliyetli urunler
   - Finans bekleyen siparisler
   - Desi/kargo farklari
   - Komisyon sapmalari
   - Iade orani yuksek urunler

6. Alt ledger
   - Siparis/urun/kategori bazli karlilik tablosu
   - Kolon secimi, siralama, mobil kart gorunumu
   - "Siparise git", "Urune git", "Finans hareketlerine git", "Kargo detayina git" aksiyonlari

Kabul kriterleri:

- Mevcut `MarketplaceFinance` ve `MarketplaceOrders` verileriyle tutarli metrik verir.
- Filtreler URL query string ile paylasilabilir.
- Maliyet eksikligi olan ciro ayri gosterilir.
- `vat_effect` hesabi acik/kapali ayarlara saygi duyar.
- Excel fallback ile gelen veri de kokpite yansir.

### 5.2. Urun Fiyatlandirma ve Kar Simulasyonu

Iki yuzey:

1. Authenticated modul
   - Route: `/marketplace-pricing-simulator`
   - Livewire: `MarketplacePricingSimulator`

2. Public acquisition araci
   - Route: `/tools/trendyol-kar-hesaplama`
   - Controller veya Livewire public component.
   - Login gerektirmez.
   - DB yazmayabilir.

Authenticated ozellikler:

- Urun secince `MpProduct` ve `ChannelListing` maliyet/fiyat/komisyon bilgileri otomatik dolar.
- Pazaryeri secimine gore komisyon varsayimi degisir.
- KDV, stopaj, kargo, hizmet bedeli, reklam payi, iade payi ve teslimat tipi hesaba katilir.
- Hedef kar tutari, hedef kar marji ve minimum fiyat hesaplanir.
- Senaryolar kaydedilir.
- Uygunsa fiyat push kuyruğuna gonderilir.

Public ozellikler:

- SellerIQ benzeri hizli form:
  - Alis fiyati
  - KDV orani
  - Kategori / komisyon
  - Kargo ucreti
  - Mikro ihracat
  - Teslimat tipi
  - Satis fiyati
- Sonuc:
  - Net kar
  - Kar marji
  - Kesinti kirilimi
  - Zarar uyarisi
  - Hedef kar icin onerilen fiyat
- CTA:
  - "ZOLM ile magazani bagla"
  - "Excel dosyani analiz et"

Kabul kriterleri:

- Public hesaplayici auth gerektirmez.
- Authenticated hesaplayici urun master verisini kullanir.
- Hesap formulu testlerle sabitlenir.
- KDV/stopaj/mikro ihracat senaryolari ayrilir.

### 5.3. Hakediş, Desi ve Kesinti Kontrolu

Onerilen route:

- `/marketplace-settlement-audit`
- Livewire: `MarketplaceSettlementAudit`

Kaynaklar:

- `OrderFinancialEvent`
- `OrderProfitSnapshot`
- `Shipment`
- `ShipmentCost`
- `CargoInvoiceLine`
- Eski `MpSettlement`, `MpOrder`, `MpAuditLog`

Ana bolumler:

- Hakediş bekleyenler
- Eksik odeme / fazla kesinti
- Komisyon farki
- Kargo/desi farki
- Hizmet bedeli artisi
- Ceza ve diger fatura
- Iade kargo zarari
- Itiraz icin hazir bilgi paketi

Kabul kriterleri:

- Kargo farki toleranslari `config/cargo.php` ile uyumlu.
- Finans farklari `MarketplaceReconciliationQueryService` ile uyumlu.
- Kullanici tek tikla ilgili siparis, shipment veya finans detayina gider.
- CSV/XLSX export Excel kurallarina uygun olur.

### 5.4. Kampanya Karar Merkezi

Mevcut moduller korunacak ama ust merkez eklenecek.

Onerilen route:

- `/campaigns/decision-center`
- Livewire: `CampaignDecisionCenter`

Ana bolumler:

- Son analizler
- Toplam mevcut kar
- Optimize kar
- Ek kar firsati
- Zararli kampanyalar
- Onaylanabilir kampanyalar
- Modullere hizli gecis:
  - Urun komisyon tarifesi
  - Plus komisyon
  - Avantajli etiket
  - Flash
  - Sepet indirimi

Gelistirme:

- Ortak `OptimizationReport` verisini daha guclu ozetle.
- Kar Kokpiti'ne "kampanya etkisi" olarak bagla.
- Urun detayinda son kampanya onerilerini goster.

Kabul kriterleri:

- Mevcut servisler bozulmadan ust ozet calisir.
- Her rapordan kaynak modüle deep link olur.
- Exportlar korunur.

### 5.5. Risk ve Uyari Merkezi

Mevcut kaynaklar:

- `AuditEngine`
- `MarketplaceDiagnosticsGuidanceService`
- `NotificationCenterService`
- `AppNotification`
- `MpAuditLog`

Onerilen route:

- `/marketplace-risk-center`
- Livewire: `MarketplaceRiskCenter`

Risk kategorileri:

- Para kaybi
- Eksik veri
- Operasyon riski
- Kargo/desi
- Iade
- Komisyon
- Finans bekleme
- Fiyat/marj
- Entegrasyon sagligi

Ilk fazda yeni tablo zorunlu degil:

- `MpAuditLog`
- diagnostics guidance
- notifications
- computed risk rows

Ikinci fazda:

- `marketplace_risk_signals` eklenebilir.
- Risk kapatma, erteleme, atama, tekrar acma eklenebilir.

Kabul kriterleri:

- Riskler severity ile siralanir.
- Her riskin net aksiyonu vardir.
- "Bugun ilk bunu yap" mantigi kokpit, finans, urun ve entegrasyon ekranlarinda ayni servisle gorunur.

### 5.6. Otomatik Raporlar

Rapor tipleri:

- Gunluk kar ozeti
- Haftalik urun/kategori kar ozeti
- Aylik finans raporu
- Kritik zarar/marj uyarilari
- Kargo/desi fark raporu
- Iade zarar raporu
- Kampanya performans raporu

Gelistirme:

- `MarketplaceReportDigestService`
- `marketplace_report_subscriptions`
- Console command: `marketplace:send-report-digests`
- Mail notification siniflari

Kabul kriterleri:

- Kullanici rapor frekansini ve filtrelerini secer.
- Rapor metrikleri Kar Kokpiti ile ayni servislerden gelir.
- Buyuk datasetlerde kuyruk kullanilir.

### 5.7. Onboarding ve Veri Hazirlik Rehberi

Hedef:

Kullanici ilk kurulumda hangi verinin eksik oldugunu ve hangi aksiyonun kari gorunur hale getirecegini anlamali.

Adimlar:

1. Firma tanimla.
2. Magaza bagla.
3. Urun sync / Excel import.
4. Maliyetleri yukle.
5. Siparis sync / Excel import.
6. Finans sync / settlement import.
7. Kargo entegrasyonu veya kargo raporu.
8. Ilk kar kokpiti.

UI:

- `MarketplaceOverview` icinde onboarding karti.
- Eksik veri durumuna gore aksiyon linkleri.
- Demo veri veya ornek Excel secenegi.

Kabul kriterleri:

- Kullanici eksik maliyet, eksik finans, eksik urun eslesmesi gibi blokajlari tek yerde gorur.
- Her blokaj bir route/aksiyon ile cozulur.

## 6. Faz Planı

### Faz 0 - Teknik Hazirlik ve Karar Sabitleme

Sure: 2-3 gun.

Isler:

- Mevcut finans event tiplerini envanterle.
- V2 ve legacy masraf kalemlerini tek kategori sozlugune map et.
- KDV/stopaj/mikro ihracat kurallarini netlestir.
- UI referansi olarak Venture fig dosyasi, `marketplace-orders` ve `marketplace-finance` kesinlesir.
- Feature flags belirlenir:
  - `profit_center_enabled`
  - `pricing_simulator_enabled`
  - `public_trendyol_profit_tool_enabled`
  - `settlement_audit_enabled`
  - `report_digest_enabled`

Cikti:

- Kategori sozlugu.
- Hesap formulu notlari.
- Test fixture listesi.

### Faz 1 - Kar Kokpiti MVP

Sure: 1-2 hafta.

Isler:

- `MarketplaceProfitCenterQueryService` yaz.
- `MarketplaceCostBreakdownService` yaz.
- `MarketplaceProfitCenter` Livewire component olustur.
- Route ekle.
- KPI, masraf kirilimi, kar trendi, risk ozeti ve ledger tabloyu yap.
- Mobil kart gorunumu ekle.
- CSV export ekle.

Testler:

- KPI toplam testleri.
- Filtre testleri.
- Masraf kategori mapping testleri.
- Snapshot eksik/finans bekleyen senaryo testleri.
- Livewire render smoke test.

Kabul:

- Kullanici tek ekranda ciro, net alacak, net kar, kesinti, zarar ve riskleri gorur.

### Faz 2 - V2 KDV/Stopaj Derinlestirme

Sure: 1 hafta.

Isler:

- `MarketplaceVatEffectService`.
- `MarketplaceProfitSnapshotService` icine vat effect entegrasyonu.
- Legal entity / store ayarlarindan KDV/stopaj okuma.
- Product/listing/item KDV fallback sirasi.
- Mikro ihracat icin kural alani.
- Snapshot backfill command.

Testler:

- %0, %1, %10, %20 KDV.
- KDV kapali sistem.
- Stopaj teorik/pratik.
- Mikro ihracat.
- Eksik KDV fallback.

Kabul:

- V2 snapshot, eski unit economics mantigina yakin dogruluk verir.

### Faz 3 - Fiyatlandirma ve Kar Simulasyonu

Sure: 1-2 hafta.

Isler:

- `MarketplacePricingSimulationService`.
- Authenticated simulator.
- Public `tools/trendyol-kar-hesaplama` ve `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED` flag'i.
- `marketplace_pricing_scenarios` migration.
- Urun secimi, senaryo kaydi, hedef kar, fiyat push hazirligi.

Testler:

- Net kar formulu.
- Hedef fiyat formulu.
- Mikro ihracat.
- Negatif kar uyarisi.
- Public route auth gerektirmeme.

Kabul:

- SellerIQ benzeri hizli arac + ZOLM icinde daha guclu urun verili simulator calisir.

### Faz 4 - Hakediş, Desi ve Kesinti Kontrolu

Durum: **Tamamlandı - 19 Haziran 2026**

Gerceklesen kapsam:

- `/marketplace-settlement-audit` route ve `settlement_audit_enabled` feature flag.
- `MarketplaceSettlementAuditQueryService` ile komisyon, hakediş, kargo tutar, desi, eksik sevkiyat, ceza/diger fatura ve iade kargo riskleri.
- Iptal siparislerin hakediş bekleme kuyrugundan cikarilmasi.
- Kesin komisyonun sadece settle edilmis finans olaylarindan okunmasi.
- Hizmet bedeli donem karsilastirmasi ve eslesmeyen kargo faturasi sinyali.
- Pazaryeri, magaza, firma, risk, tarih ve metin filtreleri.
- Mobil kart gorunumu, masaustu ledger tablosu, kolon secimi, siralama ve kolon resize.
- MarketplaceFinance ve CargoReports deep linkleri.
- `Kontrol Ozeti`, `Risk Dagilimi`, `Itiraz Detayi` ve `Toleranslar` sayfali XLSX itiraz paketi.
- Legacy finans projeksiyonunda komisyon dahil kanonik seller revenue donusumu.
- Tarayici QA: Livewire risk filtresi, yatay tasma, iptal siparis temizligi ve konsol hata kontrolu.
- Test sonucu: **441 test, 2606 assertion basarili**.

Sure: 1-2 hafta.

Isler:

- `MarketplaceSettlementAudit` component.
- Kargo/desi fark sorgulari.
- Finans fark sorgulari.
- Itiraz paketi export.
- CargoReports ve MarketplaceFinance deep linkleri.

Testler:

- Desi toleransi.
- Tutar toleransi.
- Eksik shipment.
- Fazla kargo kesintisi.
- Komisyon farki.

Kabul:

- Kullanici fazla kesinti ve hatali desi kayiplarini ayri bir kontrol yuzeyinde gorur.

### Faz 5 - Kampanya Karar Merkezi

Sure: 1 hafta.

Durum: **Tamamlandi - 19 Haziran 2026**

Gerceklesen kapsam:

- `CampaignDecisionCenter` Livewire karar yuzeyi eklendi.
- Tarife, Plus, rozet, flaş ve sepet indirimi raporlari ortak karar sozlugunde birlestirildi.
- Her kampanya tipi icin yalnizca en guncel `OptimizationReport` hesaba katilarak eski raporlarin cift sayilmasi engellendi.
- Rapor satirlari `Onaylanabilir`, `Incelenmeli` ve `Korunuyor` kararlarina ayrildi.
- Maliyet eksigi, urun eslesme sorunu, negatif kar etkisi ve rapor uyarilari guvenli onay kurallarindan ayrildi.
- `keep` aksiyonundaki negatif ham degerlerin risk ve potansiyel kar toplamlarini bozmasi engellendi.
- Karar skoru, mevcut kar, guvenli ek kar, risk maruziyeti, maliyet kapsama ve karar adetleri eklendi.
- Modul karsilastirma kartlari, rapor etki grafigi ve karar dagilimi grafigi eklendi.
- Arama, kampanya tipi ve karar filtresi; siralama, kolon secimi ve kolon resize yetenekli karar kuyrugu eklendi.
- Mobil kart gorunumu ve masaustu tablo gorunumu ZOLM Kurumsal Acik Panel Sistemi ile uygulandi.
- Ilgili kampanya araclarina ve kaynak raporlara deep linkler eklendi.
- Kar Kokpiti'ne guvenli firsat, risk maruziyeti ve onaylanabilir karar sayisini gosteren kampanya etkisi karti eklendi.
- Dort sayfali Excel cikisi eklendi: `Karar Ozeti`, `Modul Karsilastirma`, `Karar Kuyrugu`, `Son Raporlar`.
- `MARKETPLACE_CAMPAIGN_DECISION_CENTER_ENABLED` feature flag'i ve yetkili route eklendi.
- Kalici test veritabaninda Faker e-posta cakismalarini onlemek icin `UserFactory` UUID tabanli e-posta uretimine gecirildi.

Test ve dogrulama:

- Kampanya Karar Merkezi odak testleri: 4 test, 43 assertion.
- Kar Merkezi, Fiyat Simulasyonu, Mutabakat ve Kampanya paketi: 25 test, 545 assertion.
- Tum proje regresyon paketi: 445 test, 2649 assertion.
- Masaustu tarayici kontrolunde dokuman tasmasi, filtre davranisi, kolon resize ve Kar Kokpiti entegrasyonu dogrulandi.
- Tarayici konsolunda uygulama hatasi bulunmadi; yalnizca mevcut Tailwind CDN gelistirme uyarisi goruldu.

Kabul:

- Ayrik kampanya araclari tek karar yuzeyine baglandi.
- Kullanici guvenli ek kar firsatlarini riskli onerilerden ayirabiliyor.
- Kar Kokpiti kampanya kararlarinin finansal etkisini tek bakista gosterebiliyor.

### Faz 6 - Risk Merkezi ve Bildirimler

Sure: 1-2 hafta.

Durum: **Tamamlandi - 19 Haziran 2026**

Gerceklesen kapsam:

- `MarketplaceRiskSignalService` ile kar, hakediş, urun, entegrasyon, kampanya ve operasyon kaynaklari ortak risk sozlugunde birlestirildi.
- Riskler `critical`, `warning` ve `info` onem seviyeleriyle normalize edildi.
- Finansal etki, etkilenen kayit, onem ve kaynak verisi kullanilarak 0-100 oncelik skoru olusturuldu.
- Ayni riskin farkli yenilemelerde tekrar kayit olusturmasi SHA-256 fingerprint ve kullanici bazli unique index ile engellendi.
- Finans bekleyen ciro ve maliyet eksigi gibi dolayli maruziyetlerin dogrudan para kaybi gibi toplanmasi engellendi.
- Kar Merkezi ve Hakediş Kontrolu kaynaklarindaki semantik olarak ayni `hakediş bekliyor` sinyalinin cift sayilmasi engellendi.
- `marketplace_risk_signal_states` tablosu ile acik, ertelenmis ve cozulmus risk durumlari kalici hale getirildi.
- Kullanici riski 1, 3 veya 7 gun erteleyebilir, cozuldu olarak isaretleyebilir ve yeniden acabilir.
- Erteleme suresi dolan riskler otomatik olarak acik kuyruga geri doner.
- Cozulmus sinyal devam ettigi surece kullanici karari korunur; sinyal kaybolup daha sonra tekrar olusursa otomatik yeniden acilir.
- ZOLM Kurumsal Acik Panel Sistemi ile `MarketplaceRiskCenter` Livewire ekrani eklendi.
- Ekrana risk kontrol skoru, bugunun birincil odagi, KPI'lar, kategori baskisi, oncelikli aksiyonlar ve risk defteri eklendi.
- Risk defterine arama, kategori, onem ve durum filtreleri; siralama, kolon secimi ve kolon resize eklendi.
- Mobil kart gorunumu ve masaustu veri tablosu ayri responsive yuzeyler olarak uygulandi.
- Dort sayfali Excel raporu eklendi: `Risk Ozeti`, `Kategori Baskisi`, `Risk Kuyrugu`, `Bildirim Tercihleri`.
- Bildirim Merkezi'ne `risk_critical` ve `risk_warning` tipleri ile `Risk` filtresi eklendi.
- Risk bildirimleri gunluk kritik/uyari ozetlerine toplandi; tek tek risk kartlari yerine bildirim merkezinde ozet kart, Risk Merkezi'nde detay kuyrugu gosterilir.
- Eski `risk-signal:*` tekil risk bildirimleri sync sirasinda temizlenir; ayni gun ayni ozet tekrar bildirim uretmez.
- Kritik/uyari ve kategori bazli bildirim tercihleri mevcut `muted_types_json` altyapisina baglandi.
- `marketplace:sync-risk-signals` komutu eklendi ve saatlik olarak 10. dakikada calisacak sekilde planlandi.
- `MARKETPLACE_RISK_CENTER_ENABLED` feature flag'i, yetkili route ve sol menu baglantisi eklendi.
- Kar Kokpiti, Finans, Urunler ve Entegrasyonlar ekranlarina ortak `risk-guidance` component'i baglandi.
- Ortak guidance, bagli ekranin alanina uygun en yuksek oncelikli acik riski ve kaynak aksiyonunu gosteriyor.

Gercek veri dogrulamasi:

- Yonetici hesabinda 12 aktif risk sinyali tespit edildi.
- Risklerin 8'i kritik, 4'u uyari seviyesinde siniflandirildi.
- Dogrudan finansal risk baskisi 232.877,70 TL olarak hesaplandi.
- Risk kontrol skoru %48,6 ve `Incelenmeli` olarak olustu.
- En yuksek oncelik, 263 urunu ve 231.979,49 TL riski etkileyen Sepet Indirimi karari oldu.
- Kategori dagilimi: 1 karlilik, 2 hakediş/kesinti, 1 urun/maliyet, 3 entegrasyon ve 5 kampanya sinyali.

Test ve dogrulama:

- Risk Merkezi ve Bildirim Merkezi odak testleri: 10 test, 83 assertion.
- Kar, hakediş, kampanya, finans, urun ve entegrasyon paketi: 102 test, 1018 assertion.
- Tum proje regresyon paketi: 450 test, 2712 assertion.
- Masaustu gorunumde dokuman tasmasi olmadigi ve yedi kolon resize kontrolu dogrulandi.
- Mobil gorunumde masaustu tablosunun gizlendigi, kart gorunumunun aktif oldugu ve dokuman tasmasi olmadigi dogrulandi.
- Kar Kokpiti, Finans, Urunler ve Entegrasyonlar ekranlarinda ortak risk odagi ve aksiyon linkleri dogrulandi.
- Tarayici konsolunda uygulama hatasi bulunmadi; yalnizca mevcut Tailwind CDN gelistirme uyarisi goruldu.

Kabul:

- Kullanici "bugun ilk neyi duzeltmeliyim" sorusuna oncelik skoru ve finansal etkiyle net cevap alir.
- Riskler kaynak ekranlara bagli aksiyonlarla kapatilabilir, ertelenebilir veya yeniden acilabilir.
- Kritik sinyaller bildirim tercihleri dogrultusunda saatlik olarak izlenir.

### Faz 7 - Otomatik Raporlar

Sure: 1 hafta.

Durum: **Tamamlandi - 19 Haziran 2026**

Gerceklesen kapsam:

- `MarketplaceReportDigestService` ile Kar Kokpiti, Risk Merkezi ve Kampanya Karar Merkezi verileri tek e-posta payload'inda birlestirildi.
- Gunluk rapor icin bir onceki gun, haftalik rapor icin bir onceki hafta donemi standartlastirildi.
- Haftalik rapor zamanlamasinda pazartesi gonderim saati henuz gelmediyse ayni pazartesiye planlama yapilacak sekilde tarih hatasi duzeltildi.
- Alıcı listesi bos degilse yalnizca kullanicinin sectigi alicilara gonderim yapilir; liste bossa guvenli varsayilan olarak kullanici e-postasina dusulur.
- `marketplace_report_subscriptions` tablosu ile kullanici bazli rapor adi, frekans, magaza kapsami, alicilar, bolumler, saat, timezone, son durum ve siradaki calisma kalici hale getirildi.
- `marketplace_report_digest_runs` tablosu ile her alici icin donem, konu, durum, ozet payload, hata mesaji ve gonderim zamani tutuldu.
- Digest run kayitlari mevcut `reports` gecmisiyle baglandi; otomatik raporlar genel rapor gecmisi katmaninda da izlenebilir hale geldi.
- `MarketplaceReportDigestMail` ve `emails.marketplace.report-digest` mail sablonu eklendi.
- Mail sablonuna kar ozeti, risk ozeti, kampanya etkisi, pazaryeri kirilimi ve oncelikli aksiyonlar bolumleri eklendi.
- `MarketplaceReportDigestSettings` Livewire ekrani eklendi.
- Ekranda otomatik gonderim ac/kapa, rapor adi, gunluk/haftalik frekans, gonderim saati, magaza kapsami, alici listesi ve rapor bolumu secimi yonetilebilir.
- Ekranda mail on izlemesi, siradaki calisma, son gonderim durumu ve son gonderimler listesi gosterildi.
- Manuel `Simdi gonder` aksiyonu ile ayarlar kaydedilip anlik rapor gonderimi yapilabilir hale geldi.
- `MARKETPLACE_REPORT_DIGEST_ENABLED` feature flag'i, `/marketplace-report-digests` route'u ve sol menude `Otomatik Raporlar` baglantisi eklendi.
- `marketplace:send-report-digests` komutu eklendi; `--user`, `--subscription`, `--force` ve `--dry-run` opsiyonlariyla calisir.
- Komut scheduler'a 30 dakikada bir calisacak sekilde baglandi.
- `.env.example` icine varsayilan gonderim saati ve maksimum abonelik limitleri eklendi.

Isler:

- Rapor abonelik ayarlari.
- Digest servis ve command.
- Mail template.
- Rapor gecmisi ile iliski.

Testler:

- Otomatik gonderim, mail payload'i, rapor gecmisi ve digest run kaydi.
- Haftalik ve gunluk frekans planlama kurallari.
- Livewire abonelik ekrani, kaydetme ve manuel gonderim.
- Feature flag route korumasi.

Test ve dogrulama:

- Otomatik Raporlar odak testleri: 4 test, 49 assertion.
- Kar Merkezi, Risk Merkezi, Kampanya ve Otomatik Raporlar hedef paketi: 22 test, 573 assertion.
- Tum proje regresyon paketi: 454 test, 2761 assertion.
- Masaustu tarayici kontrolunde baslik, mail on izlemesi, son gonderimler, sol menu baglantisi ve yatay tasma olmadigi dogrulandi.
- Mobil tarayici kontrolunde ana icerik, form kontrolleri, mail on izlemesi ve yatay tasma olmadigi dogrulandi.
- Tarayici konsolunda uygulama hatasi bulunmadi.

Kabul:

- Gunluk/haftalik kar ozeti otomatik gonderilir.

### Faz 8 - Onboarding ve Demo Deger Akisi

Sure: 1 hafta.

Durum: **Tamamlandi - 19 Haziran 2026**

Gerceklesen kapsam:

- `MarketplaceOnboardingGuideService` ile firma, magaza, urun/eslesme, maliyet, siparis, finans, kargo ve ilk Kar Kokpiti adimlari tek veri hazirlik modelinde toplandi.
- Rehber, kullaniciya tamamlanma yuzdesi, siradaki aksiyon, aktif blokajlar ve import/demo kisayollarini `MarketplaceOverview` icinde gosteriyor.
- Eksik magaza, eksik urun, eksik maliyet, eksik finans ve tamamlandi senaryolari ayri kabul testleriyle dogrulandi.
- Urun eslesme ve maliyet hazirligi mevcut Kar Merkezi `costReadiness` mantigindan beslendigi icin kokpit hesabiyla ayni kaynak kullanildi.
- Kargo adimi, sevkiyat/kargo raporu tablolari yoksa patlamadan urun lojistik hazirligi uzerinden de karar verecek sekilde toleransli yapildi.
- `MARKETPLACE_ONBOARDING_GUIDE_ENABLED` feature flag'i eklendi.

Isler:

- MarketplaceOverview icine veri hazirlik rehberi.
- Eksik veri aksiyonlari.
- Demo/import akisi.
- Public hesaplayicidan kayit CTA.

Testler:

- Eksik magaza.
- Eksik urun.
- Eksik maliyet.
- Eksik finans.
- Tamamlandi durumu.

Test ve dogrulama:

- Onboarding odak testleri: 5 test, 20 assertion.
- Mevcut Overview regresyonlari: 19 test, 75 assertion.
- Faz 8 + Overview hedef paketi yeniden dogrulandi: 21 test, 86 assertion.
- Kar Merkezi ve otomatik rapor hedef paketiyle birlikte: 18 test, 487 assertion.
- Syntax kontrolu ve Pint duzenlemesi tamamlandi.
- Masaustu tarayici kontrolunde rehber, Kar Kokpiti CTA'si, yatay tasma ve konsol hatasi kontrol edildi.
- Mobil tarayici kontrolunde rehber, CTA, dokuman genisligi ve konsol hatasi kontrol edildi.

Kabul:

- Yeni kullanici hangi adimi atarsa kar kokpitinin calisacagini anlar.

## 7. Uygulama Sirasi

Onerilen ilk teslim sirasi:

1. Faz 0
2. Faz 1
3. Faz 2
4. Faz 3 public hesaplayici
5. Faz 3 authenticated simulator
6. Faz 4
7. Faz 6
8. Faz 5
9. Faz 7
10. Faz 8

Neden:

- Ilk once Kar Kokpiti cikmali; tum diger moduller ona veri ve aksiyon saglar.
- KDV/stopaj derinligi kokpit guvenilirligini artirir.
- Public hesaplayici hizli kazancli acquisition parcasi olur.
- Hakediş/desi kontrolu finansal kayip algisini guclendirir.
- Risk merkezi ve raporlar kokpitin urettigi degeri sureklilige tasir.

## 8. Test Stratejisi

### Unit Testler

- `MarketplaceCostBreakdownServiceTest`
- `MarketplaceVatEffectServiceTest`
- `MarketplacePricingSimulationServiceTest`
- `MarketplaceRiskSignalServiceTest`
- `MarketplaceReportDigestServiceTest`

### Feature Testler

- `MarketplaceProfitCenterTest`
- `MarketplacePricingSimulatorTest`
- `PublicTrendyolProfitCalculatorTest`
- `MarketplaceSettlementAuditTest`
- `CampaignDecisionCenterTest`
- `MarketplaceRiskCenterTest`
- `MarketplaceReportDigestTest`

### Regression Testler

Mevcut testler korunacak:

- `MarketplaceProfitSnapshotServiceTest`
- `MarketplaceFinanceReconciliationTest`
- `MpProductsManagerActionsTest`
- `MarketplaceAccountingImportTest`
- `CargoComparisonEngineTest`
- `MarketplaceListingPushServiceTest`
- Connector testleri.

### Manuel QA

- Desktop 1440px.
- Tablet 1024px.
- Mobil 390px.
- Uzun Turkce metin tasma kontrolu.
- Tablo kolon resize.
- Kolon gizleme.
- Mobil kart gorunumu.
- Excel import/export.
- Buyuk veri performansi.

## 9. Performans ve Olcek

Riskli noktalar:

- Kokpit cok fazla aggregate calistirabilir.
- Finans eventleri buyudukce dashboard yavaslayabilir.
- Urun maliyeti degisince cok siparis snapshot'i etkilenebilir.

Onlemler:

- Ilk fazda query aggregate + date filter zorunlu.
- Varsayilan tarih araligi son 7/30 gun.
- Index kontrolu:
  - `channel_orders.store_id, ordered_at`
  - `order_financial_events.channel_order_id, event_type`
  - `order_profit_snapshots.channel_order_id, calculated_at`
  - `channel_order_items.mp_product_id`
  - `shipments.channel_order_id`
- Buyuk musteride opsiyonel gunluk materialized table:
  - `marketplace_profit_daily_snapshots`
- Snapshot recalculation job chunk ile calismali.

## 10. Rollout Plani

1. Feature flag kapali sekilde deploy.
2. Local seed/test data ile dogrulama.
3. Tek kullanici / tek magaza pilot.
4. Legacy Excel + V2 API birlikte dogrulama.
5. KDV/stopaj hesaplari kullanici onayi.
6. Public hesaplayici yayina alma.
7. Kar Kokpiti genel aktif.
8. Risk/report otomasyonlarini kademeli acma.

Feature flags:

- `MARKETPLACE_PROFIT_CENTER_ENABLED`
- `MARKETPLACE_PRICING_SIMULATOR_ENABLED`
- `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED`
- `MARKETPLACE_SETTLEMENT_AUDIT_ENABLED`
- `MARKETPLACE_RISK_CENTER_ENABLED`
- `MARKETPLACE_REPORT_DIGEST_ENABLED`
- `MARKETPLACE_ONBOARDING_GUIDE_ENABLED`

## 11. Riskler

### Finansal Dogruluk Riski

KDV, stopaj, hizmet bedeli ve mikro ihracat kurallari yanlis modellenirse kullanici yanlis karar verir.

Azaltma:

- Formul testleri.
- Ayar bazli ac/kapa.
- "Tahmini" ve "kesin" ayrimini UI'da net tutma.

### Veri Kaynagi Riski

API, Excel ve legacy projection ayni siparisi farkli sekilde yazabilir.

Azaltma:

- External id, order number ve store bazli dedupe.
- Projection metadata.
- Mutabakat ekraninda kaynak gosterimi.

### Performans Riski

Dashboard aggregate sorgulari buyuk veriyle yavaslayabilir.

Azaltma:

- Varsayilan tarih filtresi.
- Chunk/backfill.
- Index.
- Gerekirse materialized daily snapshot.

### UI Karmasiklik Riski

Melontik'teki gibi her sey bir anda gorunurse ZOLM de daginik hissedebilir.

Azaltma:

- Ustte sade KPI.
- Masraf kirilimi kompakt.
- Riskler oncelikli.
- Detaylar accordion veya ledger icinde.
- ZOLM Kurumsal Acik Panel Sistemi disina cikmama.

## 12. Basari Metrikleri

Urun metrikleri:

- Ilk kurulumdan ilk kar kokpitine ulasma suresi.
- Maliyet tanimli urun orani.
- Finans hazir siparis orani.
- Snapshot eksik siparis sayisi.
- Zarar eden urun/siparis tespit sayisi.
- Hakediş/desi farkindan yakalanan toplam tutar.
- Fiyat simulasyonu kullanimi.
- Kampanya onerisi export sayisi.
- Gunluk rapor acilma oranı.

Teknik metrikler:

- Kokpit ilk yukleme suresi.
- Snapshot recalculation suresi.
- Sync hata orani.
- Queue backlog.
- Export basari orani.

## 13. Ilk Sprint Icin Net Backlog

### Sprint 1

- Finans event kategori sozlugu yaz.
- `MarketplaceCostBreakdownService` skeleton + test.
- `MarketplaceProfitCenterQueryService` skeleton + test.
- `MarketplaceProfitCenter` route ve bos ekran.
- KPI kartlari.
- Tarih/magaza/pazaryeri filtreleri.

### Sprint 2

- Masraf kirilimi.
- Kar trendi.
- Risk ozeti.
- Ledger tablo.
- Mobil kart.
- CSV export.

### Sprint 3

- `MarketplaceVatEffectService`.
- Snapshot hesap entegrasyonu.
- Backfill command.
- KDV/stopaj testleri.

### Sprint 4

- Public Trendyol kar hesaplayici.
- Authenticated fiyat simulator MVP.
- Hedef kar / onerilen fiyat.

## 14. Nihai Kabul Tanimi

Bu gelistirme tamamlandi sayilmak icin:

- ZOLM ana dashboard seviyesinde gercek kar, kesinti ve riskleri gostermeli.
- Kullanici zarar eden urun/siparisi tek bakista ayirmali.
- Hakediş, kargo/desi ve komisyon farklari aksiyona donusmeli.
- Urun fiyatlandirma araci hem public hem auth icinde calismali.
- Excel fallback bozulmamali.
- V2 entegrasyon akisi bozulmamali.
- Tum yeni exportlar Excel kurallarina uymali.
- Mobilde ana is akislari kullanilabilir olmali.
- Test paketi yeni finans formullerini korumali.
