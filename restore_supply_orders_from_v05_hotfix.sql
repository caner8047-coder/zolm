-- ==============================================================
-- ZOLM v0.5 -> v0.6 SUPPLY_ORDERS HOTFIX (KAYIPSIZ)
-- Olusturma Tarihi: 2026-03-06
-- Sadece tedarik raporu kayitlarini geri yukler.
-- ==============================================================

SET NAMES utf8mb4;
SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET @OLD_SQL_NOTES = @@SQL_NOTES;
SET UNIQUE_CHECKS = 0;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_NOTES = 0;

START TRANSACTION;

DROP TABLE IF EXISTS `_v05_supply_orders_src`;
CREATE TABLE `_v05_supply_orders_src` (
  `id` bigint unsigned NOT NULL,
  `siparis_no` varchar(50) NOT NULL,
  `kayit_tarihi` date DEFAULT NULL,
  `musteri_adi` varchar(255) NOT NULL,
  `telefon` varchar(30) DEFAULT NULL,
  `adres` text,
  `ilce` varchar(100) DEFAULT NULL,
  `il` varchar(100) DEFAULT NULL,
  `urun_adi` varchar(500) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `adet` int DEFAULT NULL,
  `soz_tarihi` date DEFAULT NULL,
  `renk_etiketi` varchar(50) DEFAULT NULL,
  `durum` varchar(20) DEFAULT NULL,
  `sebebiyet` varchar(20) DEFAULT NULL,
  `gonderim_tarihi` date DEFAULT NULL,
  `notlar` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `idx_v05_supply_orders_id` (`id`),
  KEY `idx_v05_supply_orders_siparis_no` (`siparis_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v0.5 supply_orders data
INSERT INTO `_v05_supply_orders_src` (`id`, `siparis_no`, `kayit_tarihi`, `musteri_adi`, `telefon`, `adres`, `ilce`, `il`, `urun_adi`, `kategori`, `adet`, `soz_tarihi`, `renk_etiketi`, `durum`, `sebebiyet`, `gonderim_tarihi`, `notlar`, `created_at`, `updated_at`) VALUES
(1, 'PRA-0423477093', '2026-01-29', 'Cemre çakmak', '05063735435', 'Fevzi Çakmak Mah Fevzi Çakmak Mahallesi Emre Sokak No: 24 Kat: 4 Daire: 11 Bahçelievler/İstanbul', 'BAHÇELİEVLER', 'İSTANBUL', 'PETRA BERJERİN AYAĞAI İÇİN 1 ADET PLASTİK PARÇA GÖNDERİLECEK', '', 1, '2026-01-30', 'TEDARİK EDİLECEKLER', 'kargo', 'paketleme', NULL, NULL, '2026-02-02 06:07:09', '2026-02-04 11:30:10'),
(2, 'PRA-7577642494', '2026-01-29', 'Meltem Tepe - [LORİS PARFÜM]', '05418760645', 'Cumhuriyet Mah Cumhuriyet Mah Turan caddesi no:10-12/D', 'POLATLI', 'ANKARA', 'FAVELA NATÜREL AHŞAP ÜÇLÜ BERJER İÇİN 1 ADET KOL GÖNDERİLECEK', '', 1, '2026-01-31', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-02 06:07:09', '2026-02-12 10:53:42'),
(3, 'PRA-7577642494', '2026-01-29', 'Meltem Tepe - [LORİS PARFÜM]', '05418760645', 'Cumhuriyet Mah Cumhuriyet Mah Turan caddesi no:10-12/D', 'POLATLI', 'ANKARA', 'TOSCA NATÜREL AHŞAP BERJERİ İÇİN 1 ADET KOL GÖNDERİLECEK', '', 1, '2026-01-31', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-02 06:07:09', '2026-02-12 10:53:42'),
(5, 'PRA-1372258529', '2026-01-29', 'Oya Küçükbezirci', '05058577999', 'Havzan Mah Havzan Mah.yeni Elektrik santrali Cad.dekor Apt.no:15 daire no:6meram/konya', 'MERAM', 'KONYA', 'DORİS BERJER İÇİN 2 ADET SIRT AHŞABI', '', 1, '2026-01-31', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(6, 'PRA-2755758101', '2026-01-28', 'Tugba Karadumani', '05068580353', 'Gülbahar Mah Gülbahar Mah Gülbağ mahallesi şahika sokak no 49 kat 9 daire27 mecidiyeköy istanbul 34000 Şişli / İstanbul', 'ŞİŞLİ', 'İSTANBUL', 'LEGNA AÇIK KREM KÖŞE TAKIMI İÇİN MİNDER VE KIRLENT KILIFI GÖNDERİLECEK(HİÇ GÖNDERİLMEMİŞ MÜŞTERİYE)', '', 1, '2026-01-30', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(7, 'PRA-2755758101', '2026-01-28', 'Tugba Karadumani', '05068580353', 'Gülbahar Mah Gülbahar Mah Gülbağ mahallesi şahika sokak no 49 kat 9 daire27 mecidiyeköy istanbul 34000 Şişli / İstanbul', 'ŞİŞLİ', 'İSTANBUL', 'LEGNA KÖŞE TAKIMI İÇİN KURULUM MALZEMESİ GÖNDERİLECEK', '', 1, '2026-01-30', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(8, 'PRA-2529003063', '2026-01-26', 'Rabia Karahan', '05436965190', 'Anadolu Mah Anadolu mahallesi Beysun sokak no 12-16 daire 8 Arnavutköy /İSTANBUL', 'ARNAVUTKÖY', 'İSTANBUL', 'LEGNA AÇIK KREM KÖŞE TAKIMIN SIRT MİNDERİ İÇİN 1 ADET SADECE KILIF GÖNDERİLECEK', '', 1, '2026-01-28', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(9, 'PRA-2529003063', '2026-01-26', 'Rabia Karahan', '05436965190', 'Anadolu Mah Anadolu mahallesi Beysun sokak no 12-16 daire 8 Arnavutköy /İSTANBUL', 'ARNAVUTKÖY', 'İSTANBUL', 'LEGNA KÖŞENİN AYAKLARININ KURULUMU İÇİN VİDA VE SOMUN ANAHTARI, BİRLEŞTİRMEK İÇİN APARAT', '', 1, '2026-01-28', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(10, 'PRA-0408944497', '2026-01-26', 'Oktay Çetiner', '', 'Namık Kemal Mah Namık Kemal mahallesi 4. Sokak No:46 daire no 5 esenler istanbul', 'ESENLER', 'İSTANBUL', 'LEGNA KANEPE İÇİN 1 ADET AYAK GÖNDERİLECEK', '', 1, '2026-01-28', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(11, 'PRA-7562445294', '2026-01-26', 'Deger Kazar Kazar', '05323377164', 'Fevzipaşa Mah Fevzipaşa Mah 209sok no22 daire 1 eskifoça İzmir 35000 Foça / İzmir', 'FOÇA', 'İZMİR', 'FAVELA İKİLİ BERJER İÇİN 1 ADET ARA PARÇA VE 2 ADET 6\'LIK VİDA GÖNDERİLECEK', '', 1, '2026-01-29', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(12, 'PRA-2215805006', '2026-01-26', 'Zuhal Altaş', '05543840094', 'Koşuyolu Mah Koşuyolu Mah Koşuyolu cad. No:192 daire 1', 'KADIKÖY', 'İSTANBUL', 'ALTO BERJER İÇİN 1 ADET SALLANIR KISIM GÖNDERİLECEK', '', 1, '2026-01-30', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-02 06:07:09', '2026-02-02 06:07:09'),
(13, 'PRA-0198840078', '2026-02-04', 'Münevver Ayaz', '05416191164', 'Fatih Mah Fatih Mah 142.sokak barış apartmanı no 9', 'BAĞLAR', 'Diyarbakır', 'ALAVES İKİLİ BERJER İÇİN 1 ADET ORTA AYAK GÖNDERİLECEK', '', 1, '2026-02-07', 'TEDARİK EDİLECEKLER', 'kargo', 'paketleme', NULL, NULL, '2026-02-04 11:30:10', '2026-02-12 10:53:42'),
(14, 'PRA-3935474864', '2026-02-03', 'Şevval Tüysüz', '05303509999', '15 Temmuz Mah 15 temmuz mah.424 cad.no22 Dagal 2 Apartman B blok daire 16 (Hey Dönerin yan binası ) 63000 Ceylanpınar / Şanlıurfa', 'CEYLANPINAR', 'ŞANLIURFA', 'TOSCA NATÜREL AHŞAP BERJER İÇİN 1 ADET AYAK GÖNDERİLECEK', '', 1, '2026-02-05', 'TEDARİK EDİLECEKLER', 'kargo', 'paketleme', NULL, NULL, '2026-02-04 11:30:10', '2026-02-12 10:53:42'),
(15, 'PRA-7080313892', '2026-02-05', 'Selim Uçak', '05434801400', 'ATA/EFELER/Aydın Ata mahallesi.638 sokak no:4 kat:1daire 2 Aydın Efeler (kesici ekmek fırını karşısı ) Selim Uçak', 'EFELER', 'AYDIN', 'SCALA BENCH İÇİN 1 ADET AYAK GÖNDERİLECEK', '', 1, '2026-02-07', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-06 03:30:12', '2026-02-12 10:53:42'),
(16, 'PRA-2703258468', '2026-02-05', 'Okan Alkiş', '05468895929', 'İnalı Mah İnalı mah Yeşilırmak sok lale b blok 4/15 12000 Merkez / Bingöl', 'MERKEZ', 'BİNGÖL', 'ROCK BERJER İÇİN 2 ADET AHŞAP KOL VE 2 ADET ARA PARÇA GÖNDERİLECEK (RENK TONU VE VERNİK KONTROL EDİLSİN)', '', 1, '2026-02-09', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-06 03:30:12', '2026-02-12 10:53:42'),
(17, 'PRA-1784722172', '2026-02-06', 'Nur Genç Şahin - [Nur Genç Şahin ]', '05359404666', 'Pancarlı Mah pancarlı mahallesi ,cemil alevli caddesi M.Reşit Başsimitçi No:42/AŞehitkamil /Gaziantep', 'ŞEHİTKAMİL', 'GAZİANTEP', 'PETRA BERJER İÇİN 17 ADET 12 CM SAPLAMA GÖNDERİLECEK', '', 1, '2026-02-07', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-06 11:52:43', '2026-02-16 10:22:46'),
(18, 'PRA-7486456735', '2026-02-06', 'Hicran Tenekeci Çayırlı', '05464008767', 'Şeyh Şamil Mah Şeyh Şamil Mah Gülüm Caddesi Yeşil Vadim Sitesi 1D Blok Kat 9 Daire 40 Etimesgut Ankara', 'ETİMESGUT', 'ANKARA', 'FAVELA BERJER İÇİN 2 ADET KOL VE 1 ADET ARA PARÇA', '', 1, '2026-02-09', 'TEDARİK EDİLECEKLER', 'kargo', 'paketleme', NULL, NULL, '2026-02-06 11:52:43', '2026-02-12 10:53:42'),
(19, 'PRA-1867179710', '2026-02-09', 'Leda Moripek', '05322551840', 'SAKIZAĞACI Şinasi Gürünlü Sk. No:20 Güneş Apt. Kat:5 D:5 34142 BAKIRKÖY / İstanbul', 'BAKIRKÖY', 'İSTANBUL', 'MEİRA SALLANIR BERJER İÇİN 3 ADET ARA PARÇA GÖNDERİLECEK', '', 1, '2026-02-13', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-09 11:23:07', '2026-02-12 10:53:42'),
(21, 'PRA-9910997387', '2026-01-24', 'Ece Özdoğan', '05413669401', 'Muradiye Mah Muradiye mahallesi kazım Karabekir caddesi no:4 daire:12 yunusemre/manisa', 'YUNUSEMRE', 'MANİSA', 'LEGNA GRİ KANEPENİN  SIRT MİNDERİ İÇİN 1 ADET SADECE KILIF GÖNDERİLECEK', '', 1, '2026-01-26', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(22, 'PRA-2226140310', '2026-01-23', 'sibel yılgör', '', 'Yeni Mah Yeni mahalle. İstanbul caddesi. Kaptanlar sitesi. B blok. Daire:4 sakarya/karasu', 'KARASU', 'SAKARYA', 'DORİS BERJER İÇİN 4 ADET 4 CM VİDA GÖNDERİLECEK', '', 1, '2026-01-24', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(23, 'PRA-6617850419', '2026-01-23', 'Esin Dindaroğlu', '', 'Fevzi çakmak mah. 1.Tuna sok. No:52 D:2  Fevzi Çakmak Mah Küçükçekmece/İstanbul 34000', 'KÜÇÜKÇEKMECE', 'İSTANBUL', 'LONG LİNE SANDIKLI PUF İÇİN 1 ADET SADECE ÜST KAPAK', '', 1, '2026-01-24', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(24, 'PRA-6099336288', '2026-01-23', 'berivan ataman', '05459122480', 'Gültepe Mah gültepe mahallesi alkış sokak no 3A kat -2 daire 2 34000 Kağıthane / İstanbul', 'KAĞITHANE', 'İSTANBUL', 'LEGNA KÖŞE İÇİN BİRLEŞTİRME APARATI', '', 1, '2026-01-24', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(25, 'PRA-7623410439', '2026-01-22', 'MEHMET FATİH AKGÜN', '05395069253', 'Yamanevler Mah Yamanevler Mah Alemdağ Cad. No:131A 34000 Ümraniye / İstanbul', 'ÜMRANİYE', 'İSTANBUL', 'DORİS BERJER İÇİN 4 ADET 4 CM VİDA GÖNDERİLECEK', '', 1, '2026-01-24', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(26, 'PRA-5175428368', '2026-01-21', 'Oya Küçükbezirci', '05058577999', 'Havzan Mah Havzan Mah.yeni Elektrik santrali Cad.dekor Apt.no:15 daire no:6meram/konya', 'MERAM', 'KONYA', 'DORİS BERJER İÇİN SIRT AHŞABI GÖNDERİLECEK', '', 1, '2026-01-24', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(27, 'PRA-7025989104', '2026-01-20', 'Mustafa Başbülük', '05435665339', 'Şeker mahallesi emek caddesi zentower Neshwaffel suluova/ Amasya 05000 Suluova Amasya SULUOVA / AMASYA', 'SULUOVA', 'AMASYA', 'LUPA BERJER İÇİN DEMİR GÖNDERİLECEK', '', 1, '2026-01-22', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(28, 'PRA-8634869009', '2026-01-19', 'Talha sena Erzincanlıoğlu', '', 'Ali kuşçu mahallesi hafızpaşa Sokak no 27 kat 3 daire 5 fevziye hanım apartmanı Fatih İstanbul   Ali Kuşçu Mah Fatih/İstanbul 34000', 'FATİH', 'İSTANBUL', 'DORİS BERJER İÇİN KURULUM MALZEMESİ GÖNDERİLECEK', '', 1, '2026-01-22', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(29, 'PRA-0515078629', '2026-01-15', 'Ümmühan aydın', '05388158674', 'Sahil Mah Sahil mahallesi kadı sokak no 7 Dorapark Marin evleri siteri E blok daire 2 34000 Beylikdüzü / İstanbul', 'BEYLİKDÜZÜ', 'İSTANBUL', 'GARCİA KÖŞE TAKIMI İÇİN 2 ADET BİRLEŞTİRME APARATI GÖNDERİLECEK', '', 1, '2026-01-16', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(30, 'PRA-4805623176', '2026-01-14', 'Tuğba Kayabaşı', '05369365779', 'Merkez Mah Merkez Mah Ereğli yolu caddesi geredeli trio towers kat:16 daire:103 kozlu zonguldak', 'KOZLU', 'ZONGULDAK', 'ALTO BERJER İÇİN 4 ADET AYAK 4 ADET VİDA GÖNDERİLECEK', '', 1, '2026-01-17', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(31, 'PRA-9874955074', '2026-01-14', 'esra Şafak', '05538122997', 'Zafer Mah Zafer mahallesi 3420 sokak 10/1 C : 08 kat 2 daire 13 kanlıca/toki 03000 Merkez / Afyonkarahisar', 'MERKEZ', 'AFYONKARAHİSAR', 'LEGNA KÖŞE TAKIMI İÇİN 2 ADET AYAK GÖNDERİLECEK', '', 1, '2026-01-16', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(32, 'PRA-9902624011', '2026-01-14', 'Orhan ERGÜN', '5552147345', 'Yalı Mahallesi, 282 Sokak NO 31 YALI 35000 GÜZELBAHÇE / İZMİR', 'GÜZELBAHÇE', 'İZMİR', 'PAVİA NATÜREL AHŞAP BERJER İÇİN 1 ADET KOL GÖNDERİLECEK', '', 1, '2026-01-16', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(33, 'PRA-1394283139', '2026-01-13', 'ramazan beyan', '5372431056', 'Şehit asteğmen Furkan Işık caddesi Tual Çarşı B1/28', 'BAŞAKŞEHİR', 'İSTANBUL', 'PAVİA SÜTLÜ KAHVE İKİLİ BERJER İÇİN 1 ADET SIRT(HER YERİ KONTROL EDİLSİN İYİ PAKETLENSİN)', '', 1, '2026-01-15', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(34, 'PRA-1394283139', '2026-01-13', 'ramazan beyan', '5372431056', 'Şehit asteğmen Furkan Işık caddesi Tual Çarşı B1/28', 'BAŞAKŞEHİR', 'İSTANBUL', 'PAVİA SÜTLÜ KAHVE TEKLİ BERJER İÇİN 1 ADET SIRT(HER YERİ KONTROL EDİLSİN İYİ PAKETLENSİN)', '', 1, '2026-01-15', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(35, 'PRA-4098515687', '2026-01-13', 'hatice şen aydın', '05383169909', '28 Haziran Mah 28 Haziran Mah kent konut3 E 2 blok daire 7 41000 İzmit / Kocaeli', 'İZMİT', 'KOCAELİ', 'ROCK BERJER İÇİN 1 ADET 2 CMLİK ARA BAĞLANTI SALLANIR KISMINA', '', 1, '2026-01-16', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(36, 'PRA-3098265386', '2026-01-13', 'Fatma Komanlı', '5446346360', 'Namık Kemal Caddesi No:10 Daire:5 CAMİCEDİT 17000 BAYRAMİÇ / ÇANAKKALE', 'BAYRAMİÇ', 'ÇANAKKALE', 'ALAVES BERJER İÇİN OTURUNCA SOL KOL GÖNDERİLECEK 1 ADET', '', 1, '2026-01-16', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(37, 'PRA-9401124218', '2026-01-12', 'rumeysa özdener', '', 'Gültepe Mah Gültepe Mah. Lale Sok. Özen İnş. (Apt No:36) Kat: 3 Daire: 12 58000 Merkez / Sivas', 'MERKEZ', 'SİVAS', 'TOSCA NATÜREL AHŞAP BERJER İÇİN SADECE 1 ADET AYAK', '', 1, '2026-01-15', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-11 07:00:54', '2026-02-11 07:00:54'),
(39, 'PRA-1378874587', '2026-02-10', 'Filiz Memoğlu', '05555542953', 'Adil Mah Adil Mah Polenez Caddesi, Sinpaş Liva Turkuaz Sitesi, O blok, Daire: 29, Kat: 5', 'SULTANBEYLİ', 'İSTANBUL', 'TOSCA NATÜREL AHŞAP BERJER İÇİN 1 ADET AYAK GÖNDERİLECEK', '', 1, '2026-02-12', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-11 10:30:13', '2026-02-16 10:22:46'),
(40, 'PRA-0757100278', '2026-02-13', 'Seda Yılmaz', '', 'Yenişehir Mah Yenişehir mahallesi cumhuriyet bulvarı toprak mensupları sitesi 26/4 D blok Daire:3 34000 Pendik / İstanbul', 'PENDİK', 'İSTANBUL', 'TOSCA BERJER İÇİN 1 ADET CEVİZ AHŞAP AYAK GÖNDERİLECEK', '', 1, '2026-02-16', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-13 08:21:45', '2026-02-16 10:22:46'),
(41, 'PRA-2058280229', '2026-02-16', 'elif büyükyazıcı', '', 'Kültür Mah Kültür mah Fevzi paşa cad 60 B aliağa 35000 Aliağa / İzmir', 'Aliağa', 'İZMİR', 'ROCK BERJER İÇİN 2 ADET KOL VE 2 ADET ARA PARÇA', '', 1, '2026-02-19', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-16 10:22:46', '2026-02-20 10:22:35'),
(42, 'PRA-2874759587', '2026-02-16', 'demet atmaca', '05425969944', 'Gap Mah Gap Mah Xalo heyran yanı Berzan apartmanı B blok kat:3 no:27 72000 Merkez / Batman', 'MERKEZ', 'BATMAN', '6 ADET 8\'LİK VİDA GÖNDERİLECEK', '', 1, '2026-02-18', 'TEDARİK EDİLECEKLER', 'kargo', 'paketleme', NULL, NULL, '2026-02-16 10:22:46', '2026-02-20 10:22:35'),
(43, 'PRA-5791375857', '2026-02-16', 'pınar Çubukçu', '', 'Anadolufeneri Mah Anadolufeneri Mah Fener caddesi no2 34000 Beykoz / İstanbul', 'BEYKOZ', 'İSTANBUL', 'CONTES GRİ BENCH İÇİN SADECE ÜST KAPAK VE OTURUM GÖNDERİLECEK', '', 1, '2026-02-19', 'TEDARİK EDİLECEKLER', 'kargo', 'kargo', NULL, NULL, '2026-02-17 10:25:18', '2026-02-20 10:22:35'),
(44, 'PRA-7001577145', '2026-02-19', 'Şevval Damla Ekici', '', 'Nilüfer esentepe mahallesi kasap Sokak  Bina no 8/10  Aydemir prestij Kat 3 no 17', 'Nilüfer', 'BURSA', 'ŞİLA BENCH İÇİN SADECE OTURUM GÖNDERİLECEK (AYAKLAR GÖNDERİLMEYECEK)', '', 1, '2026-02-23', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-19 10:53:19', '2026-02-20 10:22:35'),
(45, 'PRA-7317093569', '2026-02-19', 'Mehmet Kösem', '05053212842', '648 umutkent 2 sitesi 23. blok (1/Z) no:20 20030 MERKEZEFENDİ / Denizli', 'MERKEZEFENDİ', 'DENİZLİ', 'FAVELA BERJER İÇİN 2 ADET ARA BAĞLANTI VE MONTAJI İÇİN VİDA,PUL,SOMUN', '', 1, '2026-02-21', 'TEDARİK EDİLECEKLER', 'kargo', 'paketleme', NULL, NULL, '2026-02-19 10:53:19', '2026-02-20 10:22:35'),
(46, 'PRA-9130246151', '2026-02-20', 'elif Tuna', '05334366017', 'İsmetpaşa Mah İsmetpaşa mahallesi Metin Oktay caddesi 80 Evler sitesi A blok alt. no: 3/B fatura istiyorum onun için TC : 50101463358 17000 Merkez / Çanakkale', 'MERKEZ', 'ÇANAKKALE', 'LEGNA AÇIK KREM KANEPE İÇİN SADECE 1 ADET SIRT GÖNDERİLECEK', '', 1, '2026-02-24', 'TEDARİK EDİLECEKLER', 'kargo', 'kargo', NULL, NULL, '2026-02-20 10:22:35', '2026-03-04 10:23:12'),
(47, 'PRA-4273616942', '2026-02-23', 'Sara Sultan Aşkan', '', 'Kavaklı Mah Kavaklı Mah Selçuk esmer sokak apartman 12 daire 10 34000 Beylikdüzü / İstanbul', 'BEYLİKDÜZÜ', 'İSTANBUL', 'CONTES SÜTLÜ KAHVE BENCH İÇİN SADECE ÜST KAPAK VE OTURUM GÖNDERİLECEK', '', 1, '2026-02-25', 'TEDARİK EDİLECEKLER', 'kargo', 'kargo', NULL, NULL, '2026-02-23 10:31:37', '2026-03-03 10:20:40'),
(48, 'PRA-2071391464', '2026-02-23', 'emrah yaşar - [Fizyova sağlıklı yaşam merkezi]', '05074033956', 'Eski Kışla Mah Eski Kışla Mah Cengiz topel cad. No: 97 çobanoğlu plaza kat:3 daire:15', 'YÜKSEKOVA', 'HAKKARİ', 'OLA PUF İÇİN 25 ADET AYAK GÖNDERİLECEK', '', 1, '2026-02-25', 'TEDARİK EDİLECEKLER', 'kargo', 'yok', NULL, NULL, '2026-02-23 10:31:37', '2026-03-03 10:20:40'),
(49, 'PRA-3181355944', '2026-02-24', 'Murat Özden', '05426976056', 'Hurma Mah. 258. Sokak Dantalya sitesi No: 18 A Blok No 1, Konyaaltı KONYAALTI / ANTALYA', 'KONYAALTI', 'ANTALYA', 'LUPA BERJER İÇİN KURULUM MALZEMESİ (İKİ BERJERLİK GÖNDERİLECEK)', '', 1, '2026-02-27', 'TEDARİK EDİLECEKLER', 'bekliyor', 'paketleme', NULL, NULL, '2026-02-24 10:40:08', '2026-02-24 10:40:08'),
(50, 'PRA-3181355944', '2026-02-24', 'Murat Özden', '05426976056', 'Hurma Mah. 258. Sokak Dantalya sitesi No: 18 A Blok No 1, Konyaaltı KONYAALTI / ANTALYA', 'KONYAALTI', 'ANTALYA', 'LUPA BERJER İÇİN 1 ADET KOL', '', 1, '2026-02-27', 'TEDARİK EDİLECEKLER', 'bekliyor', 'uretim', NULL, NULL, '2026-02-24 10:40:08', '2026-02-24 10:40:08'),
(51, 'PRA-7315715182', '2026-02-25', 'Merve Çelikkaya - [Ören Bonn Apart]', '05524107271', 'Ören Mah Ören Mah Av. İsmail Kızıklı caddesi nu:24 10000 Burhaniye / Balıkesir', 'BURHANİYE', 'Balıkesir', 'FAVELA SÜTLÜ KAHVE TEKLİ BERJER İÇİN 2 ADET SADECE OTURUM GÖNDERİLECEK (ÇÖKME OLMUŞ DEĞİŞİM)', '', 1, '2026-03-02', 'TEDARİK EDİLECEKLER', 'kargo', 'uretim', NULL, NULL, '2026-02-25 10:20:30', '2026-03-03 10:20:40'),
(52, 'PRA-6285794480', '2026-02-26', 'Hatice Güner', '05342342834', 'Ereğli Mah Ereğli Mah Mehmet Yusuf Bora caddesi Sağlam Yapı. Kat:1 Daire:3 41000 Karamürsel / Kocaeli', 'KARAMÜRSEL', 'KOCAELİ', 'ALTO BERJER İÇİN 1 ADET KIRLENT KIFILI VE İÇİ GÖNDERİLECEK', '', 1, '2026-03-02', 'TEDARİK EDİLECEKLER', 'bekliyor', 'paketleme', NULL, NULL, '2026-02-27 11:02:45', '2026-02-27 11:02:45'),
(53, 'PRA-0986667391', '2026-03-02', 'ferda izgiç - [İzgiç gayrimenkul ticaret lim]', '05327871330', 'Yeşilova Mah Yeşilova mahallesi Yazgı sokak no 70 izmit kocaeli', 'İZMİT', 'KOCAELİ', 'LEGNA BERJER İÇİN 4 ADET AYAK GÖNDERİLECEK', '', 1, '2026-03-05', 'TEDARİK EDİLECEKLER', 'bekliyor', 'paketleme', NULL, NULL, '2026-03-02 10:28:47', '2026-03-02 10:28:47'),
(54, 'PRA-9983157448', '2026-03-02', 'Emine Anakaya', '5514089510', '1003 Nolu Sokak, Gümüşvadi Konutları, No:11, Kat:5, Daire:17 YENİMAHALLE 28000 PİRAZİZ / GİRESUN', 'Piraziz', 'Giresun', 'FAVELA SÜTLÜ KAHVE İKİLİ BERJER İÇİN SADECE OTURUM VE SIRT KISMI GÖNDERİLECEK', '', 1, '2026-03-05', 'TEDARİK EDİLECEKLER', 'bekliyor', 'kargo', NULL, NULL, '2026-03-02 10:28:47', '2026-03-02 10:28:47'),
(55, 'PRA-8086632695', '2026-03-02', 'dilan özdemir', '05546953889', 'Cevdet Paşa Mah prof. dr. akşit göktürk sk. no:20/37 Akka Aura Evleri Cevdet Paşa Mah. 65000 İpekyolu / Van', 'İPEKYOLU', 'VAN', 'PAVİA BERJER İÇİN 1 ADET ZAMAN VE ALYAN GÖNDERİLECEK', '', 1, '2026-03-04', 'TEDARİK EDİLECEKLER', 'bekliyor', 'uretim', NULL, NULL, '2026-03-02 10:28:47', '2026-03-02 10:28:47'),
(56, 'PRA-5905483827', '2026-03-03', 'merve sefa yılmaz', '05457316543', 'varlık mahallesi ,varlık evleri 1 186 sokak no 7/3 muratpaşa antalya', 'MURATPAŞA', 'ANTALYA', 'LEGNA KÖŞE TAKIMI İÇİN 1 ADET BİRLEŞTİRME APARATI', '', 1, '2026-03-04', 'TEDARİK EDİLECEKLER', 'bekliyor', 'paketleme', NULL, NULL, '2026-03-03 10:20:02', '2026-03-03 10:20:02'),
(57, 'PRA-7552370468', '2026-03-05', 'Feridun Olcay', '05526620717', 'ARMUTLU 85. YIL CUMHURİYET İzmir caddesi . No 33 kat 3 35737 KEMALPAŞA / İzmir', 'KEMALPAŞA', 'İZMİR', 'PAVİA NATÜREL AHŞAP BERJER İÇİN 2 ADET KOL GÖNDERİLECEK', '', 1, '2026-03-09', 'TEDARİK EDİLECEKLER', 'bekliyor', 'uretim', NULL, NULL, '2026-03-05 10:22:18', '2026-03-05 10:22:18'),
(58, 'PRA-5547916893', '2026-03-05', 'Aleyna Ak', '05537167846', 'Meşrutiyet mahallesi 2053 sokak no 28 cesur apartmanı kat 2 daire 5 09100 Efeler Aydın', 'EFELER', 'AYDIN', 'PAVİA CEVİZ AHŞAP BERJER İÇİN 1 ADET KOL GÖNDERİLECEK', '', 1, '2026-03-09', 'TEDARİK EDİLECEKLER', 'bekliyor', 'uretim', NULL, NULL, '2026-03-05 10:22:18', '2026-03-05 10:22:18'),
(59, 'PRA-5962383083', '2026-03-05', 'sevda calak', '05447471119', 'Abdullah Sk. Vişne evleri a blok kat 5 daire 10', 'BOZÜYÜK', 'Bilecik', 'ALAVES BERJER İÇİN 1 ADET OTURUNCA SAĞ KOL', '', 1, '2026-03-09', 'TEDARİK EDİLECEKLER', 'bekliyor', 'paketleme', NULL, NULL, '2026-03-05 10:22:18', '2026-03-05 10:22:18');

-- Index uyumlulugu: siparis_no tekil yerine siparis_no + urun_adi tekil olmalı
SET @idx_single := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'supply_orders'
    AND index_name = 'supply_orders_siparis_no_unique'
);
SET @sql := IF(
  @idx_single > 0,
  'ALTER TABLE `supply_orders` DROP INDEX `supply_orders_siparis_no_unique`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_pair := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'supply_orders'
    AND index_name = 'siparis_urun_unique'
);
SET @sql := IF(
  @idx_pair = 0,
  'ALTER TABLE `supply_orders` ADD UNIQUE KEY `siparis_urun_unique` (`siparis_no`,`urun_adi`(191))',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `supply_orders`
(
  `siparis_no`, `kayit_tarihi`, `musteri_adi`, `telefon`, `adres`, `ilce`, `il`,
  `urun_adi`, `kategori`, `adet`, `soz_tarihi`, `renk_etiketi`, `durum`,
  `sebebiyet`, `gonderim_tarihi`, `notlar`, `created_at`, `updated_at`
)
SELECT
  s.`siparis_no`,
  s.`kayit_tarihi`,
  s.`musteri_adi`,
  s.`telefon`,
  s.`adres`,
  s.`ilce`,
  s.`il`,
  s.`urun_adi`,
  s.`kategori`,
  CASE
    WHEN s.`adet` IS NULL OR s.`adet` < 1 THEN 1
    WHEN s.`adet` > 65535 THEN 65535
    ELSE s.`adet`
  END AS `adet`,
  s.`soz_tarihi`,
  s.`renk_etiketi`,
  CASE
    WHEN s.`durum` IN ('bekliyor', 'uretim', 'paketleme', 'kargo', 'gonderildi') THEN s.`durum`
    ELSE 'bekliyor'
  END AS `durum`,
  CASE
    WHEN s.`sebebiyet` IN ('uretim', 'paketleme', 'kargo', 'yok') THEN s.`sebebiyet`
    ELSE 'yok'
  END AS `sebebiyet`,
  s.`gonderim_tarihi`,
  s.`notlar`,
  s.`created_at`,
  s.`updated_at`
FROM `_v05_supply_orders_src` s
LEFT JOIN `supply_orders` so
  ON so.`siparis_no` = s.`siparis_no`
 AND so.`urun_adi` = s.`urun_adi`
WHERE so.`id` IS NULL;

DROP TABLE IF EXISTS `_v05_supply_orders_src`;

COMMIT;

SET SQL_NOTES = @OLD_SQL_NOTES;
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;

-- Script sonu
