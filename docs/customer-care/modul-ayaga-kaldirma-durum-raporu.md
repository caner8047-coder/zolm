# ZOLM AI Müşteri İletişim Merkezi — Ayağa Kaldırma Durum Raporu

Tarih: 2026-07-14

## Baş mühendis özeti

Modülün çekirdek ürün kapsamı repo içinde uygulanmış durumdadır. Faz haritasındaki teknik gereksinimler; support çekirdeği, tenant izolasyonu, outbox, kanal adapterları, AI copilot, policy engine, pilot/canary, WhatsApp, sosyal/Google/web chat köprüleri, analitik, governance, compliance, production readiness ve enterprise yüzeylere kadar kodlanmıştır.

Bu turda kalan en görünür ürünleşme boşlukları kapatıldı:

- AI Müşteri Merkezi sidebar grubu tüm mevcut alt merkezleri kapsayacak şekilde genişletildi.
- Ana `/customer-care` sayfası pasif hazırlık kartından çıkarılıp gerçek komuta merkezi / launchpad haline getirildi.
- Ayarlar ekranındaki kanal yok durumu, mevcut mağaza ve entegrasyonlardan güvenli kanal provisioning akışına bağlandı.
- Operasyonel kurulum için `customer-care:provision-channels` komutu eklendi; varsayılan dry-run çalışır, gerçek kanal oluşturma için `--execute` gerekir.
- Ayarlar ekranına kanal aktif/pasif anahtarı eklendi; kanallar güvenli varsayılanla pasif doğar, kullanıcı manual/copilot için bilinçli olarak etkinleştirir.
- Test ortamında Customer Care feature flag defaultları sabitlendi; lokal `.env` bayrakları test sonuçlarını bozmaz hale getirildi.

## Faz haritası durumu

| Faz | Kapsam | Durum |
| --- | --- | --- |
| Faz 0 | Repo doğrulaması ve boşluk analizi | Tamamlandı |
| Faz 1 | ADR, feature flag ve modül sınırı | Tamamlandı |
| Faz 2 | Tenant, yetkilendirme, KVKK temeli | Tamamlandı |
| Faz 3 | Support çekirdeği, outbox, retry, kill switch | Tamamlandı |
| Faz 4 | Pazaryeri projection ve Trendyol adapter | Tamamlandı |
| Faz 5 | Birleşik inbox ve manuel operasyon | Tamamlandı |
| Faz 6 | AI provider contract ve yapılandırılmış çıktı | Tamamlandı |
| Faz 7 | Bilgi merkezi, marka sesi, canlı bağlam | Tamamlandı |
| Faz 8 | AI copilot, insan geri bildirimi, shadow | Tamamlandı |
| Faz 9 | Güven/risk/policy motoru | Tamamlandı |
| Faz 10 | Golden dataset, shadow ve pilot kapısı | Tamamlandı |
| Faz 11 | Kontrollü otomasyon, canary, circuit breaker | Tamamlandı |
| Faz 12 | WhatsApp birleşik çekirdek bağlantısı | Tamamlandı |
| Faz 13 | Hepsiburada / N11 kanal adapterları | Tamamlandı |
| Faz 14 | Instagram, Facebook, Google Business | Kod tamam; canlı kullanım için platform app review / connector secret gerekir |
| Faz 15 | Web chat ve e-ticaret site köprüleri | Kod ve contract tamam; canlı kullanım için widget dağıtımı gerekir |
| Faz 16 | Analitik, kullanım, retention, üretim sertleştirme | Tamamlandı |

## Canlıya alma öncesi bekleyen iş türleri

Bunlar kod eksikliği değil, operasyonel aktivasyon maddeleridir:

1. Gerçek mağaza/tenant için Customer Care feature flag setinin seçilmesi.
2. İlgili mağaza için Ayarlar ekranından veya `customer-care:provision-channels --store=ID --execute` komutuyla SupportChannel provisioning yapılması.
3. Her kanalın connector credential / webhook secret / app review süreçlerinin tamamlanması.
4. Golden eval ve pilot readiness komutlarının gerçek mağaza verisiyle çalıştırılması.
5. `auto_reply_enabled` bayrağının yalnız kalite kapıları geçtikten sonra açılması.

## Bu turdaki doğrulama

- Customer Care test paketi: `428 passed / 1380 assertions`
- Full test suite: `1887 passed / 7441 assertions`
- `npm run build`: başarılı
- `git diff --check`: temiz
- Customer Care route sayısı: 30
- Customer Care artisan komutu: 39
