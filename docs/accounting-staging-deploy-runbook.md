# ZOLM ERP & Ön Muhasebe — Staging Deploy Kılavuzu (Staging Deploy Runbook)

Bu doküman, ZOLM ERP / Ön Muhasebe modüllerinin staging ortamına deploy edilmesi, feature flag ayarları, post-deploy smoke testleri ve rollback kılavuzunu tanımlar.

---

## 1. Ön Koşullar

Deploy işlemi başlatılmadan önce aşağıdaki hazırlıklar tamamlanmalıdır:
1. **Staging Ortam Yedekleri:**
   - Staging `.env` dosyasını yedekleyin: `cp .env .env.staging.bak`
   - Staging veritabanının yedeğini (dump) alın.
   - Storage dosyalarını yedekleyin.
2. **Kuyruk ve Zamanlanmış Görevler:**
   - Queue worker'ları geçici olarak durdurun: `php artisan queue:pause` veya `supervisorctl stop zolm-worker:*`
   - Cron job scheduler durumunu kontrol edin.
3. **Maintenance Mode (Bakım Modu):**
   - Kullanıcı etkileşimini kesmek için bakım moduna geçin (isteğe bağlı): `php artisan down --secret="zolm-deploy-token"`

---

## 2. Deploy Adımları

Canlı staging deploy sürecinde sırasıyla çalıştırılacak komutlar:
1. **Kod Güncelleme:**
   ```bash
   git fetch --tags
   git checkout erp-pilot-v1.0
   ```
2. **Bağımlılıkların Kurulması:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. **Frontend Varlıklarının Derlenmesi (Gerekliyse):**
   ```bash
   npm ci
   npm run build
   ```
4. **Migration Durumu & Dry-Run:**
   ```bash
   php artisan migrate:status
   ```
5. **Migration Çalıştırma:**
   ```bash
   php artisan migrate --force
   ```
6. **Önbellek (Cache) Yenileme:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 3. Feature Flag Aktivasyonu

ERP modülünü pilot kullanıcılara açmak için `.env` dosyasını güncelleyin:
1. **.env Ayarları:**
   ```env
   ACCOUNTING_ENABLED=true
   PARTY_CORE_ENABLED=true
   ```
2. **Önbellek Güncelleme:**
   ```bash
   php artisan config:cache
   ```
3. **Kullanıcı Yetkilendirmesi:** Pilot olarak belirlenen kullanıcının `users.role` değerinin `admin` olduğunu veritabanından teyit edin.

---

## 4. Demo Seeder Yönetimi

Test ortamında (staging) demo verileri hızlıca kurmak için:
```bash
php artisan accounting:seed-demo --user={admin_user_id} --reset
```
* **Production Kuralı:** Komut canlı (production) ortamda çalışırken kazara sıfırlama yapmaması için `--force` seçeneği olmadan kesinlikle çalıştırılamaz.
* **Demo Marker:** Demo seeder tarafından üretilen tüm kayıtlar deterministik `source_key` ve `meta_json->demo = true` marker'ı taşır.

---

## 5. Post-Deploy Smoke Test

Aşağıdaki URL adreslerinin (ve route'ların) erişilebilir ve başarılı (HTTP 200) döndüğünü doğrulayın:
1. Dashboard: `/accounting`
2. Cariler: `/accounting/parties`
3. Cari Hesap Ekstresi: `/accounting/party-ledger`
4. Hesap Planı: `/accounting/chart-of-accounts`
5. Ürün Kartları: `/accounting/products`
6. Denetim Günlüğü: `/accounting/audit-logs`
7. Yevmiye Fişleri: `/accounting/journal`
8. Kasa & Banka: `/accounting/cash-bank`
9. Stok Envanteri: `/accounting/stock`
10. Satış Siparişleri: `/accounting/sales`
11. Satın Alma Siparişleri: `/accounting/purchases`
12. Tahsilat & Ödemeler: `/accounting/collections-payments`
13. Web POS / Hızlı Satış: `/accounting/pos`
14. e-Fatura / e-Arşiv Belgeleri: `/accounting/e-documents`
15. Finansal Raporlar: `/accounting/reports`
16. AI Asistan: `/accounting/assistant`
17. Pazaryeri Entegrasyon Köprüsü: `/accounting/marketplace-bridge`
18. Pilot Merkezi: `/accounting/pilot-center`

---

## 6. Rollback Prosedürü (Geri Dönüş)

Eğer deployment sonrası kritik bir hata veya sistem çökmesi tespit edilirse:
1. **Feature Flag Kapatma (En Hızlı Çözüm):**
   ```env
   ACCOUNTING_ENABLED=false
   ```
   Ardından `php artisan config:cache` çalıştırın. Bu işlem tüm ERP ekranlarını 404 durumuna düşürerek erişimi keser.
2. **Kod Geri Çekme (Rollback Code):**
   ```bash
   git checkout [previous_stable_commit_or_tag]
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   ```
3. **Veritabanı Restore (Yedekten Dönme):**
   Gerekli durumlarda deploy öncesi alınan MySQL yedeğini yükleyin:
   ```bash
   mysql -u [username] -p [database_name] < zolm_pre_deploy_backup.sql
   ```
4. **Maintenance Mode Kapatma:**
   ```bash
   php artisan up
   ```
