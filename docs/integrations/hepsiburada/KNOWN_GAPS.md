# Hepsiburada API Entegrasyonu Bilinen Eksikler (Known Gaps)

Bu doküman, P0 salt-okuma sprintinin ardından Hepsiburada entegrasyonunda kalan eksikleri ve sonraki sprintler (P1 ve P2) için önerilen geliştirme adımlarını listeler.

---

## P1 — Operasyon Yazmaları ve Süreç Takibi (P1 Sprint Önerisi)

### 1. Paket Durum Güncellemeleri (Package State Mutations)
* **API Tanımı:** Sipariş paketini toplandı/hazırlandı veya kargoya verildi durumuna çekme.
* **Mevcut Durum:** `capabilities()['package_status']` ve `package_picking` false'tur.
* **Gereksinim:** ZOLM sipariş ekranından paket picking/toplama işleminin Hepsiburada API'sine gönderilmesi.

### 2. Paket Faturalandı Bildirimi & Fatura Linki
* **API Tanımı:** Paket bazında fatura linki gönderme.
* **Mevcut Durum:** `capabilities()['invoice_link']` ve `package_invoice_link` false'tur.
* **Gereksinim:** E-Fatura entegrasyonundan dönen PDF linkinin Hepsiburada'ya POST edilmesi.

### 3. Kargoya Teslim Süresi Güncelleme (Lead Time / Dispatch Time)
* **API Tanımı:** Ürün listing'inin kargoya verilme süresini (örneğin 2 gün) güncelleme.
* **Mevcut Durum:** Listing sync'de okunuyor fakat update metodu connector'da yok.

### 4. Fiyat/Stok Batch Sonuç Takip Otomasyonu
* **API Tanımı:** Gönderilen batch fiyat/stok güncellemelerinin durumunu otomatik çeken bir worker/job zinciri.
* **Mevcut Durum:** `pullBatchStatus` manuel çağrılabilir fakat otomatik bir poller job'a bağlı değil.

---

## P2 — Gelişmiş Entegrasyon ve Ekosistem (P2 Sprint Önerisi)

### 1. Gerçek Zamanlı Sipariş ve Paket Webhook'ları
* **API Tanımı:** Sipariş oluştuğunda veya paket durumu değiştiğinde anlık webhook alımı.
* **Mevcut Durum:** `capabilities()['webhooks']` false'tur. `webhook_refresh` istekleri sipariş çekmeye yönlendirilir.
* **Gereksinim:** HMAC signature doğrulaması içeren endpoint controller'ı ve payload processing job.

### 2. E-Fatura ve E-Arşiv Mükellef Sorgulama
* **API Tanımı:** Hepsiburada E-Faturam altyapısı üzerinden e-fatura sorgulama ve entegrasyonu.

### 3. Kampanya ve Promosyon Yönetimi
* **API Tanımı:** Mağaza içi aktif kampanyaları listeleme, uygun ürünleri kampanyaya dahil etme veya komisyon indirimli kampanyaları takip etme.

### 4. Ticket ve Entegrasyon Destek Entegrasyonu
* **API Tanımı:** API veya katalog yükleme hatalarında satıcı paneline girmeden ZOLM üzerinden destek kaydı açma.

### 5. Tedarikçi Entegrasyonu (Supplier API)
* **API Tanımı:** Hepsiburada'nın tedarik veya doğrudan sipariş karşılama (fulfillment) altyapısının entegre edilmesi.
