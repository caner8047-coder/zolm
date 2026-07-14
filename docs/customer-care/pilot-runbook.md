# ZOLM AI Müşteri İletişim Merkezi — Canlı Pilot Runbook (İşletme Kılavuzu)

Bu belge, ZOLM AI Müşteri İletişim Merkezi pilot süreci canlandırılmadan önce ve canlı kullanım sırasında uygulanacak adımları, rollback planlarını ve acil durum prosedürlerini tanımlar.

---

## 1. Pilot Öncesi Kontrol Listesi (Readiness Checklist)

Canlı pilotu başlatmadan önce, **ZOLM Kurumsal Açık Panel** üzerindeki hazırlık widget'larından veya aşağıdaki Artisan komutundan mağazanın tüm kapılardan geçtiği tescil edilmelidir:

```bash
php artisan customer-care:pilot-readiness --store=STORE_ID
```

### Koşullar:
1. **İzin Listesi (Allowlist):** Mağaza ID'si `.env` dosyasındaki `CUSTOMER_CARE_PILOT_STORE_ALLOWLIST` listesine eklenmiş olmalıdır.
2. **Golden Dataset Değerlendirmesi:** `PilotDashboard` üzerinden çalıştırılan değerlendirmede ortalama başarı oranı **80 ve üzeri** olmalıdır.
3. **Sistem Aktörü:** Veritabanında `system@zolm.com` kullanıcısının provision edilmiş olması gerekir.
4. **AI Sağlayıcısı:** Gemini API key doğrulanmış olmalıdır.

---

## 2. Pilot Canlandırma Adımları

Canlandırma strictly özellik bayraklarıyla yapılır. Sırasıyla şu environment adımları uygulanır:

1. Modülün ve pilot panelinin açılması:
   ```text
   CUSTOMER_CARE_ENABLED=true
   CUSTOMER_CARE_INBOX_ENABLED=true
   CUSTOMER_CARE_PILOT_DASHBOARD_ENABLED=true
   ```
2. AI Copilot (taslak hazırlama) aktif edilmesi:
   ```text
   CUSTOMER_CARE_AI_COPILOT_ENABLED=true
   ```
3. İlgili mağazanın allowlist'e eklenmesi:
   ```text
   CUSTOMER_CARE_PILOT_STORE_ALLOWLIST=STORE_ID
   ```
4. Auto-Reply (otomatik gönderim) kontrollü canlandırılması:
   ```text
   CUSTOMER_CARE_AUTO_REPLY_ENABLED=true
   ```

---

## 3. Rollback & Acil Durdurma Planı (Kill Switch)

Herhangi bir beklenmedik durumda (AI halüsinasyonu, veritabanı yığılması, cross-tenant şüphesi) aşağıdaki kill-switch adımlarından biri uygulanır:

### Seviye 1: Otomatik Yanıtı Kapatma (Auto-Reply Kill Switch)
AI taslakları üretilmeye devam eder ancak haricî kanala (Trendyol/WhatsApp) otomatik mesaj gönderimi tamamen kesilir.
```text
CUSTOMER_CARE_AUTO_REPLY_ENABLED=false
```

### Seviye 2: Küresel Kapatma (Global Master Kill Switch)
Tüm AI ve kuyruk mekanizmaları anında bypass edilir.
```text
CUSTOMER_CARE_ENABLED=false
```

---

## 4. Manuel Moda Geri Dönüş

Otomatik canlandırma kapısı engellendiğinde ya da acil durumda konuşmaların manuel moda dönmesi için:
1. Konuşmanın detayı arayüzünden ownership durumu temsilciye (`human`) çekilir.
2. `ownership_status = 'human'` durumuna geçen konuşmalara AI kesinlikle otomatik cevap gönderemez (Optimistic lock güvencesiyle korunmaktadır).
