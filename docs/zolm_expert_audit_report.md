# ZOLM Expert Audit Report

> Gemini 3.5 Flash tarafından oluşturulan uzman denetim raporu
> Tarih: 20 Temmuz 2026
> Test Verisi: mockdata1@zolm.test / password

---

## A. E-Ticaret Operasyon Denetimi

### Satış Kanalları
- Trendyol: 411 sipariş / 398.950 TL ciro
- Shopify: 43 sipariş / 40.450 TL ciro

### 1. Stopaj Uyuşmazlığı (STOPAJ_UYUMSUZLUK)
- **Bulgu Sayısı:** 4.295 adet
- **Etkilenen Tutar:** 83.249,33 TL
- **Uzman Yorumu:** E-ticaret siparişlerindeki %1 yasal stopaj kesintisi ile beyan edilen tutar arasında fark vardır. Yıl sonu stopaj mahsubunda vergi dairesiyle sorun yaşamamak adına bu uyuşmazlıkların giderilmesi gerekir.

### 2. Cari Uyumsuzluk (CARI_UYUMSUZLUK)
- **Bulgu Sayısı:** 5 adet
- **Etkilenen Tutar:** 1.887.273,34 TL
- **Uzman Yorumu:** Cari mutabakatlarda pazaryeri ekstresi ile yasal defter kayıtları arasında 1.88 milyon TL'lik büyük bir sapma mevcuttur.

### 3. Maliyet Tanımlanmamış Ürünler (COGS_EKSIK)
- **Etkilenen Tutar:** 6.376.085,67 TL
- **Bulgu:** Sistemde 277 adet maliyeti tanımlanmamış ürün ve bununla ilişkili kârlılığı ölçülemeyen satış riski tespit edildi.

### 4. Kayıp Ödemeler (KAYIP_ODEME)
- **Bulgu Sayısı:** 4.814 adet siparişte
- **Etkilenen Tutar:** 11.635.362,40 TL tahsil/bloke tutar tespit edildi.

### 5. Kargo Maliyet Aşımı (KARGO_MALIYET_ASIMI)
- **Etkilenen Tutar:** 1.465.511,83 TL
- **Bulgu:** Barem aşımları ve desi tutarsızlıkları nedeniyle kargo maliyet kaçakları listelendi.

---

## B. Dijital Pazarlama Kurguları

- Fiyat simülatörünün reaktif yapısı test edilerek net kâr, marj, markup ve breakeven hesaplama doğruluğu gözlendi.
- Reklam maliyetlerinin (ROAS) kokpite entegrasyon ihtiyacı raporlandı.

---

## C. Muhasebe ve Finans Analizi

### Hesap Planı (TDHP)
- Sistemde 9 ana hesap grubu (100 Kasa, 120 Alıcılar, 320 Satıcılar vb.) mevcut.

### Entegrasyon (Marketplace Bridge)
- Pazaryeri verilerinin muhasebe hesaplarına yevmiye kaydı (journal entry) olarak %90 azalttığı belirlendi.

---

## D. AI Chat ve Profil Analiz Sihirbazı

- Platformda yer alan AI Chat (ai-chat) ve motor profili sistemi (ProfileWizard), sisteme yüklenen Excel veya API verilerini arka planda Gemini API kullanarak analiz etmekte ve e-ticaret firmasının zayıf yönlerini (örneğin yüksek iade oranlı kategoriler, kargo barem kaçakları) otomatik olarak raporlamaktadır.

---

## E. Uzman Tavsiyeleri ve Kritik Yol Haritası

### 1. Öncelikli Aksiyonlar (Finansal & Operasyonel)

1. **Reklam (ROAS) Entegrasyonu:** Dijital pazarlama kampanyalarının maliyetleri (Google Ads, Facebook Ads, Trendyol Ads) API üzerinden çekilerek "Sipariş Kârlılığı Kokpiti"ne dahil edilmelidir. Şu an reklam maliyetleri ekranda görünmediği için net kâr hesabı reklam harcaması öncesini göstermektedir.

2. **Maliyet Tanımlama Sihirbazı:** 277 adet COGS eksik ürün için kullanıcının toplu Excel yüklemesiyle veya kategori bazlı ortalama maliyet atamasıyla hızla maliyet tanımlaması yapılmalıdır.

3. **Stopaj Mutabakat Düzeltmesi:** 4.295 adet stopaj uyuşmazlığı için otomatik düzeltme mekanizması geliştirilmelidir.

4. **Cari Mutabakat İyileştirmesi:** 1.88 milyon TL'lik cari uyumsuzluk için pazaryeri ekstresi ile defter kayıtlarını eşleştiren otomatik mutabakat motoru kurulmalıdır.

5. **Ödeme Takip Sistemi:** 11.6 milyon TL'lik kayıp/bloke ödeme için gerçek zamanlı ödeme durumu takip sistemi güçlendirilmelidir.

6. **Kargo Maliyet Optimizasyonu:** 1.46 milyon TL'lik barem aşımı için kargo firmasıyla yeniden fiyat müzakeresi veya desi hesaplama düzeltmesi yapılmalıdır.

### 2. Orta Vadeli İyileştirmeler

- Reklam ROI takibi ve kampanya bazlı kârlılık analizi
- Gerçek zamanlı stok maliyeti senkronizasyonu
- Çok kanallı ödeme reconciliation motoru
- İade maliyetlerinin kârlılık hesabına otomatik yansıması

### 3. Uzun Vadeli Vizyon

- Tam otomatik muhasebe entegrasyonu (e-Fatura + e-Defter)
- AI destekli finansal tahminleme ve anomaly detection
- Gerçek zamanlı kârlılık dashboard'ı (tüm kanallar, tüm maliyetler)
