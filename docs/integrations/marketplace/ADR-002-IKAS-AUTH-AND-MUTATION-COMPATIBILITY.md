# ADR-002 — ikas Kimlik Doğrulama ve Mutation Uyumluluğu

- **Tarih:** 2026-07-22
- **Durum:** Kabul Edildi

## Bağlam

ikas özel uygulama API'si Client ID/Secret ile `client_credentials` token üretir. Token dört saat geçerlidir. Güncel resmî mutation örnekleri ile yayımlanmış resmî istemci paketinde fiyat/stok mutation adları arasında geçiş dönemi farkı vardır. Her istekte token almak gereksiz yük; yalnız tek şema adına güvenmek ise bazı mağaza/API sürümlerinde yazma akışını kırabilir.

## Değerlendirilen seçenekler

1. Her API isteğinde yeni token almak ve yalnız güncel mutation'ları kullanmak.
2. Token'ı süresiz saklamak ve yalnız eski istemci paketi mutation'larını kullanmak.
3. Token'ı süreli cache etmek, 401'de tek yenileme yapmak; güncel mutation'ı birincil, yalnız açık şema uyumsuzluğunda eski adı fallback yapmak.

## Karar

Üçüncü yaklaşım seçildi.

- Token cache anahtarı bağlantı/Client ID parmak izine bağlıdır ve token ömründen önce dolar.
- 401 cevabında cache temizlenir ve istek yalnız bir kez yeni token'la tekrarlanır.
- Fiyat/stok için resmî güncel mutation birincildir.
- Fallback yalnız GraphQL hata metni mutation veya input tipinin bilinmediğini gösteriyorsa çalışır; ağ, yetki veya iş kuralı hatasında ikinci yazma denenmez.
- Tüm yazma flag'leri canlı kabul testine kadar kapalıdır.

## Sonuçlar

### Olumlu

- OAuth yükü ve gecikmesi azalır.
- Şema geçişindeki mağaza/API farklılıkları kontrollü biçimde tolere edilir.
- Yetki/iş kuralı hatasında çift mutation riski oluşmaz.

### Olumsuz

- İki mutation payload'ı bakım altında tutulur.
- Cache altyapısı token gizliliği ve erişim kontrolünün bir parçası olur.
- Fallback davranışı canlı mağazada ayrıca kanıtlanmalıdır.

## Yeniden değerlendirme koşulları

- ikas eski mutation adlarını resmen kaldırdığını duyurursa fallback silinir.
- OAuth token süresi veya refresh modeli değişirse cache stratejisi güncellenir.
- ZOLM merkezi credential/token vault kullanmaya başlarsa connector içi cache bu servise taşınır.
