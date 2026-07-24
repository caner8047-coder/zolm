# Notion taslağı — Queue Worker Güvenliği ve Finans Backfill

## Başlık ve özet

ZOLM pazaryeri ve WhatsApp kuyrukları için kalıcı worker servisleri, job
tekilleştirmesi, timeout güvenliği ve backlog izleme eklendi. Birikmiş test ve
tekrarlı işler temizlendi. Griza mağazasındaki `10937279991` siparişi için
tarihsel Trendyol finans backfill'i çalıştırıldı.

## İş ihtiyacı ve kullanıcıya etkisi

Scheduler iş üretirken worker bulunmadığı için 2.925 job birikmiş ve finans
senkronu Trendyol API'sine ulaşmamıştı. Yeni yapı işlerin düzenli
tüketilmesini, aynı işin tekrar tekrar oluşmamasını ve kuyruk büyümesinin
erken fark edilmesini sağlar. İlgili sipariş artık tahmini değil, gerçek
finans hareketleriyle `confirmed` durumundadır.

## Teknik yaklaşım

- İki kalıcı Docker queue worker servisi
- `marketplace-sync`, `marketplace-maintenance`, `whatsapp` ve `default`
  queue ayrımı
- 1.800 saniyelik job timeout'una karşı 2.100 saniye `retry_after`
- Dört periyodik job için `ShouldBeUnique`
- Eski queued sync run varken yenisini üretmeyen dispatcher koruması
- Beş dakikalık, 100 job eşikli queue monitor ve kritik log
- WhatsApp retry job'ında geçersiz durum sabiti ve retry sorgusu düzeltmesi

## Değiştirilen bileşenler

- `compose.yaml`
- `config/queue.php`
- `config/marketplace.php`
- `.env.example`
- `routes/console.php`
- `app/Providers/AppServiceProvider.php`
- Pazaryeri ve WhatsApp queue job sınıfları
- `DispatchDueMarketplaceSyncsCommand`
- Queue güvenliği ve WhatsApp retry testleri

## Veri modeli veya migration değişiklikleri

Migration ve şema değişikliği yoktur.

## Kullanım adımları

1. `docker compose up -d queue-marketplace queue-default scheduler`
2. `php artisan queue:monitor database:default,database:marketplace-sync,database:marketplace-maintenance,database:whatsapp --max=100`
3. Worker ve scheduler loglarını deployment log sistemi üzerinden izle.

## Yetki ve feature flag bilgileri

Yeni feature flag yoktur. Mevcut pazaryeri/WhatsApp feature flag'leri
korunmuştur.

## Test kapsamı

- Queue routing ve benzersizlik sözleşmeleri
- Retry window / job timeout uyumu
- Worker servislerinin Compose tanımı
- Altı saatlik queued sync run tekrarının engellenmesi
- WhatsApp failed/stale processing retry seçimi
- Pazaryeri schedule ve Trendyol V2 regresyonları
- Son toplam: 21 test, 91 assertion

## Canlı doğrulama

- Dört queue: `size=0`, `pending=0`, `reserved=0`
- `failed_jobs=0`
- Aktif `queued/processing` sync run: 0
- Scheduler ve iki worker: çalışıyor
- Sipariş `10937279991`: 2 finans olayı, `confirmed` snapshot
- Seller revenue: ₺1.539,23
- Commission: ₺459,77

## Bilinen sınırlamalar

- Queue alarmı kritik log üretir; Slack/PagerDuty gibi harici bildirim
  deployment log altyapısına bağlanmalıdır.
- Mevcut kurulum database queue kullanır. Yük artarsa Redis/Horizon
  değerlendirilmelidir.

## Geri alma planı

1. Scheduler'ı durdur.
2. Worker servislerini durdur.
3. Queue ve aktif sync run durumunu kontrol et.
4. Kod değişikliklerini geri al.
5. Gerekiyorsa
   `storage/app/backups/pre-queue-restart-20260724-0215.sql.gz` yedeğini
   kontrollü geri yükle.

## İlgili commit veya PR

Commit oluşturulmadı. Önerilen commit grupları:

1. `fix: add resilient queue workers and deduplicate scheduled jobs`
2. `fix: repair whatsapp failed-message retry query`
3. `test: cover queue safety and retry behavior`
4. `docs: document queue worker safety decision`

## Yayın tarihi ve sorumlu

- Tarih: 2026-07-24
- Sorumlu: ZOLM geliştirme ekibi

## Slack taslağı

```text
🚀 Queue Worker Güvenliği tamamlandı

- Ne değişti: Pazaryeri ve WhatsApp için iki kalıcı worker, ayrı queue'lar,
  job tekilleştirmesi, güvenli retry window ve backlog monitor eklendi.
- Kullanıcıya etkisi: İşler artık kuyruğa yığılıp beklemiyor; aynı job tekrar
  üretilmiyor. 10937279991 siparişinin gerçek Trendyol finansı alındı.
- Test durumu: 21 test / 91 assertion başarılı. Canlı queue ve failed job 0.
- Yayın / feature flag durumu: Yerel Docker servisleri aktif; yeni flag yok.
- Dikkat edilmesi gerekenler: Kritik queue loglarını merkezi alarma bağlayın.
- Dokümantasyon: Bu yayın notu ve ADR-010.
- PR / commit: Henüz oluşturulmadı.
```
