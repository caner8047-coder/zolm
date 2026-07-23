# ZOLM CRM + ERP + Ön Muhasebe Yol Haritası

Bu doküman ZOLM'u mevcut pazaryeri operasyon platformundan, Türkiye pazaryeri odaklı CRM + ERP + ön muhasebe platformuna dönüştürmek için izlenecek ana plandır.

Planın amacı tek seferde büyük bir yeniden yazım yapmak değil; mevcut çalışan pazaryeri, CRM, ürün, kargo, iade, reklam ve raporlama omurgasını bozmadan yeni çekirdeği parça parça devreye almaktır.

## 0. Ana Karar

ZOLM'da şu modüller hedef ürün kapsamına alınır:

- Ön Muhasebe
- Cariler
- Stok
- Satışlar
- Hızlı Satış
- Tahsilat
- Satın Alma
- Kasa ve Banka
- Virman
- e-Fatura
- Raporlar
- Asistan

Bu modüller ayrı ayrı ekranlar gibi görünse de teknik olarak dört ana omurgaya bağlanır:

1. **Cari ve CRM omurgası**
2. **Ön muhasebe ve finans omurgası**
3. **Ticari operasyon omurgası**
4. **Raporlama ve asistan omurgası**

## 1. Mevcut Durum Teşhisi

### 1.1. Güçlü Yanlar

- ZOLM gerçek operasyonlardan büyümüş bir Laravel 13 + Livewire 4 uygulamasıdır.
- Çoklu pazaryeri omurgası büyük ölçüde oluşmuştur.
- `channel_orders`, `channel_order_items`, `order_financial_events`, `order_profit_snapshots` tarafı pazaryeri karlılığı için güçlü bir temel sunar.
- Eski `mp_*` pazaryeri muhasebe katmanı Trendyol Excel/settlement süreçlerinde ciddi iş kuralı birikimi taşır.
- CRM çekirdeği başlamıştır:
  - `crm_contacts`
  - `crm_contact_identities`
  - `crm_timeline_events`
  - `crm_cases`
  - `crm_tasks`
  - `crm_notes`
  - `crm_customer_ledger_entries`
- CRM sayfaları vardır:
  - `App\Livewire\CrmWorkspace`
  - `App\Livewire\CrmCustomerLedger`
- ZOLM Kurumsal Açık Panel Sistemi ürünleşmiş modül tasarımları için iyi bir UI standardı sağlamaktadır.

### 1.2. Zayıf Yanlar

- CRM contact ile muhasebe cari aynı otoriter işletme kimliğine bağlı değildir.
- `crm_customer_ledger_entries` daha çok müşteri karlılık/sipariş defteri gibi çalışır; tam ön muhasebe cari ekstresi değildir.
- Hesap planı, yevmiye fişi, borç/alacak satırı, kasa/banka hareketi ve virman gibi muhasebe çekirdeği henüz yoktur.
- Stok alanları vardır, fakat depo bazlı stok hareket defteri ve stok bakiyesi otoriter model olarak ayrışmamıştır.
- Satış ve satın alma için klasik ticari belge yaşam döngüsü eksiktir.
- e-Fatura/e-Arşiv için belge üst kaydı ve entegratör adaptör katmanı yoktur.
- Asistan için veri çok zengin olsa da güvenli sorgu/aksiyon sınırları tanımlı değildir.

### 1.3. En Kritik Ürün Riski

En büyük risk, CRM müşteri, cari hesap, tedarikçi, pazaryeri, kargo firması ve banka kayıtlarının ayrı modeller halinde kopuk büyümesidir.

Bu olursa birkaç ay içinde şu sorunlar çıkar:

- Aynı müşteri birden fazla kayıtta görünür.
- CRM 360 ile cari ekstre uyuşmaz.
- Tahsilat bir yerde kapanır, CRM'de açık görünür.
- Satış faturası başka müşteriyle, sipariş başka müşteriyle eşleşir.
- Raporlar güven kaybeder.

Bu yüzden ilk ana geliştirme **tekil Party/Cari kimlik katmanı** olmalıdır.

## 2. Referans Repo Kararları

Bu repolar kod kopyalama kaynağı değil, mimari ve ürün davranışı referansıdır. Lisanslar ayrıca kontrol edilmeden kod alınmaz.

| Alan | Referans | Kullanım Şekli |
| --- | --- | --- |
| Ön muhasebe çekirdeği | `andrewdwallo/erpsaas` | Hesap planı, çift taraflı muhasebe, yevmiye ve finans otomasyonu fikri |
| Modüler muhasebe | `liberu-accounting/accounting-laravel` | Ledger, invoice, inventory valuation, bank reconciliation fikirleri |
| CRM | `krayin/laravel-crm` | Olgun CRM akışları, lead/contact/pipeline yapısı |
| CRM ekosistem uyumu | `liberu-crm/crm-laravel` | Laravel/Livewire/Filament yakınlığı ve activity/task modeli |
| Multi-company CRM | `Roskus/prospero-flow-crm` | Çok şirketli yapı fikri |
| Basit ERP akışı | `abdiiwan1841/Laravel-ERP-System` | Satış, satın alma, fatura ve stok akışı fikri |
| Hızlı satış/POS | `lakasir/lakasir` | POS, kasa, ürün seçimi, hızlı satış ergonomisi |
| POS + inventory | `abi-collab/open-pos-inventory-laravelvue` | Sepet, barkod, stoklu satış ekranı fikri |

## 3. Hedef Modül Haritası

### 3.1. Ön Muhasebe

Amaç:

- ZOLM'da tüm finansal olayların hesap planı ve yevmiye mantığına bağlanması.
- Pazaryeri hakedişi, satış, iade, tahsilat, ödeme, virman ve kasa/banka hareketlerinin aynı finans çekirdeğinde izlenmesi.

Ana varlıklar:

- `accounts`
- `account_groups`
- `journal_entries`
- `journal_lines`
- `financial_documents`
- `receivables`
- `payables`
- `fiscal_periods`

İlk MVP:

- Hesap planı
- Manuel yevmiye fişi
- Satıştan alacak oluşturma
- Tahsilatla alacağı kapatma
- Cari ekstre

### 3.2. Cariler

Amaç:

- CRM contact ile muhasebe cariyi tek işletme kimliğinde birleştirmek.
- Müşteri, tedarikçi, pazaryeri, kargo firması, banka ve diğer tarafları aynı üst modelde tutmak.

Ana varlıklar:

- `parties`
- `party_roles`
- `party_identities`
- `party_addresses`
- `party_contacts`
- `party_account_settings`

İlk MVP:

- Mevcut `crm_contacts` kayıtlarını `parties` ile ilişkilendirme
- Müşteri rolü
- Tedarikçi rolü
- Cari ekstreye geçiş
- CRM 360 içinde cari özet

### 3.3. Stok

Amaç:

- Stok miktarını sadece ürün kartındaki bir alan olmaktan çıkarıp hareket defteriyle yönetmek.

Ana varlıklar:

- `warehouses`
- `stock_movements`
- `stock_balances`
- `stock_reservations`
- `stock_counts`
- `stock_movement_reasons`

İlk MVP:

- Tek varsayılan depo
- Satış çıkışı
- İade girişi
- Manuel düzeltme
- Kritik stok uyarısı

### 3.4. Satışlar

Amaç:

- Pazaryeri siparişlerini ve manuel satışları ticari belge akışına bağlamak.

Ana varlıklar:

- `sales_orders`
- `sales_order_items`
- `sales_invoices`
- `sales_invoice_items`
- `sales_returns`

İlk MVP:

- Manuel satış belgesi
- Pazaryeri siparişinden satış taslağı
- Satış onayı sonrası cari alacak
- Satış onayı sonrası stok düşümü

### 3.5. Hızlı Satış

Amaç:

- POS/dokunmatik satış, barkod okutma, hızlı tahsilat ve kasa etkisini ayrı ergonomide sunmak.

Ana varlıklar:

- `pos_terminals`
- `pos_shifts`
- `quick_sales`
- `quick_sale_items`
- `quick_sale_payments`

İlk MVP:

- Barkod veya ürün arama
- Sepet
- Nakit/kart ödeme
- Satış sonrası stok düşümü
- Kasa hareketi

### 3.6. Tahsilat

Amaç:

- Açık alacakları kapatan, kasa/banka hareketi üreten kontrollü ödeme alma akışı.

Ana varlıklar:

- `collections`
- `collection_allocations`
- `payment_methods`

İlk MVP:

- Cari seç
- Açık alacakları gör
- Tam veya kısmi tahsilat al
- Kasa/banka hesabına işle
- Açık işlem bakiyesini güncelle

### 3.7. Satın Alma

Amaç:

- Tedarikçi, alış belgesi, stok girişi ve borç oluşturmayı tek akışa bağlamak.

Ana varlıklar:

- `purchase_orders`
- `purchase_order_items`
- `purchase_invoices`
- `purchase_invoice_items`
- `supplier_price_lists`

İlk MVP:

- Tedarikçi seç
- Ürünleri ekle
- Alış onayı sonrası stok girişi
- Tedarikçi carisine borç oluştur

### 3.8. Kasa ve Banka

Amaç:

- Nakit, banka, POS ve sanal pos hareketlerini tek panelden izlemek.

Ana varlıklar:

- `cash_accounts`
- `bank_accounts`
- `money_transactions`
- `bank_reconciliations`

İlk MVP:

- Kasa hesabı
- Banka hesabı
- Manuel giriş/çıkış
- Tahsilat/ödeme kaynaklı hareketler
- Hareket listesi

### 3.9. Virman

Amaç:

- İki para hesabı arasında tek işlemle transfer yapmak.

Ana varlıklar:

- `money_transfers`
- `money_transfer_lines`

İlk MVP:

- Kaynak hesap
- Hedef hesap
- Tutar
- Tarih
- Açıklama
- İki hesap bakiyesini birlikte güncelleme

### 3.10. e-Fatura

Amaç:

- Satış/alış belgelerinin e-Fatura/e-Arşiv durumunu ZOLM içinde izlemek.

Ana varlıklar:

- `e_documents`
- `e_document_events`
- `e_document_provider_accounts`

İlk MVP:

- Belge üst kaydı
- Taslak/ gönderildi / kabul / red / iptal durumları
- UBL XML ve PDF saklama alanı
- Entegratör adaptör interface'i

### 3.11. Raporlar

Amaç:

- Finans, operasyon, CRM ve pazaryeri sinyallerini tek karar yüzeyinde göstermek.

Ana varlıklar:

- `report_snapshots`
- `kpi_daily_metrics`
- `alert_rules`
- `alert_events`

İlk MVP:

- Cari bakiye
- Açık alacak/borç
- Nakit akışı
- Stok durumu
- Satış/alış özeti
- Pazaryeri karlılık özeti

### 3.12. Asistan

Amaç:

- Kullanıcının doğal dil ile güvenli rapor ve öneri alması.

Ana varlıklar:

- `assistant_queries`
- `assistant_saved_questions`
- `assistant_action_suggestions`
- `automation_rules`
- `automation_runs`

İlk MVP:

- Salt okunur soru-cevap
- Hazır güvenli sorgular
- Rapor özeti
- Aksiyon önerisi
- İnsan onayı olmadan veri değiştirmeme

## 4. Teknik Mimari Kararı

### 4.1. İlk Fazda Büyük `app/Modules` Devrimi Yapılmayacak

ZOLM canlı ve aktif kullanılan bir ürün olduğu için ilk fazda bütün kodu `app/Modules/*` altına taşımak risklidir.

İlk yaklaşım:

- Mevcut `app/Models`, `app/Services`, `app/Livewire` yapısı korunur.
- Yeni domainler namespace ile ayrıştırılır:
  - `App\Models\Accounting\...`
  - `App\Services\Accounting\...`
  - `App\Livewire\Accounting\...`
  - `App\Models\Party\...`
  - `App\Services\Party\...`
- Yeterince olgunlaştıktan sonra modüler klasörleme ayrıca değerlendirilir.

### 4.2. Event Tabanlı Akış Kullanılacak

Doğrudan tabloya tablo yazmak yerine kritik iş olayları event olarak yayınlanır.

Örnekler:

- `MarketplaceOrderReceived`
- `SalesDocumentApproved`
- `CollectionRecorded`
- `PurchaseInvoiceApproved`
- `StockMovementPosted`
- `MoneyTransferPosted`
- `EDocumentStatusChanged`

Her modül kendi etkisini listener/action sınıfıyla işler.

### 4.3. Idempotent Tasarım Zorunlu

Pazaryeri ve Excel import dünyasında aynı veri tekrar tekrar işlenebilir. Bu yüzden:

- Her otomatik kayıtta `source_type`, `source_id`, `source_key` bulunmalı.
- Tekrar çalışan projection çift kayıt üretmemeli.
- Manuel düzeltmeler otomatik sync ile ezilmemeli.

## 5. Ana İş Akışları

### 5.1. Pazaryeri Siparişinden Ön Muhasebeye

1. Pazaryeri siparişi içeri alınır.
2. Sipariş müşteri kimliği `party` ile eşleşir veya yeni party oluşur.
3. Sipariş kalemi ürün/reçete/listing ile eşleşir.
4. Satış taslağı oluşur.
5. Onay sonrası cari alacak açılır.
6. Stok rezerv/düşüm yapılır.
7. Muhasebe fişi oluşur.
8. Settlement gelince komisyon/kargo/stopaj farkları işlenir.
9. Kalan net alacak tahsilata dönüşür.
10. CRM timeline, cari ekstre ve raporlar güncellenir.

### 5.2. Manuel Satıştan Tahsilata

1. Cari seçilir.
2. Ürün/hizmet satırı eklenir.
3. Satış belgesi onaylanır.
4. Cari alacak oluşur.
5. Tahsilat alınır.
6. Kasa/banka hareketi oluşur.
7. Alacak kapanır veya kısmi açık kalır.

### 5.3. Satın Almadan Stok ve Borca

1. Tedarikçi seçilir.
2. Alış belgesi oluşturulur.
3. Belge onaylanır.
4. Stok girişi yapılır.
5. Tedarikçi borcu açılır.
6. Ödeme yapılınca kasa/banka hareketi oluşur.
7. Borç kapanır veya kısmi açık kalır.

### 5.4. Hızlı Satış

1. POS vardiyası açılır.
2. Barkod okutulur veya ürün seçilir.
3. Sepet oluşur.
4. Nakit/kart tahsilat alınır.
5. Satış kaydı, stok düşümü, kasa/banka hareketi ve CRM purchase history birlikte güncellenir.

## 6. Aşamalı Yol Haritası

### Faz 0 — Hazırlık ve Kesin Kapsam

Süre: 2-3 gün

- [ ] Mevcut CRM, pazaryeri finans, ürün, kargo ve iade tablolarının otorite haritasını çıkar.
- [ ] Hangi tablo hangi verinin sahibi kararını yaz.
- [ ] Eski `mp_*` ve yeni `channel_*` katmanlarının finansal rol ayrımını netleştir.
- [ ] Parola modül listesini ZOLM menü yapısına eşleştir.
- [ ] İlk MVP'de yapılmayacakları yaz.

Çıkış kriteri:

- [ ] `docs/` altında onaylı ürün ve teknik kapsam dokümanı var.

### Faz 1 — Party + Cari Temeli

Süre: 1-2 hafta

- [ ] `parties` migration tasarla.
- [ ] `party_roles` migration tasarla.
- [ ] `party_identities` migration tasarla.
- [ ] Mevcut `crm_contacts` için `party_id` bağlantı stratejisi belirle.
- [ ] Mevcut CRM kimlik çözümleme akışını party ile uyumlu hale getir.
- [ ] CRM 360 ekranına party/cari kimliği ekle.
- [ ] Müşteri ve tedarikçi rollerini destekle.

Çıkış kriteri:

- [ ] Aynı müşteri hem CRM hem cari tarafında tek üst kimlikten izlenir.
- [ ] Yeni pazaryeri müşterisi otomatik party ile eşleşir.

### Faz 2 — Ön Muhasebe Çekirdeği

Süre: 2-4 hafta

- [ ] `accounts` ve `account_groups` tablolarını ekle.
- [ ] Varsayılan hesap planı seed'i oluştur.
- [ ] `journal_entries` ve `journal_lines` tablolarını ekle.
- [ ] Borç/alacak dengesi validasyonu yaz.
- [ ] Manuel yevmiye fişi kaydetme action'ı yaz.
- [ ] Kaynak bazlı fiş üretimi için `source_type/source_id/source_key` standardı ekle.
- [ ] Temel finans testlerini yaz.

Çıkış kriteri:

- [ ] Dengelenmeyen fiş kaydedilemez.
- [ ] Her fiş kaynak kaydıyla izlenebilir.

### Faz 3 — Cari Açık İşlem + Tahsilat/Ödeme

Süre: 2 hafta

- [ ] `receivables` tablosu.
- [ ] `payables` tablosu.
- [ ] `collections` tablosu.
- [ ] `payments` tablosu.
- [ ] Kısmi tahsilat/ödeme desteği.
- [ ] Cari ekstre sorgu servisi.
- [ ] CRM 360 içinde açık alacak/borç özeti.

Çıkış kriteri:

- [ ] Satıştan açık alacak oluşur.
- [ ] Tahsilat alacağı tamamen veya kısmen kapatır.
- [ ] Cari ekstre doğru bakiye gösterir.

### Faz 4 — Kasa, Banka ve Virman

Süre: 1-2 hafta

- [ ] `cash_accounts`.
- [ ] `bank_accounts`.
- [ ] `money_transactions`.
- [ ] `money_transfers`.
- [ ] Tahsilat/ödeme ile kasa/banka hareketi entegrasyonu.
- [ ] Virman formu.
- [ ] Hesap bakiyesi hesaplama servisi.

Çıkış kriteri:

- [ ] Tahsilat kasa/banka bakiyesini artırır.
- [ ] Ödeme kasa/banka bakiyesini azaltır.
- [ ] Virman iki hesabı birlikte günceller.

### Faz 5 — Stok Hareket Defteri

Süre: 2-3 hafta

- [ ] `warehouses`.
- [ ] `stock_movements`.
- [ ] `stock_balances`.
- [ ] Varsayılan depo.
- [ ] Satış çıkışı.
- [ ] Alış girişi.
- [ ] İade girişi.
- [ ] Manuel stok düzeltme.
- [ ] Kritik stok uyarısı.

Çıkış kriteri:

- [ ] Stok bakiyesi hareketlerden türetilebilir.
- [ ] Ürün kartındaki stok ile hareket defteri uyumsuzluğu raporlanır.

### Faz 6 — Satışlar ve Satın Alma

Süre: 3-4 hafta

- [ ] Satış belgesi modeli.
- [ ] Satış belge kalemleri.
- [ ] Satış onay action'ı.
- [ ] Alış belgesi modeli.
- [ ] Alış belge kalemleri.
- [ ] Alış onay action'ı.
- [ ] Satış -> cari alacak -> stok çıkışı -> fiş akışı.
- [ ] Alış -> tedarikçi borcu -> stok girişi -> fiş akışı.

Çıkış kriteri:

- [ ] Satış ve alış belgeleri muhasebe ve stok etkisi üretir.

### Faz 7 — Pazaryeri Finans Köprüsü

Süre: 2-3 hafta

- [ ] `channel_orders` -> party/cari eşleşmesi.
- [ ] Pazaryeri siparişinden satış taslağı veya cari hareket üretimi.
- [ ] Settlement finans olaylarını muhasebe fişine bağlama.
- [ ] Komisyon, kargo, stopaj, ceza, kampanya maliyeti için hesap eşleme ayarları.
- [ ] Eski `mp_*` finans kuralları ile yeni finans çekirdeği arasındaki fark raporu.

Çıkış kriteri:

- [ ] Bir pazaryeri siparişinin müşteri, cari, stok, karlılık ve muhasebe etkisi izlenebilir.

### Faz 8 — Hızlı Satış

Süre: 2 hafta

- [ ] POS ekran tasarımı.
- [ ] Barkod/ürün arama.
- [ ] Sepet.
- [ ] Ödeme tipi seçimi.
- [ ] Vardiya açılış/kapanış.
- [ ] POS satışından kasa, stok, cari ve CRM etkisi.

Çıkış kriteri:

- [ ] Hızlı satış 44px mobil/dokunmatik hedeflerle çalışır.
- [ ] Satış sonrası stok ve kasa etkisi otomatik oluşur.

### Faz 9 — e-Fatura/e-Arşiv

Süre: 2-4 hafta

- [ ] `e_documents`.
- [ ] `e_document_events`.
- [ ] Entegratör interface'i.
- [ ] UBL/PDF saklama stratejisi.
- [ ] Satış faturasından e-belge taslağı.
- [ ] Mükellefiyet sorgusu için adapter alanı.
- [ ] Durum takibi.

Çıkış kriteri:

- [ ] ZOLM satış belgesi e-belge taslağına dönüşür.
- [ ] Entegratör değişse bile ana satış/muhasebe modeli değişmez.

### Faz 10 — Raporlar

Süre: sürekli, ilk MVP 2 hafta

- [ ] Cari bakiye raporu.
- [ ] Açık alacak/borç yaşlandırma.
- [ ] Kasa/banka hareket raporu.
- [ ] Stok durumu.
- [ ] Satış/alış özeti.
- [ ] Pazaryeri karlılık köprüsü.
- [ ] Excel exportlarda proje standardını uygula.

Çıkış kriteri:

- [ ] Kullanıcı finans ve operasyon durumunu tek ekrandan okuyabilir.

### Faz 11 — Asistan

Süre: veri modeli oturduktan sonra 2-3 hafta

- [ ] Salt okunur güvenli sorgu listesi.
- [ ] Doğal dil -> izinli rapor sorgusu eşlemesi.
- [ ] Rapor özeti.
- [ ] Aksiyon önerisi.
- [ ] İnsan onaylı aksiyon modeli.
- [ ] Asistan cevaplarında kaynak/veri tarihi gösterimi.

Çıkış kriteri:

- [ ] Asistan veri değiştirmeden rapor ve öneri üretir.
- [ ] Her öneri kullanıcı onayına bağlıdır.

## 7. Ekran Planı

### 7.1. Ana Menü

- Ön Muhasebe
- Cariler
- Stok
- Satışlar
- Hızlı Satış
- Tahsilat
- Satın Alma
- Kasa ve Banka
- Virman
- e-Fatura
- Raporlar
- Asistan

### 7.2. Ön Muhasebe Ekranları

- Hesap Planı
- Yevmiye Fişleri
- Açık Alacaklar
- Açık Borçlar
- Cari Ekstre
- Finans Ayarları

### 7.3. Cariler Ekranları

- Cari Listesi
- Cari 360
- Cari Ekstre
- Cari Notlar/Görevler
- Cari Risk ve Segmentler

### 7.4. Stok Ekranları

- Ürünler
- Depolar
- Stok Hareketleri
- Kritik Stok
- Sayım/Düzeltme

### 7.5. Satış ve Satın Alma Ekranları

- Satış Listesi
- Satış Belgesi
- İade
- Satın Alma Listesi
- Alış Belgesi
- Tedarikçi Fiyatları

### 7.6. Kasa/Banka/Virman Ekranları

- Kasa Hesapları
- Banka Hesapları
- Para Hareketleri
- Virman
- Mutabakat

### 7.7. Hızlı Satış Ekranları

- POS
- Vardiya
- Gün Sonu
- Hızlı Satış Raporu

### 7.8. Rapor ve Asistan Ekranları

- Finans Dashboard
- Operasyon Dashboard
- CRM Dashboard
- Pazaryeri Kar Merkezi
- Asistan Sorgu Paneli
- Aksiyon Önerileri

## 8. Uygulama Kuralları

- Canlı pazaryeri ve üretim motorları bozulmayacak.
- Tüm büyük özellikler feature flag ile açılacak.
- Migration'lar backward compatible olacak.
- Mevcut `crm_contacts` ve `crm_customer_ledger_entries` silinmeyecek.
- Önce yeni çekirdek paralel çalışacak, sonra ekranlar kademeli taşınacak.
- GPL lisanslı repolardan kod kopyalanmayacak; yalnızca fikir alınacak.
- Excel exportlarda `ExcelService::cleanString()` ve `setCellValueExplicit()` standardı korunacak.
- Yeni tablo ekranlarında görünür kolon, sıralama, resize ve mobil kart görünümü standardı uygulanacak.
- Yeni UI ZOLM Kurumsal Açık Panel Sistemi ile yapılacak.

## 9. İlk 10 İşlik Sprint Backlog

Bu liste uygulamaya başlarken tik ata ata ilerlenecek ilk somut backlog'tur.

1. [ ] Mevcut CRM/cari/marketplace finans veri otoritesi haritasını çıkar.
2. [ ] `party` model karar dokümanını yaz.
3. [ ] `parties`, `party_roles`, `party_identities` migration taslağını hazırla.
4. [ ] `crm_contacts.party_id` geçiş stratejisini belirle.
5. [ ] Hesap planı minimal tasarımını çıkar.
6. [ ] `accounts`, `journal_entries`, `journal_lines` migration taslağını hazırla.
7. [ ] Cari açık işlem tasarımını çıkar: `receivables`, `payables`.
8. [ ] Kasa/banka hesap modeli tasarımını çıkar.
9. [ ] İlk ekran setini belirle: Cari Listesi, Cari 360, Hesap Planı, Yevmiye, Açık Alacaklar.
10. [ ] Feature flag isimlerini belirle: `accounting_enabled`, `party_core_enabled`, `cash_bank_enabled`.

## 10. Başarı Kriterleri

- [ ] Bir müşteri tek party kaydıyla CRM, cari, sipariş ve tahsilat tarafında izlenir.
- [ ] Her satış bir cari hareket ve gerektiğinde muhasebe fişi üretir.
- [ ] Her tahsilat açık alacağı kapatır ve kasa/banka hareketi üretir.
- [ ] Her alış tedarikçi borcu ve stok girişi üretir.
- [ ] Her virman iki para hesabında dengeli hareket oluşturur.
- [ ] Pazaryeri siparişi müşteri, stok, karlılık, cari ve muhasebe etkisiyle takip edilir.
- [ ] Raporlar transaction tablolarından güvenilir özet üretir.
- [ ] Asistan veri değiştirmeden, kaynaklı ve kontrollü cevap verir.

## 11. Hemen Başlama Kararı

İlk uygulama adımı:

**Party + Cari Temeli**

Çünkü bu temel atılmadan:

- CRM güçlenemez.
- Ön muhasebe doğru kurulamaz.
- Tahsilat güvenilir olmaz.
- Satış/satın alma belgeleri doğru cariyle bağlanamaz.
- Asistan güvenilir cevap veremez.

Bu nedenle ilk gerçek geliştirme PR'ı şu kapsamda olmalıdır:

- `parties`
- `party_roles`
- `party_identities`
- `crm_contacts.party_id`
- `PartyIdentityResolver`
- CRM 360 içinde party/cari özeti
- Feature flag: `party_core_enabled`
