# Antigravity Promptu — Faz 0: Güncel Repo Doğrulaması

Aşağıdaki metin Antigravity'ye tek görev olarak verilmelidir.

---

ZOLM projesinde **Faz 0 — AI Müşteri İletişim Merkezi güncel repo doğrulaması ve boşluk analizi** görevini uygula.

Önce `docs/customer-care/urun-gereksinimleri.md` ve `docs/customer-care/antigravity-yurutme-plani.md` dosyalarını tamamen oku. Ürün gereksinimlerini değiştirme; mevcut kod kapsamını bu gereksinimlere göre değerlendir.

Bu faz salt okunur keşif fazıdır. Hiçbir PHP, Blade, JavaScript, CSS, migration, config, route, test veya mevcut doküman dosyasını değiştirme. Yalnızca şu yeni raporu oluşturabilirsin:

```text
docs/customer-care/00-mevcut-durum-dogrulama.md
```

Sonraki faza geçme. Kod, migration veya modül iskeleti oluşturma.

## Değişmez ürün kararı

- Qsup yalnız rakip benchmark'ıdır; entegrasyon sağlayıcısı veya mimari bağımlılık değildir.
- Hedef ZOLM'a ait, sağlayıcıdan bağımsız AI Müşteri İletişim Merkezi geliştirmektir.
- Mevcut `marketplace_questions`, `wa_*` ve `support_*` yapıları incelenmeden yeni `care_*` konuşma tabloları önerme veya oluşturma.
- Mevcut çalışan pazaryeri, WhatsApp, üretim, operasyon, iade, CRM ve muhasebe akışlarını değiştirme.

## Zorunlu başlangıç doğrulaması

Önce aşağıdakileri çalıştır ve rapora gerçek sonuçlarını yaz:

```text
pwd
git rev-parse --show-toplevel
git remote -v
git branch --show-current
git status --short
git log -1 --oneline
```

Kirli çalışma ağacındaki kullanıcı değişikliklerine dokunma. Rapor dışında yeni dosya oluşturma.

## Eksiksiz incelenecek alanlar

### Proje gerçeği

- `composer.json`
- `package.json`
- `AGENTS.md`
- `README.md`
- `.env.example`
- İlgili config, route ve middleware dosyaları
- Gerçek PHP, model, Livewire, servis, migration ve test sayıları
- En büyük ve en karmaşık ilgili sınıflar

### Birleşik Support altyapısı

- `app/Models/Support*`
- `app/Services/Support/`
- `database/migrations/*support*`
- `tests/Feature/WhatsApp/SupportChannelTest.php`
- Support ile ilişkili SLA, analitik, agent action ve sync cursor kodları
- Gerçekten çalışan, placeholder veya sahte başarı döndüren adapter metotları

### WhatsApp altyapısı

- `config/whatsapp.php`
- `.env.example` WhatsApp alanları
- `routes/api.php` ve ilgili web route'ları
- `app/Http/Controllers/WhatsApp/`
- `app/Jobs/WhatsApp/`
- `app/Services/WhatsApp/`
- `app/Livewire/WhatsApp/`
- `app/Models/Wa*`
- Bütün `wa_*` migrationları
- `tests/Feature/WhatsApp/`
- Outbox, webhook imza doğrulaması, idempotency, consent, retention, AI, knowledge, handoff, analytics ve SLA kapsamı

### Pazaryeri soru-cevap altyapısı

- `MarketplaceQuestion` modelleri ve migrationları
- `MarketplaceQuestions` Livewire ve Blade ekranı
- `MarketplaceQuestionSyncService`
- `MarketplaceQuestionAnswerService`
- `MarketplaceQuestionAiService`
- `MarketplaceQuestionRuleEngine`
- `MarketplaceConnectorManager`
- Marketplace connector contractları ve capability sistemi
- Trendyol, Hepsiburada ve N11 connectorlarında soru çekme ve cevap gönderme metotlarının gerçek durumu
- Sabit `ai_confidence` kullanımı

### AI ve firma hafızası

- `config/ai.php`
- `AIService`
- Mevcut bütün AI provider interface ve implementasyonları
- Prompt sürümü, structured output, token, maliyet, latency ve provider fallback durumu
- WhatsApp knowledge base ve diğer template/rule/bilgi kaynakları
- Firma/store bazlı veri izolasyonu

### Ticari bağlam servisleri

- Ürün, channel product/listing, stok ve fiyat servisleri
- Kampanya servisleri
- Sipariş, kargo, iade ve CRM servisleri
- AI'ın read-only araç olarak güvenle kullanabileceği mevcut servisler
- Aynı işi yapan veya çakışan modeller

### Tenant, güvenlik ve operasyon

- `user_id`, `legal_entity_id`, store sahipliği ve mevcut çok kullanıcılı firma modeli
- Policy, gate, middleware ve route model binding
- Queue driver, retry/backoff, idempotency ve failed job yaklaşımı
- Cache anahtarlarında tenant kapsamı
- Credential encryption
- Mesaj gövdesi ve raw payload saklama biçimi
- Audit, retention, export ve silme yaklaşımı
- Feature flag mekanizmaları

## Raporun zorunlu bölümleri

1. İncelenen commit, branch ve kirli çalışma ağacı
2. Gerçek teknoloji ve boyut envanteri
3. Mevcut uçtan uca Trendyol soru-cevap akışı
4. Mevcut uçtan uca WhatsApp inbound/outbound akışı
5. Mevcut `support_*` birleşik kanal mimarisi
6. Kanal capability matrisi: gerçek / kısmi / placeholder / yok
7. Yeniden kullanılacak sınıf ve tablolar
8. Güçlendirilecek sınıf ve tablolar
9. Oluşturulması gerçekten gereken yeni parçalar
10. Oluşturulmaması gereken tekrar yapılar
11. Tenant ve yetkilendirme modeli
12. Güvenlik ve KVKK riskleri
13. Queue, outbox, retry ve idempotency riskleri
14. AI doğruluk, kaynak, güven ve maliyet boşlukları
15. Test kapsamı ve güvenilirlik değerlendirmesi
16. Önerilen canonical çekirdek kararı
17. İlk Trendyol copilot pilotunun minimum kapsamı
18. Faz 1'e geçişi engelleyen açık kararlar
19. `ZCC-001`–`ZCC-018` kapsam matrisi: mevcut / kısmi / placeholder / yok
20. Her `ZCC-*` gereksinimi için mevcut kanıt dosyaları ve ana boşluk

Her önemli iddiayı dosya yolu, sınıf/metot adı ve mümkünse satır numarasıyla destekle. Kodda doğrulanmayan şeyi gerçekmiş gibi yazma; `varsayım`, `placeholder` veya `doğrulanamadı` olarak işaretle.

Özellikle aşağıdakileri doğrula:

- `support_*` tabloları yeni konuşma çekirdeği olarak genişletilebilir mi?
- WhatsApp adapterı gerçek `wa_outbox` kaydı oluşturuyor mu?
- Trendyol Support adapterı gerçek cevap servisini çağırıyor mu?
- Support cevap gönderimi senkron mu, queued mu?
- WhatsApp inbox sorguları store/tenant kapsamlı mı?
- WhatsApp bilgi aramasında relevance doğru hesaplanıyor mu?
- `MarketplaceQuestionAiService` güven puanı sabit mi?
- Mevcut testler gerçek haricî gönderimi mi, yalnız iskeleti mi kanıtlıyor?
- Site chat/widget veya CRM lead toplama altyapısı mevcut mu?
- Marka sesi, üç çalışma modu, human ownership lock ve öğrenme önerisi yaşam döngüsü gerçekten var mı?
- Gösterilen kalite yüzdeleri gerçek veriden mi hesaplanıyor, demo/placeholder mı?
- Mevcut onboarding akışı bağlantı, capability, katalog doğrulama, marka ayarı ve shadow testi içeriyor mu?
- CRM/ERP veya özel sistem bağlantıları için versiyonlu, tenant kapsamlı adapter/API sınırı var mı?
- Mesaj, credential ve kişisel veri alanlarında şifreleme/masking/RBAC gerçekten uygulanıyor mu?
- Gönderilmiş yanlış cevap için kanal capability'sine göre retract/edit veya düzeltme süreci var mı?
- Türkçe yazım hatası/argo/kısaltma ve diğer diller için ölçülebilir değerlendirme seti var mı?

## Test ve doğrulama

Uygun Docker/Sail ortamı aktifse en az aşağıdaki testi çalıştır:

```text
./vendor/bin/sail artisan test tests/Feature/WhatsApp/SupportChannelTest.php
```

Test çalışmıyorsa sebebini raporla; düzeltme yapma. İlgili başka dar kapsamlı, güvenli testler varsa çalıştırabilirsin. Bütün test paketini zorunlu olarak çalıştırma.

## Faz sonu teslimatı

Yalnız şunları teslim et:

- `docs/customer-care/00-mevcut-durum-dogrulama.md`
- `git status --short`
- Çalıştırılan testler ve sonuçları
- En kritik beş bulgunun kısa özeti
- Faz 1 öncesi karar bekleyen konular

Raporu oluşturduktan sonra dur. Faz 1'e geçme ve hiçbir uygulama kodunu değiştirme.
