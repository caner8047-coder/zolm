# ZOLM v1.1

ZOLM; üretim, operasyon, pazaryeri muhasebesi, ERP / ön muhasebe, CRM ve müşteri iletişim süreçlerini tek panelde birleştiren Laravel tabanlı iş yönetim platformudur.

## Sürüm

- **Ürün etiketi:** ZOLM v1.1
- **Uygulama sürümü:** `1.0.0`
- **Release tarihi:** 2026-07-18
- **Sürüm kaynağı:** `config/version.php`
- **GitHub tag hedefi:** `v1.0`

## Teknoloji

- **Backend:** PHP 8.3+, Laravel, Eloquent ORM
- **Frontend:** Livewire, Alpine.js, Tailwind CSS, Vite
- **Veritabanı:** MySQL 8
- **Dosya / rapor:** PhpSpreadsheet, DomPDF
- **Test:** PHPUnit / Laravel test runner

## Ana Modüller

- **Üretim ve Operasyon Motorları:** XLS tabanlı üretim ve operasyon raporlarını işler.
- **Pazaryeri Muhasebe:** Sipariş, ödeme, komisyon, kargo, stopaj ve kârlılık kontrollerini takip eder.
- **ERP / Ön Muhasebe:** Cari, ürün, stok, kasa/banka, satış, satın alma, raporlama ve pilot operasyon akışlarını kapsar.
- **CRM 360:** Müşteri kartları, etkileşim geçmişi ve ticari özetleri tek müşteri görünümünde toplar.
- **Müşteri İletişim Merkezi:** WhatsApp, destek kanalları, bilgi merkezi, kalite kapıları ve kanal adapter altyapısını yönetir.
- **Pazaryeri Entegrasyonları:** Trendyol, Hepsiburada, N11, Pazarama, Çiçeksepeti, Koçtaş, Amazon, Shopify ve WooCommerce test/adapter katmanlarını içerir.

## Kurulum

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Geliştirme ortamını tek komutla başlatmak için:

```bash
composer run dev
```

## Doğrulama

Release öncesi önerilen kontroller:

```bash
git diff --check
php artisan test
npm run build
```

ERP pilot kapısı için:

```bash
php artisan accounting:pilot-release-check --user={admin_id} --json
```

## Canlıya Alma Özeti

1. Kod ve tag doğrulanır.
2. `.env` yedeği, veritabanı dump'ı ve `storage` yedeği alınır.
3. `composer install --no-dev --optimize-autoloader` çalıştırılır.
4. `php artisan migrate --force` ile migration'lar uygulanır.
5. `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` çalıştırılır.
6. Kritik ekranlar ve entegrasyon smoke testleri tamamlanır.

## Önemli Dokümanlar

- `CHANGELOG.md`
- `docs/releases/zolm-v1.md`
- `docs/accounting-pilot-release-readiness.md`
- `docs/accounting-release-checklist.md`
- `docs/customer-care/urun-gereksinimleri.md`

## Lisans

Bu depo ZOLM uygulama kod tabanıdır. Dağıtım ve kullanım koşulları proje sahibi tarafından yönetilir.
