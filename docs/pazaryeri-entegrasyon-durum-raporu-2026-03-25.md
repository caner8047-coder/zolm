# Pazaryeri Entegrasyon Durum Raporu - 25.03.2026

Bu rapor, [Pazaryeri Entegrasyon Mimarisi v1](./pazaryeri-entegrasyon-mimarisi-v1.md) belgesinde onaylanan hedef mimarinin mevcut kod tabaniyla ne kadar tamamlandigini ozetlemek icin hazirlanmistir.

## Genel Karar

Baslangicta planlanan mimarinin cekirdek omurgasi buyuk olcude tamamlanmistir.

Ancak sistem "tamamlandi" olarak degil, "mimari olarak tamamlanmaya cok yakin; canli dogrulama ve ikinci dalga connector implementasyonlari bekleniyor" seviyesinde degerlendirilmelidir.

Kisa karar:

- Cekirdek veri modeli: tamamlandi
- V2 moduller: tamamlandi
- Sync / webhook / queue / saglik omurgasi: tamamlandi
- Trendyol dikey dilimi: buyuk olcude tamamlandi
- Finans, profit ve mutabakat omurgasi: tamamlandi
- Cift yonlu push omurgasi: tamamlandi
- Operasyon aksiyon omurgasi: tamamlandi, connector bazli yazma aksiyonlari kismi
- Diger connector'lar: kismi
- Canli credential ile dogrulama: tamamlanmadi
- Production rollout ispatı: tamamlanmadi

## Durum Ozeti

| Baslik | Hedef | Durum | Not |
| --- | --- | --- | --- |
| Cok firmali veri modeli | `legal_entities`, `marketplace_stores`, baglanti ve profil katmani | Tamamlandi | Temel tablolar ve modeller mevcut |
| Generic connector cekirdegi | Provider registry, manager, contracts, sync run log | Tamamlandi | Yeni kanal eklemeye uygun |
| Polling + webhook mimarisi | Webhook-first, polling-fallback | Tamamlandi | Debounce, dedupe ve queue akisi var |
| Siparis omurgasi | order, package, item normalize yapisi | Tamamlandi | V2 ekran yeni tablolari kullaniyor |
| Finans omurgasi | ledger + profit snapshot | Tamamlandi | Mutabakat ve fark siniflari var |
| Urun/listing omurgasi | master urun + kanal listing | Tamamlandi | matching merkezi dahil |
| Excel fallback | Gecis doneminde birlikte calisma | Buyuk olcude tamamlandi | Legacy import artik opsiyonel magaza secimiyle `channel_orders` projection'a akabiliyor; legacy `mp_orders` finans satirlari da yeni `order_financial_events` ledger'ina tasinabiliyor. Saha dogrulamasi hala gerekli |
| Trendyol | Orders, products, finance, webhook, push | Buyuk olcude tamamlandi | Canli smoke test bekleniyor |
| Hepsiburada | Orders, products, finance, push | Kismi / pilot | Webhook yok, write-side kisitli |
| WooCommerce | Orders, products, webhook, push | Buyuk olcude tamamlandi | Finans yok, dusuk etkili profil tamam |
| Shopify | Orders, products, finance, webhook, push | Buyuk olcude tamamlandi | Canli smoke test bekleniyor |
| N11 / Pazarama / Amazon / Ciceksepeti / Koctas | Faz 4 connector'lar | Skeleton | Production-ready degil |
| Operasyon sagligi | smoke test, diagnostics, retry, repair, export | Tamamlandi | Operator akislari guclu |
| Production hazirlik | deploy, env, checklist | Buyuk olcude tamamlandi | Gercek rollout ve smoke kaniti yok |

## Faz Bazli Degerlendirme

### Faz 0 - Tasarim ve Omurga

Durum: Tamamlandi

Mimari dokuman, temel migrations, provider registry, queue/scheduler/sync run/webhook run kayitlari olusturuldu.

### Faz 1 - Trendyol Dikey Dilimi

Durum: Buyuk olcude tamamlandi

Asagidaki omurga var:

- baglanti ve test connection
- orders/products/finance pull
- webhook endpoint
- order/package/item normalization
- tahmini ve kesin kar snapshot
- Siparisler V2

Eksik kisim:

- gercek canli credential ile smoke test ve field-level mapping sertlestirmesi
- production webhook akisinin uçtan uça kanitlanmasi

### Faz 2 - Finans ve Mutabakat Derinlesmesi

Durum: Tamamlandi

Finans V2, mutabakat durumlari, fark siniflari, operasyon exportlari ve diagnosic/guidance katmani mevcut.

### Faz 3 - Cift Yonlu Kanal Yonetimi

Durum: Buyuk olcude tamamlandi

Fiyat/stok push omurgasi, push run kayitlari, retry ve debounce/coalescing mantigi mevcut.

Eksik kisim:

- her connector icin ayni olgunlukta write-side coverage yok
- bazi pazaryeri yazma aksiyonlari hala kisitli veya skeleton

### Faz 4 - Diger Connector'lar

Durum: Kismi

Gercek/pilot seviyesinde:

- Trendyol
- Hepsiburada
- WooCommerce
- Shopify

Skeleton seviyesinde:

- N11
- Pazarama
- Amazon
- Ciceksepeti
- Koctas

Bu nedenle Faz 4 "mimari olarak basladi" ama "tamamlandi" denemez.

## Epic Bazli Durum

| Epic | Durum | Not |
| --- | --- | --- |
| Epic 1 - Firma ve Magaza Omurgasi | Tamamlandi | Veri modeli ve ekranlar mevcut |
| Epic 2 - Generic Connector Cekirdegi | Tamamlandi | Manager, registry, sync run, webhook log var |
| Epic 3 - Trendyol Siparis Ingestion | Buyuk olcude tamamlandi | Canli smoke test eksik |
| Epic 4 - Trendyol Urun ve Listing Ingestion | Buyuk olcude tamamlandi | Matching ve listing baglanti katmani mevcut |
| Epic 5 - Trendyol Finans Ingestion | Buyuk olcude tamamlandi | Canli payload sertlestirmesi eksik |
| Epic 6 - Profit Engine v2 | Tamamlandi | Estimated/confirmed mantigi ve mutabakat var |
| Epic 7 - Entegrasyonlar Ekrani | Tamamlandi | Readiness, guidance, export, safe profile mevcut |
| Epic 8 - Siparisler Ekrani v2 | Tamamlandi | V2 omurga ve operasyon aksiyonlari var |
| Epic 9 - Finans Ekrani v2 | Tamamlandi | Mutabakat ve export var |
| Epic 10 - Urunler Ekrani v2 | Tamamlandi | Listing, push, matching paneli var |

## Su Anda Gercekten Tamamlanan Ana Parcalar

### 1. Veri Modeli

Asagidaki planli tablo aileleri mevcut:

- firma: `legal_entities`, `legal_entity_settings`
- magaza/baglanti: `marketplace_stores`, `integration_connections`, `integration_sync_profiles`, `integration_sync_runs`, `integration_webhook_events`
- katalog: `channel_products`, `channel_listings`, `product_match_issues`
- siparis/finans: `channel_orders`, `channel_order_packages`, `channel_order_items`, `order_financial_events`, `order_profit_snapshots`
- operasyon: `integration_push_runs`, `integration_order_action_runs`

### 2. V2 Ekranlari

Asagidaki ekran ailesi mevcut:

- Kontrol Merkezi
- Entegrasyonlar
- Siparisler V2
- Urunler V2
- Eslestirme Merkezi
- Finans V2

### 3. Operator Katmani

Asagidaki operator araclri mevcut:

- health check
- smoke test
- diagnostics report
- diagnostics guidance
- failed sync/push/action/webhook repair
- CSV exportlar
- readiness ve safe profile komutlari

### 4. Dusuk Etkili Profil ve Koruma Katmani

Mevcut durumda sistemde su korumalar var:

- manual sync debounce
- listing push debounce/coalescing
- order/package action debounce/coalescing
- WooCommerce webhook topic hijyeni
- Shopify webhook topic hijyeni
- WooCommerce / Trendyol / Hepsiburada / Shopify safe profile komutlari

### 5. Legacy Kopru Katmani

Mevcut durumda gecis donemi icin su kopruler var:

- legacy operasyon Excel importu -> `channel_orders`
- mevcut eski legacy operasyon siparisleri -> `marketplace:project-legacy-orders`
- legacy `mp_orders` finans satirlari -> `order_financial_events`
- mevcut eski legacy finans satirlari -> `marketplace:project-legacy-financials`
- `Siparisler V2` icinde projection magazasi otomatik on secim ve aday satir onizleme yardimcisi
- `Finans V2` icinde legacy projection etkisi ozeti (bekleyen, projekte edilen, legacy event ve kesine donen siparis sayilari)
- `Finans V2` ust guidance bandinda legacy backlog icin magazayi odaklayan mikro kart
- `Finans V2` icinde legacy backlog ve confirmed projection etkisi icin kisa yol filtreleri mevcut
- `Kontrol Merkezi` icinde legacy projection backlog'u ve projection sonrasi kesine donen siparis ozet karti
- `Kontrol Merkezi` icinde magaza bazli legacy projection backlog kirilimi
- `Kontrol Merkezi` magaza kiriliminda siparis ve finans (backlog/confirmed) kisa yol aksiyonlari mevcut
- `Kontrol Merkezi` icinden legacy projection backlog CSV export'u
- `Entegrasyonlar` icinde magaza kartlarinda ve hazirlik CSV export'unda legacy projection backlog sinyali
- `Entegrasyonlar` icinde secili magaza panelinde legacy projection icin dry-run, gercek calistirma, backlog/confirmed kisayollari ve CLI runbook gosteriliyor
- `Siparisler V2` ust guidance bandinda legacy backlog icin store filtre ve projection alani odaklayan mikro kart
- `Siparisler V2` icindeki legacy finans projection paneli dry-run, gercek projection, sonuc ozeti ve CLI komutlari ile operator akisina donustu

Bu katman, "Excel fallback kalsin ama yeni V2 omurgaya da aksin" hedefine teknik olarak buyuk olcude ulasti.

## Henuz Tamamlanmayan veya Kaniti Eksik Olan Alanlar

### P0 - Canliya Gecis Oncesi Mutlaka Yapilacaklar

1. Gercek credential ile smoke test
   - Trendyol
   - Hepsiburada
   - WooCommerce
   - Shopify

2. Production webhook kaniti
   - Gercek marketplace callback alip imza dogrulama
   - webhook -> queue -> sync run zincirini gozlemleme

3. Field mapping hardening
   - canli payload uzerinden eksik `order/package/line`, `stock_code/barcode`, `amount/settlement` alanlarini sikilastirma

4. Production runbook denemesi
   - migrate
   - queue worker
   - scheduler
   - feature flag rollout
   - rollback adimlari

### P1 - Uretim Kalitesi Için Onemli Ama Bloke Etmeyenler

1. Excel fallback ile normalize pipeline birlesmesinin sahada dogrulanmasi
   - legacy operasyon importu icin siparis projection koprusu eklendi
   - legacy `mp_orders` finans kayitlari icin ledger projection koprusu eklendi
   - projection komutlari ve metadata hazir; saha dogrulamasi hala gerekli
2. Hepsiburada write-side operasyonlarinin genisletilmesi
3. Trendyol ve Hepsiburada paket operasyonlarinin canli endpoint kaniti
4. Smoke test sonucu cikan mapping bosluklarina gore connector bazli son sertlestirme
5. Production monitoring/log aggregation stratejisinin netlestirilmesi
6. Pilot rollout paneli ile readiness + smoke + legacy backlog + ilk aksiyonun tek ekranda birlestirilmesi

### P2 - Sonraki Faz

1. Skeleton connector'lari gercek connector'a cevirme
   - N11
   - Pazarama
   - Amazon
   - Ciceksepeti
   - Koctas

2. Skeleton kanallar icin resmi auth + endpoint + capability implementasyonu
3. Kanal bazli daha derin yazma aksiyonlari
4. ERP/ek muhasebe derin entegrasyonlari

## Risk Degerlendirmesi

### Dusuk Riskli ve Hazir Alanlar

- veri modeli
- ekran omurgasi
- operator tooling
- health/diagnostics/repair akislari
- safe profile ve debounce mantigi
- pilot rollout operatör paneli

### Orta Riskli Alanlar

- canli payload field farkliliklari
- provider bazli endpoint varyasyonlari
- canli webhook davranis farklari
- Hepsiburada write-side ve paket operasyonlari

### Yuksek Riskli Alanlar

- skeleton connector'lari production-ready varsaymak
- canli smoke test yapmadan rollout'a cikmak
- webhook kaniti olmadan "anlik" davranisi kesin kabul etmek

## Net Sonuc

Baslangicta planlanan mimarinin cekirdegi tamamlanmistir.

Fakat projenin "tamamlandi" sayilmasi icin sadece kodun yazilmis olmasi yetmez; gercek marketplace credential'lari ile smoke test, canli webhook, field mapping hardening ve production rollout kaniti gereklidir.

Bu nedenle bugunku net durum su sekilde okunmalidir:

- Mimari tamamlanma durumu: yaklasik olarak cekirdek seviyede tamam
- Uretime cikis durumu: kontrollu pilot asamasina yakin
- Tam production guveni: canli testler tamamlandiktan sonra verilebilir

## Onerilen Siradaki Uygulama Sirasi

1. Trendyol gercek smoke test
2. WooCommerce gercek smoke test
3. Shopify gercek smoke test
4. Hepsiburada gercek smoke test
5. Production webhook kaniti
6. Ilk pilot magaza rollout'u
7. Field mapping hardening
8. Ikinci dalga connector'larin gerceklestirilmesi

## Bu Raporu Destekleyen Operasyon Dokumanlari

- [Pazaryeri Entegrasyon Mimarisi v1](./pazaryeri-entegrasyon-mimarisi-v1.md)
- [Pazaryeri Entegrasyonu Canlıya Alma Checklist](./pazaryeri-canliya-alma-checklist.md)
- [Pazaryeri Smoke Test Karar Agaci](./pazaryeri-smoke-test-karar-agaci.md)
