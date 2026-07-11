# ZOLM ERP & Ön Muhasebe — Smoke Test Kanıt Şablonu (Smoke Test Evidence Template)

Bu doküman, pilot deployment sonrasında çalıştırılan manuel ve otomatik smoke testlerin sonuçlarını kayıt altına almak için kullanılacak olan resmi kanıt şablonudur.

---

## 1. Test Ortamı Genel Bilgileri

- **Test Ortamı:** Staging / Production
- **Commit Hash:** 
- **Release Tag:** `erp-pilot-v1.0`
- **Test Eden Kişi:** 
- **Test Tarihi:** 
- **Pilot Kullanıcı ID:** 
- **Feature Flag Durumu:** `ACCOUNTING_ENABLED=true` / `PARTY_CORE_ENABLED=true`
- **Migration Durumu:** Tüm migrationlar uygulandı / Bekleyen var
- **Release Evidence Doküman Linki:** [accounting-pilot-release-evidence.md](file:///Volumes/TWINMOS/zolm/docs/accounting-pilot-release-evidence.md)

---

## 2. Otomatik Test JSON Çıktıları

### 2.1. Release Checker JSON Çıktısı
*Aşağıdaki alana `php artisan accounting:pilot-release-check --user={id} --json` komutundan dönen JSON çıktısını yapıştırın:*
```json
// Buraya yapıştırılacak
```

### 2.2. Smoke Test JSON Çıktısı
*Aşağıdaki alana `php artisan accounting:pilot-smoke-test --user={id} --json` komutundan dönen JSON çıktısını yapıştırın:*
```json
// Buraya yapıştırılacak
```

---

## 3. Manuel Smoke Test Sonuç Tablosu

Aşağıdaki whitelisted 18 ERP route listesinin doğruluğunu kontrol edin:

| Test No | Route / Ekran | Beklenen Sonuç | Gerçek Sonuç | Kanıt (Screenshot / Link) | Durum | Not |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **01** | `/accounting` (accounting.dashboard) | Dashboard yüklenmeli, KPI kartları sıfırdan farklı bakiye göstermeli. | | | `Passed` / `Warning` / `Failed` | |
| **02** | `/accounting/pilot-center` (accounting.pilot-center) | Sağlık durumu listesi, UAT sekmeleri ve geri bildirim tablosu görünmeli. | | | `Passed` / `Warning` / `Failed` | |
| **03** | `/accounting/parties` | Cari listesi ve "Yeni Cari" butonu yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **04** | `/accounting/party-ledger` | Müşteri/Tedarikçi borç/alacak bakiye detay ekstresi yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **05** | `/accounting/chart-of-accounts` (accounting.chart-of-accounts) | Hesap Planı tablosu ve hesap hiyerarşisi yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **06** | `/accounting/products` (accounting.products) | Ürün kartları listesi render edilmeli. | | | `Passed` / `Warning` / `Failed` | |
| **07** | `/accounting/audit-logs` (accounting.audit-logs) | Denetim günlüğü tablosu ve bulguları render edilmeli. | | | `Passed` / `Warning` / `Failed` | |
| **08** | `/accounting/journal` | Detaylı Yevmiye Fişleri tablosu ve listesi açılmalı. | | | `Passed` / `Warning` / `Failed` | |
| **09** | `/accounting/cash-bank` | Merkez Kasa ve Ziraat Bankası hesapları bakiye detayları yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **10** | `/accounting/stock` | Stok ve envanter kartı listesi render edilmeli. | | | `Passed` / `Warning` / `Failed` | |
| **11** | `/accounting/sales` | Satış siparişleri ve "Sipariş Oluştur" butonu listelenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **12** | `/accounting/purchases` | Satın alma siparişleri ve tedarikçi hareket tablosu yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **13** | `/accounting/collections-payments` | Tahsilat ve ödeme fişleri giriş/listeleme ekranı yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |
| **14** | `/accounting/pos` | Web POS hızlı satış arayüzü render edilmeli. | | | `Passed` / `Warning` / `Failed` | Donanım testi simüledir. |
| **15** | `/accounting/e-documents` | e-Fatura draft fatura oluşturma ve gönderme ekranı yüklenmeli. | | | `Passed` / `Warning` / `Failed` | GİB portalı simüledir. |
| **16** | `/accounting/reports` | Rapor grafikleri render edilmeli. | | | `Passed` / `Warning` / `Failed` | |
| **17** | `/accounting/assistant` | AI Asistan sohbet arayüzü yüklenmeli. | | | `Passed` / `Warning` / `Failed` | Asistan salt okunurdur. |
| **18** | `/accounting/marketplace-bridge` | Trendyol/Pazaryeri entegrasyon ekranı yüklenmeli. | | | `Passed` / `Warning` / `Failed` | |

---

## 4. Genel Değerlendirme ve Onay Durumu

- **Failed Hata Sayısı:** 
- **Warning Hata Sayısı:** 
- **Pilot Release Kararı:** `Ready` / `Ready with warnings` / `Blocked`
- **Sonuç:** `ONAYLANDI` / `REDDEDİLDİ`
- **Açıklama:**
