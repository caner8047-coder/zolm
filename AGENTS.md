# ZOLM — Proje Kuralları ve Convention'lar

Bu dosya tüm AI asistanları (Codex, Gemini, Copilot) için proje kurallarını tanımlar.

## Teknoloji Stack

- **Framework:** Laravel 13 + Livewire 4 (TALL Stack)
- **Frontend:** Alpine.js + Tailwind CSS (CDN)
- **Database:** MySQL 8 (Docker/Sail)
- **Excel:** PhpSpreadsheet (okuma/yazma)
- **AI:** Gemini API (profil analizi)
- **Dil:** PHP 8.3+, Türkçe UI

## Proje Yapısı

```
app/
├── Livewire/           # Tüm sayfa component'leri (Livewire 4 full-page)
│   ├── ProductionMotor.php      # Üretim Motoru sayfası
│   ├── OperationMotor.php       # Operasyon Motoru sayfası
│   ├── ProfileWizard.php        # AI Profil oluşturma sihirbazı
│   ├── ProfileManager.php       # Profil yönetimi
│   ├── MarketplaceAccounting.php # Pazaryeri Muhasebe (en büyük modül)
│   └── ...
├── Models/             # Eloquent modelleri
├── Services/           # İş mantığı servisleri
│   ├── DynamicTransformEngine.php  # AI profilleri ile dönüşüm motoru
│   ├── ProductionEngine.php        # Sabit üretim motoru kuralları
│   ├── OperationEngine.php         # Sabit operasyon motoru kuralları
│   ├── AuditEngine.php             # Denetim motoru (24 kural)
│   ├── AIProfileAnalyzer.php       # Gemini API ile profil analizi
│   ├── ExcelService.php            # Excel okuma/yazma servisi
│   └── MarketplaceImportService.php # Trendyol Excel import servisi
└── ...
```

## Kritik Kurallar

### 1. Excel Export (ZORUNLU)
Tüm Excel çıktılarında şu kurallar uygulanır:
- `setCellValueExplicit()` kullan, `setCellValue()` KULLANMA
- UTF-8 encoding kontrolü: `mb_check_encoding()` + `mb_convert_encoding()`
- XML kontrol karakterlerini temizle: `preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value)`
- Sheet ismi max 31 karakter, yasak karakterler: `: \ / ? * [ ]`
- Referans: `App\Services\ExcelService::cleanString()`

### 2. Mobil Responsive (ZORUNLU)
Her yeni Blade view dosyasında:
- Layout: `flex flex-col sm:flex-row` ile başla
- Grid: `grid-cols-1 sm:grid-cols-2 xl:grid-cols-3`
- Butonlar: `w-full sm:w-auto px-4 py-3 sm:py-2` (min 44px touch target)
- Input font-size: `text-base sm:text-sm` (iOS zoom önleme, min 16px)
- Spacing: `p-4 lg:p-6`, `gap-3 lg:gap-4`

### 3. Tablo Standardı
Yeni tablo oluştururken:
- Kolon özelleştirme: `$visibleColumns` + `toggleColumn()` + checkbox dropdown
- Sıralama: `$sortableColumns` static array + `sortTable()` metodu
- Kolon resize: Alpine.js `columnResize()` component
- Mobil: `md:hidden` kart görünümü, `hidden md:block` tablo görünümü
- CSS: `table-layout: fixed`, `overflow: hidden; text-overflow: ellipsis`
- Referans: Sipariş Listesi tablosu (`marketplace-accounting.blade.php`)

### 4. Livewire Convention'lar
- Full-page component: `->layout('layouts.app')`
- Property isimlendirme: camelCase (`$selectedPeriodId`)
- Metod isimlendirme: camelCase (`runAudit()`, `exportAllOrders()`)
- Wire model: `wire:model.live` (instant), `wire:model.defer` (form submit)
- Loading state: `wire:loading`, `wire:loading.remove`, `wire:target`

### 5. Denetim Motoru Pattern
Yeni denetim kuralı eklerken:
1. `AuditEngine::RULES` array'ine metod adını ekle
2. `AuditEngine::RULE_META` array'ine metadata (code, title, tooltip, severity, category, icon) ekle
3. `protected function checkXxx(MpPeriod $period): int` metodu yaz
4. `MpAuditLog::create()` ile bulgu kaydet
5. Severity: `critical` (para kaybı), `warning` (operasyonel risk), `info` (bilgilendirme)

### 6. Motor Profil Sistemi
- Profil tipleri: `production`, `operation` (gelecekte `custom` eklenecek)
- `is_ai_generated=true` → `DynamicTransformEngine` kullanılır
- `is_ai_generated=false` → Sabit motor (`ProductionEngine` / `OperationEngine`)
- AI kuralları JSON: `{ version, input, transformations, outputs }`
- Dönüşüm operasyonları: `filter`, `map_column`, `sort`, `normalize_product`, `add_column`, `remove_column`, `rename_column`

### 7. Stil Kuralları
- Renk paleti: Tailwind varsayılanlar (indigo, emerald, amber, red)
- Border radius: `rounded-[10px]` (ana kartlar), `rounded-[8px]` (iç kartlar), `rounded-[6px]` (butonlar/input)
- Shadow: `shadow-sm` (kartlar), `shadow-md` (hover)
- Durum renkleri: Yeşil=başarı, Kırmızı=hata, Amber=uyarı, Mavi=bilgi
- Badge'ler: `px-2 py-0.5 text-xs font-mono rounded`
- KPI kartları: `rounded-[8px] border border-slate-200 bg-slate-50/70 p-4`

### 7.1. ZOLM Kurumsal Açık Panel Sistemi (ZORUNLU)
Yeni modül tasarımlarında varsayılan görsel dil budur.

- Referans sayfa: `resources/views/livewire/production-revenue.blade.php`
- Venture uyumlu veri yoğun referans: `resources/views/livewire/marketplace-orders.blade.php`
- Venture kaynak fig referansı: `20.3 [UI8] - Venture CRM.fig`
- Detay doküman: `docs/zolm-kurumsal-acik-panel-sistemi.md`
- İstek şablonları: `docs/zolm-ui-prompt-sablonu.md`, `docs/zolm-yeni-modul-istek-sablonu.md`
- Ortak component'ler: `resources/views/components/zolm/`

Tasarım görevi başlamadan önce zorunlu ön kontrol:
- Repo kökündeki `20.3 [UI8] - Venture CRM.fig` dosyasına bak
- Gerekirse `thumbnail.png` ve `canvas.fig` içeriğini referans olarak kullan
- Venture dilini birebir kopyalama; ZOLM açık panel sistemine adapte et

Bu tasarım sisteminin ana karakteri:
- Açık zeminli, kurumsal, sade, premium ama gösterişsiz
- Sayfa zemini açık, ana kartlar beyaz, iç kartlar açık gri
- Venture uyumlu daha ürünleşmiş command bar, ledger ve tool surface hiyerarşisi
- Border: `border-slate-200`
- Ana metin: `text-slate-900`
- Yardımcı metin: `text-slate-500`
- Primary button: `bg-slate-900 text-white`
- Ana section kartı: `rounded-[10px] border border-slate-200 bg-white shadow-sm`
- İç metrik kartı: `rounded-[8px] border border-slate-200 bg-slate-50/70`
- Input / select / mini kutu: `rounded-[6px] border border-slate-200 bg-white`

Kaçınılacaklar:
- Koyu hero alanları
- Gradient ağırlıklı dashboard dili
- Glassmorphism
- Neon renkler
- Her modülde farklı tema
- Bir modülde açık, diğerinde koyu tasarım dili
- Gereksiz büyük radius kullanan aşırı oval kartlar

Zorunlu yerleşim mantığı:
- Üstte beyaz workspace / özet kartı
- Varsa guidance veya import alanı ayrı ama kompakt kart olarak
- Altında command bar ve tablo aynı ana section içinde
- Sağ yardımcı panel desktop'ta ayrı olabilir, mobilde ana kontrol yüzeyi ile birleşmeli
- Aynı spacing sistemi: `space-y-4 lg:space-y-6`, `p-4 lg:p-6`, `gap-3 lg:gap-4`
- Aynı tipografi hiyerarşisi: `text-xl lg:text-2xl` başlık, `text-sm text-slate-500` açıklama
- Aynı buton ve badge dili

Bu proje içinde son onaylanan mikro tasarım tercihleri:
- Filtre/arama alanı ile tablo aynı ana section kartı içinde olmalı; ayrı kart gibi kopuk durmamalı
- Tablo araçları (`Kolonlar`, kolon sayısı vb.) filtre bloğunun sağ üstünde konumlanmalı
- Tablo kapsayıcıları için varsayılan radius: `rounded-lg`
- İç panel arka planları sert gri olmamalı; `bg-slate-50/60` veya beyaza yakın açık tonlar tercih edilmeli
- Section başlıkları ile içerik arasında gereksiz büyük boşluk bırakılmamalı; sıkı ama nefes alan spacing kullanılmalı
- Sağ yardımcı paneldeki özet/risk kartları desktop'ta mümkünse tek satırda hizalanmalı; gerekirse kartlar küçültülmeli
- Kompakt bilgi kartlarında taşma olmamalı; kısa açıklama, sıkı padding ve `min-w-0` yaklaşımı kullanılmalı
- Aktif filtre bilgisi kullanıcıya aynı blok içinde görünmeli; arama çalışıyor mu hissi görsel olarak net olmalı
- Guidance / kritik uyarı alanı command bar içine sıkıştırılmamalı; gerekiyorsa üstte kompakt accordion kart olmalı
- Mobilde sağ tool rail ayrı siyah panel gibi kalmamalı; command bar ile birleşmeli
- Mini stat kutuları (`Hazır`, `Finans`, `Risk` gibi) kompakt kalmalı ve mümkünse tek satır mantığında hizalanmalı
- Venture etkisi layout, kontrol yüzeyi ve hiyerarşide hissedilmeli; koyu tema kopyası yapılmamalı
- Yeni bir tasarım veya büyük UI revizyonu başlamadan önce mutlaka `20.3 [UI8] - Venture CRM.fig` kontrol edilmeli

Kullanıcı şu ifadeyi kullanırsa:
- `Bu modülü ZOLM Kurumsal Açık Panel Sistemi ile yap`

Şu anlam çıkar:
1. Üretim Ciro temelini koru ama Venture uyumlu ZOLM yorumunu varsayılan kabul et
2. Tasarıma başlamadan önce `20.3 [UI8] - Venture CRM.fig` dosyasını kontrol et
3. Mobil Responsive kurallarını eksiksiz uygula
4. Yeni tablo varsa Standart Tablo Şablonunu uygula
5. Command bar, tool rail ve ledger ilişkisini tek ürün yüzeyi gibi kur
6. Yeni Excel export varsa Excel Export kurallarını uygula
7. Türkçe UI metinlerinde doğru karakter kullan

Tasarım özgürlüğü istenmiyorsa yeni tema üretme. Mevcut ZOLM ailesinin devamı olacak şekilde uygula.

### 8. Import Servisi Pattern
Yeni veri tipi import'u eklerken:
- `MarketplaceImportService` içine kolon alias map'i ekle
- Duplicate kontrolü: `existingMap` hash key sistemi (order_number + type + document_number)
- `updateOrCreate` yerine `firstOrCreate` + manual update (performans)
- Hata toleransı: satır bazlı try/catch, devam et

## Canlı Sistemde Dikkat

- Üretim ve Operasyon motorları **her gün kullanılıyor** — bu dosyalara yapılan değişiklik geriye uyumlu olmalı
- Pazaryeri Muhasebe modülü aktif kullanımda — migration'lar backward compatible olmalı
- Feature flag ile yeni özellikler kademeli açılmalı

## Proje Dili

- Kod: İngilizce (değişken/fonksiyon isimleri)
- UI: Türkçe
- Yorumlar: Türkçe tercih edilir
- Commit mesajları: Türkçe veya İngilizce

## ZOLM Geliştirme Çalışma Sistemi

Kodun yazılmış olması tek başına bir geliştirmeyi tamamlanmış saymak için yeterli değildir. Her özellik; planlama, uygulama, doğrulama, sürümleme, dokümantasyon ve ekip iletişimiyle birlikte ele alınır.

### Zorunlu Akış

Her özellik geliştirmesinde şu sırayı izle:

1. Mevcut yapıyı ve benzer uygulamaları incele
2. Kısa ve somut bir uygulama planı hazırla
3. Küçük ve kontrollü kod değişiklikleri yap
4. İlgili testleri ve doğrulamaları gerçekleştir
5. Değişiklikleri küçük ve anlamlı commit gruplarına ayır
6. Notion dokümantasyonunu oluştur veya taslakla
7. Önemli kararları decision log'a kaydet
8. Slack için kısa bir ekip özeti hazırla veya paylaş
9. Sonuçları, riskleri ve açık kalan konuları raporla

Bir aşama başarısızsa işi tamamlanmış gibi sunma. Harici sistemlerde yapılmamış bir işlemi yapılmış gibi belirtme.

### 1. Ön İnceleme ve Plan

Kod yazmadan önce:

- `AGENTS.md` ile ilgili modülün modellerini, servislerini, Livewire component'lerini, Blade dosyalarını, migration'larını ve testlerini incele
- Benzer özelliklerde kullanılan mevcut pattern'leri tercih et
- Çalışma ağacındaki kullanıcı değişikliklerini koru ve ilgisiz dosyalara dokunma
- Geriye uyumluluk, veri kaybı, performans ve canlı kullanım risklerini değerlendir
- UI görevi varsa ZOLM Kurumsal Açık Panel Sistemi ön kontrollerini tamamla
- Varsayımları ve kullanıcı sonucuna etkilerini açıkça belirt

Plan en az şu başlıkları kapsasın:

- Amaç ve beklenen kullanıcı sonucu
- Değişecek katmanlar veya dosyalar
- Veri modeli ve migration etkisi
- Geriye uyumluluk değerlendirmesi
- Test yaklaşımı
- Riskler
- Dokümantasyon ve duyuru kapsamı

Büyük işleri küçük, bağımsız ve doğrulanabilir parçalara böl. Gereksiz kapsam genişletme.

### 2. Kodlama ve Doğrulama

- Mevcut mimariyi ve bu dosyadaki proje convention'larını koru
- İş mantığını uygun servis katmanında tut
- Migration'ları backward compatible tasarla
- Riskli özellikleri gerektiğinde feature flag arkasına al
- Sessiz hata yutma, açıklamasız davranış değişikliği veya geçici çözüm ekleme
- Yeni davranış için uygun testleri ekle; başarılı, hatalı ve sınır durumlarını doğrula
- PHP syntax ve ilgili kalite kontrollerini çalıştır
- UI değişikliklerini desktop ve mobil boyutlarda kontrol et
- Excel işlemlerinde gerçek çıktı oluşturarak veri tiplerini ve dosyanın açılabilirliğini doğrula
- Test edilemeyen noktaları ve nedenlerini sonuç raporunda açıkça yaz

### 3. Commit Sistemi

Değişiklikleri küçük ve anlamlı commit'lere ayır:

- Her commit tek bir mantıksal işi kapsasın
- Commit doğrulanabilir ve ilgisiz değişikliklerden arındırılmış olsun
- Kullanıcıya ait ilgisiz değişiklikleri stage etme veya commit'e alma
- Kullanıcı açıkça commit istemediyse commit oluşturma; önerilen commit sırasını ve mesajlarını sun

Tercih edilen mesaj biçimleri:

- `feat: add marketplace reconciliation filters`
- `fix: preserve leading zeros in Excel exports`
- `refactor: extract audit calculation service`
- `test: cover duplicate marketplace imports`
- `docs: document production profile workflow`

### 4. Notion Dokümantasyonu

Her tamamlanan özellik için aşağıdaki yapıda Notion dokümantasyonu oluştur veya doğrudan yapıştırılabilir bir taslak hazırla:

- Başlık ve özet
- İş ihtiyacı ve kullanıcıya etkisi
- Teknik yaklaşım
- Değiştirilen bileşenler
- Veri modeli veya migration değişiklikleri
- Kullanım adımları
- Yetki ve feature flag bilgileri
- Test kapsamı
- Bilinen sınırlamalar
- Geri alma planı
- İlgili commit veya PR bağlantıları
- Yayın tarihi ve sorumlu kişi

Notion bağlantısı ve yazma yetkisi varsa kullanıcı talebinin kapsamına göre sayfayı oluştur veya güncelle. Yetki yoksa çıktıyı açıkça `Notion taslağı` olarak işaretle.

### 5. Decision Log

Şu durumlardan biri oluştuysa decision log kaydı hazırla:

- Mimari yaklaşım seçimi
- Birden fazla makul seçenek arasından tercih
- Veri modeli değişikliği
- Geriye uyumluluk için özel çözüm
- Yeni bağımlılık
- Performans, güvenlik veya kullanılabilirlik arasında önemli tercih
- Feature flag kullanma veya kullanmama kararı

Kayıt formatı:

- Karar başlığı ve tarih
- Durum: Önerildi / Kabul Edildi / Değiştirildi
- Bağlam
- Değerlendirilen seçenekler
- Seçilen yaklaşım ve gerekçe
- Olumlu ve olumsuz sonuçlar
- Geri dönüş veya yeniden değerlendirme koşulları

Önemli bir karar oluşmadıysa gereksiz kayıt üretme; `Yeni mimari karar oluşmadı` diye belirt.

### 6. Slack Duyurusu

Her tamamlanan geliştirme için kısa ve ekip genelinin anlayabileceği bir Slack özeti hazırla:

```text
🚀 [Özellik adı] tamamlandı

- Ne değişti:
- Kullanıcıya etkisi:
- Test durumu:
- Yayın / feature flag durumu:
- Dikkat edilmesi gerekenler:
- Dokümantasyon:
- PR / commit:
```

Slack mesajını yalnızca kullanıcı açıkça göndermeni istediğinde ve yazma yetkisi bulunduğunda gönder. Diğer durumlarda çıktıyı `Slack taslağı` olarak işaretle.

### 7. Tamamlama Raporu

Her geliştirme sonunda şu başlıklarla rapor ver:

- **Tamamlananlar:** Kullanıcı sonucu üzerinden kısa özet
- **Değişen dosyalar:** Önemli dosyalar ve amaçları
- **Doğrulama:** Çalıştırılan testler ve sonuçları
- **Commit planı:** Önerilen commit grupları veya oluşturulan commit hash'leri
- **Notion:** Sayfa bağlantısı veya hazır taslak
- **Decision log:** Kayıt veya yeni karar oluşmadığı bilgisi
- **Slack:** Gönderilen mesaj bilgisi veya gönderime hazır taslak
- **Açık konular:** Riskler, manuel kontroller ve sonraki adımlar

### Tamamlanma Kriteri

Bir özellik ancak aşağıdaki koşullar karşılandığında tamamlanmıştır:

- İstenen davranış uygulanmış ve mevcut işlevler korunmuş
- Uygun testler geçmiş
- Kod proje standartlarına uygun
- Commit planı hazırlanmış veya yetki varsa commit'ler oluşturulmuş
- Notion dokümantasyonu hazırlanmış
- Gerekli kararlar decision log'a işlenmiş
- Slack duyurusu hazırlanmış
- Riskler ve açık konular raporlanmış
