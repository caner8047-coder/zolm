# ZOLM ERP & Ön Muhasebe — Pilot Fix Sprint Backlog (P23)

Bu doküman, pilot release sonrasında `accounting:pilot-backlog` komutuyla otomatik triage edilen geri bildirimlerin önceliklendirilmiş backlog listesini ve sprint kararlarını içerir.

---

## 1. Backlog Özeti

- **Tarih:** 11 Temmuz 2026
- **Commit Hash:** `e1495b9`
- **Monitoring Kararı:** `proceed` (Açık kritik geri bildirim yok)
- **Toplam Açık Geri Bildirim:** 0
- **Fix Now Sayısı:** 0
- **Fix Next Sayısı:** 0
- **Watch Sayısı:** 0
- **Document Sayısı:** 0

---

## 2. Öncelik Kuralları

### 2.1. Severity Puanı
| Severity | Puan |
| :--- | :--- |
| `critical` | +80 |
| `high` | +60 |
| `medium` | +35 |
| `low` | +15 |

### 2.2. Type Bonusu
| Type | Bonus |
| :--- | :--- |
| `bug`, `data` | +15 |
| `risk` | +10 |
| `ux` | +5 |
| `question` | +0 |

### 2.3. Modül Risk Bonusu
Yüksek riskli modüller: `accounting`, `stock`, `sales`, `purchase`, `pos`, `e_document`, `integration`
→ +10 puan

### 2.4. Yaş Bonusu
| Yaş | Bonus |
| :--- | :--- |
| ≥ 7 gün | +10 |
| ≥ 3 gün | +5 |

### 2.5. Recommended Action Eşiği
| Priority Score | Aksiyon |
| :--- | :--- |
| ≥ 85 | `fix_now` → **P23-hotfix** |
| 60–84 | `fix_next` → **P24** |
| 30–59 | `watch` → later |
| < 30 | `document` → later |

### 2.6. Owner Hint Kuralları
| Kural | Sahip |
| :--- | :--- |
| `integration`, `e_document`, `pos` modülleri | `ops` |
| type `bug`, `data`, `risk` | `engineering` |
| type `ux`, `question` | `product` |
| diğer | `support` |

---

## 3. Backlog Tablosu

| ID | Modül | Başlık | Severity | Type | Priority Score | Recommended Action | Owner | Target Phase | Durum |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| — | — | Açık geri bildirim yok | — | — | — | — | — | — | — |

---

## 4. P23 Hotfix Adayları

> **Boş** — Şu an `fix_now` seviyesinde açık geri bildirim bulunmamaktadır.

Pilot aktif kullanıma alındığında `fix_now` seviyesine gelen maddeler buraya eklenerek P23 hotfix sprinti başlatılacaktır.

---

## 5. P24 Sprint Adayları

> **Boş** — Şu an `fix_next` seviyesinde açık geri bildirim bulunmamaktadır.

---

## 6. Watch / Document Adayları

> **Boş** — İzleme altındaki veya dokümantasyon gerektiren açık geri bildirim bulunmamaktadır.

---

## 7. Karar

**Karar:** ✅ **No hotfix required**

Pilot henüz aktif kullanıcı geri bildirimi almadığı için tüm backlog boş durumdadır. Pilot kullanıcılar Pilot Center üzerinden geri bildirim girdikçe bu tablo otomatik olarak güncellenecektir.

---

## 8. Bilinen Sistemik Limitler (Risk Sicilinden Taşınan)

Bu maddeler backlog puanlamasına girmez; platform kararlarıdır:

1. **e-Fatura / e-Arşiv:** Gerçek GİB entegratörü yok; süreç simülasyondur.
2. **POS Donanım:** Barkod okuyucu / fiş yazıcı entegrasyonu yok; Web POS olarak çalışır.
3. **AI Asistan:** Salt okunurdur; doğrudan veri değiştirme eylemi yürütmez.
4. **MarketplaceReportDigestTest:** SQLite ↔ MySQL uyuşmazlığı — bilinen harici test sorunu, release engeli değil.
