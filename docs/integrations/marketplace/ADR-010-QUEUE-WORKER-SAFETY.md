# ADR-010 — Pazaryeri Queue Worker Güvenliği

- Tarih: 2026-07-24
- Durum: Kabul Edildi

## Bağlam

Database queue kullanılırken scheduler çalışıyor ancak kalıcı bir `queue:work`
servisi bulunmuyordu. Scheduler görev üretmeye devam ettiği için kuyruk 2.925
kayda ulaştı. Schedule seviyesindeki `withoutOverlapping()` yalnızca işi
kuyruğa bırakan kısa callback'i kilitlediğinden, bekleyen job'ın tekrar
üretilmesini engellemedi. Ayrıca 1.800 saniyelik pazaryeri job timeout'u ile
90 saniyelik database queue `retry_after` değeri aynı işin çift
çalıştırılmasına açıktı.

## Değerlendirilen seçenekler

1. Tüm işleri `sync` connection ile web/scheduler sürecinde çalıştırmak.
2. Bütün job türlerini tek bir `default` worker ile tüketmek.
3. Pazaryeri ve genel/WhatsApp işlerini ayrı worker ve queue'lara ayırmak;
   tekrar eden job'ları benzersiz yapmak ve aktif sync run tekrarını
   dispatcher seviyesinde engellemek.

## Karar

Üçüncü seçenek seçildi.

- `queue-marketplace`, `marketplace-sync` ve `marketplace-maintenance`
  queue'larını tüketir.
- `queue-default`, `whatsapp` ve `default` queue'larını tüketir.
- Worker servisleri `restart: unless-stopped` ile kalıcı çalışır.
- `DB_QUEUE_RETRY_AFTER=2100`, en uzun job timeout'u olan 1.800 saniyenin
  üzerinde tutulur.
- Buybox, batch takip, WhatsApp retry ve sepet kurtarma job'ları
  `ShouldBeUnique` kullanır.
- Zamanı gelen pazaryeri sync dispatcher'ı, yaşı ne olursa olsun aktif
  `queued`/`processing` run varken yenisini üretmez.
- Dört queue her beş dakikada `queue:monitor` ile izlenir; 100 job eşiği
  `critical` log üretir.

## Sonuçlar

### Olumlu

- Worker kesintisinde aynı periyodik job binlerce kez birikmez.
- Uzun pazaryeri senkronları WhatsApp işlerini bloke etmez.
- Worker çökmesi Docker tarafından otomatik toparlanır.
- Timeout/retry uyumu aynı işin erken tekrar alınma riskini azaltır.
- Backlog eşiği log üzerinden izlenebilir.

### Olumsuz

- İki worker servisi ek kaynak tüketir.
- Database cache benzersizlik kilitlerinin sürekliliği için cache tablosunun
  erişilebilir olması gerekir.
- Kritik logların harici alarm kanalına taşınması deployment ortamının log
  toplama sistemine bağlıdır.

## Geri dönüş ve yeniden değerlendirme

Worker servisleri durdurulup scheduler kapatılarak güvenli moda dönülebilir.
Kod geri alınacaksa önce scheduler durdurulmalı ve queue boşluğu
doğrulanmalıdır. İş hacmi belirgin biçimde büyürse Redis queue/Horizon ve
queue başına yatay worker ölçekleme yeniden değerlendirilmelidir.
