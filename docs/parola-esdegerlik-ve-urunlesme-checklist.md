# ZOLM ERP & Muhasebe - Parola Esdegerlik ve Urunlesme Checklist

Bu dokuman, ZOLM on muhasebe/ERP modulunun Parola'nin herkese acik web sitesinde vadettigi islevlerle karsilastirilmasi ve eksiklerin urunlesme sirasi ile kapatilmasi icin olusturuldu.

Referans kaynaklar:

- https://parola.com/
- https://parola.com/ozellikler/

Son kontrol tarihi: 2026-07-10

## Durum Ozeti

ZOLM, Parola'nin cekirdek ERP ve on muhasebe vaadinin onemli kismini altyapi ve MVP seviyesinde karsiliyor. Ticari urun olgunlugu icin ozellikle gercek e-belge entegrasyonu, cari/urun kart deneyimi, POS donanim/odeme entegrasyonu, iade surecleri, yetki rolleri ve dokuman ciktilari guclendirilmelidir.

Bu sprint ile iki kritik eksik urun yuzeyine tasindi:

- `Cariler` ekrani: musteri/tedarikci tek listesi, rol yonetimi, iletisim/vergi bilgisi, aktif-pasif ve bakiye ozeti.
- `Urun Kartlari` ekrani: barkod, SKU, kategori, birim, KDV, maliyet, satis fiyati, stok ve kritik stok esigi.

## Esdegerlik Matrisi

| Parola vaadi | ZOLM durumu | Not |
| --- | --- | --- |
| Cari tek liste | Tamamlandi | `accounting.parties` ile musteri/tedarikci rolleri tek listede. |
| Cari detay, bakiye, ekstre | Kismi | Cari kart + acik hesap var. PDF/Excel ekstre sonraki sprint. |
| Aktif/pasif cari | Tamamlandi | Cari silinmeden pasiflenir, gecmis hareket korunur. |
| Urun karti | Tamamlandi | Barkod, SKU, kategori, birim, KDV, maliyet, satis fiyati, stok ve kritik esik. |
| Stok hareketleri | Tamamlandi | Depo, hareket, bakiye ve kritik stok altyapisi var. |
| Satis belgesi | MVP tamam | Taslak, onay, stok dusumu, cari alacak olusumu var. Iade ve PDF cikti eksik. |
| Alis belgesi | MVP tamam | Taslak, onay, stok girisi, cari borc olusumu var. Gelen e-fatura donusumu eksik. |
| Tahsilat/odeme | MVP tamam | Acik islem kapatma ve bakiye etkisi var. Cek/senet ve gelismis odeme tipleri eksik. |
| Kasa/banka/virman | MVP tamam | Kasa, banka, virman ve hesap ekstresi var. Acilis bakiyesi UI iyilestirilecek. |
| Hizli satis/POS | Kismi | Web POS var. Barkod okutma, fis yazici, temassiz odeme entegrasyonu eksik. |
| e-Fatura/e-Arsiv/e-Irsaliye | Kismi | Simule e-belge var. Gercek ozel entegrator/GIB entegrasyonu eksik. |
| Raporlar | MVP tamam | Mizan, bilanço, gelir tablosu, nakit/stok/alacak raporlari ve Excel var. PDF eksik. |
| AI Asistan | Kismi | Kural tabanli finansal asistan var. Gercek LLM ve aksiyon olusturma eksik. |
| Yetki/kasiyer rolu | Eksik | Admin guard var. Kasa personeli/finans rolu ayrimi eklenmeli. |
| Veri tasima | Kismi | Pazaryeri ve Excel altyapilari var. Cari/urun icin sihirbazli import gerekli. |

## Urunlesme Sirasi

1. Cari ve urun kartlarini tamamla.
2. Satis/alis iade akisini ekle.
3. Cari ekstre PDF/Excel ve urun liste Excel ciktilarini ekle.
4. POS barkod hizli giris, fis taslagi ve odeme yontemi detaylarini ekle.
5. Gercek e-Fatura/e-Arsiv/e-Irsaliye entegrasyon adapter katmanini ayir.
6. Yetki rolleri: yonetici, muhasebe, kasiyer.
7. AI Asistan'i salt rapor cevaplayan yapi olmaktan cikarip aksiyon onerisi ve guvenli islem taslagi uretecek hale getir.

## Sprint P1 Kabul Kriterleri

- [x] `/accounting/parties` route'u feature flag ve admin guard arkasinda.
- [x] Cari kart olusturma, duzenleme, aktif/pasif, musteri/tedarikci rol yonetimi.
- [x] Cari manuel kimlikleri: email, telefon, vergi no.
- [x] Cari listesi tenant izolasyonlu ve bakiye ozetli.
- [x] `/accounting/products` route'u feature flag ve admin guard arkasinda.
- [x] Urun karti olusturma, duzenleme, aktif/pasif.
- [x] Barkod tekilligi kullanici bazinda korunur.
- [x] Kritik stok filtresi ve stok degeri KPI'i.
- [x] Dashboard ve sidebar linkleri eklendi.
- [x] Feature testler eklendi.

## Sprint P2 Onerisi

Bir sonraki guvenli sprint:

- Cari ekstre Excel/PDF ciktilari.
- Urun listesi Excel export.
- Satis iade akisi: onayli satis uzerinden iade olusturma, stok geri girisi, cari ters kayit.
- Alis iade akisi: tedarikciye iade, stok cikisi, cari ters kayit.
- POS barkod arama alaninin barkod/SKU ile hizli sepete eklemesi.
