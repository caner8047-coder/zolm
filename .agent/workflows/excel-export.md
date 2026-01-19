---
description: Excel dosyası oluşturma ve export kuralları - UTF-8 encoding ve XML uyumluluk
---

# Excel Export Kuralları

Bu proje PhpSpreadsheet kullanarak Excel dosyaları oluşturuyor. Aşağıdaki kurallar **mutlaka** uygulanmalıdır.

## 🔴 Kritik Kurallar

### 1. UTF-8 Encoding
Türkçe karakterler (İ, Ş, Ü, Ö, Ç, ğ, ı) sorun çıkarır. Tüm string değerler yazılmadan önce temizlenmeli:

```php
// Windows-1254 → UTF-8 dönüşümü
if (!mb_check_encoding($value, 'UTF-8')) {
    $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1254');
}
```

### 2. XML Kontrol Karakterleri
Excel dosyaları = ZIP içinde XML. Kontrol karakterleri XML'i bozar:

```php
// Kontrol karakterlerini kaldır (tab ve newline hariç)
$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

// XML-safe karakterler (Unicode uyumlu)
$value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value);
```

### 3. setCellValueExplicit Kullan
`setCellValue()` yerine tip belirten versiyon kullan:

```php
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// String için
$sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);

// Sayı için
$sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
```

### 4. Sheet İsmi Kuralları
- Max 31 karakter
- Yasak karakterler: `: \ / ? * [ ]`

```php
$name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name);
$name = mb_substr($name, 0, 31);
```

## 📁 Referans Dosya

Tüm bu kurallar `App\Services\ExcelService` içinde uygulanmıştır:
- `cleanString()` - encoding ve karakter temizleme
- `sanitizeSheetName()` - sheet ismi temizleme
- `exportToXlsx()` - güvenli Excel export

## ⚠️ Yapılmaması Gerekenler

1. ❌ `setCellValue()` ile doğrudan değer yazma
2. ❌ Encoding kontrolü yapmadan string yazma
3. ❌ 31 karakterden uzun sheet isimleri
4. ❌ Sheet isminde özel karakterler kullanma

## ✅ Test Kontrol Listesi

Yeni Excel export özelliği eklerken:

1. [ ] Türkçe karakterli veri ile test et
2. [ ] Özel karakterler içeren veri ile test et
3. [ ] Boş değerler ile test et
4. [ ] Çok uzun string değerler ile test et
5. [ ] Microsoft Excel'de aç ve kontrol et
