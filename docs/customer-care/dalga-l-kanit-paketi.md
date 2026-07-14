# Dalga L Kanıt Paketi — Canary Pilot, Monitoring ve Circuit Breaker

Bu doküman, Dalga L kapsamında gerçekleştirilen Canary izleme, hata eşikleri, otomatik devre kesici (Circuit Breaker) ve manuel müdahale araçlarının doğrulama adımlarını ve kanıtlarını içerir.

## 1. Uygulanan Özellikler ve Kapsam
- **Eşik Yapılandırmaları:** `config/customer-care.php` dosyasına pilot izleme limitleri eklenmiştir:
  - `max_dispatch_failures_15m`: Son 15 dakikada izin verilen maksimum giden mesaj API hatası.
  - `max_policy_blocks_15m`: Son 15 dakikada izin verilen maksimum politika ihlali engellemesi.
  - `auto_reply_max_per_hour`: Mağaza başına saatlik maksimum otomatik yanıt sayısı (Rate Limit).
- **Otomatik Devre Kesici (Circuit Breaker):** `CustomerCarePilotMonitorService` ile hata oranları izlenmektedir. Belirlenen limitler aşıldığında sistem otomatik olarak `open` (açık - trip) durumuna geçer. Bu durumda, `CustomerCareAutomationGate` otomatik yanıt gönderimini bloke eder, ancak müşteri temsilcilerinin manuel olarak cevap yazabilmesine engel olmaz (Fail-Safe/Fail-Closed).
- **Manuel Müdahale Arayüzleri:** 
  - **Artisan Komutları:** `customer-care:pilot-monitor` komutuyla pilot durum analizi yapılabilir. `customer-care:circuit-breaker {--store=} {--enable|--disable}` komutuyla devre kesici manuel olarak tetiklenebilir (Forced Open) ya da kapatılabilir.
  - **Pilot Kontrol Paneli:** Pilot dashboard ekranına mağaza bazlı devre kesici durumu, aktif hata oranları, kalan limitler gösterge paneli eklenmiş ve temsilcilerin tek tıkla sistemi kilitleyebileceği "Acil Durdurma / Devre Kesiciyi Aç" override butonu entegre edilmiştir.
- **Konsol Zamanlayıcısı:** Monitor görevi `routes/console.php` üzerinden her 15 dakikada bir otomatik çalışacak şekilde zamanlanmıştır.

## 2. Test Sonuçları (CanaryCircuitBreakerTest)
İlgili test paketi (`tests/Feature/CustomerCare/CanaryCircuitBreakerTest.php`) yazılmış ve başarıyla geçmiştir.

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CanaryCircuitBreakerTest.php --compact
```

### Geçen Test Senaryoları
1. `test_monitor_calculates_correct_metrics`: Monitor servisinin son 15 dakikadaki dispatch hatalarını ve politika engellemelerini doğru saydığı doğrulanmıştır.
2. `test_circuit_breaker_trips_due_to_recent_dispatch_failures`: 15 dakikadaki dispatch hata limitinin aşılması durumunda devre kesicinin otomatik olarak tetiklendiği ve AI yanıtlarının gönderilmesinin engellendiği doğrulanmıştır.
3. `test_circuit_breaker_trips_due_to_policy_blocks`: Politika engelleme limiti aşıldığında devre kesicinin tetiklendiği doğrulanmıştır.
4. `test_manual_override_blocks_automatic_reply_but_allows_manual_reply`: Devre kesici manuel kilitleme durumundayken AI cevaplarının engellendiği ama temsilci cevaplarının başarılı bir şekilde gönderilebildiği doğrulanmıştır.
5. `test_circuit_breaker_rate_limiting_hourly`: Saatlik otomatik yanıt limitinin aşılması durumunda rate limit korumasının devreye girip gönderimi engellediği doğrulanmıştır.
6. `test_artisan_commands_run_successfully`: `pilot-monitor` ve `circuit-breaker` Artisan komutlarının başarıyla çalıştığı doğrulanmıştır.
7. `test_schedule_registration`: Zamanlayıcı listesinde monitor görevinin başarıyla kayıtlı olduğu doğrulanmıştır.

---
**Durum:** Başarılı (Wave L Teslime Hazır)
