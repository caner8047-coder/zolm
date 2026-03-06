---
description: Standart tablo şablonu - Kolon özelleştirme, sıralama, resize ve mobil kart görünümü ile profesyonel tablo yapısı
---

# Standart Tablo Şablonu

Bu workflow, tüm yeni tablolarda uygulanması gereken standart tablo yapısını tanımlar.

## Özellikler

### 1. Kolon Özelleştirme (Visibility Toggle)
- Tablonun üstünde **⚙️ Kolonlar** dropdown butonu
- Checkbox listesi ile hangi kolonların görüneceğini seçme
- Tercihler **veritabanına kaydedilir** (MpSettingsService veya benzeri)
- Sayfa yenilendiğinde seçimler korunur
- "X / Y kolon gösteriliyor" bilgi metni

### 2. Kolon Sıralama (A→Z / Z→A)
- Sıralanabilir kolon başlıklarına tıklayınca sıralama değişir
- İlk tıklama: artan (▲), ikinci tıklama: azalan (▼)
- Sıralanabilir kolonlarda `⇅` ikonu, aktif sıralama kolonunda `▲` veya `▼`
- Sıralama **backend** (Livewire) seviyesinde yapılır (`ORDER BY`)
- Sıralama değiştiğinde `resetPage()` çağrılır (pagination sıfırlanır)
- Sıralanabilir kolonlar: DB sütununa karşılık gelen tüm kolonlar
- Hesaplanmış (accessor) kolonlar sıralanamaz (veya DB'de saklanmış sütun kullanılır)

### 3. Kolon Genişliği Ayarlama (Excel Tarzı Resize)
- Her kolon başlığının sağ kenarında ince sürükleme çubuğu (4px)
- Mouse ile sürükleyerek genişlik ayarlanır
- Alpine.js `columnResize()` component'i kullanılır
- `table-layout: fixed` zorunlu
- Tüm hücrelerde `overflow: hidden; text-overflow: ellipsis; white-space: nowrap`
- Minimum genişlik: 40px
- Drag handle hover'da indigo renk gösterir

### 4. Yazı Boyutları (Kompakt)
- `text-xs` → **11px** (Tailwind varsayılanı 12px yerine)
- `text-sm` → **13px** (Tailwind varsayılanı 14px yerine)
- `text-[10px]` → **9px**
- Bu override'lar sadece tablo scope'unda (`#tableId`) uygulanır

### 5. Mobil Responsive (Kart Görünümü)
- **Desktop** (≥768px / md): Normal tablo görünümü (`hidden md:block`)
- **Mobil** (<768px): Her satır bir **kart** olur (`md:hidden`)
- Kart yapısı:
  - **Üst satır**: Checkbox + ID/numara + Durum badge + Aksiyon butonu
  - **Orta**: Ana bilgi (ürün adı, açıklama vb.) — truncated
  - **Alt**: Finansal/sayısal veriler **2 sütunlu grid** halinde (label: değer)
  - Grid her eleman: `flex justify-between` ile label solda, değer sağda
- Kartlar `$visibleColumns` tercihlerine uyar
- Kartlar `rounded-xl border border-gray-200 p-4` stilinde

## Implementasyon Şablonu

### PHP (Livewire Component)
```php
// Properties
public array $visibleColumns = ['col1', 'col2', ...]; // varsayılan görünür kolonlar
public string $sortBy = 'created_at';
public string $sortDir = 'desc';

public static array $sortableColumns = [
    'col1' => 'db_column1',
    'col2' => 'db_column2',
];

public static array $allColumnDefs = [
    'col1' => 'Kolon 1 Başlık',
    'col2' => 'Kolon 2 Başlık',
];

// Methods
public function toggleColumn(string $column) { ... }
public function sortTable(string $column) { ... }
```

### Blade (CSS)
```html
<style>
    .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
    .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
    .sortable-th { cursor: pointer; user-select: none; position: relative; }
    .sortable-th:hover { background: #f3f4f6; }
    #tableId .text-xs { font-size: 11px !important; }
    #tableId .text-sm { font-size: 13px !important; }
    #tableId { table-layout: fixed; width: 100%; }
    #tableId th, #tableId td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
```

### Blade (Alpine.js Resize)
```html
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('columnResize', () => ({
        resizing: false, startX: 0, startWidth: 0, currentTh: null, handle: null,
        startResize(e, th) {
            this.resizing = true; this.startX = e.pageX; this.currentTh = th;
            this.startWidth = th.offsetWidth; this.handle = e.target;
            this.handle.classList.add('active');
            const onMouseMove = (ev) => {
                if (!this.resizing) return;
                const newWidth = Math.max(40, this.startWidth + (ev.pageX - this.startX));
                this.currentTh.style.width = newWidth + 'px';
                this.currentTh.style.minWidth = newWidth + 'px';
            };
            const onMouseUp = () => {
                this.resizing = false;
                if (this.handle) this.handle.classList.remove('active');
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }
    }));
});
</script>
```

## Referans Dosya
Tam çalışan örnek: `resources/views/livewire/marketplace-accounting.blade.php` → Siparişler tabı
