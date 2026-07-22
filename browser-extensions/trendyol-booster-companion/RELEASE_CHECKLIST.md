# ZOLM Booster Chrome Web Store Yayın Kontrolü

## Otomatik kapılar

- [x] Manifest V3 ve semantik sürüm kontrolü
- [x] JavaScript sözdizimi ve zorunlu özellik işaretleri
- [x] Yüksek riskli `camera`, `history`, `cookies`, `downloads`, `webRequest` izinlerinin reddi
- [x] Production ZIP'inden `localhost` ve `127.0.0.1` köprü izinlerinin çıkarılması
- [x] Production ZOLM köprüsünün yalnız `https://m.zolm.com.tr/*` ile sınırlandırılması
- [x] 16/32/48/128 px PNG ikon doğrulaması
- [x] Gizlilik ve veri kullanımı beyanı
- [x] Companion route'larında oturum, admin/rol, feature flag, CSRF ve kullanıcı bazlı hız sınırı
- [x] `off / beta / ga` yayın halkası ve beta kullanıcı izin listesi
- [x] Payload/URL tutmayan companion durum, gecikme ve hata telemetrisi
- [x] ZIP için SHA-256 bütünlük dosyası
- [x] 1280×800 ürün kararı, Radar/aksiyon ve Seller Panel showcase kaynağı

Komut: `npm run extension:release`

Çıktılar: `build/trendyol-booster-companion.zip` ve `build/trendyol-booster-companion.zip.sha256`

## Mağaza alanları

- Ürün adı: `ZOLM Trendyol Booster — Kâr ve Ürün Kararı`
- Kategori: Üretkenlik
- Dil: Türkçe
- Gizlilik URL: `https://m.zolm.com.tr/privacy/trendyol-booster-companion`
- Destek URL: `https://m.zolm.com.tr/marketplace-trendyol-booster`
- Ayrıntılı metin: `STORE_LISTING_TR.md`
- Veri kullanımı: `DATA_USE_DECLARATION_TR.md`

## Manuel yayın adımı

Chrome Web Store geliştirici hesabında ZIP yükleme, `store-assets/showcase.html` içindeki üç 1280×800 görünümün PNG olarak alınması, görsellerin yerleştirilmesi ve Google'ın inceleme formunun gönderilmesi yalnız hesap sahibi tarafından tamamlanır. Bu depo, gönderime hazır ZIP'i, bütünlük özetini, yeniden üretilebilir görsel kaynağını ve beyan metinlerini üretir; dış mağazada yayınlama işlemi yapmaz.
