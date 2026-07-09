# Cline Görev Brief'i — Party + Cari Temeli

Bu görevde amaç tüm ERP/CRM/ön muhasebe sistemini tek seferde kodlamak değildir.

Amaç, ZOLM'un mevcut CRM ve pazaryeri finans yapısını bozmadan ilk mimari temeli hazırlamaktır:

**Party + Cari Temeli**

## Mimari Bağlam

Ana yol haritası:

- `docs/zolm-crm-erp-on-muhasebe-yol-haritasi.md`

Mevcut CRM planı:

- `docs/crm-modulu-gelistirme-plani.md`

Mevcut CRM çekirdek dosyaları:

- `database/migrations/2026_04_27_093000_create_crm_core_tables.php`
- `database/migrations/2026_04_29_120000_create_crm_customer_ledger_entries_table.php`
- `app/Livewire/CrmWorkspace.php`
- `app/Livewire/CrmCustomerLedger.php`
- `app/Services/Crm/CrmIdentityResolver.php`
- `app/Services/Crm/CrmProjectionService.php`
- `app/Services/Crm/CrmCustomerLedgerProjectionService.php`

## Kesin Talimat

İlk turda büyük refactor yapılmayacak.

Şunlar yapılmayacak:

- `app/Modules` yapısına toplu taşıma
- Mevcut `crm_contacts` tablosunu silme veya yeniden adlandırma
- Mevcut pazaryeri sipariş/finans akışlarını bozma
- Bütün ERP modüllerini tek PR'da açma
- Filament kurma veya mevcut UI sistemini değiştirme
- GPL lisanslı repolardan kod kopyalama

## İlk Görev

Önce keşif ve tasarım yapılacak.

### 1. Veri Otoritesi Haritası

Aşağıdaki alanlar için mevcut tabloları ve servisleri incele:

- CRM müşteri kimliği
- Pazaryeri müşteri bilgisi
- Cari benzeri müşteri ledger
- Pazaryeri siparişleri
- Pazaryeri finans olayları
- Ürün/stok alanları
- Tedarikçi benzeri kayıtlar
- Kargo firması / banka / tüzel kişi kayıtları

Çıktı dosyası:

- `docs/party-core-veri-otoritesi-haritasi.md`

Bu dosyada şu başlıklar olmalı:

- Mevcut tablo
- Hangi verinin sahibi
- Hangi modüller okuyor
- Hangi modüller yazıyor
- Yeni party katmanıyla ilişkisi
- Riskler

### 2. Party Model Karar Dokümanı

Yeni üst kimlik modelini tasarla.

Çıktı dosyası:

- `docs/party-core-model-karari.md`

Bu dosyada şu kararlar olmalı:

- `parties` alanları
- `party_roles` alanları
- `party_identities` alanları
- `party_addresses` gerekli mi?
- `crm_contacts.party_id` eklenecek mi?
- Mevcut `crm_contact_identities` ile ilişki nasıl kurulacak?
- `party` ile `legal_entities` ilişkisi kurulacak mı?
- Müşteri / tedarikçi / pazaryeri / kargo / banka rolleri nasıl ayrılacak?
- Geriye uyumluluk stratejisi

### 3. İlk Migration Taslağı

Kod yazılacaksa sadece taslak ve güvenli migration kapsamı yaz.

Önerilen migration kapsamı:

- `create_parties_table`
- `create_party_roles_table`
- `create_party_identities_table`
- `add_party_id_to_crm_contacts_table`

Kurallar:

- Migration backward compatible olacak.
- Var olan tablo/kolon silinmeyecek.
- `nullable` geçiş alanları kullanılacak.
- Unique index'ler canlı veriyi kırmayacak şekilde dikkatli tasarlanacak.
- `user_id` izolasyonu korunacak.

## Kabul Kriterleri

İlk turun sonunda şu kutular işaretlenebilir olmalı:

- [ ] Mevcut CRM/cari/marketplace finans veri otoritesi haritası çıkarıldı.
- [ ] `party` model karar dokümanı yazıldı.
- [ ] İlk migration taslağı geriye uyumlu şekilde hazırlandı veya önerildi.
- [ ] Mevcut CRM akışlarını bozacak değişiklik yapılmadı.
- [ ] Büyük modül/refactor çalışmasına başlanmadı.

## Cline'dan Beklenen Final Raporu

Cline iş sonunda şunları raporlamalı:

- Hangi dosyalar incelendi?
- Hangi tablolar mevcut otorite kabul edildi?
- Hangi yeni tablolar önerildi?
- Hangi riskler var?
- Kod değişikliği yapıldıysa hangi dosyalar değişti?
- Test çalıştırıldı mı?
- Bir sonraki güvenli adım ne?

## Sonraki Adım

Bu görev tamamlanmadan:

- Ön Muhasebe ekranlarına,
- Kasa/Banka ekranlarına,
- Satış/Satın Alma belgelerine,
- e-Fatura adaptörüne,
- Asistan modülüne

başlanmayacak.

