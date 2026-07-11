# ZOLM ERP & Ön Muhasebe — Release Kontrol Listesi (Release Checklist)

Bu doküman, ZOLM ERP / Ön Muhasebe modüllerinin canlıya alınması (deployment), yetki ve feature flag kontrolleri, demo veri yönetimi ve UI standartlarının doğrulanması için hazırlanmış işaretlenebilir kontrol listesidir.

---

## 1. Feature Flag & Konfigürasyon Kontrolü
Canlı veya test ortamındaki `.env` dosyalarında aşağıdaki flag'lerin durumunu teyit edin:
- `[ ]` `CRM_ENABLED=true` (Cari entegrasyonları için crm modülünün açık olması gerekir).
- `[ ]` `PARTY_CORE_ENABLED=true` (Cari alt yapısının etkinleştirilmesi).
- `[ ]` `ACCOUNTING_ENABLED=true` (Ön Muhasebe / ERP route'larının ve Livewire component'lerinin açılması).
- `[ ]` Production ortamı için default değerlerin `config/marketplace.php` dosyasında `false` olarak kaldığından emin olun.

---

## 2. Route & Middleware Güvenlik Denetimi
Tüm `/accounting/*` route'larının aşağıdaki katı koruma katmanları arkasında olduğu doğrulanmalıdır:
- `[ ]` `/accounting` (Dashboard) -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/parties` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/party-ledger` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/chart-of-accounts` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/journal` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/cash-bank` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/stock` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/products` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/sales` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/purchases` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/collections-payments` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/pos` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/e-documents` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/reports` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/assistant` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/marketplace-bridge` -> `mp.feature:accounting_enabled` + `AdminMiddleware`
- `[ ]` `/accounting/audit-logs` -> `mp.feature:accounting_enabled` + `AdminMiddleware`

---

## 3. Demo Veri Kurulumu & Reset Güvenliği
Yeni bir tenant (kullanıcı) onboard edildiğinde veya test süreçlerinde demo veri oluşturmak / temizlemek için:
- `[ ]` **Seed:** `php artisan accounting:seed-demo --user={admin_id}`
- `[ ]` **Reset & Seed:** `php artisan accounting:seed-demo --user={admin_id} --reset`
- `[ ]` **Güvenlik Notu:** Reset işlemi **kesinlikle** doğrudan `legal_entity_id` üzerinden toptan yevmiye fişi veya allocation silmez. Silme işlemleri tamamen deterministic `source_key` listeleri ve demo markers (`meta_json->demo = true`) üzerinden gerçekleşir. Gerçek kullanıcı verilerinin silinme riski P14 & P15 QA aşamasında kapatılmıştır.

---

## 4. UI Standartları & Responsive Kontrolü
Tüm ekranların ZOLM Kurumsal Açık Panel visual diliyle uyumluluğunu doğrulayın:
- `[ ]` **Zemin Renkleri:** Sayfa arka planı açık gri/sade, ana kartlar beyaz (`bg-white`), iç alanlar hafif gri (`bg-slate-50/70`).
- `[ ]` **Radius Ailesi:** Ana kartlar `rounded-[10px]`, iç/kpi kartlar `rounded-[8px]`, input/select alanları `rounded-[6px]`.
- `[ ]` **Primary Button:** `bg-slate-900 text-white` (büyük gradient veya neon renkler kullanılmamıştır).
- `[ ]` **Touch Target:** Mobil görünümde tüm aksiyon butonları ve linkleri min 44px (`min-h-[44px]` veya uygun padding).
- `[ ]` **Yatay Taşma:** Sayfalar mobil (390px), tablet (768px) ve desktop (1440px) viewport'larda yatay kaydırma yapmadan sığmaktadır.
- `[ ]` **Filtre & Tablo Bütünlüğü:** Arama/filtre alanı ile tablo aynı section kartı içindedir.

---

## 5. Deployment & Rollback Adımları
Canlı yayına çıkış esnasında izlenecek sıra:
- `[ ]` **Release Checker:** Canlıya kod gönderilmeden önce durum kontrolü yapılır:
  `php artisan accounting:pilot-release-check --user={admin_id}`
- `[ ]` **Release Git Tag:** Stabil durum git tag ile işaretlenir:
  `git tag -a zolm-erp-pilot-v0.9 -m "ZOLM ERP pilot v0.9"`
- `[ ]` Yayına çıkış öncesi veritabanı yedeğini (backup) alın.
- `[ ]` Yeni migration'ları uygulayın: `php artisan migrate --force`
- `[ ]` Uygulama cache'ini temizleyin: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:clear`
- `[ ]` Feature flag'leri aktifleştirin (`ACCOUNTING_ENABLED=true`).
- `[ ]` **Post-Deploy Smoke Test:** Aşağıdaki URL'lerin 200 döndüğünü ve düzgün render olduğunu doğrula:
  - Dashboard: `/accounting`
  - Pilot Merkezi: `/accounting/pilot-center`
  - Cariler: `/accounting/parties`
  - Cari Bakiye: `/accounting/party-ledger`
  - Satışlar: `/accounting/sales`
  - Satın Alma: `/accounting/purchases`
  - Kasa & Banka: `/accounting/cash-bank`
- `[ ]` **Hata Durumunda Geri Dönüş (Rollback):**
  - Feature flag'i `.env` dosyasında `ACCOUNTING_ENABLED=false` olarak değiştirerek tüm ERP modüllerini anında kapatabilirsiniz (Tüm route'lar otomatik olarak 404 dönecektir).
  - deploy rollback yaparak bir önceki tag'e dönün.
  - gerekirse veritabanı restore adımlarını uygulayın.
  - migration rollback için: `php artisan migrate:rollback`

---

## 6. Bilinen Harici Test Bağımlılıkları (Known Issues)
- **MarketplaceReportDigestTest:** Bu test sınıfı (`test_send_due_delivers_mail_and_creates_report_history`), SQLite in-memory veritabanı yerine doğrudan gerçek MySQL veritabanı bağlantısı (`database.default = mysql`) yapılandırmasını zorlamaktadır. Ortamdaki MySQL veritabanı durumuna veya mail fakelerine bağlı olarak processed sayısının 0 dönmesinden ötürü başarısız olabilmektedir. Bu durum ERP/Ön Muhasebe kapsamındaki değişikliklerle ilgili olmayıp, harici bir test setup bağımlılığıdır. Canlıya çıkışta veya yerel test koşumlarında bu test bilinen bir riskli durum (Known Issue) olarak göz ardı edilebilir.
