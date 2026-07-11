# ZOLM ERP & Ön Muhasebe — Pilot Risk Sicili (Pilot Risk Register)

Bu doküman, ZOLM Ön Muhasebe / ERP modülünün pilot kullanıma sunulması öncesinde belirlenen teknik ve operasyonel riskleri, bunların olası etkilerini, olasılık derecelerini, mitigasyon (azaltma) planlarını ve pilot kararlarını içerir.

---

## 1. Risk: Gerçek e-Fatura / e-Arşiv Entegratörünün Bulunmaması (MVP Limitasyonu)

- **Açıklama:** Sistemde gerçek bir özel entegratör (Uyumsoft, QNB vb.) veya GİB portal entegrasyonu bulunmamaktadır. Akış simüle edilmektedir.
- **Olasılık:** Yüksek (%80)
- **Etki:** Kritik (Kullanıcı yasal e-fatura kesemez).
- **Mitigasyon Planı:** Pilot kullanıcılara bu modülün yalnızca "Demo/Simülasyon" amacıyla açık olduğu, yasal bir geçerliliği bulunmadığı arayüz üzerinden net uyarılar ve yönlendirmelerle gösterilmelidir.
- **Pilot Kararı:** Bu modül MVP (Minimum Viable Product) kabul kriteriyle, bilgilendirme uyarısı eklenerek pilot aşamasında aktif bırakılacaktır.

---

## 2. Risk: POS Donanım ve Ödeme Cihazı Entegrasyonunun Bulunmaması

- **Açıklama:** Fiş yazıcı, barkod okuyucu ve temassız ödeme terminali gibi donanımsal entegrasyonlar mevcut değildir.
- **Olasılık:** Yüksek (%90)
- **Etki:** Orta (Kasiyer manuel işlem yapmak zorunda kalır).
- **Mitigasyon Planı:** POS ekranının şimdilik sadece web tarayıcı üzerinden manuel hızlı satış yapabilen bir "Web POS" olarak lanse edilmesi.
- **Pilot Kararı:** MVP kapsamında donanımsız web satışı olarak kabul edilmiştir; donanım entegrasyonu sonraki fazlara ertelenmiştir.

---

## 3. Risk: MarketplaceReportDigestTest Known Issue (Test Failures)

- **Açıklama:** Pazaryeri modülünün bir digest mail testi (`MarketplaceReportDigestTest`) harici MySQL bağlantısı zorladığı için in-memory test koşumlarında başarısız olabilmektedir.
- **Olasılık:** Yüksek (%90)
- **Etki:** Düşük (ERP ve ön muhasebe fonksiyonlarını doğrudan etkilemez, sadece CI/CD pipelines süreçlerinde kafa karıştırır).
- **Mitigasyon Planı:** Release checklist dokümanında bu durum "Known Issue" olarak belgelenmiştir ve test koşumlarında göz ardı edilebilir.
- **Pilot Kararı:** Canlı yayına alımı (release) engellemeyecektir.

---

## 4. Risk: Gerçek Kullanıcı Veritabanında Demo Seed Çalıştırılması

- **Açıklama:** `accounting:seed-demo` veya `accounting:seed-demo --reset` komutunun canlı veritabanında çalıştırılması sonucu veri kirliliği veya risk oluşması.
- **Olasılık:** Düşük (%15)
- **Etki:** Yüksek (Gerçek ve demo verilerin birbirine karışması).
- **Mitigasyon Planı:** Command sınıfı içine `app()->environment('production')` kontrolü eklenmiş olup, production ortamında `--force` parametresi olmadan çalışması engellenmiştir. Ayrıca reset işlemi sadece demo marker'lı kayıtları silebilecek şekilde izole edilmiştir.
- **Pilot Kararı:** Güvenlik önlemleri (P14 ve P17 fix) tamamlandığından pilot için risk minimize edilmiştir.

---

## 5. Risk: Feature Flag'in Yanlışlıkla Tüm Kullanıcılara Aktif Edilmesi

- **Açıklama:** `ACCOUNTING_ENABLED` feature flag'inin config veya .env üzerinden kontrolsüz şekilde tüm üyelere açılması.
- **Olasılık:** Orta (%30)
- **Etki:** Yüksek (Korumasız veya hazır olmayan ekranların tüm tenant'lar tarafından görülmesi).
- **Mitigasyon Planı:** Route ve menü yetkilendirmeleri sıkı şekilde `roleSlug() === 'admin'` kontrolü ile bağlanmıştır. Admin olmayanlar flag açık olsa dahi ekranları göremez.
- **Pilot Kararı:** Sadece kontrollü pilot admin kullanıcılara açılacaktır.

---

## 6. Risk: Rol ve Yetki Matrisinin Sınırlı Olması (Admin Ağırlıklı Yapı)

- **Açıklama:** Kasiyer, Muhasebe Elemanı veya Satış Temsilcisi gibi ara rollerin detaylı yetki sınırlandırması henüz yapılmamıştır. Tüm ERP ekranları sadece `admin` rolüne açıktır.
- **Olasılık:** Yüksek (%100)
- **Etki:** Orta (Büyük ölçekli tenant'lar için rol dağılımı yapılamaz).
- **Mitigasyon Planı:** Pilot aşamasında sadece tek kullanıcılı/küçük işletme sahibi olan admin profilli pilot kullanıcıların seçilmesi.
- **Pilot Kararı:** Ara yetki rolleri sonraki fazın (P18-P19) öncelikli işidir; ilk pilot için mevcut durum yeterlidir.

---

## 7. Risk: Browser / Viewport Farklılıklarından Kaynaklanan Küçük UI Notları

- **Açıklama:** Safari, Firefox veya eski mobil cihazlarda CSS Flex/Grid veya touch target boyutlarının ufak kaymalara sebep olması.
- **Olasılık:** Orta (%40)
- **Etki:** Düşük (Kozmetik sorunlar).
- **Mitigasyon Planı:** Arayüzün ZOLM Kurumsal Açık Panel kılavuzuna göre test edilmesi ve kritik responsive kontrollerinin yapılması.
- **Pilot Kararı:** Kabul kriterlerini engellemeyen küçük UI kusurları sonraki görsel cila aşamalarında düzeltilecektir.
