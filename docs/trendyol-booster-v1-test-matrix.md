# ZOLM Trendyol Booster v1.0 — Kabul Test Matrisi

| Alan | Senaryo | Beklenen kanıt |
|---|---|---|
| Ürün kararı | Maliyetli Trendyol ürününü analiz et | Sat/Satma kararı, güven, kanıt kaynakları ve tahmin etiketi |
| Maliyet bariyeri | Alış maliyeti olmadan ürün aç | Kâr kararı yok; maliyet gerekli uyarısı |
| Medya | Görsel seç, indir ve bağlantı kopyala | Yalnız seçili görseller; 15 MB ve içerik türü bariyeri |
| Liste fırsatı | Arama sayfasında fırsat taraması | En fazla 40 görünür ürün, açıklanabilir skor |
| Karar kuyruğu | Toplu kuyruğu başlat ve sekmeyi yenile | Kuyruk kalıcı; geçici hata bir kez yeniden denenir |
| Radar | 1–4 ürünü toplu takibe al | Başarısız ürün diğerlerini durdurmaz |
| Mağaza 360 | Rakip mağazayı tara | Portföy, fiyat bandı, kategori ve değişim sinyalleri |
| Seller Panel | Fiyat/kampanya/reklam sayfası | Birim ekonomi, maksimum reklam maliyeti, başa baş ROAS |
| Sipariş kârı | Snapshot olan ve olmayan sipariş | Kesinleşmiş Kâr / Anlık Tahmin ayrımı |
| Tedarikçi | Alış ve hedef fiyat gir | Maksimum alış maliyeti, marj ve kanıt sınırı |
| Mobil keşif | Barkod fotoğrafı veya manuel kod | Görsel cihazda işlenir; yalnız kod gönderilir |
| Koleksiyon | Ürünü koleksiyona ekle/çıkar | Kullanıcı izolasyonu ve kalıcı üyelik |
| Rapor | Excel ve PDF indir | Türkçe karakter, veri kapsamı, Excel güvenli string yazımı |
| AI asistan | Ürün hakkında soru sor | Yalnız kullanıcı ürünü; `[K1]` kanıt referansı; maliyetsiz kâr yok |
| Takım | Aksiyonu başka kullanıcıya ata | Manager yetkisi, atama ve audit geçmişi |
| Güvenlik | Oturumsuz/izinsiz companion çağrısı | Redirect/403; route başına 120/dk throttle |
| Beta | İzin listesi dışı operator | 403; admin veya pilot kullanıcı erişir |
| Telemetri | Başarılı, 4xx ve 5xx çağrılar | Durum/süre kaydı; URL ve payload alanı yok |
| Paket | `npm run extension:release` | v1.0.0 ZIP, production origin, SHA-256, yasak izin yok |
| Geri alma | Ring'i `off` yap | Veriyi silmeden panel ve companion erişimi kapanır |
