# ZOLM Çoklu Kargo Entegrasyonu — Hesap Kurulumu ve Taşıyıcı Sürücüleri

## Notion taslağı

- **Başlık:** ZOLM Çoklu Kargo Entegrasyonu — Hesap Kurulumu ve Taşıyıcı Sürücüleri
- **Tür:** Reference
- **Kategori:** Engineering / Operations
- **Etiketler:** cargo, integration, surat, yurtici, aras, ptt, hepsijet, dhl, trendyol-express
- **Durum:** In Review
- **Sorumlu:** Atanacak
- **Yayın tarihi:** Henüz yayınlanmadı
- **Son gözden geçirme:** 2026-07-21

## Özet

Kargo Operasyon Merkezi, mevcut Sürat Kargo davranışı korunarak taşıyıcıdan bağımsız bir connector sözleşmesine geçirildi. Yurtiçi Kargo, Aras Kargo, PTT Kargo, HepsiJet ve DHL Express için resmi protokollere bağlı sürücüler ile ortak hesap ekleme/düzenleme/bağlantı testi yüzeyi eklendi. Trendyol Express ayrı bir kurumsal kargo hesabı yerine Trendyol mağaza/paket akışı üzerinden yönetilir.

## İş ihtiyacı ve kullanıcıya etkisi

- Bir müşterinin Sürat dışında bir kargo firmasıyla çalışabilmesi için ZOLM çekirdeğinde firma bağımsız gönderi orkestrasyonu gerekir.
- Kullanıcı artık Gönderi Defteri'nde taşıyıcı seçerek taslak oluşturabilir ve kayıtları taşıyıcıya göre filtreleyebilir.
- Taşıyıcılar ekranı geliştirici hazırlık durumunu değil, kullanıcının gerçek hesap durumunu gösterir: **Kuruluma hazır**, **Test bekliyor**, **Bağlı** veya **Kontrol gerekli**.
- Mevcut Sürat hesapları, gönderileri, raporları ve WooCommerce–Sürat eşleştirmesi geriye uyumlu kalır.

## Entegrasyon matrisi

| Taşıyıcı | ZOLM durumu | Canlı sürücü | Sonraki gereksinim |
| --- | --- | --- | --- |
| Sürat Kargo | Aktif | Evet | Mevcut sertifikasyonu ve hesap akışını koru |
| Yurtiçi Kargo | Kuruluma hazır | Evet | Web servis kullanıcı adı/şifresi girilir; SOAP bağlantısı test edilir |
| Aras Kargo | Kuruluma hazır | Evet | Web servis kullanıcı adı/şifresi girilir; SOAP bağlantısı test edilir |
| PTT Kargo | Kuruluma hazır | Evet | Müşteri No, şifre, 12 haneli barkod başlangıç/bitiş aralığı ve isteğe bağlı Posta Çeki No girilir |
| HepsiJet | Kuruluma hazır | Evet | API kullanıcısı, firma/adres/cross-dock kodları girilir |
| DHL Express | Kuruluma hazır | Evet | MyDHL API kullanıcısı, müşteri numarası ve gönderici adresi girilir |
| Trendyol Express | Pazaryeri yönetimli | Ayrı sürücü yok | Trendyol mağaza paket ve kargo alanlarını kullan; bağımsız hesap açma |

Resmi başvuru ve ürün kapsamı kaynakları:

- [Aras Kargo Entegrasyon Hizmetleri](https://www.araskargo.com.tr/hizmetlerimiz/kurumsal-hizmetlerimiz/entegrasyon-hizmetlerimiz)
- [Hepsiburada Developers — HepsiJet API'leri](https://developers.hepsiburada.com/)
- [DHL Express MyDHL API](https://developer.dhl.com/api-reference/dhl-express-mydhl-api)
- [Trendyol Developers](https://developers.trendyol.com/)

## Teknik yaklaşım

### Ortak sözleşme

`CargoCarrierConnector` sözleşmesi korunur. `CargoCarrierRegistry`, isim/alias normalizasyonu, yetenekler ve kurulum alanlarını merkezi olarak sunar. `CargoCarrierManager`, connector sınıflarını Laravel container üzerinden çözer. Yurtiçi, Aras ve PTT ortak SOAP tabanını; HepsiJet ve DHL, Laravel HTTP istemcisini kullanır.

### PTT barkod yönetimi

PTT hesabına 12 haneli başlangıç/bitiş aralığı kaydedilir. `PttCargoConnector`, her gönderi için hesap satırını kilitleyerek sıradaki barkodu atomik ayırır ve PTT dokümanındaki 1/3 çarpanlı algoritmayla 13. kontrol hanesini üretir. Aralık tükendiğinde gönderi oluşturmayı durdurup yeni aralık ister; aynı barkodun eşzamanlı iki gönderide kullanılmasını engeller. `Posta Çeki No`, `rezerve1`; SMS tercihi, `aliciSms` ve `SB` ek hizmet alanlarına aktarılır.

### Gönderi orkestrasyonu

`CargoShipmentService` artık:

- taslakları seçilen `carrier_code` ve taşıyıcı adıyla oluşturur,
- ilgili taşıyıcı hesabını bulur,
- connector'ı manager üzerinden çözer,
- paketlerdeki `cargo_company` alanını gerçek taşıyıcı adıyla günceller,
- olayları gönderinin taşıyıcı koduyla kaydeder,
- fatura satırlarını yalnızca aynı taşıyıcının gönderileriyle eşleştirir.

### Hesap kurulumu ve kimlik bilgileri

`cargo_carrier_accounts.credentials_encrypted` alanı farklı API kimlik bilgilerini şifrelenmiş dizi olarak saklar. Ortak Livewire yüzeyi hesap ekleme, düzenleme, test/canlı ortam seçme ve bağlantı doğrulama işlemlerini yapar. Gizli alanlar düzenlemede ekrana geri basılmaz; boş bırakılırsa mevcut şifre korunur. Mevcut Sürat'e özel kolonlar kaldırılmadı; Sürat popup'ı aynı kolonları okumaya ve yazmaya devam eder. Migration yalnızca nullable alan ekler.

Hesap penceresinin dış kabuğu ve gönderici bilgileri bütün taşıyıcılarda ortaktır; kimlik bilgisi alanları taşıyıcının `setup_fields` şemasından üretilir. Sürat gönderim/sorgulama, Yurtiçi/Aras web servis, PTT müşteri ve barkod aralığı, HepsiJet firma/adres/cross-dock, DHL Express ise MyDHL alanlarını gösterir. Tarayıcı ve parola yöneticilerinin ZOLM giriş e-posta/şifresini bu API alanlarına otomatik doldurmaması için entegrasyon formu ayrı bir form türü olarak işaretlenir; parola alanları `new-password` otomatik tamamlama politikası kullanır.

Sürat kartı artık ayrı entegrasyon sekmesine geçmez; diğer taşıyıcılarla aynı popup'ı açar. Eski `activeTab=surat` bağlantıları Taşıyıcılar yüzeyine yönlendirilir. Eski ayarlar ekranındaki hesaplar, şifreler ve endpoint ayarları aynı veri modelinde kaldığı için yeniden kurulum gerekmez.

### Pazaryeri aksiyonları

`MarketplaceOrderActionService`, eski Sürat aksiyon kodlarını korurken `cargo_create_shipment` ve `cargo_refresh_tracking` genel aksiyonlarını kabul eder. Taşıyıcı `request_context_json.carrier` üzerinden seçilebilir; eksikse mevcut varsayılan olan `surat` kullanılır.

## Değiştirilen bileşenler

- `config/cargo.php`: taşıyıcı kataloğu, alias, yetenek ve hazırlık durumları
- `CargoCarrierRegistry`: merkezi taşıyıcı metadata ve normalizasyon
- `CargoCarrierManager`: connector çözümleme ve korumalı hata üretimi
- `CargoShipmentService`: taşıyıcı-bağımsız gönderi orkestrasyonu
- `SyncCargoTrackingCommand`: sürücüsü etkin bütün taşıyıcıları sorgulayan komut
- `ShipmentLedger`: taşıyıcı seçimi, filtreleme ve genel aksiyon metinleri
- `CarrierIntegrations`: hesap ekleme, düzenleme, durum ve bağlantı testi
- `YurticiCargoConnector`, `ArasCargoConnector`: SOAP gönderi/iptal/takip sürücüleri
- `HepsiJetCargoConnector`: token, gönderi/iptal/takip REST sürücüsü
- `DhlExpressCargoConnector`: MyDHL gönderi ve takip sürücüsü
- `PttCargoConnector`: PTT veri yükleme, barkod silme, takip ve atomik barkod aralığı yönetimi
- `MarketplaceOrderActionService`: genel kargo aksiyon sözleşmesi
- `CargoCarrierAccount`: şifrelenmiş genel credentials alanı

## Veri modeli ve migration

Migration: `2026_08_04_470000_add_generic_credentials_to_cargo_carrier_accounts.php`

- Yalnızca nullable `LONGTEXT credentials_encrypted` alanı ekler.
- Modelde `encrypted:array` cast kullanılır.
- Mevcut satırlar ve Sürat alanları değiştirilmez.
- Geri alma işlemi yalnızca yeni kolonu kaldırır; geri almadan önce yeni taşıyıcı credential verisinin dışa alınması gerekir.

## Kullanım

1. Kargo Operasyon Merkezi → **Taşıyıcılar** bölümünde taşıyıcıdan **Hesap ekle** seçin.
2. Önce **Test** ortamında firmanın verdiği kullanıcı/anahtar ve gönderici bilgilerini kaydedin.
3. Kaydedilen hesapta **Test et** seçin; başarılı hesap **Bağlı** olur.
4. Gerçek ortam bilgilerini alınca hesabı **Canlı** ortama çevirip yeniden test edin.
5. Gönderi Defteri'nde taşıyıcıyı seçerek sipariş, iade/değişim veya tedarik taslağını oluşturun.
6. Sürat dahil API hesabı kullanan taşıyıcılar aynı hesap popup'ından; Trendyol Express ise **Trendyol mağazasını bağla** akışından yönetilir.

PTT için PTT’nin verdiği Müşteri No ve şifreye ek olarak 12 haneli ilk/son barkod değerlerini girin. Posta Çeki No yoksa boş bırakılabilir. SMS seçeneği yalnızca sözleşmede SMS hizmeti açıksa etkinleştirilmelidir.

## Yetki ve feature flag

- Mevcut kullanıcı/tenant filtreleri korunur.
- Yeni bir genel feature flag eklenmedi; Sürat davranışı aynı kaldığı ve sürücüsüz taşıyıcılarda dış çağrı teknik olarak mümkün olmadığı için güvenlik sınırı connector kaydıdır.
- Canlı ortama alınmadan önce her müşteri hesabı taşıyıcının test hesabıyla doğrulanmalıdır. Gerekirse taşıyıcı bazlı feature flag sonraki yayın diliminde eklenebilir.

## Test kapsamı

- İstenen taşıyıcıların merkezi katalogda bulunması ve alias normalizasyonu
- Yurtiçi, Aras, PTT, HepsiJet ve DHL connector sınıflarının manager üzerinden çözülebilmesi
- Yeni bir sürücünün orkestrasyon değiştirilmeden etkinleştirilebilmesi
- Hesap endpoint'inin taşıyıcıya göre izole edilmesi
- Genel kimlik bilgilerinin veritabanında şifreli tutulması
- Aynı takip numarasında taşıyıcılar arası yanlış fatura eşleşmesinin engellenmesi
- Gönderi Defteri taşıyıcı seçimi ve filtre görünümü
- Taşıyıcılar yüzeyinde hesap kaydetme, şifreleme ve hesap durumları
- Sürat hesabının ortak popup'tan oluşturulması, eski şifre kolonlarının korunması ve eski sekme isteğinin Taşıyıcılar'a yönlenmesi
- Taşıyıcıya göre farklı kurulum alanlarının gösterilmesi ve yeni formun sunucu tarafında boş kimlik bilgileriyle açılması
- Tarayıcı/parola yöneticisi otomatik doldurma koruma işaretlerinin formda bulunması
- SOAP yanıtlarının ortak gönderi/takip durumuna çevrilmesi
- HepsiJet token ve DHL ürün doğrulama HTTP istekleri
- PTT kontrol hanesi, gönderi/iptal/takip eşlemesi ve hesap formu
- Mevcut Sürat connector, WooCommerce–Sürat eşleştirme ve kargo karşılaştırma regresyonları

## Bilinen sınırlamalar

- Gerçek müşteri kimlik bilgileri verilmediği için taşıyıcı sandbox/canlı kabul testleri bu çalışma ortamında yapılamadı; sürücüler contract testleriyle doğrulandıktan sonra pilot hesapla sertifikalanmalıdır.
- Taşıyıcıların yeni hata/durum kodları pilot kullanım sırasında eşleme tablosuna eklenmelidir.
- “DHL Kargo” kapsamı bu taslakta **DHL Express** kabul edilmiştir. DHL eCommerce Türkiye isteniyorsa ayrı ürün ve connector kodu açılmalıdır.
- Trendyol Express için bağımsız kurumsal kargo hesabı varsayılmamıştır.
- DHL Express MyDHL, oluşturulmuş gönderiyi silme endpoint'i sunmadığı için ZOLM sürücüsü gönderi iptalini desteklemez; pickup rezervasyonu ayrıca iptal edilmelidir.
- PTT gerçek müşteri hesabıyla kabul testi yapılmadı; canlıya geçmeden önce müşterinin PTT servis izni ve barkod aralığıyla test edilmelidir.
- MNG Kargo aktif taşıyıcı kataloğundan ve yeni kayıt seçimlerinden kaldırılmıştır. Geçmiş kayıtları okuyabilen uyumluluk eşlemeleri veri bütünlüğü için korunur; bu kayıtlar DHL Express'e otomatik çevrilmez.

## Sonraki uygulama dilimleri

1. Yurtiçi, Aras, PTT, HepsiJet ve DHL için gerçek sandbox/müşteri hesaplarıyla kabul testi
2. DHL uluslararası ticari gönderileri için gümrük kalemleri ve posta kodu veri modelinin genişletilmesi
3. DHL eCommerce Türkiye için ihtiyaç oluşursa DHL Express'ten ayrı ürün ve connector kodunun değerlendirilmesi
4. Trendyol Express paket durumlarının Trendyol connector üzerinden sertifikasyonu

## Geri alma planı

1. Yeni taşıyıcı connector kayıtlarını `config/cargo.php` içinden kapatın.
2. Gönderi Defteri'nde varsayılan taşıyıcıyı `surat` olarak bırakın.
3. Servis refaktörü geri alınırsa yeni taşıyıcı taslaklarını arşivleyin; Sürat satırlarına dokunmayın.
4. Credential migration'ını ancak yeni alanda veri olmadığını doğruladıktan sonra geri alın.

## İlgili commit / PR

Henüz commit veya PR oluşturulmadı. Önerilen commit sırası:

1. `feat: add cargo carrier registry and secure credentials`
2. `refactor: make cargo shipment orchestration carrier agnostic`
3. `feat: expose multi-carrier controls in cargo operations`
4. `test: cover multi-carrier isolation and surat regressions`
5. `docs: document multi-carrier rollout plan`

## Slack taslağı

```text
🚀 Çoklu kargo hesap kurulumu ve PTT sürücüsü hazırlandı

- Ne değişti: Sürat dahil Yurtiçi, Aras, PTT, HepsiJet ve DHL Express hesapları taşıyıcıya özel alanlar kullanan aynı popup'tan yönetiliyor. Sürat'in çalışan eski hesap ve şifre kolonları korundu. PTT barkod aralığı otomatik yönetiliyor; MNG aktif katalogdan kaldırıldı ve tarayıcı otomatik doldurma koruması eklendi.
- Kullanıcıya etkisi: Kargo firmasının verdiği bilgileri girip bağlantıyı test ederek hesabı gönderi operasyonunda kullanabilir.
- Test durumu: Otomatik contract/regresyon testleri hazır; gerçek taşıyıcı sandbox kabul testi hesap bilgileriyle yapılacak.
- Yayın / feature flag durumu: Yayınlanmadı; migration ve pilot hesap doğrulaması gerekli.
- Dikkat edilmesi gerekenler: PTT barkod aralığı tükenmeden yenilenmeli; Trendyol Express mağaza üzerinden yönetilir; DHL kapsamı DHL Express'tir ve API gönderi silme desteklemez.
- Dokümantasyon: docs/integrations/cargo/MULTI_CARRIER_FOUNDATION.md
- PR / commit: Henüz oluşturulmadı.
```
