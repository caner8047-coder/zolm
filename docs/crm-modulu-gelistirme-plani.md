# ZOLM CRM Modülü Geliştirme Planı

Bu plan CRM modülünü ZOLM'ün müşteri merkezli operasyon kalbi olarak konumlandırır. Amaç; pazaryeri siparişleri, müşteri soruları, iadeler, kargo raporları, tedarik kayıtları ve finans sinyallerini tek müşteri defterinde birleştirmek, operasyona aksiyon üreten bir çalışma alanı sunmaktır.

## 1. Ürün Vizyonu

CRM modülü klasik bir kişi rehberi değildir. ZOLM içinde her müşteri için canlı bir 360 görünüm sağlar:

- Sipariş geçmişi ve ciro etkisi
- Açık müşteri soruları ve yanıt SLA riski
- İade inceleme ve karar süreçleri
- Kargo farkı, kayıp veya tazmin sinyalleri
- Tedarik ve üretim gecikme riski
- Zarar eden sipariş veya finans anomali kayıtları
- İç not, görev, vaka ve takip aksiyonları

CRM bu nedenle bağımsız bir ekran değil, tüm modüllerin ortak müşteri hafızasıdır.

## 2. Katmanlı Mimari

### 2.1 Veri Katmanı

Çekirdek tablolar:

- `crm_contacts`: Tekilleştirilmiş müşteri profili
- `crm_contact_identities`: Pazaryeri, telefon, e-posta, vergi no ve kaynak kimlikleri
- `crm_timeline_events`: Sipariş, soru, iade, kargo, tedarik, finans ve CRM olayları
- `crm_cases`: Operasyonel takip gerektiren açık vakalar
- `crm_tasks`: İç görevler ve takip planı
- `crm_notes`: İç müşteri notları
- `crm_customer_ledger_entries`: Müşteri cari hareketleri; platform, ürün/reçete, tarife, fiyat, komisyon, net ve kâr bilgisi

Bu yapı kaynak veriyi kopyalayıp koparmak yerine, `subject_type` ve `subject_id` ile ilgili modül kaydına geri bağlanır.

### 2.2 Kimlik Çözümleme Katmanı

`CrmIdentityResolver` müşteriyi şu sırayla eşleştirir:

1. Kaynak müşteri kimliği ve mağaza
2. Normalize telefon
3. Normalize e-posta
4. Vergi numarası
5. Normalize ad + şehir

Bu yaklaşım pazaryeri verilerinde sık görülen eksik telefon, farklı yazılmış ad ve kısmi müşteri bilgisini tolere eder.

### 2.3 Projeksiyon Katmanı

`CrmProjectionService` operasyon modüllerinden CRM'e olay üretir:

- `ChannelOrder` -> sipariş timeline, iade/zarar vakası
- `ChannelOrderItem` -> müşteri cari satırı, reçete/tarife/komisyon/kârlılık defteri
- `MarketplaceQuestion` -> soru timeline, yanıt bekleyen vaka
- `ReturnIntakeItem` -> iade timeline, karar/hasar vakası
- `ChannelClaim` -> pazaryeri iade timeline ve karar vakası
- `CargoReportItem` -> kargo farkı timeline ve vaka
- `SupplyOrder` -> tedarik timeline, gecikme vakası

Projeksiyon idempotent çalışır. Aynı kaynak tekrar işlendiğinde `event_key` ve `case_key` ile çift kayıt üretmez.

### 2.4 Kaynak Bağlantı Katmanı

`CrmSourceLinkService` iki yönlü entegrasyon sözleşmesini yönetir:

- Kaynak modülden CRM'e geçiş: `source` ve `sourceId` ile doğru müşteri 360 kaydı açılır.
- CRM'den kaynak modüle dönüş: timeline veya vaka `subject_type` / `subject_id` bilgisinden ilgili operasyon ekranına aksiyon linki üretilir.
- Sipariş, soru, iade, pazaryeri iade, kargo ve tedarik için aksiyon URL'leri tek servis üzerinden standardize edilir.
- Modül ekranlarında değişen route veya query parametreleri CRM view içine dağılmaz; bağlantı kontratı servis içinde kalır.
- `CrmCustomerSnapshotService` kaynak ekranlarda risk, değer, açık vaka ve 360 bağlantısını kompakt CRM sinyali olarak gösterir.
- `CrmAlertRuleService` farklı modül vakaları aynı müşteride çakıştığında otomatik CRM uyarı vakaları üretir ve koşul ortadan kalkınca kapatır.
- `CrmCustomerLedgerProjectionService` pazaryeri sipariş satırlarını ve manuel cari kayıtları aynı müşteri defterinde idempotent şekilde birleştirir.

### 2.5 Uygulama Katmanı

Livewire component:

- `App\Livewire\CrmWorkspace`
- `App\Livewire\CrmCustomerLedger`

Sorumlulukları:

- Filtrelenebilir ve sıralanabilir müşteri ledger'ı
- Kolon görünürlüğü yönetimi
- Seçili müşteri 360 paneli
- Aksiyon Merkezi ile kaynak modüle geri dönüş
- Not ve görev ekleme
- Vaka kapatma
- Son 7 günü hızlı güncelleme
- Müşteri cari ekranı ile manuel hareket kaydı
- Manuel cari hareket düzenleme ve iptal akışı
- Sipariş satırlarından platform, reçete, tarife, komisyon ve kâr projeksiyonu
- Müşteri cari Excel export'u
- CRM 360 panelinden müşteri cari ekranına filtreli geçiş ve seçili müşterinin cari özeti
- Cari özet KPI'larında iptal/iade satırlarını defterde gösterip finansal toplamdan ayrı tutma

Komut:

- `php artisan crm:project`
- `--user-id`
- `--source`
- `--since`
- `--recent-days`

Bu komut ileride cron veya queue tabanlı senkronizasyona taşınabilecek şekilde kaynak bazlı çalışır.

### 2.6 UI Katmanı

CRM arayüzü ZOLM Kurumsal Açık Panel Sistemi'ni takip eder:

- Açık zemin ve beyaz ana section kartları
- Kompakt command bar + ledger ilişkisi
- Mobilde kart liste, desktop'ta tablo
- Sağda müşteri 360 yardımcı paneli
- `rounded-[10px]`, `rounded-[8px]`, `rounded-[6px]` hiyerarşisi
- Veri yoğun ama sade Venture uyumlu yüzey

## 3. Entegrasyon Matrisi

| Modül | CRM Sinyali | Vaka Kuralı | Entegrasyon Davranışı |
| --- | --- | --- | --- |
| Pazaryeri Sipariş | Sipariş timeline, ciro, kâr | İade durumlu veya zarar eden sipariş | CRM'den sipariş ekranına filtreli dönüş |
| Müşteri Soruları | Soru timeline | Açık, bekleyen veya taslak soru | CRM'den soru merkezinde seçili kaydı açma |
| İade Merkezi | İade kabul ve karar timeline | Hasarlı, inceleme bekleyen veya karar bekleyen iade | CRM'den karar havuzunda seçili iadeyi açma |
| Pazaryeri İade | Claim timeline | Bekleyen veya teslim edilmiş claim | CRM'den pazaryeri iade merkezinde claim açma |
| Kargo Raporu | Kargo farkı timeline | Hatalı veya tazmin edilebilir satır | CRM'den tazmin sekmesinde talep başlatma |
| Tedarik Raporu | Tedarik/üretim timeline | Geciken söz tarihi | CRM'den tedarik raporuna sipariş filtresiyle dönüş |
| Finans | Ciro, kâr, komisyon etkisi | Negatif kâr veya anomali | Finans mutabakat görevleri |

## 4. Geliştirme Fazları

### Faz 1: CRM Çekirdeği

Tamamlanan kapsam:

- CRM tabloları ve modelleri
- Kimlik çözümleme servisi
- Kaynak projeksiyon servisi
- CRM artisan komutu
- CRM workspace route, gate ve sidebar linki
- Müşteri ledger, filtreler, kolon yönetimi
- Müşteri 360 paneli
- Not, görev ve vaka kapatma akışı
- Feature flag: `CRM_ENABLED`
- Feature test kapsamı

### Faz 2: Modül İçi Bağlantılar

Tamamlanan kapsam:

- Sipariş detayında "CRM 360 Aç" bağlantısı
- Soru ekranlarında "CRM 360" bağlantısı
- İade kabul / pazaryeri iade ekranlarında "CRM 360" bağlantısı
- Kargo raporunda hatalı satırdan CRM'e geçiş
- Tedarik raporunda müşteri bazlı CRM bağlantısı
- CRM'de Aksiyon Merkezi ile ilgili kaynak modüle geri dönüş
- Vaka ve timeline kartlarında kaynak aksiyon butonları
- Kargo tazmin ekranında `cargoItem` query parametresiyle talep modalı açma
- Kaynak ekranlarda ortak CRM sinyal kartı: risk, değer, açık vaka ve 360 geçişi
- Çapraz modül CRM uyarıları: çoklu operasyon baskısı, tedarik + müşteri deneyimi çakışması, iade + kargo çakışması, yüksek değerli riskli müşteri
- CRM alt menüsünde Müşteri Cari defteri
- Manuel cari kayıtların CRM 360 timeline olayına dönüşmesi
- Manuel cari kayıt düzenleme/iptal işlemlerinin CRM 360 özetine yansıması
- Pazaryeri sipariş satırlarının müşteri cari hareketlerine projekte edilmesi

Kalan geliştirme:

- Kaynak detay panellerinde CRM özet snippet'i

### Faz 3: Otomasyon ve SLA

Tamamlanan kapsam:

- Projeksiyon sonunda CRM uyarı kurallarının otomatik çalışması
- Uyarı koşulu kalkınca CRM uyarı vakasının otomatik kapanması
- CRM snapshot içinde aktif CRM uyarı sayısının görünmesi

Hedefler:

- Günlük veya saatlik `crm:project --recent-days=...` zamanlaması
- SLA yaklaşan vaka bildirimleri
- Yüksek risk müşteri uyarıları
- Açık soru/iade/kargo vaka kuyrukları

### Faz 4: Aksiyon Motoru

Hedefler:

- CRM vakasından müşteri sorusu yanıt taslağı üretme
- İade karar akışına CRM vaka aksiyonu bağlama
- Kargo tazmin talebi açma görevleri
- Tedarik gecikmesini üretim planına görev olarak düşme

### Faz 5: Analitik ve Segmentasyon

Hedefler:

- Müşteri değeri, risk ve memnuniyet segmentleri
- Tekrarlı iade, zarar, soru ve kargo problemi analizleri
- Pazaryeri/mağaza bazlı CRM sağlık skoru
- Excel export ve yönetici raporları

## 5. Operasyonel Kurallar

- CRM projeksiyonları idempotent kalmalı.
- Mevcut kaynak tabloları silinmemeli veya yeniden modellenmemeli.
- Yeni kaynak eklenirken önce `source_type`, `event_type`, `case_key` standardı belirlenmeli.
- Çok kullanıcılı veride her CRM kaydı `user_id` ile izole edilmeli.
- Yeni Excel export eklenirse `ExcelService::cleanString()` ve `setCellValueExplicit()` standardı uygulanmalı.
- Büyük riskli entegrasyonlar feature flag ile açılmalı.

## 6. Kabul Kriterleri

- `/crm` yetkili kullanıcıda açılır.
- CRM kapalıysa route feature flag ile kapanır.
- Projeksiyon tekrar çalışınca çift timeline/vaka oluşmaz.
- Telefon formatları normalize edilip aynı müşteride birleşir.
- Ledger mobilde kart, desktop'ta tablo olarak çalışır.
- Kolon görünürlüğü kaydedilir.
- Müşteri 360 panelinde kimlikler, vakalar, görevler, notlar ve timeline görünür.
- Not ekleme timeline'a olay olarak düşer.
- Testler ve frontend build başarılıdır.
