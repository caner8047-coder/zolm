---
description: Mobil responsive tasarım kuralları - tüm yeni view dosyalarında uygulanmalı
---

# Mobil Responsive Tasarım Kuralları

Bu kurallar tüm yeni Blade view dosyalarında **ZORUNLU** olarak uygulanmalıdır.

## 1. Breakpoint Sistemi

```
sm: 640px   - Küçük tablet
md: 768px   - Tablet
lg: 1024px  - Laptop
xl: 1280px  - Desktop
```

## 2. Layout Kuralları

### Grid Sistemi
```html
<!-- YANLIŞ -->
<div class="grid grid-cols-3 gap-6">

<!-- DOĞRU -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
```

### Flexbox Stack
```html
<!-- YANLIŞ -->
<div class="flex items-center justify-between">

<!-- DOĞRU - Mobilde stack, desktopda yan yana -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
```

## 3. Typography

```html
<!-- Başlıklar -->
<h1 class="text-xl lg:text-2xl font-bold">

<!-- Açıklamalar -->
<p class="text-sm lg:text-base text-gray-500">

<!-- Etiketler -->
<label class="text-xs sm:text-sm font-medium">
```

## 4. Touch-Friendly Butonlar

```html
<!-- Minimum 44px height mobilde -->
<button class="px-4 py-3 sm:py-2 text-sm">

<!-- Full width mobilde -->
<button class="w-full sm:w-auto px-4 py-3">
```

## 5. Form Elemanları

```html
<!-- Input/Select -->
<input class="w-full px-3 py-2 sm:px-4 border rounded-lg text-sm">

<!-- iOS zoom önleme - font-size: 16px -->
<input class="text-base sm:text-sm"> <!-- veya min 16px -->
```

## 6. Spacing

```html
<!-- Padding -->
<div class="p-4 lg:p-6">

<!-- Margin -->
<div class="mb-6 lg:mb-8">

<!-- Gap -->
<div class="gap-3 lg:gap-4">
```

## 7. Sidebar/Navigation

- Mobilde `hidden lg:block` veya Alpine.js toggle kullan
- Hamburger menü sol üstte
- Overlay ile backdrop ekle
- `@click="sidebarOpen = false"` link tıklamalarında

## 8. Tablolar

```html
<!-- Mobilde kart görünümü veya horizontal scroll -->
<div class="overflow-x-auto">
    <table class="min-w-full">
```

## 9. Modal'lar

```html
<!-- Full screen mobilde -->
<div class="fixed inset-0 sm:inset-4 lg:inset-auto lg:max-w-md">
```

## 10. Zorunlu CSS (layouts/app.blade.php)

```css
@media (max-width: 768px) {
    button, .btn, a.btn {
        min-height: 44px;
    }
    input, select, textarea {
        min-height: 44px;
        font-size: 16px; /* iOS zoom önleme */
    }
}
```

---

## Kontrol Listesi (Her Yeni View İçin)

- [ ] `flex-col sm:flex-row` kullandın mı?
- [ ] `grid-cols-1` ile başladın mı?
- [ ] `w-full sm:w-auto` butonlar için?
- [ ] `text-xl lg:text-2xl` başlıklar için?
- [ ] `p-4 lg:p-6` spacing için?
- [ ] `gap-3 lg:gap-4` grid/flex gap için?
- [ ] Touch-friendly min-height 44px?
- [ ] Input font-size 16px (iOS)?

---

> **NOT:** Bu kurallar her yeni Blade view dosyası oluşturulurken otomatik olarak uygulanmalıdır.
