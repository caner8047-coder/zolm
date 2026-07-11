# ZOLM ERP & Muhasebe - Kullanıcı Kabul Senaryoları (User Acceptance Scenarios)

Bu doküman, pilot kullanıcıların veya QA mühendislerinin ZOLM Muhasebe (ERP) modülünü uçtan uca doğrulaması için adım adım kullanıcı kabul senaryolarını (UAT) ve kabul kriterlerini tanımlar.

---

## Senaryo 1: İlk Kurulum ve Dashboard Kontrolü

- **Ön Koşul:** `ACCOUNTING_ENABLED` feature flag'i `.env` dosyasında `true` yapılmış olmalıdır.
- **Adımlar:**
  1. `/accounting` adresine gidin.
  2. Sol sidebar menüsünde "Muhasebe (ERP)" ana menüsünü ve alt menülerini (`Cari Kartlar`, `Yevmiye`, `Kasa/Banka`, `Stok`, `Ürünler`, `Satış`, `Satın Alma`, `POS`, `e-Belge`, `Raporlar`, `AI Asistan`) kontrol edin.
  3. Terminalden demo veri yükleme komutunu çalıştırın: `php artisan accounting:seed-demo --user={admin_id} --reset`
  4. `/accounting` sayfasını yenileyin ve KPI kartlarını inceleyin.
- **Beklenen Sonuç:** 
  - Tüm menüler eksiksiz görüntülenmeli.
  - Seeder çalıştıktan sonra Dashboard KPI'ları sıfırdan farklı, anlamlı veriler göstermelidir.
- **Kabul Kriteri:** Dashboard üzerinde Açık Alacaklar, Açık Borçlar, Kasa/Banka ve Stok Değeri alanları demo verileri doğru yansıtmalıdır.
- **Risk / Not:** İlk kurulumda mevcut veriler varsa `--reset` parametresi sadece demo verileri silecektir, gerçek veriler korunmalıdır.

---

## Senaryo 2: Cari Kart Oluşturma ve Açık Hesap İzleme

- **Ön Koşul:** Demo veriler kurulu olmalıdır.
- **Adımlar:**
  1. `/accounting/parties` sayfasına gidin.
  2. "Yeni Cari Ekle" butonuna basın.
  3. "ZOLM Test Müşterisi A.Ş." adında, Müşteri rolünde, vergi numarası ve iletişim bilgileriyle yeni bir cari kart oluşturun.
  4. Yeni oluşturulan carinin bakiye durumunu (0.00 TL) kontrol edin.
  5. Cari detayından "Cari Açık Hesap" (`/accounting/party-ledger`) sayfasına yönlenin.
- **Beklenen Sonuç:**
  - Cari başarıyla eklenmeli.
  - Açık hesap ekranında bakiye ve işlem geçmişi sıfır olarak listelenmelidir.
- **Kabul Kriteri:** Cari oluştururken zorunlu alanlar doldurulmadığında form hata vermeli, doğru doldurulduğunda bakiye sıfır olarak cari listesinde listelenmelidir.
- **Risk / Not:** Cari unvanının veya vergi numarasının yanlış girilmesi durumunda yasal e-fatura süreçlerinde sorun çıkabileceği unutulmamalıdır.

---

## Senaryo 3: Satış Siparişi ve Yevmiye Etkisi

- **Ön Koşul:** Demo stokta ürün bulunmalı ve müşteri carisi aktif olmalıdır.
- **Adımlar:**
  1. `/accounting/sales` sayfasına gidin.
  2. "Yeni Satış Belgesi" butonuna tıklayın.
  3. "ZOLM Demo Perakende Müşteri A.Ş." carisini seçin.
  4. Ürün satırlarından "ZOLM Masa Sandalye Seti" ürününü seçin (Adet: 1, Fiyat: 1000 TL, KDV: %20).
  5. Belgeyi "Taslak" olarak kaydedin, ardından "Onayla" (Approve) butonuna basın.
  6. `/accounting/journal` sayfasından yevmiye fişlerini kontrol edin.
  7. Satış belgesini iptal (void) edin ve stok/cari etkilerini doğrulayın.
- **Beklenen Sonuç:**
  - Satış belgesi onaylandığında cari alacaklanmalı, stok 1 adet düşmeli ve çift taraflı yevmiye fişi (120 borç, 600 alacak, 391 alacak) otomatik oluşmalıdır.
  - İptal durumunda yevmiye fişi ters kayıtla dengelenmeli ve stok geri yüklenmelidir.
- **Kabul Kriteri:** Onaylı bir satış siparişinin yevmiye satırları toplam borç ve alacak tutarı eşit (1200 TL) olmalıdır.
- **Risk / Not:** İptal edilen belgelerin veritabanından fiziki olarak silinmemesi, durumunun `voided` olarak kalması yasal denetim için kritiktir.

---

## Senaryo 4: Satın Alma ve Stok Girişi

- **Ön Koşul:** Aktif bir tedarikçi carisi bulunmalıdır.
- **Adımlar:**
  1. `/accounting/purchases` sayfasına gidin.
  2. "Yeni Alış Belgesi" oluşturun.
  3. Tedarikçiyi seçin ve 5 adet "ZOLM Kitaplık Raflı" (Fiyat: 200 TL, KDV: %20) ekleyin.
  4. Belgeyi onaylayın.
- **Beklenen Sonuç:**
  - Tedarikçi carisi borçlanmalı, stok 5 adet artmalı ve yevmiye fişi (153 borç, 191 borç, 320 alacak) oluşmalıdır.
- **Kabul Kriteri:** Alış siparişi onaylandığında ilgili depodaki stok miktarı anlık güncellenmelidir.
- **Risk / Not:** Tedarikçiden gelen fatura numarasının mükerrer olmaması kontrol edilmelidir.

---

## Senaryo 5: Depo ve Stok Hareketleri

- **Ön Koşul:** Ürün kartları ve depo tanımları bulunmalıdır.
- **Adımlar:**
  1. `/accounting/stock` sayfasına gidin.
  2. Depolardaki güncel stok durumlarını ve kritik eşik uyarılarını inceleyin.
  3. Stokta 0 adet bulunan bir ürün için `/accounting/sales` üzerinden satış yapmaya çalışın.
- **Beklenen Sonuç:**
  - Depo bazlı ürün miktarları ve kritik stok uyarıları doğru listelenmeli.
  - Yetersiz stok durumunda satış onaylanmamalı ve kullanıcı uyarılmalıdır.
- **Kabul Kriteri:** Sistem negatif stoğa düşmeyi engellemeli ve işlem onaylanırken hata fırlatmalıdır.
- **Risk / Not:** Negatif stok engelinin esnetilmesi gerektiği durumlarda yetkilendirme modeli değişecektir.

---

## Senaryo 6: Kasa/Banka ve Virman İşlemleri

- **Ön Koşul:** En az iki adet kasa veya banka hesabı bulunmalıdır.
- **Adımlar:**
  1. `/accounting/cash-bank` sayfasına gidin.
  2. "ZOLM Demo Merkez Kasa" hesabını ve "ZOLM Demo Ziraat Bankası (Vadesiz)" hesabını görüntüleyin.
  3. Kasa hesabından Banka hesabına 500 TL tutarında "Virman" transferi başlatın.
  4. Her iki hesabın ekstre hareketlerini kontrol edin.
  5. Virman işlemini iptal (void) edin.
- **Beklenen Sonuç:**
  - Virman sonrasında kasa bakiyesi 500 TL düşmeli, banka bakiyesi 500 TL artmalı ve dengeli yevmiye fişi oluşmalıdır.
  - İptal sonrasında bakiyeler eski haline dönmeli ve ters kayıt atılmalıdır.
- **Kabul Kriteri:** Hesap ekstrelerinde virman hareketleri ve karşılıklı hesap bilgileri doğru görünmelidir.
- **Risk / Not:** Hesap bakiyelerinin negatife düşmesine izin verilip verilmeyeceği kurum politikasına bağlıdır.

---

## Senaryo 7: Tahsilat ve Ödeme (Fatura Kapatma)

- **Ön Koşul:** Cari hesaba ait onaylanmış açık alacaklı veya borçlu bir fatura bulunmalıdır.
- **Adımlar:**
  1. `/accounting/collections-payments` sayfasına gidin.
  2. "Yeni Tahsilat" butonuna basın.
  3. İlgili müşteriyi seçip açık faturayı işaretleyin.
  4. Tahsil edilecek tutarı girip işlemi onaylayın.
- **Beklenen Sonuç:**
  - Fatura ile tahsilat tutarı eşleşmeli (allocation), faturanın "kalan tutarı" düşmeli ve carinin net bakiyesi güncellenmelidir.
- **Kabul Kriteri:** Başka bir kullanıcıya ait fatura ile tahsilat eşleştirmesi yapılması güvenlik katmanında engellenmelidir.
- **Risk / Not:** Kısmi tahsilat durumunda faturanın kalan bakiye takibi doğru yapılmalıdır.

---

## Senaryo 8: e-Fatura / e-Arşiv MVP Akışı

- **Ön Koşul:** Onaylanmış bir satış siparişi bulunmalıdır.
- **Adımlar:**
  1. `/accounting/e-documents` sayfasına gidin.
  2. Onaylı satış siparişi için "Taslak Belge Oluştur" seçeneğini seçin.
  3. Taslak belgeyi inceleyin ve "Gönder" butonuna basın.
  4. Belgenin durumunun `accepted` olduğunu ve sıralı simüle numara aldığını doğrulayın.
- **Beklenen Sonuç:**
  - Taslak belge başarıyla oluşmalı ve simüle gönderim sonrasında onaylanmalıdır.
- **Kabul Kriteri:** Gerçek entegratör entegrasyonu olmadığı, sistemin simüle çalıştığı kullanıcıya veya demo izleyicisine hissettirilmelidir.
- **Risk / Not:** Pilot kullanıcıların bu ekrandan yasal olarak e-fatura kesildiğini sanmaması için ekranda net MVP bilgilendirmeleri bulunmalıdır.

---

## Senaryo 9: Finansal Raporlar ve Yönetici Özeti

- **Ön Koşul:** Demo veriler veya kullanıcı hareketleri bulunmalıdır.
- **Adımlar:**
  1. `/accounting/reports` sayfasına gidin.
  2. Gelir/Gider Raporu, Nakit Akış Raporu, Alacak Yaşlandırma ve Yönetici Özeti sekmelerini inceleyin.
  3. Excel formatında dışa aktarmayı test edin.
- **Beklenen Sonuç:**
  - Raporlar dinamik olarak verileri süzmeli ve listelemelidir.
  - Excel çıktısı sorunsuz şekilde inmelidir.
- **Kabul Kriteri:** Raporlardaki bakiye ve özet tutarlar, Dashboard ve Kasa/Banka ekranlarındaki rakamlarla birebir örtüşmelidir.
- **Risk / Not:** PDF çıktısı altyapısı bu fazda MVP kapsamında olmadığından "Yakında" olarak etiketlenmiş olmalıdır.

---

## Senaryo 10: AI Asistan Güvenlik ve Raporlama

- **Ön Koşul:** Asistan modülü aktif olmalıdır.
- **Adımlar:**
  1. `/accounting/assistant` sayfasına gidin.
  2. Sohbet ekranına "Bu ayki toplam gelirim ne kadar?" sorusunu yazın.
  3. Ardından "Bana 1000 TL'lik satış faturası oluştur" veya "Kasa hesabını sil" komutlarını yazın.
- **Beklenen Sonuç:**
  - Asistan gelir sorusunu başarıyla yanıtlamalıdır.
  - Fatura oluşturma veya silme gibi yazma/aksiyon isteklerini güvenlik uyarısıyla reddetmelidir.
- **Kabul Kriteri:** AI Asistan kesinlikle veritabanı üzerinde yazma, değiştirme veya silme (DML) işlemi yapmamalı, salt okunur kalmalıdır.
- **Risk / Not:** LLM halüsinasyon riskine karşı asistan çıktılarının altına yasal sorumluluk reddi notu konulmalıdır.
