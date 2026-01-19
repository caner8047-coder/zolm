# ZOLM - Kurulum ve Çalıştırma Rehberi

## Gereksinimler

- PHP 8.3+
- Composer
- MySQL
- Node.js 18+
- Laragon (önerilir)

---

## 1. Kurulum Adımları

```bash
# Proje dizinine git
cd C:\laragon\www\zolm

# Composer bağımlılıklarını yükle
composer install

# Livewire yükle
composer require livewire/livewire

# Excel kütüphanesini yükle
composer require maatwebsite/excel

# Node bağımlılıklarını yükle
npm install

# Frontend'i derle
npm run dev
```

---

## 2. Veritabanı Ayarları

`.env` dosyasını düzenle:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zolm
DB_USERNAME=root
DB_PASSWORD=

# AI Ayarları (opsiyonel)
AI_PROVIDER=groq
AI_API_KEY=your_groq_api_key
AI_MODEL=llama-3.3-70b-versatile
```

---

## 3. Migration ve Seed

```bash
# Tabloları oluştur
php artisan migrate

# Varsayılan verileri ekle (roller + admin kullanıcı)
php artisan db:seed
```

---

## 4. Admin Hesabı

- **E-posta:** `admin@zolm.test`
- **Şifre:** `password`

---

## 5. Sayfa URL'leri

| Sayfa | URL |
|-------|-----|
| Giriş | http://zolm.test/login |
| Üretim | http://zolm.test/production |
| Operasyon | http://zolm.test/operation |
| Geçmiş Raporlar | http://zolm.test/reports |
| AI Chat | http://zolm.test/ai-chat |
| Profil Yönetimi | http://zolm.test/profiles |

---

## 6. Geliştirme

```bash
# Vite dev server'ı başlat
npm run dev

# Cache temizle
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## 7. Kullanıcı Rolleri

| Rol | Yetkiler |
|-----|----------|
| Admin | Tüm yetkiler |
| Üretim Sorumlusu | Üretim motoru + Raporlar |
| Operasyon Sorumlusu | Operasyon motoru + Raporlar |
| CRM Sorumlusu | Raporlar + AI Chat |

---

## 8. AI Entegrasyonu (Opsiyonel)

1. [Groq Console](https://console.groq.com/keys) adresinden API anahtarı al
2. `.env` dosyasına ekle: `AI_API_KEY=your_key`
3. Ücretsiz tier: Günde ~14.400 istek
