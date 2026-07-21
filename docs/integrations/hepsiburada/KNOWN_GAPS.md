# Hepsiburada API Entegrasyonu Bilinen Eksikler (Known Gaps)

Bu doküman, Hepsiburada P0 salt-okuma sprintinin ardından entegrasyonda kalan eksikleri ve sonraki aşamalar için planları listeler.

---

## 1. Resmî API Dokümantasyonunda Bulunmayan veya Doğrulanamayan Gaps (P1/P2 Kapsamı)

Aşağıdaki yollar/endpoint'ler Hepsiburada resmî geliştirici portalında doğrudan sunulmadığı veya doğrulanamadığı için capabilities `false` yapılmış ve connector çağrıları devre dışı bırakılmıştır:
* **Prepared paket endpoint'i** (`packages/.../prepared`) -> `not_verified, not_implemented`
* **Split paket endpoint'i** (`packages/.../split`) -> `not_verified, not_implemented`
* **Cancelled paket endpoint'i** (`packages/.../cancelled`) -> `not_verified, not_implemented`
* **Unpaid veya ödeme bekleyen sipariş endpoint'i** (`packages/.../unpaid`) -> `not_verified, not_implemented`
* **Marka listesi API'si** (Katalog entegrasyon referanslarında genel marka sorgulama listesi API'si doğrulanmadı) -> `not_implemented`

---

## 2. P1 — Operasyon Yazmaları ve Süreç Takibi (P1 Sprint Önerisi)

### 2.1 Paket Durum Değişiklikleri (Package Mutations)
* **Aksiyon:** Sipariş paketini kabul etme, toplandı/hazırlandı statüsüne çekme veya kargoya teslim bildirimleri.
* **Durum:** `not_implemented` (`package_status => false`, `package_picking => false`).

### 2.2 Paket Fatura Linki Gönderimi
* **Aksiyon:** E-Fatura entegrasyonu tamamlandığında oluşan fatura PDF linkinin Hepsiburada'ya bildirilmesi.
* **Durum:** `not_implemented` (`invoice_link => false`, `package_invoice_link => false`).

---

## 3. P2 — Gelişmiş Entegrasyon ve Ekosistem (P2 Sprint Önerisi)

### 3.1 Gerçek Zamanlı Sipariş ve Paket Webhook'ları
* **Aksiyon:** Sipariş oluştuğunda veya paket durumu değiştiğinde anlık webhook alımı.
* **Durum:** `not_implemented` (`webhooks => false`).

### 3.2 E-Fatura Mükellef Sorgulama & Kampanya Yönetimi
* **Durum:** `not_implemented`.
