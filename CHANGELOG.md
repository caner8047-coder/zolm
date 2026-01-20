# ZOLM - Changelog

## V0.2 - 2026-01-20 (Responsive)

### ✨ Yeni Özellikler

#### Mobil Responsive Tasarım
- **Hamburger Menü** - Mobilde sidebar toggle
- **Touch-Friendly Butonlar** - Min 44px height
- **Stack Layout** - Mobilde dikey düzen
- **Responsive Grid** - Tüm sayfalarda uyumlu grid sistemi

#### Yeni Menü Öğeleri (Yakında)
- **Kargo Raporları** - Kargo XLS karşılaştırma (placeholder)
- **Tedarik Raporu** - Eksik/kusurlu ürün takibi (placeholder)
- **Pazaryeri Muhasebe** - 178 faktörlü check list (placeholder)
- **API / Dev** - zemuretim.online entegrasyonu (placeholder)

### 🔧 İyileştirmeler
- Admin paneli mobil uyumlu hale getirildi
- AI Chat mobil görünümü düzeltildi
- Activity Logs mobilde card view eklendi
- Report History filtreleri mobil uyumlu

### 🐛 Hata Düzeltmeleri
- Alpine.js çift yükleme sorunu düzeltildi (Livewire 3 dahili)
- Profil modal açılmama sorunu düzeltildi
- Sidebar mobile overlay eklendi

---

## V0.1 - 2026-01-20 (Genesis)

Bu sürüm ZOLM XLS Dönüşüm Platformu'nun temel özelliklerini içerir.

---

### ✨ Özellikler

#### Motor Sistemi
- **Üretim Motoru** - XLS dosyalarını üretim raporlarına dönüştürme
- **Operasyon Motoru** - XLS dosyalarını operasyon raporlarına dönüştürme
- **DynamicTransformEngine** - AI profillerini işleyen dinamik dönüşüm motoru

#### Profil Yönetimi
- **AI ile Profil Oluşturma** - Groq AI kullanarak otomatik kural oluşturma
- **Manuel Profil** - Temel profil oluşturma
- **Profil Wizard** - 4 adımlı profil oluşturma sihirbazı
- **JSON Editör** - AI kurallarını manuel düzenleme

#### Raporlama
- **Geçmiş Raporlar** - Tarih bazlı rapor görüntüleme
- **Toplu İndirme** - Birden fazla dosyayı zip olarak indirme
- **Toplu Silme** - Seçilen raporları silme
- **30+ Gün Temizleme** - Eski raporları otomatik temizleme

#### Admin Paneli
- **Dashboard** - Sistem istatistikleri
- **Kullanıcı Yönetimi** - CRUD + şifre sıfırlama
- **Aktivite Logları** - Sistem aktivitelerini takip
- **Rol Sistemi** - Admin, Manager, Operator

#### AI Chat
- **E-ticaret Uzmanı** - Raporları analiz eden AI chat
- **Rol Seçimi** - Üretim Müdürü, Operasyon Sorumlusu, Genel Uzman
- **Rapor Bağlama** - Chat'e rapor bağlayarak analiz

#### UI/UX
- **Mobil Responsive** - Tüm sayfalar mobil uyumlu
- **Hamburger Menü** - Mobil sidebar toggle
- **Touch-Friendly** - 44px min button height
- **Dark Theme Header** - Profesyonel görünüm

---

### 🛠️ Teknik Detaylar

- **Framework:** Laravel 11 + Livewire 3
- **AI:** Groq API (llama-3.3-70b-versatile)
- **Excel:** PhpSpreadsheet
- **Styling:** TailwindCSS
- **Interactivity:** Alpine.js (Livewire dahili)

---

### 📁 Dosya Yapısı

```
app/
├── Livewire/
│   ├── Admin/
│   │   ├── Dashboard.php
│   │   ├── UserManager.php
│   │   └── ActivityLogs.php
│   ├── AIChat.php
│   ├── OperationMotor.php
│   ├── ProductionMotor.php
│   ├── ProfileManager.php
│   ├── ProfileWizard.php
│   └── ReportHistory.php
├── Models/
│   ├── ActivityLog.php
│   ├── Profile.php
│   ├── Report.php
│   ├── ReportFile.php
│   └── User.php
└── Services/
    ├── AIProfileAnalyzer.php
    ├── DynamicTransformEngine.php
    ├── ExcelService.php
    └── OperationEngine.php
```

---

### ⚠️ Bilinen Sorunlar

- Email şifre sıfırlama henüz aktif değil (SMTP gerekli)
- Toplu zip indirme henüz "yakında" durumunda

---

### 📋 Sonraki Sürüm için Planlanan

- [ ] Email ile şifre sıfırlama
- [ ] Toplu zip indirme
- [ ] Rapor paylaşım linki
- [ ] Dark mode toggle
- [ ] PWA desteği
