# ADR-004 — Sürat Hesabının Ortak Taşıyıcı Popup'ına Alınması

- **Tarih:** 2026-07-22
- **Durum:** Kabul Edildi

## Bağlam

Yeni taşıyıcı hesapları Taşıyıcılar ekranında ortak bir popup ile kurulurken Sürat Kargo kartı eski `Sürat Entegrasyon` sekmesine geçiyordu. Aynı kullanıcı işi için iki farklı etkileşim modeli oluşuyordu. Sürat hesapları ayrıca çalışan operasyonlarda kullanılan özel şifre ve endpoint kolonlarına sahiptir.

## Değerlendirilen seçenekler

1. Sürat'i ayrı sekmede bırakmak.
2. Eski Sürat verisini genel credentials alanına taşımak ve eski kolonları bırakmak.
3. Sürat'i ortak popup'a almak, ancak mevcut özel kolonları popup arkasında okumaya/yazmaya devam etmek.

## Karar

Üçüncü seçenek seçildi. Sürat kartı diğer API taşıyıcıları gibi ortak hesap popup'ını açar. Popup Sürat'e özel gönderim, sorgulama, kapıda ödeme ve endpoint alanlarını gösterir. Mevcut `sender_password_encrypted`, `query_password_encrypted`, `cod_password_encrypted` ve bağlantı kolonları korunur.

Eski `activeTab=surat` istekleri `carriers` sekmesine eşlenir. Eski Livewire bileşeni veri geri dönüşü veya acil uyumluluk ihtiyacı için kod tabanında tutulur, fakat ana navigasyonda gösterilmez.

## Sonuçlar

- Tüm kurumsal kargo hesapları aynı kullanıcı akışından yönetilir.
- Çalışan Sürat hesaplarında migration veya yeniden şifre girişi gerekmez.
- Genel popup servisinde Sürat için kontrollü bir legacy alan eşlemesi bulunur.
- Sürat'e özgü ileri düzey endpoint düzenleme yüzeyi ana akıştan kalkar; mevcut özel ayarlar korunur ve varsayılan endpointler merkezi config'den kullanılır.

## Yeniden değerlendirme

Sürat hesap modeli ileride tamamen genel credentials şemasına taşınacaksa ayrı, geri alınabilir bir veri migration'ı ve canlı hesap kabul testi hazırlanmalıdır.
