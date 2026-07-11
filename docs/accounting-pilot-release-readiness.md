# ZOLM ERP & Ön Muhasebe — Pilot Release Hazırlık Kılavuzu (Release Readiness)

Bu doküman, ZOLM Ön Muhasebe / ERP pilot sürümünün (v0.9) canlı yayına alınması, deploy adımları, backup/restore planları ve rollback matrisini tanımlar.

---

## 1. Pilot Release Amacı

Bu sürümün amacı, ZOLM ERP ve Ön Muhasebe modüllerinin seçilen pilot işletmelerde (örneğin tek kullanıcılı/admin profilli küçük işletmelerde) veri bütünlüğünü, mobil responsive deneyimi ve temel muhasebe akışlarını test etmektir. 

---

## 2. Commit ve Tag Ön Koşulları

Release öncesinde tüm değişiklikler test edilip onaylanmış olmalıdır:
1. Son commit mesajı formatı: `feat(accounting): add pilot operations center`
2. Release Tag önerisi:
   ```bash
   git tag -a zolm-erp-pilot-v0.9 -m "ZOLM ERP pilot v0.9"
   ```

---

## 3. Ortam Değişkenleri (Environment Variables)

Canlı ortam `.env` dosyasındaki varsayılan değerler:
```env
ACCOUNTING_ENABLED=false
PARTY_CORE_ENABLED=false
```
* **Güvenlik Notu:** `SeedAccountingDemoCommand` production ortamında `--force` girilmedikçe çalışmayacak şekilde sertleştirilmiştir.

---

## 4. Pre-Deploy Checklist

1. [ ] Tüm test suite lokal ortamda %100 yeşil geçmeli.
2. [ ] `php artisan config:clear` ve `php artisan cache:clear` çalıştırılmalı.
3. [ ] `git status --short` çıktısı tamamen temiz olmalı.

---

## 5. Migration ve Dry-Run Adımları

Migration'ları uygulamadan önce durum kontrolü yapın:
```bash
php artisan migrate:status
```
Eğer yeni tablolar listeleniyorsa:
- `accounting_pilot_feedbacks`
- `accounting_pilot_health_snapshots`
bu tablolar idempotent olup, Schema check barındırdığı için güvenle uygulanabilir.

---

## 6. Backup ve Restore Planı

Herhangi bir olumsuzluk durumunda geri dönebilmek için deploy öncesi mutlaka yedek alın:
1. **Veritabanı Dump (MySQL):**
   ```bash
   mysqldump -u [username] -p [database_name] > zolm_pre_deploy_backup.sql
   ```
2. **Storage Yedekleme:**
   ```bash
   tar -czf storage_backup.tar.gz storage/app/public/
   ```
3. **.env Yedekleme:**
   ```bash
   cp .env .env.backup
   ```

---

## 7. Deploy Adımları

Canlı ortama sürüm çıkarken şu sıralamayı takip edin:
1. Kodu sunucuya çekin (`git pull`).
2. Composer bağımlılıklarını kurun:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Migration'ları uygulayın:
   ```bash
   php artisan migrate --force
   ```
4. Önbelleği yenileyin:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 8. Post-Deploy Smoke Test Listesi

Deploy tamamlandıktan sonra sırayla aşağıdaki URL adreslerini ziyaret edip smoke test yapın:
- [ ] `/accounting` (Dashboard)
- [ ] `/accounting/pilot-center` (Pilot Merkezi)
- [ ] `/accounting/parties` (Cariler)
- [ ] `/accounting/party-ledger` (Cari Ekstre / Açık Hesap)
- [ ] `/accounting/chart-of-accounts` (Hesap Planı)
- [ ] `/accounting/products` (Ürün Kartları)
- [ ] `/accounting/audit-logs` (Denetim Günlüğü)
- [ ] `/accounting/sales` (Satışlar)
- [ ] `/accounting/purchases` (Satın Alma)
- [ ] `/accounting/stock` (Stok / Envanter)
- [ ] `/accounting/cash-bank` (Kasa / Banka)
- [ ] `/accounting/reports` (Finansal Raporlar)

---

## 9. Feature Flag Açma / Kapatma Prosedürü

1. **Açma:** `.env` dosyasında `ACCOUNTING_ENABLED=true` yapın ve `php artisan config:cache` çalıştırın.
2. **Kapatma:** `.env` dosyasında `ACCOUNTING_ENABLED=false` yapın ve `php artisan config:cache` çalıştırın.

---

## 10. Rollback Karar Matrisi

| Durum / Hata Seviyesi | Aksiyon Planı | Geri Dönüş Yöntemi |
| :--- | :--- | :--- |
| **Kritik Hata (Veri Kaybı / Çökme)** | Anında Rollback | `ACCOUNTING_ENABLED=false` yapın, `zolm_pre_deploy_backup.sql` veritabanını restore edin. |
| **UX / Arayüz Hataları** | Hotfix Planı | Modülü kapatmayın. Bir sonraki sprint/hotfix commit'ini bekleyin. |
| **Entegrasyon Kesintisi** | Pilot Merkezi Bildirimi | Pilot merkezinden feedback açın, durum geçici ise servis sağlayıcısını kontrol edin. |

---

## 11. Bilinen MVP Limitleri (Limits & Known Issues)

Canlı pilot ortamında bilinen limitler:
1. **e-Fatura Entegrasyonu:** Gerçek Özel Entegratör/GİB entegrasyonu yoktur. Süreç simüle edilmiştir.
2. **POS Donanımı:** Fiş yazıcı, barkod okuyucu veya ödeme terminali entegrasyonu yoktur. Arayüz Web POS olarak çalışır.
3. **AI Asistan:** Salt okunurdur, veritabanına yazma yetkisi güvenlik sebebiyle engellenmiştir.
4. **MarketplaceReportDigestTest Known Issue:** Pazaryeri digest testlerindeki known issue (processed beklenen değer uyuşmazlığı) bu modülle veya pilot süreçleriyle ilişkili değildir.

---

## 12. Release Onay Kutuları

- [ ] [P1] Tüm güvenlik guard'ları test edildi.
- [ ] [P1] Test suite %100 yeşil.
- [ ] [P1] `php artisan accounting:pilot-release-check` çalıştırıldı ve hata (`failed`) tespit edilmedi.
- [ ] [P1] `php artisan accounting:pilot-smoke-test` çalıştırıldı ve route hatası bulunmadı.
- [ ] [P2] Pilot Center touch target uyumluluğu sağlandı.
- [ ] [P2] Idempotent migration doğrulandı.
