# Trendyol Booster Canlıya Geçiş Kontrol Listesi

Bu belge Trendyol Booster ve tüm alt modüllerinin kontrollü yayın, pilot izleme ve geri dönüş adımlarını tanımlar.

## 1. Temel Güvenlik İlkeleri

- İlk yayın sırasında retention temizliği kapalı kalır.
- Migration geri alınmaz; eski uygulama koduyla uyumlu ek şema korunur.
- Temizlik tüm kullanıcılar için topluca çalıştırılmaz.
- Gerçek temizlik yalnızca kullanıcı bazlı dry-run kanıtından sonra açılır.
- Scheduler veya companion sorunu varsa ürün verisi elle değiştirilmez.
- Canlıya çıkış engeli bulunan denetim sonucu görmezden gelinmez.

## 2. Otomatik Hazırlık Denetimi

Genel denetim:

```bash
php artisan marketplace:trendyol-booster-readiness
```

Pilot kullanıcı için denetim:

```bash
php artisan marketplace:trendyol-booster-readiness --user=USER_ID
```

Pipeline/otomasyon için JSON çıktı:

```bash
php artisan marketplace:trendyol-booster-readiness --json
```

Sonuçlar:

- `Canlıya geçiş hazır`: Engel veya uyarı yok.
- `Kontrollü canlıya geçiş`: Engel yok, açık uyarılar izlenmeli.
- `Canlıya geçiş engelli`: Deploy veya feature flag açılışı durdurulmalı.

Komut engel varsa başarısız exit code döndürür.

## 3. Deploy Öncesi

- [ ] Veritabanı yedeği doğrulandı.
- [ ] Mevcut çalışan uygulama sürümü/commit bilgisi kaydedildi.
- [ ] `.env` ve secret yönetiminde değişiklikler gözden geçirildi.
- [ ] `MARKETPLACE_TRENDYOL_BOOSTER_RETENTION_CLEANUP_ENABLED=false` doğrulandı.
- [ ] Queue sürücüsü `database` veya `redis` olarak doğrulandı.
- [ ] Queue worker ve scheduler servislerinin yeniden başlatma prosedürü hazır.
- [ ] `php artisan migrate:status` çıktısında bekleyen Booster migration'ları belirlendi.
- [ ] `npm run extension:check` başarılı.
- [ ] Companion manifest ve README sürümü eşleşiyor.
- [ ] Booster test paketi başarılı.

Test komutu:

```bash
php artisan test tests/Feature/TrendyolBoosterTest.php
```

## 4. Deploy Sırası

1. Uygulama kodunu yayınla.
2. Bakım penceresi politikasına uygun şekilde migration çalıştır.
3. Config, route ve view cache'lerini kontrollü yenile.
4. Queue worker'ları yeni kodla yeniden başlat.
5. Scheduler servisinin çalıştığını doğrula.
6. Hazırlık denetimini genel kapsamda çalıştır.
7. Seçilen pilot kullanıcı için hazırlık denetimini çalıştır.
8. Panel ve companion smoke testlerini tamamla.
9. Pilot izleme başarılıysa kullanıcı erişimini genişlet.

## 5. Scheduler ve Queue Smoke Testi

Önce HTTP isteği atmayan dry-run:

```bash
php artisan marketplace:sync-trendyol-booster --user=USER_ID --dry-run
```

Pilot gerçek koşu:

```bash
php artisan marketplace:sync-trendyol-booster --user=USER_ID --limit=5
```

Kontroller:

- [ ] Scheduler son çalışma izi güncellendi.
- [ ] Ürün, analiz, rakip, kelime ve mağaza satırlarında beklenmeyen hata yok.
- [ ] Snapshot sayısı yalnızca beklenen takiplerde arttı.
- [ ] Queue worker hata döngüsüne girmedi.
- [ ] Operasyon alarmı kritik seviyede değil.
- [ ] İlk tarama kuyruğu düzenli azalıyor.

## 6. Panel Smoke Testi

- [ ] Takip ekranı masaüstü ve mobilde açılıyor.
- [ ] Scheduler sağlık kartı son koşuyu gösteriyor.
- [ ] Operasyon alarmı beklenen seviyeyi gösteriyor.
- [ ] “Bugün ne yapmalıyım?” listesi açıklanabilir ürün aksiyonları gösteriyor.
- [ ] “İncele” aksiyonu ürünü değiştirmeden analiz ekranına götürüyor.
- [ ] Filtre, sıralama, kolon seçimi ve mobil kart görünümü çalışıyor.
- [ ] Anlık analiz yalnızca seçilen ürünü yeniliyor.
- [ ] Bildirim merkezi küçük değişikliklerle gürültü üretmiyor.

## 7. Companion Smoke Testi

```bash
npm run extension:check
npm run extension:package
```

- [ ] Chrome'da manifest sürümü beklenen sürümle aynı.
- [ ] “Oturumu test et” başarılı.
- [ ] Ürün ön izlemesi kayıt oluşturmuyor.
- [ ] Takibe al işlemi tek ürün kaydı oluşturuyor/güncelliyor.
- [ ] Stok satıcı limiti aşıldığında istek reddediliyor.
- [ ] Mağaza ürün limiti aşıldığında istek reddediliyor.
- [ ] Trendyol'un yayınlamadığı metrikler uydurulmuyor.

## 8. Retention Kademeli Açılış

İlk yayın sırasında sadece rapor:

```bash
php artisan marketplace:trendyol-booster-retention-report --user=USER_ID
php artisan marketplace:trendyol-booster-retention-cleanup --user=USER_ID
```

İkinci komut `--execute` olmadan dry-run çalışır.

Gerçek temizlikten önce:

- [ ] En az bir pilot kullanıcı dry-run raporu incelendi.
- [ ] Saklama günleri iş ihtiyacıyla doğrulandı.
- [ ] Silme batch ve koşu üst sınırı doğrulandı.
- [ ] Kullanıcı dışındaki kayıtların aday listesine girmediği doğrulandı.
- [ ] Veritabanı yedeği ve geri yükleme testi güncel.

Gerçek temizlik yalnızca feature flag açıldıktan sonra:

```bash
php artisan marketplace:trendyol-booster-retention-cleanup --user=USER_ID --execute
```

Gerçek silmede `--days` kullanılamaz. Tüm kullanıcıları kapsayan execute desteklenmez.

## 9. İlk 24 Saat İzleme

- İlk saat: 10 dakikada bir scheduler, queue ve hata loglarını kontrol et.
- İlk 6 saat: saatlik backlog, ilk tarama ve bildirim gürültüsünü kontrol et.
- İlk 24 saat: retention sadece dry-run kalmalı.
- Kritik hata oranı veya sürekli backlog artışı varsa erişimi genişletme.
- Companion 4xx/5xx oranı ve payload doğrulama hatalarını incele.
- Kullanıcı bazlı öncelik listesinin yanlış ürünleri sürekli öne çıkarmadığını örnekle.

## 10. Geri Dönüş Prosedürü

Sorun Booster ile sınırlıysa önce özellikleri kapat:

```env
MARKETPLACE_TRENDYOL_BOOSTER_ENABLED=false
MARKETPLACE_TRENDYOL_BOOSTER_RETENTION_CLEANUP_ENABLED=false
```

Ardından config cache'i kontrollü yenile. Booster sync komutu feature flag kapalıyken veri işlemez.

Kod geri dönüşü gerekiyorsa:

1. Önce retention cleanup'ı kapat.
2. Booster feature flag'i kapat.
3. Önceki doğrulanmış uygulama paketini yayınla.
4. Queue worker'ları yeniden başlat.
5. Yeni migration'ları otomatik rollback etme; backward-compatible şemayı koru.
6. Panel, sipariş ve pazaryeri muhasebe smoke testlerini çalıştır.
7. Companion gerekiyorsa önceki doğrulanmış pakete geri dön ve Chrome'da yeniden yükle.

Veritabanı geri yükleme yalnızca doğrulanmış veri kaybı/bozulması varsa ve ayrı olay prosedürüyle yapılır.

## 11. Yayın Onayı

- [ ] Hazırlık denetiminde engel yok.
- [ ] Tam Booster test paketi geçti.
- [ ] Extension paket kontrolü geçti.
- [ ] Pilot kullanıcı smoke testi geçti.
- [ ] Retention cleanup kapalı veya açık olması yazılı olarak onaylandı.
- [ ] İzleme sorumlusu ve geri dönüş kararı verecek kişi belirlendi.

## 12. Geliştirme Aşamasındaki Yorum Çekme Modülü

Yorum çekme modülü aktif geliştirme aşamasındadır ve bu belgedeki mevcut Booster canlı kapsamından ayrıdır.

Genel readiness sonucuna dahil edilmeden önce:

- [ ] Ayrı feature flag tanımlanmalı ve varsayılan kapalı olmalı.
- [ ] Review sync, review ve filtre migration'ları backward-compatible olmalı.
- [ ] Migration `up()` akışında mevcut tabloyu veya veriyi silen işlem bulunmamalı.
- [ ] Kullanıcı izolasyonu tüm sorgu, eşleştirme ve unique kurallarında doğrulanmalı.
- [ ] Companion payload limiti ve doğrulama kuralları eklenmeli.
- [ ] WooCommerce push işlemi idempotent ve geri alınabilir olmalı.
- [ ] KVKK maskeleme, soft-delete ve audit geçmişi test edilmeli.
- [ ] Spam filtresinin yanlış pozitifleri için manuel inceleme akışı bulunmalı.
- [ ] Modüle özel test paketi ve ayrı readiness kontrolü tamamlanmalı.
