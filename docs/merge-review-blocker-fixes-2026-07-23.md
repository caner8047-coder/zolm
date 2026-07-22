# e7fca9e Birleşme Blokajı Düzeltmeleri

## Notion taslağı

### Başlık ve özet

`e7fca9e` birleşme incelemesinde bulunan pazaryeri zamanlayıcı, hassas İK belge yetkilendirmesi ve test veritabanı izolasyonu blokajları giderildi. Diğer bilgisayardan gelen recovery/İK geliştirmeleri, bu bilgisayardaki kargo ve pazaryeri entegrasyonlarıyla `codex/fix-e7fca9e-review-blockers` dalında birleştirildi. Yerel çalışma ayrıca `codex/local-cargo-marketplace-backup-20260723` dalında korunmaktadır.

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
- Yerel çoklu kargo ve hazır e-ticaret connector çalışması recovery dalıyla birleştirildi; route ve middleware çatışmaları mevcut güvenlik katmanları korunarak çözüldü.
- İK payroll migration rollback işlemi, oluşturulmayan bir foreign key'i silmek yerine gerçek `salary_record_id` kolonunu kaldıracak şekilde düzeltildi.

### Değiştirilen bileşenler

- `app/Models/IntegrationConnection.php`
- `routes/console.php`
- `app/Modules/Hr/Document/Policies/HrEmployeeDocumentPolicy.php`
- `app/Modules/Hr/Personnel/Livewire/EmployeeDetail.php`
- `tests/TestCase.php`
- MySQL kullanan pazaryeri ve CRM testleri
- Yeni scheduler, bağlantı durumu, yetkilendirme ve test DB izolasyon testleri
- Çoklu kargo ve pazaryeri connector servisleri, Livewire ekranları, route'lar ve bunların testleri
- `database/migrations/2026_08_04_470000_add_generic_credentials_to_cargo_carrier_accounts.php`
- `database/migrations/2026_08_24_100003_add_calculation_trace_to_hr_payroll_records.php`

### Veri modeli veya migration değişiklikleri

Birleştirilen yerel kargo çalışması, taşıyıcı hesaplarına genel credential alanlarını ekleyen backward-compatible bir migration içerir. Ayrıca mevcut İK payroll migration'ının yalnız rollback davranışı düzeltildi; `up()` şeması değiştirilmedi. Ayrı `testing` veritabanına kargo migration'ı doğrulama amacıyla uygulanmıştır; canlı `zolm` veritabanına test sırasında işlem yapılmamıştır.

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

- Birleşik kargo/pazaryeri/İK hedef paketi: 62 test, 363 assertion
- MySQL bağımlı pazaryeri/CRM paketi: 420 test, 2.845 assertion
- HR paketi: 336 test, 1.028 assertion
- Unit paketi: 72 test, 293 assertion
- Hepsiburada/Trendyol V2/Pazarama seçili paket: 67 test, 287 assertion
- Kod stili: birleşimde değişen 134 PHP dosyası Pint kontrolünden geçti
- Frontend: Vite production build başarıyla tamamlandı

### Bilinen sınırlamalar

- Canlı pazaryeri credential'larıyla gerçek API smoke testi bu çalışmada yapılmadı.
- `demo` durumundaki bağlantılar üretim scheduler kapsamına bilinçli olarak alınmadı.

### Geri alma planı

1. Düzeltme commit'lerini ters sırada revert edin.
2. Scheduler durum scope'u geri alınırsa otomatik görevlerin yalnız `active` bağlantıları göreceği unutulmamalıdır.
3. Yetkilendirme geri alınmamalıdır; zorunlu bir rollback durumunda hassas belge aksiyonları geçici olarak feature erişiminden kapatılmalıdır.
4. Test DB yönlendirmesi geri alınırsa ana DB koruma listener'ı korunmalıdır.

### Commit veya PR bağlantıları

- `8be4ae7` — `fix: resolve e7fca9e merge blockers`
- `0caadf4` — `chore: checkpoint local cargo and marketplace work`
- `f02d5fa` — `merge: combine local cargo marketplace work with e7fca9e fixes`
- `7bf1cdc` — `fix: align merged smoke and payroll rollback`
- PR oluşturulmadı ve uzak repoya push yapılmadı.

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

### Payroll rollback şemasını gerçek `up()` davranışıyla eşitleme — 23 Temmuz 2026

- **Durum:** Kabul Edildi
- **Bağlam:** `salary_record_id` normal unsigned bigint olarak eklenmesine rağmen rollback, mevcut olmayan bir foreign key'i silmeye çalışıyordu.
- **Değerlendirilen seçenekler:** Geçmiş `up()` migration'ına foreign key eklemek, koşullu foreign key kontrolü yapmak, yalnız oluşturulan kolonu kaldırmak.
- **Seçilen yaklaşım:** `down()` içinde `salary_record_id` kolonu doğrudan kaldırılır.
- **Gerekçe:** Yayınlanmış `up()` şemasını değiştirmez ve rollback'i migration'ın gerçekten oluşturduğu nesnelerle sınırlar.
- **Olumlu sonuç:** MySQL ve SQLite rollback akışları tutarlı çalışır.
- **Olumsuz sonuç:** İleride bu kolona ayrı migration ile foreign key eklenirse o migration kendi rollback'ini yönetmelidir.
- **Yeniden değerlendirme koşulu:** Kolon için yeni bir ilişki migration'ı eklendiğinde bağımlı rollback sırası tekrar test edilir.

## Slack taslağı

```text
🚀 Recovery, kargo ve pazaryeri entegrasyon birleşimi tamamlandı

- Ne değişti: Diğer bilgisayardaki recovery/İK kodu yerel çoklu kargo ve pazaryeri entegrasyonlarıyla birleştirildi; scheduler, belge yetkisi, test DB izolasyonu ve iki birleşim kusuru düzeltildi.
- Kullanıcıya etkisi: Yerel proje iki geliştirme grubunu birlikte içeriyor; API bilgileri girilmiş mağazaların otomatik görevleri çalışır ve hassas İK belge işlemleri korunur.
- Test durumu: 420 pazaryeri/CRM, 336 HR, 72 unit ve 62 hedefli birleşim testi geçti. 134 PHP dosyasında Pint ve Vite production build temiz.
- Yayın / feature flag durumu: Mevcut feature flag'ler korundu; henüz main veya canlıya alınmadı.
- Dikkat edilmesi gerekenler: Canlı credential smoke testi yayın öncesi ayrıca yapılmalı.
- Dokümantasyon: docs/merge-review-blocker-fixes-2026-07-23.md
- PR / commit: codex/fix-e7fca9e-review-blockers; 8be4ae7, 0caadf4, f02d5fa ve 7bf1cdc.
```
