# ZOLM v1 Release Notları

**Tag:** `v1.0`  
**Sürüm:** `1.0.0`  
**Ürün etiketi:** `ZOLM v1`  
**Release tarihi:** 2026-07-18  
**Hedef depo:** `caner8047-coder/zolm`

## Özet

ZOLM v1; üretim ve operasyon motorlarının üzerine pazaryeri muhasebe, ERP / ön muhasebe, CRM ve müşteri iletişim merkezi katmanlarını ekleyen ilk bütünleşik platform release'idir. Bu sürüm, pilot kullanıma hazır iş akışlarını, release dokümantasyonunu ve canlıya alma kontrollerini tek etiket altında toplar.

## Öne Çıkanlar

- **Platform sürümü:** Uygulama rozeti ve merkezi sürüm konfigürasyonu `ZOLM v1` olarak güncellendi.
- **ERP / Ön Muhasebe:** Cari, ürün, stok, kasa/banka, satış, satın alma, finansal raporlar ve pilot operasyon merkezi v1 kapsamına alındı.
- **Pazaryeri muhasebe:** Sipariş, ödeme, kesinti, komisyon, kargo, stopaj, kârlılık ve denetim akışları release kapsamına dahil edildi.
- **CRM 360:** Cari/müşteri görünümü, ticari özetler ve pazaryeri-finans köprüsüyle müşteri merkezi güçlendirildi.
- **Müşteri iletişim merkezi:** WhatsApp, destek konuşmaları, bilgi merkezi, kanal adapter sözleşmeleri ve kalite kapıları platform temelinin parçası oldu.
- **Dokümantasyon:** README, CHANGELOG ve release notları v1 seviyesine taşındı.
- **Bağımlılık güvenliği:** Composer paketleri güncellendi, kullanılmayan eski `maatwebsite/excel` / `PHPExcel` zinciri kaldırıldı ve `composer audit --locked` temizlendi.

## Kapsam

### Üretim ve Operasyon

- XLS tabanlı üretim ve operasyon dönüşüm motorları korunur.
- AI profil sistemi ve dinamik dönüşüm motoru mevcut geriye uyumluluğu bozmadan kalır.
- Rapor geçmişi ve Excel işleme akışları mevcut kullanıcı alışkanlıklarını korur.

### Pazaryeri ve Finans

- Trendyol başta olmak üzere pazaryeri sipariş, finans ve kârlılık kontrolleri merkezi muhasebe yüzeyine bağlanır.
- Denetim motoru, hakediş, kesinti, kargo, stopaj ve operasyonel riskleri görünür kılar.
- Pazaryeri entegrasyon testleri ve güvenli profil komutları v1 kalite kapısının parçasıdır.

### ERP / Ön Muhasebe

- Cari kartlar, ürün kartları, hesap planı, satış, satın alma, stok, kasa/banka ve yönetim raporları pilot seviyede birlikte çalışır.
- Pilot operasyon merkezi; readiness, feedback, monitoring ve evidence pack dokümantasyonuyla desteklenir.
- Demo seed ve access hardening iş akışları kontrollü pilot açılışı için korunur.

### CRM ve Müşteri İletişimi

- CRM 360, müşteri bazlı ticari bağlamı ve muhasebe özetini tek panelde toplar.
- WhatsApp ve müşteri iletişim merkezi altyapısı; konuşma sahipliği, bilgi merkezi, kanal adapterları ve kalite kontrolleriyle v1'e dahil edilir.
- Destek kanalları için güvenli adapter yaklaşımı ve sözleşme testleri korunur.

## Deployment Notları

1. Release tag'i doğrulanır: `git describe --tags --always`.
2. Çalışma alanı temiz olmalıdır: `git status --short`.
3. Bağımlılıklar production modunda kurulur: `composer install --no-dev --optimize-autoloader`.
4. Frontend varlıkları derlenir: `npm run build`.
5. Migration'lar uygulanır: `php artisan migrate --force`.
6. Laravel cache'leri yenilenir:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
7. Kritik ekranlar için smoke test yapılır:
   - `/dashboard`
   - `/marketplace-accounting`
   - `/marketplace-orders`
   - `/accounting`
   - `/crm`
   - `/customer-care`

## Doğrulama Komutları

Release için çalıştırılacak temel kontroller:

```bash
git diff --check
composer validate --no-check-publish
composer audit --locked
php artisan test
npm run build
```

Bu release hazırlığında `php artisan test` sonucu **1965 test / 7872 assertion** başarılıdır.

ERP pilot readiness kontrolü için:

```bash
php artisan accounting:pilot-release-check --user={admin_id} --json
```

## Bilinen Limitasyonlar

- ERP / ön muhasebe kapsamı pilot kullanıma uygundur; canlı muhasebe entegrasyonlarında işletme bazlı doğrulama gerekir.
- e-Fatura / e-Belge akışlarında gerçek GİB veya özel entegratör bağlantısı ortam yapılandırmasına bağlıdır.
- POS donanım bağlantıları web POS seviyesindedir; barkod okuyucu, fiş yazıcı ve terminal entegrasyonları ayrıca doğrulanmalıdır.
- Bazı pazaryeri senaryoları gerçek mağaza API izinlerine ve sağlayıcı limitlerine bağlıdır.

## Rollback

Kritik hata durumunda:

```bash
git checkout v0.9
php artisan migrate:rollback --step=1
php artisan config:clear
php artisan cache:clear
```

Veritabanı ve `storage` geri dönüşleri deploy öncesi alınan yedeklerden yapılmalıdır.
