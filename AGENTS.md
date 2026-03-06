# ZOLM — Proje Kuralları ve Convention'lar

Bu dosya tüm AI asistanları (Codex, Gemini, Copilot) için proje kurallarını tanımlar.

## Teknoloji Stack

- **Framework:** Laravel 11 + Livewire 3 (TALL Stack)
- **Frontend:** Alpine.js + Tailwind CSS (CDN)
- **Database:** MySQL 8 (Docker/Sail)
- **Excel:** PhpSpreadsheet (okuma/yazma)
- **AI:** Gemini API (profil analizi)
- **Dil:** PHP 8.2+, Türkçe UI

## Proje Yapısı

```
app/
├── Livewire/           # Tüm sayfa component'leri (Livewire 3 full-page)
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
- Border radius: `rounded-xl` (kartlar), `rounded-lg` (butonlar/input)
- Shadow: `shadow-sm` (kartlar), `shadow-md` (hover)
- Durum renkleri: Yeşil=başarı, Kırmızı=hata, Amber=uyarı, Mavi=bilgi
- Badge'ler: `px-2 py-0.5 text-xs font-mono rounded`
- KPI kartları: `rounded-xl border p-4 text-center`

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
