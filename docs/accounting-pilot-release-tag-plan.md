# ZOLM ERP & Ön Muhasebe — Pilot Release Tag Planı (Release Tag Plan)

Bu doküman, ZOLM ERP / Ön Muhasebe modüllerini sürümlemek (release) için sürümleme standardı, git tag kuralları, tag öncesi zorunlu adımlar ve tag geri alma yollarını tanımlar.

---

## 1. Release ve Git Tag Önerisi

- **Release Sürüm Adı:** `erp-pilot-v1.0`
- **Git Tag Adı:** `erp-pilot-v1.0`

---

## 2. Tag Öncesi Zorunlu Kontroller (Pre-Tag Checks)

Git tag'i basılmadan önce aşağıdaki adımların başarıyla tamamlandığı teyit edilmelidir:

1. **Staging Alanı Kontrolü:** Çalışma alanının temiz olduğundan emin olun:
   ```bash
   git status --short
   ```
2. **Son Commits Teyidi:** En son 5 commit listesini kontrol edin:
   ```bash
   git log --oneline -5
   ```
3. **Format ve Boşluk Denetimi:**
   ```bash
   git diff --check
   ```
4. **Veritabanı Migration Durumu:** Migration'ların tamamının uygulandığını teyit edin:
   ```bash
   php artisan migrate:status
   ```
5. **Release Hazırlık Kontrolü:** Artisan release checker komutunun başarılı sonuçlandığını teyit edin:
   ```bash
   php artisan accounting:pilot-release-check --user={pilot_user_id} --json
   ```
6. **Kritik ERP Test Seti:** Tüm kabul ve regresyon testlerinin başarıyla geçtiğinden emin olun.

---

## 3. Git Tag Oluşturma ve Gönderme

Kontroller başarıyla tamamlandıktan sonra lokal tag oluşturup remote sunucuya gönderin:
1. **Lokal Tag Oluşturma:**
   ```bash
   git tag -a erp-pilot-v1.0 -m "ZOLM ERP pilot release v1.0"
   ```
2. **Uzak Sunucuya Gönderme (Push):**
   ```bash
   git push origin erp-pilot-v1.0
   ```

---

## 4. Tag Geri Alma Prosedürü (Rollback Tag)

Eğer basılan tag'de kritik bir hata tespit edilirse ve tag'in iptal edilmesi gerekirse:
1. **Lokal Tag'i Silme:**
   ```bash
   git tag -d erp-pilot-v1.0
   ```
2. **Uzak Sunucudaki Tag'i Silme (Push Delete):**
   ```bash
   git push origin --delete erp-pilot-v1.0
   ```

---

## 5. Tag Öncesi Bilinen Riskler (MVP Limitasyonları)

Tag basılmadan önce aşağıdaki MVP limitasyonları bilinmeli ve kabul edilmiş sayılmalıdır:
1. **e-Fatura / e-Belge:** Canlı GİB veya Özel Entegratör bağlantısı yoktur. Akışlar simülasyon düzeyindedir.
2. **POS Modülü:** Barkod okuyucu, fiş yazıcı ve ödeme terminali donanım bağlantısı yoktur. Web POS olarak çalışır.
3. **AI Asistan:** Veritabanına yazma (silme/ekleme) yetkisi engellenmiştir, salt okunur çalışır.
4. **MarketplaceReportDigestTest Known Issue:** Pazaryeri digest rapor testlerinin in-memory SQLite yerine MySQL bağımlılığı olması sebebiyle local'de zaman zaman processed değerinin 0 dönme riski vardır. Bu durum ERP modülü için release engeli olarak kabul edilmez.
