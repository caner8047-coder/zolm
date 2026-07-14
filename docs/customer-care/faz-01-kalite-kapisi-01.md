# ZOLM AI Müşteri İletişim Merkezi — Faz 1 Kalite Kapısı 01

## Karar

**REVİZYON GEREKLİ — FAZ 1 HENÜZ KABUL EDİLMEDİ**

Uygulama iskeleti, güvenli varsayılanlar, feature middleware, route ve minimal giriş ekranı genel olarak başarılıdır. Bağımsız test ve build kontrolleri geçmiştir. Bununla birlikte bazı ADR'ler `Accepted` durumunda olmalarına rağmen uygulanabilir tek bir mimari karar vermemekte veya birbirinden bağımsız durum eksenlerini aynı state machine içinde birleştirmektedir.

**Faz 2 kapalıdır.** Aşağıdaki revizyonlar tamamlanıp yeniden baş mühendis incelemesine sunulmadan Faz 2'ye geçilemez.

## Antigravity İçin İzin Verilen Dar Kapsam

Yalnız şu dosyalar değiştirilebilir:

- `docs/customer-care/adr/001-support-projection-cekirdegi.md`
- `docs/customer-care/adr/002-tenant-ve-organizasyon-siniri.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/004-customer-care-ai-provider.md`
- `docs/customer-care/adr/005-bilgi-merkezi-siniri.md`
- `docs/customer-care/adr/006-human-ownership-state-machine.md`
- `tests/Feature/CustomerCare/CustomerCareFeatureTest.php`

Production kodu, config, env, middleware, route, Livewire component veya Blade görünümü değiştirilmeyecektir.

## Zorunlu Düzeltmeler

### 1. ADR-003 tek bir generic dispatch mimarisi seçmeli

ADR `Accepted` durumda olduğu için iki alternatif arasında kararsız bırakılamaz.

Karar şu şekilde netleştirilecektir:

- `support_messages`, müşteri ve temsilci mesajlarının kanonik iş kaydıdır; delivery queue/outbox görevi görmez.
- Kanallar arası gönderim yaşam döngüsü için yeni ve generic bir `support_dispatches` yapısı kullanılacaktır.
- Deneme geçmişi append-only bir `support_dispatch_attempts` yapısında tutulacaktır. MVP sırasında ayrı tablo yerine JSON düşünülse dahi nihai hedef ve audit gereksinimi açıkça belirtilmelidir; tercih edilen karar ayrı attempts yapısıdır.
- Idempotency key, durum, deneme sayısı, sonraki deneme zamanı, provider/channel external ID ve son hata dispatch katmanına aittir.
- `wa_outbox` WhatsApp adapter'ının iç teslimat detayı olarak kalır; generic çekirdeğe dönüştürülmez.
- Uygulama Faz 3'e ertelenir; Faz 1'de migration veya model yazılmaz.

### 2. ADR-004 karar/uygulama zamanlaması çelişkisi giderilmeli

- Faz 1'de generic Customer Care AI sınırı ve sözleşme ilkeleri **mimari olarak kabul edilmiştir**.
- Interface, DTO ve provider adapter kodunun uygulanması Faz 6'dır. Faz 1'de contract kodunun oluşturulduğu iddia edilmemelidir.
- Generic AI izlenebilirlik kaydı için kanonik hedef açıkça seçilmelidir: `support_ai_runs` ve gerekli source/draft ilişkileri Customer Care çekirdeğine aittir.
- Mevcut `wa_ai_runs` WhatsApp kaynak kaydı/uyumluluk katmanı olarak korunur; generic ledger yerine geçirilmez ve hemen kaldırılmaz.
- Production binding fail-closed olmalı; fake provider yalnız test/local açık seçimiyle kullanılabilmelidir.

### 3. ADR-006 üç bağımsız eksene ayrılmalı

Otomasyon modu, insan sahipliği ve konuşma yaşam döngüsü tek state machine değildir. ADR şu üç ekseni ayrı tanımlamalıdır:

1. **Conversation lifecycle:** `open`, `pending`, `resolved`, `closed` ve gerekirse `snoozed`.
2. **Ownership:** `unassigned`, `ai`, `human`; insan sahipliğinde owner kimliği ve kilit bilgisi tutulur.
3. **Automation mode:** `manual`, `copilot`, `automatic`.

Kurallar:

- Human ownership kilidi, conversation/kanal otomasyon modunun önüne geçer.
- `resolve` konuşmanın yaşam döngüsünü değiştirir; sahipliği AI'a bırakmakla aynı işlem değildir.
- `releaseToAi` veya eşdeğer işlem ayrı, yetkili, auditli ve concurrency-safe olmalıdır.
- Devralma, bırakma, resolve/reopen işlemleri optimistic lock veya atomik koşullu update ile korunmalıdır.
- Uygulama sonraki fazlara ertelenir; Faz 1'de model/migration yazılmaz.

### 4. ADR-002 tenant güvenlik iddiaları düzeltilmeli

ADR `Proposed` kalabilir; ancak çözülmemiş bir modeli olmuş gibi anlatmamalıdır.

- Eloquent global scope'un veritabanı seviyesinde izolasyon sağlamadığı açıkça yazılmalıdır; bu uygulama katmanı korumasıdır.
- `store_id` otomatik olarak tenant kimliği kabul edilmemelidir. Store, tenant içindeki kaynak sınırı olabilir.
- Faz 2'de organization/legal entity/member/store ilişkisi ve `TenantContext`/`CurrentOrganization` yaklaşımı karara bağlanmadan global scope uygulanmamalıdır.
- Policy, scoped repository/query builder, job context propagation ve çapraz-tenant negatif testler karar kriterlerine eklenmelidir.

### 5. ADR-001 güvenlik dili ölçülü hale getirilmeli

- Projection yaklaşımının tenant sızıntısını tek başına "engellediği" söylenmemelidir; tekrar ve tutarsızlığı azaltır, izolasyon ayrıca enforce edilir.
- Encrypted cast mutlak güvenlik olarak sunulmamalıdır; veri ifşası riskini azaltır. Maskeleme, anahtar rotasyonu, log redaksiyonu ve yetkilendirme ayrıca gereklidir.

### 6. ADR-005 seçenekleri isim ve sınır bakımından netleştirilmeli

`WaKnowledgeArticle` sınıfının generic tabloya "genişletilmesi" gibi belirsiz ifade kaldırılmalıdır. En az şu seçenekler açıkça karşılaştırılmalıdır:

- Yeni generic `support_knowledge_*` çekirdeği ve mevcut WhatsApp bilgisinden uyumluluk/projection köprüsü.
- Mevcut WhatsApp bilgi yapısının geçici adapter arkasında kullanılması.

ADR `Proposed` kalacaksa Faz 2/ilgili fazdaki karar ölçütleri: tenant kapsamı, kaynak izlenebilirliği, sürümleme, onay akışı, kanal bağımsızlığı ve geri uyumluluk olmalıdır.

### 7. Bilinmeyen feature anahtarı için fail-closed testi eklenmeli

Mevcut middleware davranışı güvenli görünse de doğrudan kanıt eksiktir.

- Test içinde geçici bir route veya middleware'in doğrudan kullanımıyla `customer-care.feature:unknown_feature` erişiminin `404` döndürdüğü kanıtlanmalıdır.
- Test kalıcı uygulama route'u eklememelidir.
- Test dosyasındaki kullanılmayan `use Livewire\Livewire;` import'u kaldırılmalıdır.

### 8. Regresyon sonucu doğru raporlanmalı

WhatsApp SupportChannel test sonucu `10/10 PASS` olarak raporlanmamalıdır. Bağımsız çalıştırmada sonuç:

- 10 test geçti.
- 1 test `risky` durumundadır.

Risky test Faz 0'da bilinen boş assertion problemidir; bu dar revizyonda production/test düzeltmesi yapılmayacak, yalnız raporda doğru ifade edilecektir.

## Bağımsız Doğrulama Kanıtı

Baş mühendis tarafından çalıştırılan kontroller:

```text
CustomerCare: 7 passed
WhatsApp SupportChannel: 10 passed, 1 risky
Toplam: 17 passed, 1 risky, 36 assertions
npm run build: PASS
git diff --check: PASS
customer-care route middleware sırası: auth -> customer-care.feature:inbox_enabled
```

## Revizyon Sonrası Beklenen Kanıt Paketi

Antigravity aşağıdakileri sunup duracaktır:

1. Değiştirilen dosyaların tam listesi.
2. Altı ADR için yapılan düzeltmelerin kısa özeti.
3. Customer Care test çıktısı.
4. WhatsApp SupportChannel regresyon çıktısı; risky test doğru şekilde raporlanmalı.
5. `git diff --check` sonucu.
6. `git status --short` sonucu.
7. Kapsam dışı dosyaya dokunulmadığı beyanı.

Commit, push ve branch değişikliği yapılmayacaktır. Faz 2'ye geçilmeyecektir.
