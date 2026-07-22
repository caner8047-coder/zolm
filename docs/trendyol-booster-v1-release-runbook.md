# ZOLM Trendyol Booster v1.0 — Yayın ve Geri Alma Runbook'u

## Yayın modeli

Booster iki bağımsız bariyerle açılır:

1. `MARKETPLACE_TRENDYOL_BOOSTER_ENABLED`: modülün ana feature flag'i.
2. `MARKETPLACE_TRENDYOL_BOOSTER_RELEASE_RING`: `off`, `beta` veya `ga`.

`beta` halkasında yöneticiler ve `MARKETPLACE_TRENDYOL_BOOSTER_BETA_USER_IDS` içindeki kullanıcılar erişebilir. Varsayılan `ga`, mevcut operator/manager/admin yetkilendirmesini geriye uyumlu biçimde korur.

## Ön kontrol

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan route:cache
php artisan marketplace:trendyol-booster-health --minutes=60
npm run extension:release
```

Şunlar doğrulanmadan halka büyütülmez:

- Readiness raporunda engelleyici kontrol yok.
- Companion route zincirinde auth, operator yetkisi, feature flag, release ring ve kullanıcı bazlı throttle var.
- Production manifestinde `localhost`, `127.0.0.1` ve yüksek riskli izin yok.
- ZIP SHA-256 değeri yüklenen dosyayla aynı.
- Son 60 dakikada companion 5xx hata oranı `%5` veya altında.
- İlk pilot ürün için analiz, Radar takibi, stok, fırsat taraması ve rapor indirme akışı tamamlandı.

## Kontrollü beta

```dotenv
MARKETPLACE_TRENDYOL_BOOSTER_ENABLED=true
MARKETPLACE_TRENDYOL_BOOSTER_RELEASE_RING=beta
MARKETPLACE_TRENDYOL_BOOSTER_BETA_USER_IDS=12,34
MARKETPLACE_TRENDYOL_BOOSTER_OBSERVABILITY_ENABLED=true
```

Config cache yenilendikten sonra pilot kullanıcılar test matrisini uygular. Telemetri yalnız route adı, HTTP metodu, durum kodu, süre, yayın halkası, sonuç ve kullanıcı ID'sini tutar; URL, query, ürün içeriği ve request/response payload'ı tutulmaz.

## GA geçişi

Beta gözlem penceresi temizse `MARKETPLACE_TRENDYOL_BOOSTER_RELEASE_RING=ga` yapılır ve config cache yenilenir. Eski extension sürümü zorla kapatılmaz; companion API geriye uyumlu kalır.

## Geri alma

En hızlı ve veri kaybetmeyen geri alma:

1. `MARKETPLACE_TRENDYOL_BOOSTER_RELEASE_RING=off` yap.
2. `php artisan config:cache` çalıştır.
3. Queue worker ve scheduler'ı durdurmadan companion 5xx oranını incele.
4. Gerekirse `MARKETPLACE_TRENDYOL_BOOSTER_ENABLED=false` ile ana flag'i kapat.
5. Extension mağaza sürümünü önceki onaylı ZIP'e geri döndür veya yayını duraklat.

Migration rollback ilk tercih değildir. Yeni tablolar mevcut üretim/operasyon motorlarına dokunmaz ve kapalı ring altında güvenle yerinde kalabilir.

## Operasyon komutları

```bash
php artisan marketplace:trendyol-booster-health --minutes=60
php artisan marketplace:trendyol-booster-health --user=12 --minutes=240 --json
php artisan marketplace:trendyol-booster-readiness --user=12
php artisan marketplace:sync-trendyol-booster --dry-run
```

## Dış mağaza teslimi

`npm run extension:release` şu iki imzalı teslimi üretir:

- `build/trendyol-booster-companion.zip`
- `build/trendyol-booster-companion.zip.sha256`

Chrome Web Store hesabında ZIP yükleme, mağaza görsellerini yerleştirme ve Google inceleme formunu gönderme hesap sahibinin manuel işlemidir. Gerekli metin, izin gerekçeleri, veri kullanımı beyanı ve showcase kaynağı extension klasöründedir.
