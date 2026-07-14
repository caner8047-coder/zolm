# ZOLM AI Müşteri İletişim Merkezi — Antigravity Yürütme Planı

## 1. Amaç

Bu belge, ZOLM'a bağımsız bir **AI Müşteri İletişim Merkezi** eklenirken Antigravity'nin uygulayıcı mühendis, Codex'in ise baş mühendis/mimar ve kalite kapısı olarak çalışacağı yürütme modelini tanımlar.

Davranışsal ürün şartnamesi `docs/customer-care/urun-gereksinimleri.md` dosyasında tanımlanır. Her faz bu şartnamedeki `ZCC-*` gereksinimlerine izlenebilir olmalıdır.

Hedef; ZOLM kullanan firmaya ürünlerini, siparişlerini, stoklarını, kampanyalarını ve marka dilini bilen, 7/24 çalışan dijital müşteri hizmetleri ve satış çalışanı sunmaktır.

Qsup yalnızca rakip ve ürün benchmark'ıdır. Qsup API'si, Qsup hesabı, Qsup veri modeli veya Qsup'ın devamlılığı hiçbir mimari kararın ön koşulu değildir.

## 2. Değişmez mimari ilkeler

1. Kullanıcıya sunulan yeni üst seviye modülün adı **ZOLM AI Müşteri İletişim Merkezi** olacaktır.
2. Yeni modül, mevcut verileri tekrar eden bağımsız bir ada olmayacaktır.
3. `marketplace_questions` pazaryeri soru-cevap kaynağı, `wa_*` tabloları WhatsApp kanal kaynağı olarak geriye uyumlu şekilde korunacaktır.
4. Mevcut `support_*` modelleri ve `App\Services\Support` katmanı birleşik iletişim çekirdeğinin ilk canonical adayıdır. Faz 0 doğrulaması yapılmadan paralel `care_*` konuşma tabloları oluşturulmayacaktır.
5. Mevcut çalışan pazaryeri, WhatsApp, üretim, operasyon, iade, CRM ve muhasebe akışları kırılmayacaktır.
6. AI sağlayıcısı değiştirilebilir bir contract arkasında olacaktır. Şirket verisi, konuşma geçmişi, bilgi merkezi, değerlendirme seti ve öğrenme kayıtları ZOLM'da kalacaktır.
7. Fiyat, stok, kampanya, sipariş ve kargo gibi değişken gerçekler model hafızasına bırakılmayacak; ZOLM servislerinden kontrollü araç çağrısıyla alınacaktır.
8. Her firma/tenant için veri, bilgi merkezi, prompt bağlamı, cache, queue, dosya ve rapor izolasyonu zorunludur.
9. Güvenlik, KVKK, idempotency, audit ve veri minimizasyonu son faz değil bütün fazların kalite kapısıdır.
10. Otomatik cevap varsayılan olarak kapalıdır. Copilot ve shadow sonuçları onaylanmadan auto-reply açılamaz.
11. Yeni özellikler feature flag arkasında ve varsayılan `false` olarak geliştirilir.
12. Destructive migration, big-bang refactor ve çalışan tabloların erken yeniden adlandırılması yasaktır.

## 3. Görev dağılımı

### Codex — Baş mühendis

- Mimari kararları ve faz kapsamını belirler.
- Her faz için Antigravity promptunu, kabul kriterlerini ve yasaklı değişiklikleri hazırlar.
- Antigravity çıktısını git diff, migration, güvenlik, test ve geriye uyumluluk açısından inceler.
- Faz sonucunu `KABUL`, `REVİZYON GEREKLİ` veya `DURDUR` olarak değerlendirir.
- Bir faz kabul edilmeden sonraki fazı açmaz.

### Antigravity — Uygulayıcı mühendis

- Yalnız verilen fazın kapsamını uygular.
- Başlangıçta repo, branch ve kirli çalışma ağacını doğrular.
- Kullanıcıya ait mevcut değişikliklere dokunmaz.
- Kapsam dışı refactor veya sonraki faz çalışması yapmaz.
- Test, build ve faz sonu kanıt paketini üretir.
- Codex onayı olmadan sonraki faza geçmez.

### Kullanıcı — Ürün sahibi

- İş hedefi, kanal önceliği ve risk toleransı kararlarını verir.
- Antigravity'ye Codex tarafından hazırlanan faz promptunu iletir.
- Haricî platform hesapları, sözleşmeler ve uygulama inceleme süreçleri için gerekli işletme yetkisini sağlar.

## 4. Her fazın çalışma protokolü

1. Codex faz promptunu yayınlar.
2. Antigravity önce salt okunur keşif yapar ve kısa uygulama planını bildirir.
3. Antigravity yalnız faz kapsamındaki değişiklikleri uygular.
4. Antigravity test ve doğrulama komutlarını çalıştırır.
5. Antigravity aşağıdaki kanıt paketini verir.
6. Codex gerçek çalışma ağacını ve diff'i bağımsız inceler.
7. Codex kabul veya revizyon kararı verir.

Zorunlu faz sonu kanıt paketi:

```text
git branch --show-current
git status --short
git diff --stat
değiştirilen/eklenen dosyalar
migrationların tam adları ve rollback açıklaması
çalıştırılan testler ve sonuçları
çalıştırılan build/lint sonuçları
manuel test adımları
bilinen eksikler ve riskler
kapsam dışında özellikle dokunulmayan alanlar
```

Antigravity açık talimat olmadan commit, push, branch değiştirme, veri silme veya production işlemi yapmaz.

## 5. Faz haritası

### Faz 0 — Güncel repo doğrulaması ve boşluk analizi

Kod yazılmaz. Mevcut `support_*`, `wa_*`, `marketplace_questions`, AI, tenant, policy, queue ve test altyapısı gerçek koddan çıkarılır. Eski raporların varsayımları kullanılmaz.

Faz 0 raporu ayrıca `ZCC-001`–`ZCC-018` için `mevcut / kısmi / placeholder / yok` kapsam matrisi üretir.

Çıkış kapısı: Canonical çekirdek adayı, yeniden kullanılacak parçalar, gerçek eksikler ve riskler dosya kanıtıyla belirlenmiş olmalıdır.

### Faz 1 — ADR'ler, ürün sınırı ve feature flag iskeleti

Modül adı, namespace yaklaşımı, `support_*` çekirdeğinin kaderi, tenant sahipliği, kanal projection modeli, AI provider sınırı ve asenkron işleme ADR'lerle sabitlenir. Modül varsayılan kapalı oluşturulur.

Çıkış kapısı: Yeni ve mevcut veri modelleri arasında belirsizlik kalmamalıdır.

### Faz 2 — Tenant, yetkilendirme ve veri güvenliği temeli

Mevcut `user_id`, `legal_entity_id`, store sahipliği ve olası organization ihtiyacı doğrulanır. Query, route binding, queue, cache, credential, export ve audit izolasyonu kurulur.

Bu faz `ZCC-016` güvenlik, KVKK ve rol bazlı erişim şartlarını teknik temele bağlar.

Çıkış kapısı: Tenant A'nın Tenant B verisine erişemediğini kanıtlayan otomatik testler geçmelidir.

### Faz 3 — Birleşik Support çekirdeği ve outbound güvenilirliği

Mevcut `support_*` modelleri genişletilir; status lifecycle, idempotency, concurrency, gerçek outbox, retry/backoff, delivery durumu ve kill switch temeli tamamlanır. Senkron haricî kanal gönderimi kaldırılır.

Bu faz `ZCC-017` yanlış cevap, geri alma/düzeltme ve kritik hata durdurma yaşam döngüsünün kanal bağımsız temelini oluşturur.

Çıkış kapısı: Retry veya çift tıklama çift cevap üretmemelidir; sahte başarı sonucu bulunmamalıdır.

### Faz 4 — Pazaryeri projection köprüsü ve Trendyol adapterı

`MarketplaceQuestion` kayıtları idempotent biçimde birleşik konuşma görünümüne yansıtılır. Trendyol adapterı mevcut sync ve answer servislerini gerçekten kullanır. Legacy ekran çalışmaya devam eder.

Çıkış kapısı: Aynı soru iki kez oluşmamalı ve eski cevaplama akışı bozulmamalıdır.

### Faz 5 — Birleşik gelen kutusu ve manuel operasyon

AI olmadan çalışan inbox; arama, filtre, atama, iç not, öncelik, durum, müşteri/ürün/sipariş bağlamı ve queued manuel cevap akışıyla geliştirilir.

Çıkış kapısı: AI tamamen kapalıyken uçtan uca güvenilir manuel destek mümkün olmalıdır.

### Faz 6 — AI provider contractı ve yapılandırılmış çıktı

Mevcut AI servisleri bozulmadan müşteri iletişimine özel provider contractı, structured output, prompt sürümü, token/maliyet/latency ve hata kayıtları eklenir. Qsup bağımlılığı eklenmez.

Provider çıktısı `ZCC-018` için dil ve dil güveni alanlarını yapılandırılmış biçimde taşıyabilmelidir.

Çıkış kapısı: Provider değişimi domain kodunu değiştirmemeli; geçersiz çıktı güvenli biçimde reddedilmelidir.

### Faz 7 — Firma hafızası, bilgi merkezi ve canlı bağlam araçları

Onaylı cevaplar, politikalar, marka dili ve sürümlü belgeler firma bazında tutulur. Ürün, stok, fiyat, kampanya, sipariş, kargo ve iade için read-only ZOLM araçları hazırlanır.

Bu faz özellikle `ZCC-001`, `ZCC-002`, `ZCC-006`, `ZCC-007` ve `ZCC-010` gereksinimlerini karşılar.

Çıkış kapısı: Her önemli iddia kaynak veya canlı ZOLM verisiyle desteklenmelidir.

### Faz 8 — AI copilot ve insan geri bildirimi

AI taslağı, kaynakları, niyeti ve risk bilgisi kullanıcıya gösterilir. Kabul, düzenleme, ret ve insana aktarma kayıtları tutulur. Düzenleme mesafesi ölçülür.

Bu faz özellikle `ZCC-003`, `ZCC-004` ve `ZCC-012` gereksinimlerinin insan kontrollü çekirdeğini karşılar.

Çıkış kapısı: AI hiçbir cevabı kendiliğinden göndermez; bütün kararlar audit edilebilir olmalıdır.

### Faz 9 — Güven, risk ve kanal politika motoru

Kaynak yeterliliği, entity eşleşmesi, güncellik, intent, risk ve kanal politikalarından hesaplanan kalibre edilebilir güven sistemi kurulur. Sabit güven puanı kaldırılır.

Bu faz özellikle `ZCC-003` ve `ZCC-005` gereksinimlerini karşılar.

Çıkış kapısı: Yüksek riskli ve kaynaksız cevaplar otomatik olarak engellenmelidir.

### Faz 10 — Golden dataset, shadow ve Trendyol pilotu

Anonimleştirilmiş gerçek Türkçe mesaj seti hazırlanır. AI cevapları müşteriye gitmeden çalışan cevaplarıyla karşılaştırılır. İlk pilot yalnız düşük riskli Trendyol ürün sorularında yapılır.

Çıkış kapısı: Kritik hata, düzenleme, kaynak ve yanlış ürün eşleşme oranları kabul edilen eşiklerde olmalıdır.

### Faz 11 — Kontrollü otomasyon

Yalnız onaylanan kanal/mağaza/niyetlerde canary auto-reply açılır. Tenant, kanal, niyet ve provider kill switch; harcama ve hata oranı limitleri uygulanır.

Çıkış kapısı: Otomatik rollback ve manuel moda dönüş test edilmeden kapsam genişletilmez.

### Faz 12 — WhatsApp'ın birleşik çekirdeğe bağlanması

Mevcut geniş `wa_*` altyapısı yeniden yazılmaz. `Support` projection, gerçek outbox gönderimi, medya, müşteri hizmetleri penceresi, template, consent ve human handoff akışları tamamlanır.

Çıkış kapısı: WhatsApp kaynak kayıtları ile birleşik görünüm arasında reconciliation yapılabilmelidir.

### Faz 13 — Pazaryeri kapsamını genişletme

Hepsiburada ve N11 öncelikli olmak üzere gerçek API kabiliyetine göre kanal adapterları tamamlanır. Her kanal ortak contract test paketinden geçer.

### Faz 14 — Instagram, Facebook ve Google Business

Meta app review, OAuth/token lifecycle, webhook güvenliği, DM/yorum ayrımı, insan devri ve Google yorum cevaplama akışları geliştirilir.

### Faz 15 — Site canlı destek ve e-ticaret kanalları

ZOLM web chat widgetı ile Ikas, Shopify ve WooCommerce müşteri mesajı bağlantıları gerçek API kabiliyetlerine göre eklenir.

Widget `ZCC-008` ve `ZCC-012` gereksinimlerini; özellikle KVKK aydınlatması, izin ayrımı, domain doğrulaması, rate limit ve human handoff kurallarını karşılamalıdır.

Haricî CRM/ERP ve özel sistem bağlantıları `ZCC-015` adapter, source-of-truth, idempotency ve degraded mode kurallarına uyar.

### Faz 16 — Analitik, kullanım ölçümü ve üretim sertleştirmesi

Operasyon, AI kalite, satış desteği, maliyet ve kanal sağlığı metrikleri tamamlanır. Kota, retention, veri export/silme, alarm, runbook ve felaket senaryoları doğrulanır.

Analitik ekranları `ZCC-011` metrik tanımı ve minimum örneklem kurallarına uymalı; kanıtlanmamış başarı yüzdeleri göstermemelidir.

Bu faz `ZCC-013` iş sonucu ölçümünü ve `ZCC-014` onboarding süresi/başarı hunisini ürünleştirir.

## 6. Otomatik cevap için zorunlu kalite eşiği

Başlangıç hedefleri ürün sahibi ve pilot verisiyle kesinleştirilecektir. Aşağıdaki koşullar sağlanmadan otomatik gönderim açılamaz:

- Kritik yanlış cevap oranı tanımlanan eşikten düşük.
- Yanlış ürün/sipariş eşleşmesi kritik seviyede değil.
- Kaynaksız kesin iddia otomatik gönderilmiyor.
- Yüksek riskli niyetler insana aktarılıyor.
- Tenant sızıntısı testleri eksiksiz geçiyor.
- Retry ve webhook tekrarları çift cevap üretmiyor.
- Kill switch ve otomatik rollback denenmiş.
- İnsan düzenleme oranı ve güven kalibrasyonu kabul edilmiş.

## 7. Güncel yürütme durumu

Faz 0 doğrulaması ve devamındaki uygulama dalgaları tamamlanmıştır. `ZCC-001`–`ZCC-018` teknik kabul kapsamı 14 Temmuz 2026 tarihinde 501 Customer Care testi/1.769 assertion ve 1.960 tam proje testi/7.830 assertion ile hatasız kapatılmıştır. Son Customer Care migration'ları yerel MySQL 8 üzerinde uygulanmış, 41 route ve 5 zamanlanmış iş doğrulanmıştır.

Güncel source-of-truth:

- Ürün şartnamesi: `urun-gereksinimleri.md`
- Nihai kod/test kabul matrisi: `tamamlama-ve-kabul-raporu.md`
- Canlı connector doğrulaması: `connector-certification-runbook.md`
- Pilot işletimi: `pilot-runbook.md`
- KVKK/retention işletimi: `kvkk-retention-policy.md`

Yeni iş artık Faz 0 uygulaması değildir. Üretim mağazası için credential sağlama, platform app-review, mağaza bazlı connector sertifikasyonu, golden/shadow/canary kanıtı ve hukuk onayı tamamlandıkça kontrollü aktivasyon yapılır. Bu dış kapılar geçilmeden sistem varsayılan kapalı ve fail-closed kalır.
