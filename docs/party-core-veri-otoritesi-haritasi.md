# Party Core — Veri Otoritesi Haritası

Bu doküman, ZOLM'da müşteri, cari, pazaryeri, sipariş, finans, ürün/stok, tedarikçi, kargo, banka ve tüzel kişi verisinin **şu an hangi tabloda** tutulduğunu, **kimin yazıp kimin okuduğunu** ve yeni `party` (üst kimlik) katmanıyla nasıl ilişkileneceğini haritalar.

Amaç büyük refactor yapmak değil; mevcut otoriteyi bozmadan, tekil Party/Cari kimlik katmanının nereye bağlanacağını netleştirmektir.

Bu doküman aşağıdaki brief kapsamında üretilmiştir:

- `docs/cline-party-core-gorev-briefi.md`
- Ana yol haritası: `docs/zolm-crm-erp-on-muhasebe-yol-haritasi.md`

> Önemli kural: Mevcut CRM, pazaryeri sipariş/finans ve cari benzeri akışlar bozulmayacaktır. Bu harita yalnızca okuma/teshis amaçlıdır; hiçbir tabloyu silmez veya yeniden adlandırmaz.

## 0. Okuma Kuralları

Her satır için:

- **Mevcut tablo:** Tablo adı ve oluşturan migration.
- **Hangi verinin sahibi:** O tablonun asıl "otorite" kabul ettiği veri parçası.
- **Hangi modüller okuyor:** Tabloyu okuyan servis/Livewire/route'lar.
- **Hangi modüller yazıyor:** Tabloya yazan (import, sync, projeksiyon, UI action) katmanlar.
- **Yeni party katmanıyla ilişkisi:** `parties` / `party_roles` / `party_identities` sonrası bağlantı noktası.
- **Riskler:** Otorite çatışması, tekilleştirme, çift kayıt ve geçiş riskleri.

İzolasyon kuralı: ZOLM çok kullanıcılıdır; neredeyse her tabloda `user_id` ile tenant izolasyonu vardır. CRM ve pazaryeri finans katmanları bu kuralı tutarlı uygular.

## 1. CRM Müşteri Kimliği

### 1.1. `crm_contacts`

- **Mevcut tablo:** `database/migrations/2026_04_27_093000_create_crm_core_tables.php`
- **Hangi verinin sahibi:** Tekilleştirilmiş müşteri profili. `display_name`, `normalized_name`, `primary_email`, `primary_phone`, `normalized_phone`, `billing_tax_number`, `city`, `district` ve `user_id` izolasyonu CRM müşteri kimliğinin **asıl sahibidir**. Agregat metrikler (`order_count`, `gross_revenue_total`, `risk_score`, `value_score`, `open_case_count` vb.) projeksiyonla beslenir.
- **Hangi modüller okuyor:** `App\Livewire\CrmWorkspace`, `App\Livewire\CrmCustomerLedger`, `App\Services\Crm\CrmIdentityResolver`, `App\Services\Crm\CrmProjectionService`, `App\Services\Crm\CrmCustomerLedgerProjectionService`, `App\Services\Crm\CrmSourceLinkService`, CRM snapshot/alert servisleri.
- **Hangi modüller yazıyor:** `CrmIdentityResolver::resolve()` (upsert/create), `CrmProjectionService` (metrik/son olay güncellemeleri), `CrmCustomerLedgerProjectionService` (metrik yeniden hesaplama), `CrmCustomerLedger` (manuel cari kayıt + metrik).
- **Yeni party katmanıyla ilişkisi:** `crm_contacts` CRM tarafının müşteri otoritesi olarak kalır; `parties` üst kimlik katmanı `crm_contacts.party_id` (nullable) ile bağlanır. CRM önce çalışmaya devam eder; party ilişkisi kademeli ve nullable kurulur. `crm_contacts` silinmez/yeniden adlandırılmaz.
- **Riskler:**
  - Müşteri metrikleri (`order_count`, `gross_revenue_total`) pazaryeri sipariş projeksiyonuna bağlıdır; tam bir cari ekstre değildir.
  - `İsimsiz Müşteri` fallback'i tekilleştirmede gürültü yaratabilir.
  - Aynı müşteri pazaryeri tarafında farklı `external_customer_id` ile birden fazla identity satırına sahip olabilir; `crm_contacts` seviyesinde tekilleştirme `CrmIdentityResolver` sıralı eşleme mantığına bağlıdır.

### 1.2. `crm_contact_identities`

- **Mevcut tablo:** Aynı migration (`2026_04_27_093000_create_crm_core_tables.php`).
- **Hangi verinin sahibi:** Çok kaynaklı müşteri kimlikleri: `source_type`, `store_id`, `external_customer_id`, `email`, `normalized_phone`, `normalized_name`, `tax_number`. Unique index `crm_identities_user_source_store_external_unique` ile pazaryeri kaynak kimliğini tekilleştirir.
- **Hangi modüller okuyor:** `CrmIdentityResolver` (mevcut eşleşme arama), `CrmSourceLinkService`, CRM 360 görünümleri.
- **Hangi modüller yazıyor:** `CrmIdentityResolver::upsertIdentity()` (`updateOrCreate`).
- **Yeni party katmanıyla ilişkisi:** `crm_contact_identities` CRM tarafının kaynak-kimlik otoritesi olarak korunur. Yeni `party_identities` katmanı, bu tablonun **üst/ortak** katmanı olacak: `crm_contact_identities` kaynak kimliklerini `party_identities`'e bağlayan bir köprü düşünülür, ancak mevcut tablo bozulmaz. Aşağıdaki "Party Kimlik Katmanı" bölümüne bakın.
- **Riskler:**
  - `confidence`, `raw_payload` gibi zengin alanlar yeni `party_identities`'e taşınırsa veri kaybı riski; bu yüzden taşınmaz, referans/yansıtma bırakılır.
  - Unique index'in `store_id` içermesi, store'a bağlı olmayan kaynaklar (manuel, tedarikçi) için farklı davranış gerektirir.

## 2. Pazaryeri Müşteri Bilgisi

### 2.1. `channel_orders` müşteri alanları

- **Mevcut tablo:** `database/migrations/2026_03_20_090300_create_marketplace_order_finance_tables.php`
- **Hangi verinin sahibi:** `customer_name`, `customer_email`, `customer_phone`, `billing_name`, `billing_tax_number`, `shipment_country`, `shipment_city`, `shipment_district` — pazaryeri siparişinin **snapshot** müşteri bilgisidir. Otorite pazaryeri API'sidir; bu tablo salt okunur snapshot tutar. `store_id` ve `legal_entity_id` ile bağlıdır.
- **Hangi modüller okuyor:** `CrmProjectionService::projectOrders()`, `CrmCustomerLedgerProjectionService` (resolveContactForOrder), sipariş listesi/detay Livewire component'leri.
- **Hangi modüller yazıyor:** Pazaryeri entegrasyon sync katmanı (integration sync run), webhook işleyiciler.
- **Yeni party katmanıyla ilişkisi:** Bu alanlar snapshot olarak kalır; party'ye geçişte `CrmIdentityResolver`/`PartyIdentityResolver` bu alanlardan `parties` kaydını resolve eder. `channel_orders`'a doğrudan `party_id` eklemek ilk fazda **zorunlu değildir**; öncelik `crm_contacts.party_id` üzerinden gitmektir.
- **Riskler:**
  - Pazaryeri müşteri bilgisi eksik/yanlış olabilir (boş telefon, farklı ad yazımı); CRM resolve mantığı buna toleranslıdır, party katmanı da aynı toleransı taşımalıdır.
  - `channel_orders` pazaryeri satıcısına değil, **alıcı müşteriye** aittir; `legal_entity_id` burada satıcı (ZOLM kullanıcısının şirketi) içindir — bu ayrım party katmanında müşteri rolü vs. "kendi şirketim" rolü olarak netleşmelidir.

### 2.2. `mp_operational_orders` müşteri alanları

- **Mevcut tablo:** `database/migrations/2026_02_25_224640_create_mp_operational_orders_table.php`
- **Hangi verinin sahibi:** `customer_name`, `customer_city`, `customer_district`, `customer_address`, `customer_phone`, `company_name`, `tax_office`, `tax_number` — eski Excel/settlement akışından beslenen **operasyonel** sipariş müşteri snapshot'ı.
- **Hangi modüller okuyor:** Operasyon motoru, pazaryeri muhasebe listeleri.
- **Hangi modüller yazıyor:** Eski `mp_*` import/Excel akışları.
- **Yeni party katmanıyla ilişkisi:** Bu tablo `order_number` üzerinden `channel_orders` ile yan yana yaşar; party resolve için birincil kaynak **değildir** ama müşteri identity ipucu sağlar.
- **Riskler:**
  - `user_id` izolasyonu bu tabloda yoktur; çok kullanıcılı izolasyon `order_number`/diğer tablolar üzerinden dolaylıdır. Party geçişinde izolasyon party katmanında `user_id` ile garantilenmelidir.
  - `mp_operational_orders` ile `channel_orders` arasında müşteri verisi çift yazılır; tekilleştirmede çatışma riski.


### 2.3. `supply_orders` müşteri alanları

- **Mevcut tablo:** `database/migrations/2026_02_02_000001_create_supply_orders_table.php`
- **Hangi verinin sahibi:** `musteri_adi`, `telefon`, `adres`, `ilce`, `il` — tedarik/sipariş akışındaki **müşteri (sipariş sahibi)** snapshot'ı. Bu tablo aslında "tedarik siparişi" değil, **müşteriye üretim/sipariş takibi** gibidir; isim kafa karıştırıcıdır.
- **Hangi modüller okuyor:** Üretim/operasyon motoru, `CrmProjectionService::projectSupplyOrders()`.
- **Hangi modüller yazıyor:** Üretim siparişi import/giriş akışları.
- **Yeni party katmanıyla ilişkisi:** Müşteri rolü için bir identity kaynağıdır; party resolve sırasında `musteri_adi`/`telefon` ipucu olarak kullanılabilir.
- **Riskler:**
  - `user_id` izolasyonu yok; party katmanına bağlanırken izolasyon party seviyesinde eklenmelidir.
  - Türkçe kolon adları (`musteri_adi` vb.) mevcut; yeni party referansları bu tabloyu bozmadan dışarıdan resolve eder.

## 3. Cari Benzeri Müşteri Ledger

### 3.1. `crm_customer_ledger_entries`

- **Mevcut tablo:** `database/migrations/2026_04_29_120000_create_crm_customer_ledger_entries_table.php`
- **Hangi verinin sahibi:** Müşteri bazlı **satır defteri**: `product_name`, `stock_code`, `barcode`, `quantity`, `unit_price`, `gross_amount`, `discount_amount`, `commission_amount`, `cargo_amount`, `cost_amount`, `net_amount`, `profit_amount`, `platform`, `status`, `purchased_at`. `contact_id` ile CRM'e, `channel_order_id`/`channel_order_item_id` ile pazaryeri siparişine bağlıdır. Unique: `crm_customer_ledger_user_source_unique`.
- **Hangi modüller okuyor:** `CrmCustomerLedger` (Livewire), `CrmCustomerLedgerProjectionService`, CRM 360 özeti.
- **Hangi modüller yazıyor:** `CrmCustomerLedgerProjectionService::syncOrder()` (idempotent upsert), `CrmCustomerLedgerProjectionService::createManualEntry()`, `CrmCustomerLedger` (düzenle/iptal).
- **Yeni party katmanıyla ilişkisi:** Bu tablo **karlılık/satış defteri** olarak kalır; tam ön muhasebe cari ekstresi (borç/alacak, vade, kapanış) **değildir**. Ön muhasebe cari hareketi için yeni `receivables`/`payables`/`journal_lines` gibi modeller gelecektir; bu tablo bozulmadan yan yana yaşayacaktır. `party_id` ilk fazda zorunlu değildir; `contact_id` üzerinden dolaylı party ilişkisi yeterlidir.
- **Riskler:**
  - Yol haritası 1.2 zayıf yanında belirtildiği gibi: bu tablo daha çok müşteri karlılık/sipariş defteri gibi çalışır; tam cari ekstre değildir. Muhasebe cari hareketi olarak yanlış yorumlanmamalıdır.
  - Pazaryeri siparişinden projeksiyon idempotent olsa da, manuel kayıtlar ile projeksiyon arasındaki tutar tutarlılığı metrik yeniden hesaplamasına bağlıdır.

### 3.2. `mp_transactions` (eski cari ekstre yaklaşımı)

- **Mevcut tablo:** `database/migrations/2026_02_24_100003_create_mp_transactions_table.php`
- **Hangi verinin sahibi:** Çift taraflı (debt/credit/balance) "cari hesap ekstresi" benzeri kayıt; `transaction_type`, `document_number`, `order_number`, `is_matched`. Trendyol settlement/mutabakat odaklıdır.
- **Hangi modüller okuyor:** Pazaryeri muhasebe mutabakat akışları.
- **Hangi modüller yazıyor:** Eski `mp_*` import/Excel akışları.
- **Yeni party katmanıyla ilişkisi:** Bu satıcı-pazaryeri mutabakat defteridir; müşteri carisi değildir. Party katmanında **pazaryeri** rolünün finans hareketleri buradan beslenebilir, ama cari (müşteri/tedarikçi) ile karıştırılmamalıdır.
- **Riskler:**
  - `user_id` izolasyonu yok; `period_id` üzerinden dolaylı izolasyon.
  - Müşteri cari ekstresi ile satıcı-pazaryeri mutabakat ekstresi kavramsal olarak farklıdır; party katmanında bu ayrım net tutulmalıdır.


## 4. Pazaryeri Siparişleri

### 4.1. `channel_orders` (yeni omurga)

- **Mevcut tablo:** `database/migrations/2026_03_20_090300_create_marketplace_order_finance_tables.php`
- **Hangi verinin sahibi:** Yeni pazaryeri sipariş otoritesi: `store_id`, `legal_entity_id`, `external_order_id`, `order_number`, `order_status`, tarihler. Unique: `channel_orders_store_external_unique`.
- **Hangi modüller okuyor:** `CrmProjectionService`, `CrmCustomerLedgerProjectionService`, sipariş listesi/detay component'leri, shipment/cargo akışları.
- **Hangi modüller yazıyor:** Integration sync run, webhook işleyiciler, order action run.
- **Yeni party katmanıyla ilişkisi:** Müşteri snapshot'ı burada durur; party resolve `CrmIdentityResolver`/`PartyIdentityResolver` üzerinden yapılır. `legal_entity_id` satıcı tarafını temsil eder (kendi şirketim).
- **Riskler:** Müşteri alanları snapshot'tır; pazaryeri güncellerse overwrite riski. Party tekilleştirme bu snapshot'tan bağımsız çalışmalıdır.

### 4.2. `channel_order_items` ve `channel_order_packages`

- **Mevcut tablo:** Aynı migration.
- **Hangi verinin sahibi:** Sipariş kalemleri (`stock_code`, `barcode`, `quantity`, `unit_price`, `gross_amount`, `commission_rate`, `vat_rate`) ve paketler (`cargo_company`, `cargo_tracking_number`, `cargo_desi`).
- **Hangi modüller okuyor:** `CrmCustomerLedgerProjectionService`, kar/profit hesaplama, kargo operasyonları.
- **Hangi modüller yazıyor:** Integration sync, item match akışı.
- **Yeni party katmanıyla ilişkisi:** Ürün/stok tarafı (`mp_product_id`, `channel_listing_id`) ile bağlıdır; müşteri tarafıyla doğrudan ilişkisi yoktur.
- **Riskler:** `cargo_company` paket seviyesinde serbest metindir; kargo firması tekilleştirmesi için party (kargo rolü) ile normalize edilmelidir.

### 4.3. `mp_orders` (eski omurga)

- **Mevcut tablo:** `database/migrations/2026_02_24_100002_create_mp_orders_table.php`
- **Hangi verinin sahibi:** Eski Excel/settlement sipariş kayıtları; `period_id`, `order_number`, `barcode`, `stock_code`, `cargo_company`, finansal tutarlar. Trendyol hakediş/mutabakat iş kuralı birikimi taşır.
- **Hangi modüller okuyor:** Pazaryeri muhasebe modülü, audit engine.
- **Hangi modüller yazıyor:** `MarketplaceImportService` (Excel import).
- **Yeni party katmanıyla ilişkisi:** Eski omurga olarak korunur; yeni finans çekirdeği ile fark raporu (Yol Haritası Faz 7) sonrası köprü kurulur. Müşteri rolü için doğrudan kaynak değildir.
- **Riskler:** Müşteri kimliği bu tabloda tutulmaz; `mp_operational_orders` ile yan yana yaşar; çift yazım riski.

## 5. Pazaryeri Finans Olayları

### 5.1. `order_financial_events` (yeni omurga)

- **Mevcut tablo:** `database/migrations/2026_03_20_090300_create_marketplace_order_finance_tables.php`
- **Hangi verinin sahibi:** Finans olayları: `event_source`, `event_type`, `external_event_id`, `reference_number`, `event_date`, `due_date`, `settlement_date`, `amount`, `direction` (debit/credit), `status`. Unique: `order_financial_events_store_source_external_unique`.
- **Hangi modüller okuyor:** Kar merkezi/profit hesaplama, mutabakat.
- **Hangi modüller yazıyor:** Integration sync, settlement import.
- **Yeni party katmanıyla ilişkisi:** Finans olaylarının **karşı tarafı** genelde pazaryeridir; party (pazaryeri rolü) ile karşı taraf resolve edilebilir. Açık alacak/borç kapatma (yeni `receivables`/`payables`) bu olaylardan beslenir.
- **Riskler:** `direction`/`status` semantiği net olmalı; çift taraflı muhasebe fişine dönüşümde tutarlılık.

### 5.2. `order_profit_snapshots`

- **Mevcut tablo:** Aynı migration.
- **Hangi verinin sahibi:** Karlılık snapshot'ı (order ve item seviyesinde, version'lu): `gross_revenue`, `net_receivable`, `commission_total`, `cargo_total`, `cogs_cost`, `estimated_profit`, `confirmed_profit`, `margin_percent`.
- **Yeni party katmanıyla ilişkisi:** Doğrudan party ilişkisi yok; sipariş+kalem üzerinden dolaylı.
- **Riskler:** Sipariş-item bakiyesi versionlandığı için "son geçerli" seçiminde tutarlılık.

### 5.3. `mp_settlements`, `mp_invoices`

- **Mevcut tablolar:** `2026_02_24_210910_create_mp_settlements_table.php`, `2026_02_24_100004_create_mp_invoices_table.php`.
- **Hangi verinin sahibi:** Eski hakediş (`mp_settlements`: `ty_hakedis`, `seller_hakedis`, `is_reconciled`) ve fatura (`mp_invoices`: KDV mahsup) kayıtları.
- **Yeni party katmanıyla ilişkisi:** Pazaryeri rolünün finans hareketleri; müşteri/tedarikçi carisi değildir.
- **Riskler:** Eski akışın korunması gerekir; yeni finans çekirdeği ile fark raporu sonrası köprü.


## 6. Ürün / Stok Alanları

### 6.1. `products` (ürün master)

- **Mevcut tablo:** `database/migrations/2026_01_27_130001_create_products_table.php`
- **Hangi verinin sahibi:** `stok_kodu` (unique), `urun_adi`, `parca`, `desi`, `tutar`, `kategori`. Kargo karşılaştırma için standart desi/parça/tutar otoritesi.
- **Yeni party katmanıyla ilişkisi:** Doğrudan yok; ürün tarafıdır.
- **Riskler:** `user_id` izolasyonu yok; çok kullanıcılı izolasyon dikkat.

### 6.2. `mp_products` (pazaryeri ürünü + maliyet)

- **Mevcut tablo:** `database/migrations/2026_02_25_123700_create_mp_products_table.php`
- **Hangi verinin sahibi:** `barcode` (user bazlı unique), `cogs`, `packaging_cost`, `vat_rate`. Kalem eşleştirmede `channel_order_items.mp_product_id` ile bağlı.
- **Yeni party katmanıyla ilişkisi:** Yok (ürün tarafı).
- **Riskler:** Stok miktarı ürün kartında ayrı bir otorite modeli olarak henüz ayrışmamış (Yol Haritası 1.2).

### 6.3. `channel_listings` / `channel_products`

- **Mevcut tablo:** `2026_03_20_090200_create_marketplace_catalog_tables.php`
- **Hangi verinin sahibi:** Pazaryeri listing/katalog; komisyon, stok uyarı alanları.
- **Yeni party katmanıyla ilişkisi:** Yok (ürün+pazaryeri tarafı).
- **Riskler:** Stok hareket defteri (`stock_movements`/`stock_balances`) henüz yok.

### 6.4. `materials` / `recipes` / `product_costs`

- **Mevcut tablolar:** `2026_03_02_100001_create_materials_table.php`, `2026_03_02_100002_create_recipes_table.php`, `2026_02_11_000001_create_product_costs_table.php`.
- **Hangi verinin sahibi:** Üretim/reçete/maliyet tarafı; cari ile ilgili değildir.
- **Yeni party katmanıyla ilişkisi:** Yok.

## 7. Tedarikçi Benzeri Kayıtlar

### 7.1. Mevcut durum: tedarikçi tablosu yok

- **Tesbit:** ZOLM'da **bağımsız bir tedarikçi (supplier) entity/tablosu yoktur.** Tedarik bilgisi şu an dağınıktır:
  - `supply_orders` aslında müşteriye üretim/sipariş takibidir (müşteri rolü).
  - `trendyol_booster_supplier_research` / `trendyol_booster_supplier_offers` pazar araştırması/tedarikçi teklif kayıtlarıdır; cari/muhasebe ilişkisi yoktur.
- **Yeni party katmanıyla ilişkisi:** Party katmanı, **tedarikçi rolünü** (`party_roles`) ilk kez tanımlayacak alandır. Mevcutta tedarikçi "karşı taraf" kavramı yoktur; party bunu kurar.
- **Riskler:**
  - Tedarikçi rolü tanımlandığında mevcut `supply_orders` ile karışıklık: `supply_orders` müşteri siparişi, yeni `purchase_orders` tedarikçi alışı olacak; isim benzerliği dikkat.
  - Tedarikçi için vergi no/IBAN/iletişim tutulacak yer party + party_addresses olmalıdır; dağınık kayıt önlenmelidir.

## 8. Kargo Firması Kayıtları

### 8.1. `cargo_carrier_accounts`

- **Mevcut tablo:** `database/migrations/2026_04_27_130000_create_cargo_operation_tables.php`
- **Hangi verinin sahibi:** Kargo firması hesabı: `carrier_code`, `carrier_name`, `customer_code`, `account_name`, `sender_username`, `branch_code`, `origin_city`, `contact_name`, `contact_phone`. Unique: `cargo_accounts_user_carrier_customer_unique`.
- **Hangi modüller okuyor/yazıyor:** Kargo operasyon Livewire component'leri, shipment oluşturma akışları.
- **Yeni party katmanıyla ilişkisi:** Kargo firması bir "karşı taraf" olduğu için party (kargo rolü) ile temsil edilebilir. `cargo_carrier_accounts` teknik bağlantı (credentials/branch) olarak kalır; party ise ticari kimlik (unvan, vergi no, iletişim) seviyesinde olur. `cargo_carrier_accounts`'a `party_id` (nullable) eklenebilir.
- **Riskler:** Kargo firması `carrier_code` (`surat`, `aras` vb.) serbest metin/tabiri; tekilleştirme için normalize kargo rolü gerekir. `channel_order_packages.cargo_company` ve `mp_orders.cargo_company` ile tutarlılık.

### 8.2. Kargo adı dağınık alanları

- `channel_order_packages.cargo_company` (serbest metin)
- `mp_orders.cargo_company` (serbest metin)
- `mp_operational_orders.cargo_company` (serbest metin)
- `cargo_invoice_lines.carrier_code` / `sender_name` / `recipient_name`

**Yeni party katmanıyla ilişkisi:** Bu dağınık kargo adları, party (kargo rolü) + normalize `carrier_code` ile tekilleştirilebilir; ilk fazda zorunlu değildir.
**Riskler:** Serbest metin → normalize eşleme kayıp riski; geçiş eşleme tablosu gerekebilir.


## 9. Banka Kayıtları

### 9.1. Mevcut durum: bağımsız banka hesabı modeli yok

- **Tesbit:** Banka bilgisi şu an `legal_entities.iban` ve `legal_entities.bank_name` alanlarında **satıcı (kendi şirketim)** seviyesinde tutulur. Müşteri/tedarikçi/pazaryeri için banka hesabı modeli yoktur.
- **Yeni party katmanıyla ilişkisi:** Party (müşteri/tedarikçi/pazaryeri rolleri) için `party_account_settings` veya ileride `bank_accounts` düşünülmüştür (Yol Haritası 3.2). İlk party fazında banka hesabı modeli **zorunlu değildir**.
- **Riskler:** Banka hareketi/virman modeli (Yol Haritası 3.8/3.9) party'den sonra gelir; party'siz banka hareketi karşı tarafı boş kalır.

## 10. Tüzel Kişi (Legal Entity) Kayıtları

### 10.1. `legal_entities`

- **Mevcut tablo:** `database/migrations/2026_03_20_090000_create_legal_entities_tables.php`
- **Hangi verinin sahibi:** **Satıcı (ZOLM kullanıcısının kendi şirketi)** tüzel kişi kaydı: `name`, `tax_number`, `tax_office`, `mersis_number`, `company_type`, `iban`, `bank_name`. Unique: `legal_entities_user_tax_unique`. `marketplace_stores`, `channel_orders`, `order_financial_events`, `cargo_carrier_accounts`, `shipments` bu tabloya bağlıdır.
- **Hangi modüller okuyor/yazıyor:** Pazaryeri entegrasyon, sipariş/finans, kargo ayarları; tenant şirketi seçimi.
- **Yeni party katmanıyla ilişkisi:** **Kritik karar noktası:** `legal_entities` "kendi şirketim" tarafıdır; müşteri/tedarikçi/pazaryeri/kargo/banka **karşı taraf**dır. Party katmanı **karşı tarafları** modellemek içindir. `legal_entities` ile `parties` **birleştirilmez**; iki ayrı kavram: biri tenant'ın kendisi, diğeri dış taraflar. İlişki "party, hangi legal entity'ye (şirkete) karşı taraf olarak bağlı" şeklinde `parties.legal_entity_id` (nullable) ile kurulabilir — bkz. model karar dokümanı.
- **Riskler:**
  - `legal_entities` ile `parties` karıştırılırsa "kendi şirketim" ile "müşterim" tekilleştirilir; bu yanlış olur.
  - Çok şirketli tenant yapısında her `legal_entity`'nin kendi müşteri/cari kümi olabilir; party izolasyonu hem `user_id` hem `legal_entity_id` ile düşünülmelidir.

## 11. Otorite Özeti ve Party Katmanına Bağlantı Noktaları

| Alan | Mevcut otorite tablosu | Karşı taraf tipi | Party'ye bağlantı | İlk faz |
| --- | --- | --- | --- | --- |
| CRM müşteri kimliği | `crm_contacts` (+ `crm_contact_identities`) | Müşteri | `crm_contacts.party_id` (nullable) | Evet |
| Pazaryeri müşteri snapshot | `channel_orders` alanları | Müşteri (resolve ile) | dolaylı, `crm_contacts.party_id` üzerinden | Hayır (dolaylı) |
| Cari kar defteri | `crm_customer_ledger_entries` | Müşteri | `contact_id` → `party_id` dolaylı | Hayır |
| Pazaryeri mutabakat | `mp_transactions` / `mp_settlements` | Pazaryeri | ileride pazaryeri rolü | Hayır |
| Pazaryeri siparişi | `channel_orders` | Müşteri + satıcı | resolve ile, nullable | Hayır |
| Finans olayı | `order_financial_events` | Pazaryeri | ileride | Hayır |
| Ürün/stok | `products` / `mp_products` | — (ürün tarafı) | yok | — |
| Tedarikçi | (yok) | Tedarikçi | party_roles ile yeni | Rol tanımı evet, veri yok |
| Kargo firması | `cargo_carrier_accounts` (+ dağınık `cargo_company`) | Kargo | nullable `party_id` (ileride) | Hayır |
| Banka | (yok, `legal_entities` içinde) | Banka | ileride | Hayır |
| Tüzel kişi (satıcı) | `legal_entities` | Kendi şirketim | ayrı kavram, birleştirme | — |

**Sonuç:** İlk party fazının tek zorunlu bağlantısı `crm_contacts.party_id` (nullable) + yeni `parties`/`party_roles`/`party_identities` tablolarıdır. Diğer bağlantılar (kargo, pazaryeri, banka, tedarikçi) ileri fazlara bırakılır ve hiçbiri mevcut otoriteyi bozmaz.

## 12. Tespit Edilen Ana Riskler

1. **Otorite dağınıklığı:** Müşteri kimliği `crm_contacts`'ta, snapshot `channel_orders`'ta, operasyonel `mp_operational_orders`/`supply_orders`'ta dağınık. Party tekilleştirme `CrmIdentityResolver` mantığını merkeze almalıdır.
2. **`legal_entities` ile `parties` karışması:** "Kendi şirketim" ile "müşteri/tedarikçi" tek modelde birleştirilirse rapor ve cari yanlış kurulur. Ayrı tutulmalıdır.
3. **İzolasyon eksiklikleri:** `supply_orders`, `mp_operational_orders`, `mp_orders`, `mp_transactions`, `products` tablolarında `user_id` izolasyonu yok/destekli değil. Party katmanı `user_id` ile tenant izolasyonunu zorunlu kılmalıdır.
4. **Kargo adı normalize eksikliği:** `cargo_company` serbest metin; kargo rolü tekilleştirme için eşleme tablosu gerekebilir.
5. **Cari vs. kar defteri karışması:** `crm_customer_ledger_entries` kar defteridir, cari ekstresi değildir; yeni `receivables`/`payables` ile karıştırılmamalıdır.
6. **Snapshot overwrite:** Pazaryeri sync, müşteri snapshot alanlarını overwrite edebilir; party resolve snapshot'tan bağımsız, idempotent çalışmalıdır.
7. **Geçiş sırası:** Party, CRM'i bozmadan nullable `party_id` ile başlamalı; mevcut `crm_contacts`/`crm_contact_identities` akışları aynen korunmalıdır.

