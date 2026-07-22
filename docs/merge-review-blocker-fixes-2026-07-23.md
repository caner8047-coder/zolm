# e7fca9e Birleşme Blokajı Düzeltmeleri

## Notion taslağı

### Başlık ve özet

`e7fca9e` birleşme incelemesinde bulunan pazaryeri zamanlayıcı, hassas İK belge yetkilendirmesi ve test veritabanı izolasyonu blokajları giderildi. Düzeltmeler, mevcut kullanıcı çalışma ağacını etkilememek için `codex/fix-e7fca9e-review-blockers` dalında hazırlandı.

### İş ihtiyacı ve kullanıcıya etkisi

- API bilgileri kaydedilmiş ve `configured`, `verified`, `active` veya `connected` durumundaki Trendyol mağazalarının buybox, referans ve kargo faturası görevleri otomatik çalışır.
- Sağlık veya yüksek hassasiyetli İK belgeleri yalnız görüntülemede değil; indirme, doğrulama, yeni sürüm yükleme ve arşivleme işlemlerinde de ek yetki kontrolünden geçer.
- Testler canlı `zolm` veritabanına bağlanamaz; MySQL gerektiren paketler varsayılan olarak ayrı `testing` veritabanını kullanır.

### Teknik yaklaşım

- Çalışabilir bağlantı durumları `IntegrationConnection::OPERATIONAL_STATUSES` içinde merkezileştirildi ve `operational` Eloquent scope'u eklendi.
- Üç Trendyol scheduler sorgusu ortak scope'a taşındı.
- `HrEmployeeDocumentPolicy`, tenant ve hassas içerik erişimini tüm belge aksiyonlarında ortak olarak uygular hale getirildi.
- Livewire belge aksiyonları policy üzerinden yetkilendirildi.
- MySQL test veritabanı adı `Tests\TestCase::mysqlTestDatabaseName()` ile merkezileştirildi. `DB_TEST_DATABASE` verilmezse güvenli `testing` varsayılanı kullanılır; `zolm` reddedilir.
- Eski smoke-test connector metod adı güncel sözleşmeyle eşitlendi ve Booster şema beklentisi yeni tablo sayısına güncellendi.

### Değiştirilen bileşenler

- `app/Models/IntegrationConnection.php`
- `routes/console.php`
- `app/Modules/Hr/Document/Policies/HrEmployeeDocumentPolicy.php`
- `app/Modules/Hr/Personnel/Livewire/EmployeeDetail.php`
- `tests/TestCase.php`
- MySQL kullanan pazaryeri ve CRM testleri
- Yeni scheduler, bağlantı durumu, yetkilendirme ve test DB izolasyon testleri

### Veri modeli veya migration değişiklikleri

Yeni migration veya üretim veri modeli değişikliği yoktur. Yerel `testing` veritabanına bu dalda zaten bulunan eksik migration'lar doğrulama amacıyla uygulanmıştır; canlı `zolm` veritabanına işlem yapılmamıştır.

### Kullanım adımları

1. Trendyol mağazasında geçerli API bilgilerini kaydedin ve bağlantıyı doğrulayın.
2. Bağlantı `configured`, `verified`, `active` veya `connected` durumundaysa scheduler görevleri mağazayı otomatik kapsar.
3. Testlerde farklı bir MySQL test veritabanı gerekiyorsa `DB_TEST_DATABASE` değişkenini güvenli bir test veritabanı adıyla tanımlayın.

### Yetki ve feature flag bilgileri

- Sağlık belgesi için `hr.documents.view_health` zorunludur.
- Yüksek hassasiyetli belge için `hr.documents.view_sensitive` zorunludur.
- Bir belge iki koruma sınıfına da giriyorsa iki izin birlikte gerekir.
- Mevcut pazaryeri feature flag davranışları değiştirilmemiştir.

### Test kapsamı

- Hedefli blokaj testleri: 22 test, 81 assertion
- MySQL bağımlı pazaryeri/CRM paketi: 420 test, 2.843 assertion
- HR paketi: 336 test, 1.028 assertion
- Unit paketi: 60 test, 256 assertion
- Hepsiburada/Trendyol V2/Pazarama seçili paket: 67 test, 287 assertion
- Kod stili: değişen 72 PHP dosyası Pint kontrolünden geçti

### Bilinen sınırlamalar

- Canlı pazaryeri credential'larıyla gerçek API smoke testi bu çalışmada yapılmadı.
- `demo` durumundaki bağlantılar üretim scheduler kapsamına bilinçli olarak alınmadı.

### Geri alma planı

1. Düzeltme commit'lerini ters sırada revert edin.
2. Scheduler durum scope'u geri alınırsa otomatik görevlerin yalnız `active` bağlantıları göreceği unutulmamalıdır.
3. Yetkilendirme geri alınmamalıdır; zorunlu bir rollback durumunda hassas belge aksiyonları geçici olarak feature erişiminden kapatılmalıdır.
4. Test DB yönlendirmesi geri alınırsa ana DB koruma listener'ı korunmalıdır.

### Commit veya PR bağlantıları

Henüz commit veya PR oluşturulmadı. Önerilen dal: `codex/fix-e7fca9e-review-blockers`.

### Yayın tarihi ve sorumlu kişi

- Hazırlanma tarihi: 23 Temmuz 2026
- Sorumlu: ZOLM geliştirme ekibi

## Decision log

### Çalışabilir bağlantı durumlarını merkezileştirme — 23 Temmuz 2026

- **Durum:** Kabul Edildi
- **Bağlam:** Bağlantı kaydı `configured` veya `connected` üretirken scheduler yalnız `active` arıyordu.
- **Değerlendirilen seçenekler:** Scheduler içinde tekrarlı `whereIn`, yalnız `configured` kullanmak, model scope'u oluşturmak.
- **Seçilen yaklaşım:** Model üzerinde ortak durum listesi ve `operational` scope'u.
- **Gerekçe:** Durum anlamını tek yerde tutar ve yeni scheduler sorgularında aynı hatanın tekrarlanmasını önler.
- **Olumlu sonuç:** Tüm gerçek canlı bağlantı durumları otomatik görevlere katılır.
- **Olumsuz sonuç:** Yeni bir bağlantı durumu eklenirse merkezi liste güncellenmelidir.
- **Yeniden değerlendirme koşulu:** Bağlantı durumları enum veya state machine yapısına taşınırsa scope bu yapıya geçirilir.

### Test veritabanını canlı DB'den kesin ayırma — 23 Temmuz 2026

- **Durum:** Kabul Edildi
- **Bağlam:** Test güvenlik listener'ı `zolm` bağlantısını engelliyor, ancak 58 test dosyası aynı adı sabit kullanıyordu.
- **Değerlendirilen seçenekler:** Listener'ı gevşetmek, SQLite'a zorunlu geçiş, ayrı MySQL test DB adı.
- **Seçilen yaklaşım:** Listener korunarak `DB_TEST_DATABASE` ve güvenli `testing` varsayılanı kullanıldı.
- **Gerekçe:** MySQL'e özgü test davranışını korurken canlı veri riskini kaldırır.
- **Olumlu sonuç:** Testler gerçek şema davranışını güvenli veritabanında çalıştırır.
- **Olumsuz sonuç:** Test ortamında `testing` veritabanının migration'ları güncel tutulmalıdır.
- **Yeniden değerlendirme koşulu:** Tüm paket SQLite uyumlu hale gelirse MySQL bağımlılığı azaltılabilir.

## Slack taslağı

```text
🚀 e7fca9e birleşme blokajı düzeltmeleri tamamlandı

- Ne değişti: Trendyol scheduler bağlantı durumları düzeltildi, hassas İK belge aksiyonları ek yetki kontrolüne alındı, testler ayrı testing DB'ye taşındı.
- Kullanıcıya etkisi: API bilgileri girilmiş mağazaların otomatik görevleri çalışır; sağlık/hassas belgelerde işlem güvenliği güçlendi.
- Test durumu: 420 pazaryeri/CRM, 336 HR ve 60 unit test geçti. Pint temiz.
- Yayın / feature flag durumu: Mevcut feature flag'ler korundu; henüz main veya canlıya alınmadı.
- Dikkat edilmesi gerekenler: Canlı credential smoke testi yayın öncesi ayrıca yapılmalı.
- Dokümantasyon: docs/merge-review-blocker-fixes-2026-07-23.md
- PR / commit: Henüz oluşturulmadı; dal codex/fix-e7fca9e-review-blockers.
```
