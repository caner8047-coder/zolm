# Chrome Web Store Veri Kullanımı Beyanı

## Tek amaç

Trendyol'da kullanıcıya gösterilen ürün ve satıcı verisini kullanıcının ZOLM hesabındaki ürün araştırması, stok/fiyat takibi ve satıcı kârlılığı iş akışlarıyla birleştirmek.

## İşlenen veri sınıfları

- Web sitesi içeriği: Trendyol ürün başlığı, fiyat, görünür stok, puan, yorum/favori ve satıcı bilgileri.
- Kullanıcı etkinliği: Yalnız kullanıcının başlattığı analiz, takip, indirme ve araştırma aksiyonları.
- Kimlik doğrulama bilgisi: ZOLM oturum çerezi tarayıcı tarafından istekle birlikte gönderilir; eklenti parolayı veya çerezin değerini okumaz ve saklamaz.
- Uygulama ayarları: ZOLM adresi ve kârlılık tercihleri Chrome depolamasında tutulur.

## Kullanılmayan amaçlar

- Reklam hedefleme veya kişiselleştirilmiş reklam
- Kredi değerliliği veya finansal uygunluk profili
- Eklenti işlevi dışındaki kullanıcı takibi
- Verilerin üçüncü taraflara satışı

## İzin doğrulaması

- `storage`: ayarlar, son beş arama ve kullanıcı kontrollü karar kuyruğu.
- `activeTab`, `tabs`, `scripting`: kullanıcı tarafından başlatılan görünür sayfa okuma ve geçici araştırma sekmeleri.
- `alarms`: dayanıklı karar kuyruğu ve kontrollü arka plan işleri.
- Production paketi yalnız `https://m.zolm.com.tr/*`, gerekli Trendyol/medya ve Google alışveriş alanlarını içerir; geliştirme `localhost` izinleri mağaza ZIP'ine alınmaz.

Kamuya açık ayrıntılı politika: `https://m.zolm.com.tr/privacy/trendyol-booster-companion`
