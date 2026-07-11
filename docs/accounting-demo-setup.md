# ZOLM ERP & Ön Muhasebe — Onboarding & Demo Kurulum Kılavuzu

Bu doküman, ZOLM ERP/Ön Muhasebe modüllerini kurmak, feature flag'leri yapılandırmak ve demo verilerini oluşturmak/sıfırlamak için gerekli olan tüm adımları içerir.

---

## 1. Feature Flag Yapılandırması

Ön muhasebe modüllerinin aktif edilebilmesi için `.env` dosyanızda aşağıdaki feature flag tanımlarını yapmanız gerekir:

```env
# CRM modülünü aktif eder
CRM_ENABLED=true

# Cari/Party temel katmanını aktif eder (CRM entegrasyonu için zorunlu)
PARTY_CORE_ENABLED=true

# Ön Muhasebe / ERP modülünün tamamını aktif eder
ACCOUNTING_ENABLED=true
```

> [!WARNING]
> Production ortamlarında bu flag'lerin default değerleri güvenlik amacıyla `false` olarak kalmalıdır. Canlı ortamda kademeli olarak aktif edilmesi önerilir.

---

## 2. Demo Veri Kurulumu

Yeni kurulan bir ZOLM ERP sistemini test etmek ve tüm ekranlardaki KPI'ları anlamlı hale getirmek için Artisan demo seeder komutunu kullanabilirsiniz:

### Demo Verisi Oluşturma
Komut çalıştırılırken hangi kullanıcı için demo veri üretileceği `--user` parametresiyle belirtilmelidir:

```bash
php artisan accounting:seed-demo --user=1
```

### Demo Veriyi Sıfırlama ve Yeniden Kurma
Daha önce oluşturulmuş demo verilerini tamamen temizleyip temiz bir kurulum yapmak için `--reset` seçeneğini ekleyebilirsiniz:

```bash
php artisan accounting:seed-demo --user=1 --reset
```

> [!IMPORTANT]
> `--reset` seçeneği **yalnızca** deterministik olarak demo amaçlı üretilmiş (source_key değeri `demo_` veya `DEMO-` ile başlayan) verileri temizler. Gerçek kullanıcı verilerine kesinlikle dokunmaz.

---

## 3. Demo Veri Kapsamı

Komut çalıştırıldıktan sonra aşağıdaki test verileri tenant izolasyonlu olarak kullanıcı hesabına eklenir:
- **1x Yasal Birlik (Legal Entity):** ZOLM Demo Ticaret A.Ş.
- **7x Muhasebe Hesabı (GL Accounts):** Alıcılar (120), Satıcılar (320), Satışlar (600), Ticari Mallar (153), KDV hesapları (391/191) ve Giderler (770).
- **2x Müşteri & 2x Tedarikçi Cari:** (Örnek: ZOLM Demo Perakende Müşteri A.Ş., vb.)
- **1x Kasa & 1x Banka Hesabı:** (ZOLM Demo Merkez Kasa, Ziraat Bankası)
- **1x Depo & 5x Ürün/Stok Kartı:** Her bir üründen depoda 100 adet açılış stoğu bulunur.
- **1x Onaylanmış Satış Siparişi:** 6.400 TL toplam tutarlı (Receivable fatura borcu oluşturur).
- **1x Onaylanmış Satın Alma Siparişi:** 4.800 TL toplam tutarlı (Payable fatura borcu oluşturur).
- **1x Tahsilat:** Kasa üzerinden yapılan 3.000 TL tahsilat faturayla eşleştirilir.
- **1x Ödeme:** Banka üzerinden yapılan 2.000 TL ödeme faturayla eşleştirilir.
- **1x Kasa/Banka Transferi (Virman):** Kasa hesabından bankaya 1.000 TL transfer.
- **1x Manuel Yevmiye Fişi:** Gider hesabı ve kasa arasında 500 TL yevmiye fişi.

---

## 4. Ekranlarda Beklenen KPI Örnekleri

Demo verisi kurulduktan sonra aşağıdaki ERP ekranlarında şu veriler gözlemlenmelidir:

- **Muhasebe Paneli (Dashboard):**
  - **Net Alacak/Borç Durumu:** Müşteri alacak bakiyesi, tedarikçi borç bakiyesi ve net durum.
  - **Kasa & Banka Bakiyeleri:** Kasa ve bankadaki güncel nakit durumu.
- **Cari Açık Hesap:**
  - Cari bakiyeleri, son hareketler (tahsilat, ödeme, sipariş onay yansımaları).
- **Stok / Ürünler:**
  - Depodaki 5 ürünün güncel stok miktarları (satış sonrası düşen, satın alma sonrası artan adetler).
- **Yevmiye Defteri:**
  - Çift taraflı muhasebe kuralına göre otomatik oluşan tüm çift taraflı (Borç / Alacak eşit) yevmiye fişleri.

---

## 5. Güvenlik Notları (Tenant Isolation)

- Demo command'ı, veritabanına doğrudan SQL yazmaz. Kayıtları oluştururken ve onaylarken ERP servis katmanlarını (`TradeService`, `CashBankService`, `CollectionPaymentService`, `JournalService`) kullanır.
- Tüm SQL ve servis sorgularında strict `user_id` izolasyonu korunur. Bir kullanıcının demo komutu başka bir kullanıcının verilerini değiştiremez veya silemez.
