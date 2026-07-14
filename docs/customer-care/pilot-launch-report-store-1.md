# Pilot Lansman Raporu — Mağaza: ZEM SORULAR TRENDYOL (ID: 1)

**Oluşturulma Tarihi:** 2026-07-14 15:36:53
**Genel Durum:** ⚠️ KANARYA GEÇİT ENGELLERİ MEVCUT

## 1. Hazırlık Durumu (Readiness Checks)

| Kriter | Durum | Detay |
|---|---|---|
| Müşteri İletişim Merkezi Master Switch | ✅ PASS | Aktif |
| Otomatik Yanıt Özelliği (Auto-Reply) | ⚠️ WARN | Pasif |
| Pilot Mağaza İzin Listesi (Allowlist) | ❌ FAIL | İzin verilmemiş |
| Aktif İletişim Kanalları | ❌ FAIL | Toplam 1 kanaldan 0 tanesi aktif. |
| AI Servis Bağlantısı (Gemini) | ❌ FAIL | Gemini sağlayıcı yapılandırması kullanılamıyor. |
| Sistem Aktörü (System Actor) | ✅ PASS | Kullanıcı bulundu: System Actor |
| Outbox Bekleyen Mesaj Kuyruğu (Backlog) | ✅ PASS | Kuyrukta bekleyen 0 mesaj var (üst sınır: 10). |
| Golden Dataset Değerlendirme Eşiği | ❌ FAIL | Henüz değerlendirme yapılmadı (kanıt bulunamadı). |
| Shadow Mode Benzerlik Ortalaması | ❌ FAIL | Henüz karşılaştırma yapılmadı |
| Türkçe Dil Kalite Kapısı | ❌ FAIL | En az 20 örnek, %80 kalite, %95 kaynak doğruluğu ve sıfır kritik hata gerekli |
| Doğrulanmış Onboarding Kanıtı | ❌ FAIL | Güncel kanal/capability, katalog ve ilk doğrulanmış AI taslağı kanıtı eksik. |
| Kanal Politika Motoru (Policy Engine) | ✅ PASS | Aktif |

## 2. Devre Kesici (Circuit Breaker)

- **CB Durumu:** 🟢 CLOSED (Normal)
- **Son 15 Dakikadaki Hatalar:** 0
- **Son 15 Dakikadaki Politika Blokajları:** 0
- **Blokaj Sebebi:** Yok

## 3. Kota ve Kullanım Sınırları (Quotas)

| Metrik | Harcanan | Limit | Durum |
|---|---|---|---|
| Ai drafts | 0 | 500 | ✅ Limit Altı |
| Auto replies | 0 | 200 | ✅ Limit Altı |
| Connected channels | 0 | 5 | ✅ Limit Altı |
| Knowledge suggestions | 0 | 20 | ✅ Limit Altı |

## 4. Son 24 Saatlik Operasyon Metrikleri

- **AI Taslak Sayısı:** 0
- **Otomatik Cevap Sayısı:** 0
- **Politika Blokajı:** 0
- **Temsilciye Aktarma (Handoff):** 0

## 5. Hata Detayları

Son dönemde herhangi bir outbox gönderim hatası kaydedilmedi.

## 6. Route & Command Inventory

### Aktif Rotalar (Routes)
- **customer-care.onboarding:** `/customer-care/onboarding` (Guided Setup Wizard)
- **customer-care.admin:** `/customer-care/admin` (Yönetici Kontrol Merkezi)
- **customer-care.inbox:** `/customer-care/inbox` (Temsilci Çalışma Ekranı)
- **customer-care.analytics:** `/customer-care/analytics` (Metrik ve Analizler)
- **customer-care.settings:** `/customer-care/settings` (Modül Ayarları)

### Konsol Komutları (Artisan Commands)
- `customer-care:pilot-launch-report`: Pilot Mağaza lansman raporunu üretir.
- `customer-care:usage-report`: Mağaza bazlı kota kullanım raporunu üretir.
- `customer-care:circuit-breaker`: Manuel CB Override kontrolü sağlar.
- `customer-care:generate-knowledge-suggestions`: Bilgi bankası önerilerini analiz eder.
- `customer-care:run-golden-eval`: Golden dataset değerlendirmesini çalıştırır.
- `customer-care:anonymize`: KVKK PII maskeleme ve temizliğini tetikler.

## 7. Golden Evaluation Summary

Bu mağaza için henüz yapılmış bir Golden Dataset değerlendirme kaydı bulunmamaktadır.
