# Pazaryeri UI Reset Planı

Tarih: 26 Mart 2026

## Durum

Mevcut V2 ekranlar teknik olarak güçlü ama ürünleşme açısından zayıf.

En büyük problem veri eksikliği değil, bilgi mimarisinin dağınık olması:

- aynı sayfada çok fazla blok yarışıyor
- her blok aynı önemde görünüyor
- sağ panel aşırı dolu
- aynı tür uyarılar her sayfada tekrar ediyor
- kullanıcı dili yerine iç ekip dili öne çıkıyor
- boş ekranda bile fazla kontrol gösteriliyor

Bu yüzden ekranlar "premium ve güçlü" değil, "yoğun ve yorucu" hissi veriyor.

## Eski Ekranlardan Alınacak Doğru Dersler

Eski iyi örnekler:

- `localhost_marketplace-accounting.png`
- `localhost_cargo-reports.png`

Bu ekranlarda doğru çalışan şeyler:

1. Sayfanın tek bir ana sorusu var.
2. Üstte kısa özet, altta ana çalışma alanı var.
3. Sağ panel destekleyici; ana içeriği boğmuyor.
4. Kart sayısı az ama anlamı net.
5. Renk sadece vurgu için kullanılıyor.

## V2 Ekranların Ana Hataları

### 1. Aynı ekranda çok fazla “yönetim katmanı” var

Örnek:

- hero
- KPI
- guidance
- filtreler
- toplu aksiyonlar
- legacy köprü
- sync butonları
- sağ panel özetleri
- tablo

Hepsi aynı anda görünce kullanıcı nereden başlayacağını bilmiyor.

### 2. “Guidance” fazlasıyla görünür

Diagnostik rehber, overview dışında her sayfada büyük yer kaplıyor.

Doğru yaklaşım:

- Overview: tam rehber merkezi
- Diğer sayfalar: tek satırlık kompakt öncelik bandı

### 3. Sağ panel fazla kalabalık

Özellikle:

- `marketplace-overview`
- `marketplace-orders`
- `marketplace-finance`
- `mp-products-manager`

sağ panelde çok sayıda küçük kart var. Bu kartlar okunmuyor, sadece gürültü üretiyor.

### 4. İç ekip dili fazla teknik

UI üzerinde şu tip ifadeler azaltılmalı:

- `V2`
- `guidance`
- `snapshot`
- `legacy projection`
- `delta state`
- `mapping`

Bunlar kullanıcıya sade Türkçe ile anlatılmalı.

## Yeni Tasarım İlkeleri

Bu altı sayfa artık tek aile gibi davranmalı:

- `Özet`
- `Entegrasyonlar`
- `Siparişler`
- `Ürünler`
- `Eşleştirme`
- `Finans`

### Zorunlu sayfa omurgası

Her sayfa:

1. Kompakt başlık kartı
2. En fazla 4 KPI kartı
3. Tek ana çalışma kartı
4. Sağda en fazla 2 yardımcı kart
5. Gelişmiş / legacy / teşhis alanları gizli veya ikinci seviye

### Görsel kurallar

- Tek sayfada maksimum 2 ana aksiyon
- Export butonları toplu menüye alınmalı
- Boş ekranda büyük yönetim yüzeyi gösterilmemeli
- İlk bakışta sadece ana iş akışı görünmeli
- Aynı tonda 8-10 kart üst üste kullanılmamalı

## Sayfa Bazlı Yeni Hedef

### 1. Özet

Ana soru:

`Bugün hangi mağaza veya sorun öncelikli?`

Yeni yapı:

- üstte 4 KPI
- altında `Bugün önce buna bak` listesi
- altında `Pilot canlıya geçiş`
- en altta sağlık geçmişi ve export

Kaldırılacak / küçültülecek:

- birden fazla büyük teşhis kartı
- uzun sağ panel yığınları
- tekrarlı mini özet blokları

### 2. Entegrasyonlar

Ana soru:

`Hangi mağaza hazır, hangisi eksik, sonraki adım ne?`

Yeni yapı:

- mağaza kartları
- seçili mağaza detay paneli
- tek blok içinde:
  - credential durumu
  - safe profile
  - smoke test
  - legacy backlog

Kaldırılacak / küçültülecek:

- çok fazla ayrı kontrol kutusu
- ayrı ayrı aynı şeyi söyleyen readiness kartları

### 3. Siparişler

Ana soru:

`Sipariş nerede, ne kadar, sorun var mı?`

Yeni yapı:

- üstte 4 KPI:
  - sipariş
  - ciro
  - net kâr
  - finans bekleyen
- ana kart:
  - arama
  - filtreler
  - tablo
- sağ panel:
  - operasyon özeti
  - gerekiyorsa yalnızca Excel aktarım kartı

Kaldırılacak / küçültülecek:

- büyük guidance bloğu
- legacy blokların sürekli açık olması
- aynı anda hem sipariş hem paket hem projection kontrol paneli

Varsayılan kolonlar azaltılmalı:

- sipariş no
- müşteri
- mağaza
- durum
- ciro
- kâr
- finans

### 4. Ürünler

Ana soru:

`Bu ürün hangi kanalda yayında ve temel durumu ne?`

Yeni yapı:

- üstte 4 KPI
- ana kartta filtre ve liste
- satır açılınca listing detayları
- sağ panel:
  - push durumu
  - bugünkü aksiyon

Kaldırılacak / küçültülecek:

- sağ panelde çok fazla küçük sayı
- büyük diagnostik kart listesi

### 5. Eşleştirme

Ana soru:

`Bugün hangi eşleştirme sorunu çözülmeli?`

Yeni yapı:

- tek ana metrik bandı
- solda issue listesi
- sağda:
  - bugünün önceliği
  - nasıl ilerlerim

Kaldırılacak / küçültülecek:

- üstte çok sayıda istatistik kartı
- sağ panelde tekrarlı bloklar

### 6. Finans

Ana soru:

`Para nerede, kesinti nerede, hangi siparişte fark var?`

Yeni yapı:

- üstte 4 KPI:
  - net alacak
  - toplam kesinti
  - kesin kâr
  - materyal fark
- ana kart:
  - filtre
  - mutabakat tablosu
- sağ panel:
  - tahsilat sağlığı
  - kesintiyi etkileyen kalemler

Kaldırılacak / küçültülecek:

- çok sayıda küçük metrik kutusu
- legacy ve guidance alanlarının aynı seviyede görünmesi

## İçerik Dili Reset'i

UI etiketleri sadeleştirilecek:

- `Pazaryeri Siparişleri V2` -> `Siparişler`
- `Pazaryeri Ürünlerim V2` -> `Ürünler`
- `Finans V2` -> `Finans`
- `Legacy fallback` -> `Eski veri aktarımı`
- `Legacy projection` -> `Eski kayıtları taşı`
- `Guidance` -> `Bugün öncelik`
- `Snapshot` -> `Kâr görünümü`

## Teknik Uygulama Planı

### Faz 1

Ortak sayfa iskeleti

- ortak başlık yapısı
- ortak KPI satırı
- ortak sağ panel standardı
- ortak kompakt öncelik bandı

### Faz 2

Özet + Siparişler redesign

Sebep:

- en çok görünen sayfalar
- bütün modül dilini bunlar belirliyor

### Faz 3

Finans + Ürünler redesign

### Faz 4

Eşleştirme + Entegrasyonlar redesign

## Çalışma Kararı

Bu noktadan sonra pazaryeri modülünde yeni feature eklemek yerine önce UI reset yapılmalıdır.

Önerilen sıra:

1. Overview redesign
2. Orders redesign
3. Finance redesign
4. Products redesign
5. Matching redesign
6. Integrations redesign

## Başarı Ölçütü

Bir ekran ilk açıldığında kullanıcı 5 saniyede şunu anlayabiliyorsa tasarım doğrudur:

- bu sayfa ne için var
- şu an durum iyi mi kötü mü
- bugün ne yapmam gerekiyor
- ana liste veya ana iş akışı nerede
