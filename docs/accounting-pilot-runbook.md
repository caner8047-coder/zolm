# ZOLM ERP & Ön Muhasebe — Pilot Çalıştırma Kitabı (Pilot Runbook)

Bu doküman, ZOLM Ön Muhasebe / ERP modülünü pilot kullanıcılara açma, izleme, hata/geri bildirim toplama ve kriz durumlarında rollback adımlarını içerir.

---

## 1. Pilot Başlatma Adımları

Canlı pilot aşamasını başlatmak için sırasıyla aşağıdaki adımlar uygulanmalıdır:
1. **Veritabanı Yedeği:** Canlı veritabanının yedeğini alın.
2. **Migration:** Yeni pilot ve geri bildirim tablolarını uygulayın:
   ```bash
   php artisan migrate --force
   ```
3. **Feature Flag:** Canlı ortam `.env` dosyasında flag'i aktif edin:
   ```env
   ACCOUNTING_ENABLED=true
   ```
4. **Cache Temizleme:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```

---

## 2. Feature Flag Yönetimi

ERP modülü tamamen `ACCOUNTING_ENABLED` feature flag'i arkasındadır. 
- Flag **true** yapıldığında: Sadece `admin` yetkisine sahip kullanıcılar sol menüde "Muhasebe (ERP)" başlığını görür ve `/accounting/*` route'larına erişebilir.
- Flag **false** yapıldığında: Tüm ERP route'ları otomatik olarak 404 (Not Found) döner ve menü gizlenir.

---

## 3. Demo Veri Kurulumu & Production Guard

Test veya pilot onboard aşamalarında demo veri kurmak için:
```bash
php artisan accounting:seed-demo --user={admin_user_id} --reset
```
* **Production Guard:** Canlı (production) ortamda bu komutun kazara çalışarak verileri sıfırlamasını engellemek için güvenlik kilidi eklenmiştir. Canlı ortamda komutun çalışabilmesi için `--force` seçeneğinin eklenmesi zorunludur:
  ```bash
  php artisan accounting:seed-demo --user={admin_user_id} --reset --force
  ```

---

## 4. Health Check Nasıl Okunur?

Pilot Merkezi (`/accounting/pilot-center`) üzerinden veya `AccountingPilotReadinessService` servisiyle çalıştırılan sağlık taramaları şu sonuçları üretir:
- **Passed (Tamam):** Sistem tamamen pilot kullanıma hazır.
- **Warning (Uyarı):** Sistemde hafif aksaklıklar veya MVP limitasyonları mevcut (örn: çözülmemiş feedback'ler, simüle e-fatura).
- **Failed (Hata):** Feature flag kapalı, kullanıcı admin değil veya şirket/depo/kasa/ürün kartlarından biri eksik. **Canlı pilot bu durumdayken başlatılamaz.**

---

## 5. Kullanıcı Geri Bildirim Süreci

Kullanıcılar veya test ekipleri karşılaştıkları sorunları Pilot Merkezi sohbet/geri bildirim formu üzerinden bildirebilir.
- Bildirimler önem derecesine (`low`, `medium`, `high`, `critical`) göre sınıflandırılır.
- Kritik geri bildirimlerin sayısı son health check skorunu doğrudan düşürür.
- Çözülen bildirimler admin tarafından "Çözüldü" (resolved) olarak işaretlenir.

---

## 6. Kritik Durumda Rollback (Geri Dönüş) Adımları

Canlı pilot esnasında veri bütünlüğünü bozan veya kritik sistem kesintisine yol açan bir durum yaşanırsa:
1. **Feature Flag Kapatma:** `.env` dosyasından anında modülü kapatın:
   ```env
   ACCOUNTING_ENABLED=false
   ```
   Bu işlem tüm ERP arayüzünü ve API route'larını anında kapatarak kullanıcıların sisteme erişmesini engeller.
2. **Cache Yenileme:**
   ```bash
   php artisan config:cache
   ```
3. **Migration Rollback (Gerekirse):**
   ```bash
   php artisan migrate:rollback --step=1
   ```

---

## 7. Bilinen MVP Limitleri (Limitasyonlar)

Pilot kullanıcılara ve demo izleyicilerine aşağıdaki limitasyonlar önceden hatırlatılmalıdır:
1. **e-Fatura Entegrasyonu:** Gerçek bir özel entegratör veya GİB portal entegrasyonu yoktur. Belge süreçleri simüledir.
2. **POS Donanım Entegrasyonu:** Barkod okuyucu, fiş yazıcı veya temassız ödeme cihazı entegrasyonu yoktur; POS arayüzü Web POS olarak çalışır.
3. **AI Asistan:** Salt okunur çalışır; veritabanı üzerinde fatura oluşturma, silme veya güncelleme gibi yazma yetkisi güvenlik gereği engellenmiştir.
