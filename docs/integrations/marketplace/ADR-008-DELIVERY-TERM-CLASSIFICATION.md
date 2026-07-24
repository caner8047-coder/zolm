# ADR-008 — Termin sınıflandırması ve çok mağazalı ürün davranışı

- Tarih: 23 Temmuz 2026
- Durum: Kabul Edildi

## Bağlam

Ürün tablosundaki ham gün değeri operasyonel anlamı yeterince hızlı aktarmıyordu. Aynı ürün birden fazla mağazada farklı termin sürelerine sahip olabildiği için tek bir renk ve durum gösterilirken yanıltıcı derecede iyimser bir sonuç üretme riski de vardı. Ayrıca işletmelerin operasyon kapasitesi farklı olduğundan eşiklerin sabit kalmaması gerekiyordu.

## Değerlendirilen seçenekler

1. En kısa termin süresini kullanmak: En iyi mağaza deneyimini gösterir ancak diğer mağazalardaki gecikme riskini gizler.
2. Ortalama termin süresini kullanmak: Genel eğilimi gösterir ancak yüksek terminli tek bir mağazayı maskeleyebilir.
3. En uzun termin süresini kullanmak: Operasyonel risk açısından ihtiyatlıdır ve kullanıcıyı gecikmeye karşı erken uyarır.

## Seçilen yaklaşım

Çok mağazalı ürünün renkli termin sınıfı en uzun gün değerine göre belirlenir. Mağaza bazındaki tüm değerler hover detayında korunur. Varsayılan eşikler `0–1`, `2–3`, `4–7` ve `8+` gündür; kullanıcı bunları Pazaryeri Ayarları içinden kendi operasyonuna göre değiştirebilir.

## Sonuçlar

- Olumlu: Tablo taranabilirliği artar, gecikme riski saklanmaz ve firma bazlı operasyon farklılıkları desteklenir.
- Olumsuz: Tek bir yavaş mağaza ürünün genel etiketini daha olumsuz gösterebilir.
- Geriye uyumluluk: Veri modeli değişmez; mevcut gün ve metin alanları korunur. Sınıflandırma yalnızca sunum ve ayar katmanında uygulanır.

## Yeniden değerlendirme koşulları

Mağaza bazlı ayrı satırlar eklenirse veya kullanıcılar ortalama/en kısa termin görünümü talep ederse sınıflandırma stratejisi seçilebilir bir ayara dönüştürülebilir.
