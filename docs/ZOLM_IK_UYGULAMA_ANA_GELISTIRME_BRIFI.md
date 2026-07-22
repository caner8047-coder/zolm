# ZOLM İK — Uygulama Ana Geliştirme Brifi

**Sürüm:** 1.0  
**Tarih:** 21 Temmuz 2026  
**Amaç:** ZOLM İK ürün ailesini, modüler monolit mimarisi üzerinde güvenli ve aşamalı biçimde geliştirmek. Bu belge geliştirme ajanı için kapsam, sınır, kabul kriteri ve checkpoint sözleşmesidir.

## 1. Ürün kararı

ZOLM İK; Personel, Bordro, Performans, Aday Takip, Vardiya, Ücret, İK Analitiği, PDKS ve İzin Yönetimi özelliklerinin tamamını hedefler. Farkı, bu süreçleri ZOLM'un üretim, stok, sipariş, CRM ve ön muhasebe verileriyle kontrollü iş akışlarında birleştirmesidir.

Bu bir “tek seferde büyük modül” işi değildir. Her sürüm, tek başına güvenle kullanılabilir bir ürün yüzeyi ve geri alınabilir bir Git checkpoint'i olmalıdır.

## 2. Mevcut doğrulanmış durum

| Checkpoint | Durum | İçerik |
|---|---|---|
| `hr-phase-0` | Tamam | Tenant context, lisans, yetki, audit, private dosya altyapısı |
| `hr-phase-1a` / `hr-phase-1a.1` | Tamam | Organizasyon, çalışan çekirdeği, birim/ekip, fotoğraf ve test kapanışı |
| `hr-phase-1b` | Tamam | Belge yönetimi çekirdeği |
| `hr-phase-1b.1` | Tamam | Belge event/job/listener, dashboard, export, profil belgeleri ve güvenlik kapanışı |

Sıradaki uygulama fazı **Faz 1C: İzin Yönetimi**'dir. Faz 1C tamamlanmadan Vardiya, PDKS, Puantaj veya Bordro kodu yazılmaz.

## 3. Değişmez teknik ve güvenlik kuralları

- Stack: Laravel, Livewire, MySQL; mevcut `User`, `LegalEntity`, `ActivityLog`, bildirim ve Excel servisleri yeniden kullanılır.
- Her HR kaydı tenant (`legal_entity_id`) kapsamındadır. Route binding, policy, query scope, cache key, dosya yolu ve job context katmanlarında tenant izolasyonu zorunludur.
- `User`, `HrEmployee` ve çalışma ilişkisi birbirinden ayrıdır. Bir kişi farklı zamanlarda veya tüzel kişiliklerde birden fazla employment kaydına sahip olabilir.
- Modüller birbirlerinin tablolarına doğrudan iş kuralı yazmaz; onaylanmış domain event ve küçük listener'lar kullanılır.
- Hassas veri varsayılan olarak görünmez. Ücret, sağlık, kimlik, banka ve disiplin verileri ayrı izin gruplarıyla korunur; audit log açık hassas veri içermez.
- Yeni export'larda `ExcelService::cleanString()` yaklaşımı, `setCellValueExplicit()`, UTF-8/XML kontrolü ve CSV/XLSX formula injection koruması zorunludur.
- Yeni ekranlar ZOLM Kurumsal Açık Panel Sistemiyle yapılır: açık zemin, beyaz workspace kartı, `border-slate-200`, command bar ve ledger/tablo aynı ana section içinde; mobilde kart görünümü bulunur.
- Yeni tablo: görünür kolonlar, sıralama, resize, arama/filtre, mobil kart görünümü ve loading/empty/error durumlarını kapsar.
- Migration, işlev, yetki ve export için test olmadan checkpoint oluşturulmaz. Normal geliştirme veya üretim veritabanında `migrate:fresh` çalıştırılmaz.

## 4. Nihai uygulama ailesi ve sürümleme

| Sürüm | Uygulamalar | Çıkış ölçütü |
|---|---|---|
| 1 | Personel, Organizasyon, Belge, İzin, çalışan yüzeyi | Güvenli çalışan kaydı, belge ve izin yaşam döngüsü |
| 2 | Vardiya, PDKS, Puantaj, Fazla Mesai | Planlanan ve gerçekleşen çalışma tek günlük kayıtta birleşir |
| 3 | Bordro hazırlık, Masraf, Avans, Zimmet | Onaylı operasyon girdileri bordroya/muhasebeye izlenebilir aktarılır |
| 4 | Performans, Eğitim, Bağlılık | Yetkinlik ve gelişim verisi çalışan profiline bağlanır |
| 5 | Aday Takip, Onboarding, Offboarding | Adaydan çalışanlığa ve çıkışa kontrollü yaşam döngüsü |
| 6 | Ücret, Kadro Planlama, Analitik, İSG, Destek | Maliyet, kapasite, uyum ve destek tek ürün yüzeyinde |
| 7 | Tam Bordro ve resmî çıktılar | Hukuk/mali müşavir tarafından doğrulanmış kural sürümlü hesaplama |
| 8 | PWA, asistan, ileri entegrasyonlar | Mobil self-service ve insan onaylı öneri sistemi |

Tam bordro ve resmî bildirge üretimi için mevzuat parametreleri, mali müşavir doğrulaması ve sürümlenmiş kural seti zorunludur; tahmine dayalı hesaplama üretime alınmaz.

## 5. Ortak HR temel servisleri

Her yeni modül önce aşağıdaki bağımlılıkları tüketir; aynı işi yapan ikinci servis oluşturmaz.

| Servis | Sorumluluk |
|---|---|
| Tenant / Legal Entity Context | İstek, job ve cache kapsamı |
| HR Permission & Policy | Kapsam: own, direct_reports, department, legal_entity, all |
| Approval Engine | Sıralı/paralel adım, vekâlet, SLA, eskalasyon, revizyon |
| Document & Template Service | Private dosya, sürüm, indirme audit'i, şablon değişkenleri |
| Audit & Notification | Maskeleme, idempotent kayıt, job üzerinden dış bildirim |
| Calendar & Rule Engine | Tatil, çalışma günü, kıdem, uygunluk ve çakışma kuralları |
| Reporting / Import / Export | Tenant güvenli rapor, import hata toleransı, Excel güvenliği |
| HR Integration Gateway | Üretim, stok, CRM, finans ve harici cihaz bağlantılarında kontrollü sınır |

## 6. Faz 1C — İzin Yönetimi: uygulama sözleşmesi

### 6.1 Kapsam

- İzin türü, atama kuralı, yıllık/hareket bazlı bakiye, devir ve düzeltme.
- Tam gün, yarım gün ve saatlik izin; ücretli/ücretsiz ayrımı; belge zorunluluğu.
- Çalışan talebi, yönetici/İK onayı, iptal ve geri alma.
- Takım/şirket takvimi, tatil ve hafta sonu kuralı; izin çakışması ve bakiye kontrolü.
- Onaylanan izin için audit, bildirim ve ileride Vardiya/Puantaj'ın dinleyeceği `LeaveApproved` olayı.
- İlk sürümde çalışan portalı için yalnızca kendi izin talebi/listeleme yüzeyi; geniş portal veya PDKS eklenmez.

### 6.2 Faz dışı

- Vardiya planı, QR/turnike, puantaj kapanışı, fazla mesai, bordro hesaplama.
- Genel amaçlı satın alma/masraf onay motorunun tüm entegrasyonları.
- Bordro hukuk kuralı veya otomatik mali karar.

### 6.3 Veri modeli

| Tablo / aggregate | Zorunlu alanlar ve kurallar |
|---|---|
| `hr_leave_types` | tenant, code, ad, ücret durumu, birim (gün/saat), belge gereksinimi, aktiflik |
| `hr_leave_policies` | tenant, tür, kapsam (şirket/şube/departman/pozisyon/istihdam tipi), kıdem/period kuralı, devir/eksi bakiye kuralı, geçerlilik tarihleri |
| `hr_leave_balances` veya ledger | employee + leave type + period; bakiye elle güncellenmez, hareketlerden türetilir |
| `hr_leave_transactions` | accrual, carryover, adjustment, usage, cancellation; source anahtarıyla idempotent |
| `hr_leave_requests` | employee, type, başlangıç/bitiş/saat, working duration, durum, vekil, gerekçe, belge referansı, iptal/revizyon zinciri |
| `hr_leave_approval_steps` | talep, sıra/parallel grup, atanmış onaylayıcı/rol, karar, yorum, karar zamanı |
| `hr_holidays` | tenant veya ortak takvim, tarih/aralık, çalışma günü etkisi |

Tüm foreign key'ler tenant tutarlılığıyla action/service katmanında doğrulanır. Onaylı talep değiştirilemez; iptal veya revizyon yeni hareket ve audit izi üretir.

### 6.4 Ekranlar, tablolar ve yetki

| Ekran | Temel kontroller | Yetki | Kabul kriteri |
|---|---|---|---|
| `/hr/leaves` İzin listesi | tarih, tür, durum, çalışan, ekip filtreleri; kolon seçimi; toplu yalnızca yetkili aksiyon | `hr.leaves.view` | Tenant dışı kayıt hiçbir filtre/URL ile görünmez; mobil kart vardır |
| `/hr/leaves/requests/create` | tür, tarih/saat, gerekçe, vekil, belge; canlı süre/bakiye özeti | `hr.leaves.request` | Çakışma, yetersiz bakiye ve zorunlu belge engellenir |
| `/hr/leaves/{request}` | timeline, kararlar, audit özeti, iptal/revizyon | scope + ilgili izin | Çalışan yalnızca kendi talebini, yönetici yalnızca yetki kapsamını görür |
| `/hr/leaves/approvals` | bekleyen adımlar, tekli/toplu onay, ret/revizyon yorumu | `hr.leaves.approve` | Kendi kendini onaylama ve atlanan sıra engellenir |
| `/hr/leaves/balances` | dönem, tür, çalışan/ekip; hareket ledger'ı; export | `hr.leaves.manage_balances` | Toplam, yalnızca hareketlerin deterministik sonucudur |
| `/hr/settings/leave-types` | liste, oluşturma, düzenleme, pasife alma | `hr.leaves.manage_types` | Kullanılan tür silinmez; pasife alınır |
| `/hr/settings/leave-policies` | kapsam/rule builder, öncelik görünümü, çakışma uyarısı | `hr.leaves.manage_policies` | En spesifik aktif kural seçilir; eşit öncelikli çakışma kaydedilemez |
| Çalışan profilindeki İzinler sekmesi | bakiye, yaklaşan/geçmiş talepler, işlem linkleri | profile scope + leave izinleri | Belge sekmesiyle aynı responsive ve audit standardı uygulanır |

Gerekli minimum izinler: `hr.leaves.view`, `request`, `approve`, `cancel_own`, `cancel_any`, `manage_types`, `manage_policies`, `manage_balances`, `adjust_balance`, `export`, `view_team_calendar`.

### 6.5 Onay ve iş akışı kuralları

1. Talep oluşturulunca uygun politika ve onay akışı sürümlü snapshot olarak talebe bağlanır.
2. Onay sırası: doğrudan yönetici, gerekirse departman yöneticisi ve İK. Akış tanımı ileride ortak motorla genelleştirilecek, fakat Faz 1C bu üç adımı güvenli biçimde yönetmelidir.
3. Vekâlet, talep tarihindeki etkin ilişkiyle değerlendirilir; yoksa adım bekler veya eskalasyon kuralına göre İK'ya gider.
4. Onayda `LeaveApproved`, rette `LeaveRejected`, iptal/geri almada uygun ayrı event yayınlanır. Eventler commit sonrası yayınlanır.
5. Aynı talep için çift kullanım hareketi, çift bildirim veya çift onay üretilemez.

### 6.6 Faz 1C test matrisi

- Tenant izolasyonu: ekran, route binding, policy, export, job ve cache key.
- Bakiye ledger: hak ediş, kullanım, iptal, devir, düzeltme, aynı source key tekrar denemesi.
- Süre hesabı: hafta sonu, tatil, yarım gün, saatlik izin, tenant takvimi.
- Kural çözümü: şirket/şube/departman/pozisyon/istihdam tipi önceliği ve çakışma reddi.
- Onay: sıraya uyma, paralel grup, yetkisiz karar, vekâlet, revizyon, ret nedeni.
- UI: loading, empty, validation, unauthorized, mobil kart, gerçek sayaç ve filtreli linkler.
- Export: tenant/policy kapsamı, hassas alan dışlama, formula injection.
- Migration rollback: Faz 0–1B.1 tabloları korunurken Faz 1C tabloları geri alınır.

Faz ancak hedefli testler, `php artisan test --filter=Hr`, `git diff --check` ve izole veritabanı migration rollback kontrolleri geçince `hr-phase-1c` etiketiyle kapanır.

## 7. Sonraki sürümlerin ürün kabul çerçevesi

| Modül | Ekran omurgası | Kritik kabul kriteri |
|---|---|---|
| Vardiya / İş Gücü | plan takvimi, açık vardiya, yetkinlik/kapasite uyarıları | İzinli veya sertifikasız çalışan kritik işe atanamaz |
| PDKS / Puantaj | olay listesi, uyuşmazlık kuyruğu, günlük/aylık ledger | Onaylı dönem değiştirilemez; düzeltme revizyon üretir |
| Bordro | dönem kontrol merkezi, hesaplama izi, çıktı merkezi | Her rakam kural sürümü ve kaynak hareketle açıklanabilir |
| Ücret / Yan Hak | ücret geçmişi, teklif/simülasyon, bütçe görünümü | Ücret erişimi ayrı izin olmadan görünmez |
| Performans | döngü, form, hedef/OKR, kalibrasyon | Operasyon verileri karar desteğidir, otomatik cezalandırma değildir |
| Aday Takip | kadro talebi, ilan, Kanban, aday profili | AI aday özetler; otomatik ret vermez |
| Eğitim / Yetkinlik | katalog, oturum, sertifika ve matris | Kritik operasyona sertifikasız atama engellenir |
| On/Offboarding | checklist, sahiplik, 7/30/60/90 zaman çizgisi | Çıkışta zimmet, erişim ve açık görev kontrolleri tamamlanır |
| Masraf / Avans / Zimmet | talep, onay, ödeme/teslim ledger'ı | Finans/stock kaynağı ve bordro etkisi izlenebilirdir |
| Analitik / Kadro | KPI dashboard, dönem filtreleri, senaryo | Kaynaklar tanımlı; tenant dışı kıyas/veri sızıntısı yoktur |
| İSG / Destek / Şablon | uyum register'ı, ticket, şablon merkezi | Sağlık/hassas belge erişimi ayrı policy ile sınırlandırılır |
| PWA / Asistan | self-service mobil yüzey, öneri sonuçları | Asistan ücret, bordro, işe alım veya disiplin işlemini kendi başına yapamaz |

## 8. ZOLM'a özgü entegrasyon sınırları

- İzin onayı: Vardiya ve Puantaj modülleri yayımlanan olayı dinler; Faz 1C doğrudan onların tablolarına yazmaz.
- Üretim talep yoğunluğu: ileride iş gücü planlama servisine veri sağlar; izin kararını otomatik değiştirmez.
- CRM satışları: sadece onaylı komisyon kuralı için bordro girdisi üretir.
- Stok/demirbaş: zimmet modülü referans verir; stok hareketinin sahibi mevcut stok modülüdür.
- Finans: masraf/avans/bordro onaylandıktan sonra, idempotent kaynak anahtarıyla muhasebe köprüsüne aktarılır.

## 9. Tasarım ve kullanıcı deneyimi sözleşmesi

Venture CRM referansının ürünleşmiş command bar, yoğun veri yüzeyi ve tool-rail hiyerarşisi incelendi. ZOLM uygulaması koyu tema veya marka kopyası değildir: açık zeminli, kurumsal panel dili uygulanır.

- Üstte özet/workspace kartı; guidance alanı gerekiyorsa ayrı kompakt accordion.
- Filtre, aktif filtre bilgisi, kolon araçları ve tablo tek ana section kartında.
- Desktop'ta yardımcı özet paneli; mobilde command bar ile birleşen kontrol yüzeyi.
- `flex flex-col sm:flex-row`, `grid-cols-1 sm:grid-cols-2 xl:grid-cols-3`, 44px dokunma hedefli butonlar ve `text-base sm:text-sm` input kuralı zorunludur.

## 10. Geliştirme ajanı çalışma talimatı

1. Bir faz başlamadan önce mevcut checkpoint, ilgili modeller/routes/tests ve bu brif kontrol edilir.
2. Faz dışı kod, migration veya “sonra lazım olur” altyapısı eklenmez.
3. Her batch sonunda PHP syntax, hedef testler ve tenant güvenlik testleri çalıştırılır.
4. Kullanıcıya ait untracked dosyalar stage edilmez; `git add .`, reset, clean veya force push kullanılmaz.
5. Faz kapanışında yalnızca doğrulanmış kapsam commit edilir; tag mevcut checkpoint'leri değiştirmez.
6. Bir kabul kriteri eksikse yeni faza geçilmez; eksikler `phase-x.y` kapanış planı olarak raporlanır.

## 11. Şimdi yapılacak iş

**Yalnızca Faz 1C keşif ve uygulama planı hazırlanacaktır.** Kodlamaya geçmeden önce mevcut HR çekirdeğiyle ilişki, izin veri modeli, permission matrisi, route listesi, migration sırası, ekran wireframe'i ve hedef test sınıfları ayrı bir Faz 1C teknik planında kilitlenmelidir.

