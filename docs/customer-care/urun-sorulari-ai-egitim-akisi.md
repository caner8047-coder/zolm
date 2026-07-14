# Ürün Soruları ve AI Eğitim Havuzu

**Tarih:** 14 Temmuz 2026  
**Ekran:** `/customer-care/product-questions`  
**Feature flag:** `CUSTOMER_CARE_ENABLED=true` ve `CUSTOMER_CARE_KNOWLEDGE_ENABLED=true`

## Amaç

Pazaryerlerinden çekilen gerçek ürün sorularını ve yayınlanmış satıcı cevaplarını AI Müşteri Merkezi'nde mağaza bazlı göstermek; güvenli kayıtları insan onaylı bilgi tabanına taşımak ve seçilmiş örnekleri golden dataset aday havuzunda işaretlemek.

Ham soru-cevaplar doğrudan modele öğretilmez. Canlı AI bağlamına yalnız Bilgi Bankası Önerileri ekranında insan tarafından onaylanıp yayınlanan, süreli ve kaynaklı kayıtlar girer.

## Veri akışı

1. Kullanıcı **Soru ve Cevapları Çek** işlemini başlatır.
2. Connector açık ve cevaplanmış soruları varsayılan 365 günlük geriye dönük pencereyle çeker.
3. `MarketplaceQuestionSyncService` kayıtları mağaza + haricî soru kimliğiyle idempotent saklar.
4. Customer Care açıksa soru ve yayınlanmış cevap `SupportProjectionService` ile ilgili pazaryeri kanalına yansıtılır.
5. Kullanıcı uygun bir kaydı **Bilgi Adayı Yap** işlemiyle inceleme kuyruğuna gönderir.
6. Soru, cevap, ürün adı ve stok kodu PII temizliğinden geçirilerek `SupportKnowledgeSuggestion` kaydı oluşturulur.
7. Bilgi yöneticisi öneriyi düzenler, reddeder veya onaylar.
8. Onaylanan kayıt `WaKnowledgeArticle` olarak yayınlanır ve ürün adı/SKU ile grounding aramasına katılır.
9. Yalnız yayınlanmış kayıtlar **Golden Adayı Yap** işlemiyle ayrı aday havuzuna alınabilir. Bu işlem canlı golden dataset'i kendiliğinden değiştirmez.

## Durumlar

| Durum | Anlamı |
|---|---|
| `new` | Henüz eğitim kararı verilmedi |
| `candidate` | Bilgi bankası insan inceleme kuyruğunda |
| `applied` | İnsan onayıyla bilgi tabanında yayınlandı |
| `excluded` | İnsan kararı veya güvenlik gerekçesiyle eğitim dışında |
| Golden aday | Yayınlanmış kayıt ayrıca kalite/eval aday havuzunda |

## Güvenlik kuralları

- Siparişe özel “kargom”, “siparişim”, takip numarası ve adres değişikliği cevapları yeniden kullanılamaz.
- Sağlık, hukuk veya kesin vaat içeren cevaplar otomatik bilgi adayı olamaz.
- Prompt injection şüphesi bulunan soru veya cevap fail-closed engellenir.
- Telefon, e-posta, isim ve diğer PII alanları merkezi `PiiRedactor` ile maskelenir.
- Cevabı pazaryerinde yayınlanmamış taslaklar eğitim adayı yapılamaz.
- Fiyat, stok, kampanya ve teslimat içerikleri 7 gün; diğer ürün bilgileri 180 gün sonra geçersiz olur.
- Mağaza/organizasyon sınırı hem Livewire sorgusunda hem servis katmanında yeniden doğrulanır.
- Bilgi adayı oluşturmak `ai_draft_generate`, yayın dışı bırakmak `knowledge_publish`, golden aday kararı `approve_quality_review` yetkisi gerektirir.
- Bekleyen bir aday eğitim dışı bırakıldığında bağlı bilgi önerisi de aynı transaction içinde reddedilir; **Yeniden İncele** işlemi öneriyi tekrar `pending` kuyruğuna açar. Yayınlanmış makale bu ekrandan geri alınamaz.

## Desteklenen kaynaklar

Ürün sorusu capability'si bulunan mevcut connectorlar kullanılır. Bunlar arasında Trendyol, Hepsiburada, N11, Pazarama, ÇiçekSepeti, Koçtaş ve WooCommerce bulunur. Her kanal gerçek capability sonucu üzerinden çalışır; desteklenmeyen connector başarı uydurmaz.

## Doğrulama

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareProductQuestionsTest.php --no-coverage
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage
```

Otomatik testler tenant izolasyonu, PII temizliği, riskli soru engeli, idempotent aday üretimi, hariç tutma/yeniden inceleme eşitlemesi, insan onaylı yayın, golden aday kapısı, Hepsiburada projeksiyonu ve feature flag fail-closed davranışını kapsar.
