# ZOLM AI Müşteri İletişim Merkezi — Ürün Gereksinimleri

## 1. Ürün vaadi

ZOLM kullanan firma yalnız bir yazılım modülü değil; ürünlerini, siparişlerini, müşterilerini, kampanyalarını ve kurallarını bilen, 7/24 çalışan dijital müşteri hizmetleri ve satış çalışanı edinmelidir.

Bu çalışan rastgele veya yalnız model hafızasına dayalı cevap vermez. ZOLM'un doğrulanmış ticari verisini, firma bilgi merkezini ve kanal kurallarını kullanır; yeterli kanıt bulamazsa cevap uydurmak yerine konuşmayı insana aktarır.

Qsup bu belge için yalnız ürün benchmark'ıdır. Aşağıdaki gereksinimlerin bütün iş mantığı, verisi ve fikrî mülkiyeti ZOLM'a ait olacaktır.

## 2. Değişmez ürün ilkeleri

1. Canlı ticari gerçekler LLM hafızasından değil ZOLM servislerinden alınır.
2. Her önemli cevap iddiası bir kaynağa veya canlı veri sorgusuna bağlanır.
3. Düşük güven, eksik kaynak veya yüksek risk otomatik cevap üretmez.
4. İnsan devraldığı konuşmayı açıkça serbest bırakmadan AI tekrar devralamaz.
5. Firma verileri tenantlar arasında eğitim, retrieval, cache veya rapor düzeyinde karışamaz.
6. Sistem kendi kendine bilgi yayınlamaz; öğrenme önerileri insan onayından geçer.
7. Kanal politikaları prompt tavsiyesi değil deterministik validator olarak uygulanır.
8. Otomatik cevap açılmadan önce copilot, golden dataset, shadow ve canary kapıları geçilir.
9. Kullanıcı arayüzünde ölçülmemiş başarı oranı veya doğruluk iddiası gösterilmez.
10. Müşteri AI ile konuştuğunu anlayabilmeli; insan devri kesintisiz olabilir ancak yanıltıcı kimlik sunulamaz.

## 3. Temel yetenekler ve kabul ölçütleri

> **Uygulama durumu (14 Temmuz 2026):** ZCC-001–ZCC-018 uygulama kapsamı tamamlandı. Customer Care regresyonu 501 test/1.769 assertion, tam proje regresyonu 1.960 test/7.830 assertion ile hatasız geçti; yerel MySQL Customer Care migration'ları uygulandı. Kod/test kanıt matrisi ve üretim açılış koşulları `tamamlama-ve-kabul-raporu.md` belgesindedir. “Tamamlandı” üretim credential'ı, platform app-review veya hukuk onayı varmış gibi yorumlanmaz; bu dış bağımlılıklar canlıya geçiş kapılarında fail-closed tutulur.

### ZCC-001 — Katalog ve sipariş temelli cevap

Sistem ürün, varyant, stok, fiyat, kampanya, sipariş, kargo, iade ve CRM bağlamını ZOLM'un gerçek kayıtlarından çözmelidir.

Kabul ölçütleri:

- Ürün ID/SKU/barkod veya sipariş bağlantısı doğrulanmadan kesin cevap verilmez.
- Stok, fiyat ve kampanya her cevap üretiminde güncellik kuralına göre yeniden doğrulanır.
- Sipariş verisi yalnız konuşmayla güvenli biçimde eşleşen müşteriye gösterilir.
- “Siparişim nerede?” cevabı gerçek durum, kargo firması ve takip verisine dayanır.
- Tahmini teslimat ile kesin teslimat birbirinden ayrılır; kaynaksız “yarın teslim” sözü verilmez.

### ZCC-002 — Kaynak ve iddia defteri

Her AI taslağı hangi kaynaklara ve araç sonuçlarına dayandığını kaydetmelidir.

Kabul ölçütleri:

- Agent panelinde kaynak adı, türü, sürümü ve güncellik bilgisi görünür.
- Sipariş, kampanya ve ürün iddiaları kullanılan kayıt kimliklerine bağlanır.
- Kaynak güncelliğini kaybederse önceki taslak otomatik gönderilemez.
- Kaynaksız kesin iddia policy motoru tarafından engellenir.
- Müşteriye gösterilecek kaynak özeti ile iç denetim ayrıntısı ayrı tasarlanır.

### ZCC-003 — Güven, risk ve sessiz insan devri

Güven puanı modelin kendi beyanı değil; kaynak, entity eşleşmesi, güncellik, intent ve politika sinyallerinden hesaplanmalıdır.

Kabul ölçütleri:

- Güven eşiği tenant, mağaza, kanal ve intent bazında ayarlanabilir.
- Eksik kaynak, belirsiz ürün/müşteri ve yüksek risk güveni düşürür.
- Eşik altındaki cevap müşteriye gönderilmez.
- Devir nedeni, risk düzeyi, kullanılan kaynaklar ve AI özeti temsilciye aktarılır.
- Müşteri konuşması kesintiye uğramaz; ancak sistem kendisini insan temsilci gibi tanıtmaz.
- Devir sonrası `human_owned` kilidi oluşur; yetkili kullanıcı serbest bırakmadan AI cevap gönderemez.

### ZCC-004 — Üç yanıt modu

Desteklenen modlar:

- `automatic`: Onaylı düşük riskli kapsamda AI gönderir.
- `copilot`: AI taslak ve kaynak hazırlar, insan onaylar.
- `manual`: AI cevap üretmez; yalnız bağlam ve kaynak gösterebilir.

Kabul ölçütleri:

- Varsayılan mod `manual` veya pilotta `copilot` olur; `automatic` varsayılan olamaz.
- Mod tenant, mağaza, kanal, intent ve gerekirse konuşma bazında daraltılabilir.
- Daha dar kapsamlı güvenli ayar daha geniş ayarı geçersiz kılar.
- Mod değişiklikleri kim, ne zaman, neden bilgisiyle audit edilir.
- Human handoff, automation mode'dan bağımsız en yüksek öncelikli kilittir.

### ZCC-005 — Kanal politika motoru

Her kanalın gönderim kabiliyeti ve içerik kısıtları adapter/capability sistemiyle uygulanmalıdır.

Asgari kontroller:

- Maksimum karakter ve mesaj türü
- Telefon/e-posta paylaşımı
- Dış bağlantı
- Haricî ödeme veya kampanya yönlendirmesi
- Yasaklı ifade ve kesin vaat
- Kişisel veri
- İade/para iadesi taahhüdü
- Sağlık ve hukuki iddia
- Mesajlaşma penceresi, template ve consent gereksinimi

Kabul ölçütleri:

- Kural ihlali prompta bırakılmaz; gönderim öncesi deterministik kontrol edilir.
- Kanal kuralları sürümlenir ve değişiklikler audit edilir.
- “Kurallara uygun” durumu yalnız validator seti geçtiğinde gösterilir.
- Platform puanını koruma gibi sonuç garantileri ölçüm olmadan pazarlama iddiası yapılmaz.

### ZCC-006 — İnsan onaylı öğrenme merkezi

Sistem cevaplayamadığı, düşük güvenli veya sık düzenlenen konuşmalardan bilgi önerisi üretmelidir.

Kabul ölçütleri:

- Gece analizi ham konuşmaları tenant sınırı içinde işler.
- Benzer eksik sorular kümelenir; önerinin hangi konuşmalardan geldiği izlenebilir.
- Öneri `draft` oluşturulur; otomatik yayınlanmaz.
- Yetkili kullanıcı `onayla`, `düzenle` veya `reddet` işlemi yapar.
- Onaylanan bilginin geçerlilik tarihi, kapsamı, sahibi ve sürümü bulunur.
- Reddedilen öneri aynı veriyle sürekli yeniden üretilmez.
- Pazaryerinde yayınlanmış ürün soru-cevapları mağaza bazında listelenebilir; yalnız insan onaylı, PII temizlenmiş ve yeniden kullanıma uygun kayıtlar ürün kapsamlı bilgi önerisine dönüştürülebilir.
- Siparişe özel, yüksek riskli veya prompt-injection içeren kayıtlar fail-closed dışarıda bırakılır; hariç tutma ve yeniden inceleme kararı öneri yaşam döngüsüyle birlikte izlenir.

### ZCC-007 — Marka sesi

Firma ve gerekirse mağaza/kanal bazında marka profili bulunmalıdır.

Asgari ayarlar:

- Resmî/samimi ton
- `siz`/`sen` hitabı
- Cevap uzunluğu
- Emoji seviyesi
- Selamlama ve kapanış
- Temsilci/marka imzası
- Yasaklı ve tercih edilen ifadeler
- Şikâyet, satış ve kriz tonu
- Dil bazlı kurallar

Kabul ölçütleri:

- Profil prompta eklenmenin yanında cevap sonrası validator ile kontrol edilir.
- Kanal karakter sınırı marka profiline üstün gelir.
- Marka profili değişikliği eski cevapların kaynak geçmişini bozmaz.
- İnsan tarafından onaylanan iyi örnekler few-shot/retrieval örneği olabilir.

### ZCC-008 — Site canlı destek widget'ı

ZOLM müşterisinin sitesine eklenebilen hafif bir sohbet widget'ı sağlanmalıdır.

Temel yetenekler:

- Tema, logo, asistan adı ve karşılama metni
- Popüler konular ve hazır başlangıç soruları
- Ürün/sipariş bağlamlı sohbet
- Dosya veya görsel ekleme, kanal politikası izin veriyorsa
- İnsan temsilciye aktarım
- Konuşmayı daha sonra sürdürme
- Lead ve iletişim bilgisi toplama
- Kaynak/marka ibaresi ayarı

Kabul ölçütleri:

- Widget tenant ve site domainiyle imzalı şekilde eşleşir.
- CORS, rate limit, bot/spam koruması ve payload sınırı bulunur.
- Telefon/e-posta istemeden önce amaç, aydınlatma ve gerekli izin gösterilir.
- Açık rıza gerektiren pazarlama izni destek talebinden ayrı alınır.
- Hassas bilgi inputta ve loglarda maskelenir.
- İnsan devrinde konuşma bağlamı ve iletişim tercihi korunur.

### ZCC-009 — Birleşik kanal deneyimi

Trendyol, Hepsiburada, N11, WhatsApp, Instagram, Facebook, Google Business, site chat ve desteklenen e-ticaret kanalları tek operasyon yüzeyinde görünmelidir.

Kabul ölçütleri:

- Domain kodu kanal adına göre dağılmış `if/else` yerine capability kullanır.
- Her kanalın source-of-truth kaydı korunur; birleşik görünüm projection olarak uzlaştırılabilir.
- Aynı haricî mesaj retry veya webhook tekrarıyla çoğalmaz.
- Kanal kesintisi mesaj kaybına veya sahte başarıya dönüşmez.
- Kanal sağlığı, son senkronizasyon ve hata durumu agent panelinde görünür.

### ZCC-010 — Satış ve ürün danışmanlığı

AI yalnız destek vermemeli; müşterinin ihtiyacına göre uygun ürünleri bulup açıklayabilmelidir.

Kabul ölçütleri:

- Aday ürünleri LLM uydurmaz; ZOLM ürün sorgu motoru seçer.
- Yalnız satışa açık ve güncel stok/fiyatı doğrulanmış ürünler önerilir.
- Öneri nedeni gerçek özelliklerle açıklanır.
- En fazla yapılandırılabilir sayıda seçenek gösterilir.
- Beden, sağlık, uyumluluk ve yüksek riskli öneriler özel kurallara tabi olur.
- Önerinin satışa katkısı yalnız güvenilir attribution varsa raporlanır.

### ZCC-011 — Kalite ve operasyon analitiği

Gösterilecek temel metrikler:

- İlk yanıt ve çözüm süresi
- Açık, bekleyen ve devredilen konuşmalar
- Copilot kabul/düzenleme/ret oranı
- Düzenleme mesafesi
- Kritik yanlış cevap ve yanlış entity eşleşmesi
- Kaynaksız cevap ve policy engelleme oranı
- Intent bazlı güven kalibrasyonu
- Kanal/API/queue hata oranı
- AI sağlayıcı maliyeti ve latency

Kabul ölçütleri:

- Örnek ekranlardaki `%96`, `%91` gibi oranlar demo sabiti olamaz.
- Metrik tanımı, pay/payda, dönem ve minimum örnek sayısı görünür olmalıdır.
- Düşük örneklemli oranlar güvenilir başarı metriği gibi sunulmaz.

### ZCC-012 — Site asistanı ve lead devri

Kendi tanıtım sitesinde veya müşteri sitesinde çalışan asistan, ürün/destek sorularını cevaplayıp uygun durumda lead oluşturabilmelidir.

Kabul ölçütleri:

- Asistan doğrulanmış şirket/ürün bilgisinden cevap verir.
- Demo veya satış talebi CRM lead'ine idempotent yazılır.
- Telefon numarası tek başına istenmez; amaç ve aydınlatma gösterilir.
- Asistan bilinmeyen müşteri/referans veya ürün iddiası uydurmaz.
- Lead kaynağı, kampanya, izin durumu ve konuşma özeti kaydedilir.

### ZCC-013 — Çözülen iş problemleri ve sonuç ölçümü

Ürün üç temel operasyon yükünü azaltmalıdır:

- Tekrar eden kargo, beden, stok, ölçü ve iade soruları
- Satın alma anında geç yanıt nedeniyle kaybedilen satış fırsatları
- Mesai dışında biriken mesaj ve yorum kuyruğu

Kabul ölçütleri:

- Tekrar eden soru oranı intent bazında ölçülür.
- İlk yanıt süresi mesai içi ve mesai dışı ayrı raporlanır.
- AI tarafından çözülen, copilotla çözülen ve insana devredilen konuşmalar ayrılır.
- Sepet/satış etkisi yalnız güvenilir attribution bağlantısı varsa gösterilir.
- “Ekibiniz uyurken çoğunu yanıtlar” iddiası gerçek after-hours otomasyon verisine dayanır.
- Özel durumlara ayrılan insan süresindeki değişim pilot öncesi/sonrası karşılaştırılır.

### ZCC-014 — Hızlı ve doğrulanabilir onboarding

Standart mağaza bağlantısı adım adım kurulum sihirbazıyla yapılmalıdır.

Önerilen onboarding akışı:

1. Firma, mağaza ve kullanıcı yetkisini doğrula
2. Kanal credential/OAuth bağlantısını kur
3. Capability ve health check çalıştır
4. Katalog, sipariş ve geçmiş soru örneklerini dry-run ile tara
5. Marka sesi ve yasaklı ifadeleri tanımla
6. Düşük/yüksek riskli intent tercihlerini belirle
7. Örnek sorularla test konsolunu çalıştır
8. İlk modu `copilot` veya `shadow` olarak aç

Kabul ölçütleri:

- Kurulum süresi `bağlantı başlangıcı → ilk doğrulanmış taslak` olarak ölçülür.
- Bağlantı başarılı görünmeden gerçek capability ve credential testi yapılır.
- Katalog senkronizasyonu tamamlanmadan ürün otomasyonu açılmaz.
- Karmaşık entegrasyonlar için teknik tanılama paketi ve destek devri bulunur.
- “Dakikalar içinde kurulum” yalnız desteklenen standart kanal ölçümleriyle kanıtlanır.
- Onboarding tekrar çalıştırılabilir ve yarıda kaldığı adımdan güvenli devam edebilir.

### ZCC-015 — CRM, ERP ve iç sistem entegrasyon sınırı

ZOLM mevcut ticari çekirdeğini kullanmalı; haricî CRM, ERP ve özel sistemleri adapter veya sözleşmeli API katmanıyla bağlamalıdır.

Kabul ölçütleri:

- Domain servisleri belirli bir ERP/CRM ürününe doğrudan bağımlı olmaz.
- Haricî entegrasyonlar capability, health check, rate limit ve normalized error sağlar.
- Inbound webhook imza, replay ve idempotency kontrolünden geçer.
- Outbound işlemler queue/outbox üzerinden izlenebilir yapılır.
- Veri sahipliği ve source-of-truth her alan için açıkça tanımlanır.
- Public/custom API erişimi tenant kapsamlı, yetkili, versiyonlu ve auditli olur.
- Entegrasyon kesildiğinde mesaj veya müşteri talebi kaybolmaz; degraded mode görünür.

### ZCC-016 — Veri güvenliği, KVKK ve rol bazlı erişim

Müşteri mesajları, kişisel veriler, credentiallar ve AI bağlamı veri sınıfına göre korunmalıdır.

Kabul ölçütleri:

- Aktarımda TLS zorunludur.
- Credential, token, telefon ve gerekli hassas alanlar uygulama/veritabanı düzeyinde şifrelenir.
- Ham payload ve loglarda gereksiz kişisel veri maskelenir veya hiç tutulmaz.
- Roller en az owner/admin, support manager, support agent, knowledge manager, automation manager, analyst ve read-only ayrımını destekler.
- Kullanıcı yalnız yetkili olduğu firma, mağaza, kanal ve konuşmaları görür.
- Bilgi görüntüleme, export, silme, otomasyon açma ve credential değiştirme audit edilir.
- Retention, veri sahibi talebi, export ve silme prosedürleri uygulanabilir olmalıdır.
- Alt veri işleyenler ve LLM sağlayıcılarına gönderilen alanlar kayıtlı ve minimize edilmiş olmalıdır.
- “KVKK ile tam uyumlu” ifadesi teknik testlere ek olarak hukuk/onay süreci tamamlanmadan kullanılmaz.

### ZCC-017 — Yanlış cevap, geri alma ve düzeltme yaşam döngüsü

AI veya insan tarafından gönderilen hatalı cevabın etkisini sınırlayan kanal bağımsız süreç bulunmalıdır.

Kabul ölçütleri:

- Her outbound mesaj model/prompt, kaynak, policy sonucu ve gönderen aktörle izlenir.
- Kanal edit/delete/retract destekliyorsa capability üzerinden uygulanır.
- Kanal geri almayı desteklemiyorsa düzeltme mesajı, agent görevi ve gerekirse müşteri araması oluşturulur.
- Kritik hata otomasyonu ilgili tenant/kanal/intent için durdurabilir.
- Hata kaydı neden sınıfı, etkilenen iddia, düzeltme ve sonuç alanlarını içerir.
- Düzeltici kural veya bilgi güncellemesi insan onayından geçer.
- “Aynı hatayı bir daha yapmaz” iddiası yerine ilgili regression/golden testinin eklendiği kanıtlanır.

### ZCC-018 — Türkçe öncelikli çok dilli çalışma

Birincil kalite hedefi Türkçedir. Yazım hatası, argo, kısaltma ve Türkçe karakter eksikliği değerlendirme setinde bulunmalıdır.

Kabul ölçütleri:

- Dil otomatik tespit edilir; düşük güvende kullanıcı veya agent doğrulaması istenir.
- Cevap dili müşteri dili, tenant tercihi ve kanal politikasına göre seçilir.
- Kaynaklar farklı dildeyse iddia anlamı korunarak çevrilir; canlı ticari değerler değiştirilmez.
- Marka sesi her desteklenen dil için ayrı örnek ve yasaklı ifade barındırabilir.
- Dil bazında golden dataset, kritik hata ve kaynak doğruluğu ölçülür.
- Test edilmemiş dilde otomatik mod açılmaz; copilot/manual fallback uygulanır.
- “45+ dil” benzeri sayı yalnız her dil için tanımlanan minimum değerlendirme kapısı geçildiyse yayımlanır.

## 4. “Kendi kendine öğrenme” sınırı

İlk sürümde kendi kendine öğrenme, temel model ağırlıklarını otomatik değiştirmek anlamına gelmez.

İzin verilen öğrenme:

- Onaylı cevapları retrieval örneği yapmak
- İnsan düzenlemelerini kalite verisi olarak saklamak
- Eksik bilgi önerisi oluşturmak
- Intent/risk kalibrasyonu için etiketli veri üretmek
- Golden dataset'i insan onayıyla genişletmek

Yasaklanan öğrenme:

- Tenant A verisini Tenant B için kullanmak
- Ham kişisel veriyi eğitim setine almak
- İnsan onayı olmadan bilgi yayınlamak
- Üretimde otomatik fine-tune/deploy yapmak
- Model cevabını gerçek kabul edip kendi bilgi tabanına geri yazmak

## 5. MVP sınırı

İlk ticari MVP aşağıdakilerle sınırlıdır:

1. Trendyol ürün soruları
2. Birleşik inbox ve manuel operasyon
3. Firma/mağaza bilgi merkezi
4. Canlı ürün, stok ve sipariş bağlam araçları
5. Kaynaklı AI copilot taslağı
6. İnsan onayı, düzenleme ve devir
7. Marka sesi
8. Kanal politika kontrolü
9. Kalite ölçümü ve shadow pilot

WhatsApp, web widget, diğer pazaryerleri ve sosyal kanallar bu çekirdek doğrulandıktan sonra sırayla açılır.

## 6. Pazarlama iddiası yayınlama kapısı

Aşağıdaki ifadeler ancak üretim verisiyle kanıtlandıktan sonra kullanılabilir:

- “Asla uydurmaz”
- “Tam otomatik uyum”
- “Mağaza puanınız risk altına girmez”
- “Satışları artırır”
- “İade oranını düşürür”
- “%X doğruluk/çözüm oranı”
- “Tamamen insan gibi”

Başlangıçta daha doğru ifade:

> ZOLM doğrulanmış kaynakları kullanır, düşük güvenli veya yüksek riskli cevapları insan onayına aktarır ve bütün kararları ölçülebilir şekilde kaydeder.
