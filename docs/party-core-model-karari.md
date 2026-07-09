# Party Core — Model Karar Dokümanı

Bu doküman, ZOLM'da yeni **tekil Party/Cari kimlik katmanı**nın veri modeli kararlarını belirler. Amaç, CRM müşteri, cari hesap, tedarikçi, pazaryeri, kargo firması ve banka gibi "karşı taraf" kayıtlarının ayrı modeller halinde kopuk büyümesini önlemek; tek bir üst kimlik (`parties`) katmanında birleştirmektir.

Bu doküman aşağıdaki brief kapsamında üretilmiştir:

- `docs/cline-party-core-gorev-briefi.md`
- Ana yol haritası: `docs/zolm-crm-erp-on-muhasebe-yol-haritasi.md`
- Veri otoritesi haritası: `docs/party-core-veri-otoritesi-haritasi.md`

> Önemli kural: Bu doküman **tasarım kararı** belgesidir. Migration yazma aşaması, bu doküman onaylandıktan sonra ayrıca başlatılacaktır. Mevcut CRM/pazaryeri akışları bozulmayacak; `crm_contacts` ve `crm_contact_identities` silinmeyecek/yeniden adlandırılmayacaktır.

## 0. Temel Karar Özeti

1. **`parties` tablosu:** Tekil "karşı taraf" (müşteri/tedarikçi/pazaryeri/kargo/banka) kimliği. `legal_entities` (kendi şirketim) ile **birleştirilmez**.
2. **`party_roles` tablosu:** Bir party'nin üstlendiği roller (müşteri, tedarikçi, pazaryeri, kargo, banka). Bir party birden fazla role sahip olabilir.
3. **`party_identities` tablosu:** Çok kaynaklı kimlikler (e-posta, telefon, vergi no, pazaryeri müşteri ID). Mevcut `crm_contact_identities`'in **üst/ortak** katmanı; mevcut tablo bozulmadan yan yana yaşar.
4. **`party_addresses`:** İlk fazda **gerekli değildir**; mevcut snapshot adres alanları yeterlidir. İleri faz (tedarikçi fatura adresi, kargo şube) için ayrılır.
5. **`crm_contacts.party_id`:** Evet, **nullable** olarak eklenecek. CRM önce çalışmaya devam edecek; party ilişkisi kademeli kurulacak.
6. **`legal_entities` ilişkisi:** Party, `legal_entity_id` (nullable) ile **hangi şirketin karşı tarafı** olduğunu gösterebilir; birleştirme yok.
7. **Roller:** `customer`, `supplier`, `marketplace`, `carrier`, `bank` rolleri `party_roles` üzerinden ayrılır.
8. **Geriye uyumluluk:** Tüm geçiş alanları nullable; unique index'ler canlı veriyi kırmaz; `user_id` izolasyonu korunur.

## 1. `parties` Alanları

`parties`, tekil karşı-taraf kimliğidir. Tenant'a göre `user_id` ile izole edilir.

| Alan | Tip | Zorunlu | Açıklama |
| --- | --- | --- | --- |
| `id` | bigint unsigned PK | evet | Birincil anahtar. |
| `user_id` | FK → users | evet | Tenant izolasyonu. `cascadeOnDelete`. |
| `legal_entity_id` | FK → legal_entities | nullable | Bu party hangi şirketin karşı tarafı (çok şirketli tenant). `nullOnDelete`. |
| `display_name` | string(180) | evet | Görünen ad (müşteri unvanı, tedarikçi ünvanı). |
| `normalized_name` | string(180) | nullable | Eşleştirme için normalize ad. |
| `party_type` | string(30) | evet (default `unknown`) | `person` / `organization` / `unknown`. İlk faz default `unknown`. |
| `primary_email` | string(180) | nullable | Birincil e-posta. |
| `primary_phone` | string(40) | nullable | Birincil telefon. |
| `normalized_phone` | string(40) | nullable | Normalize telefon (eşleştirme). |
| `tax_number` | string(40) | nullable | Vergi/TCKN. Müşteri/tedarikçi için. |
| `tax_office` | string(120) | nullable | Vergi dairesi. |
| `city` | string(120) | nullable | Şehir. |
| `district` | string(120) | nullable | İlçe. |
| `status` | string(30) | evet (default `active`) | `active` / `archived` / `blacklisted`. |
| `is_blacklisted` | boolean | evet (default false) | Risk/kara liste bayrağı. |
| `meta_json` | json | nullable | Esnek meta (segment, etiket vb.). |
| `timestamps` | — | evet | `created_at`, `updated_at`. |

### 1.1. İndeksler (`parties`)

- `index(['user_id', 'normalized_name'])` — isim arama.
- `index(['user_id', 'normalized_phone'])` — telefon eşleştirme.
- `index(['user_id', 'tax_number'])` — vergi no eşleştirme.
- `index(['user_id', 'status'])` — aktif filtre.
- `index(['user_id', 'legal_entity_id'])` — şirket bazlı cari.

### 1.2. Unique kısıt (`parties`)

- **İlk fazda unique index yok** (tekil "tek party" kuralı `party_identities` seviyesinde sağlanır). Canlı veride null/duplikasyon riski olmadan yazılabilir. İleri fazda `user_id` + `tax_number` (non-null) üzerinde **partial/conditional unique** düşünülebilir, ancak canlı veriyi kırmamak için ilk migration'da eklenmez.

## 2. `party_roles` Alanları

Bir party birden fazla role sahip olabilir (örn. hem müşteri hem tedarikçi). Roller `party_roles` ile ayrıştırılır.

| Alan | Tip | Zorunlu | Açıklama |
| --- | --- | --- | --- |
| `id` | bigint unsigned PK | evet | Birincil anahtar. |
| `user_id` | FK → users | evet | Tenant izolasyonu. `cascadeOnDelete`. |
| `party_id` | FK → parties | evet | İlişkili party. `cascadeOnDelete`. |
| `role` | string(30) | evet | Rol: `customer`, `supplier`, `marketplace`, `carrier`, `bank`, `employee`, `other`. |
| `role_code` | string(60) | nullable | Rol bazlı alt kod (örn. pazaryeri adı `trendyol`, kargo `surat`). |
| `is_primary` | boolean | evet (default false) | Birincil rol bayrağı (UI/sıralama). |
| `status` | string(30) | evet (default `active`) | Rol aktif mi. |
| `assigned_at` | timestamp | nullable | Rolün atandığı tarih. |
| `meta_json` | json | nullable | Role özel meta (kredi limiti, ödeme vadesi vb.). |
| `timestamps` | — | evet | `created_at`, `updated_at`. |

### 2.1. İndeksler / Unique (`party_roles`)

- **Karar (kapatıldı):** `unique(['user_id', 'party_id', 'role'])` — aynı party'nin aynı rolü tekil. `role_code` unique'e **dahil edilmez**; nullable kalır ve ayrı bir index ile aranır. Bu, nullable kolonlu composite unique'in MySQL'deki belirsiz davranışını önler.
- `index(['user_id', 'role_code'])` — `role_code` (nullable) bazlı arama.
- `index(['user_id', 'role', 'status'])` — rol bazlı liste.
- `index(['party_id', 'is_primary'])` — birincil rol sorgusu.

### 2.2. Rol Sözlüğü (ilk faz)

| Rol kodu | Anlamı | Bağlandığı mevcut veri |
| --- | --- | --- |
| `customer` | Müşteri (alıcı) | `crm_contacts`, `channel_orders` müşteri snapshot |
| `supplier` | Tedarikçi (şu an tablosu yok, party kurar) | — |
| `marketplace` | Pazaryeri karşı taraf | `marketplace_stores` (satıcı tarafı), `mp_transactions`/`mp_settlements` (finans) |
| `carrier` | Kargo firması | `cargo_carrier_accounts`, dağınık `cargo_company` |
| `bank` | Banka karşı taraf (ileride) | — |
| `employee` | Çalışan/diğer (ileride) | — |

## 3. `party_identities` Alanları

Çok kaynaklı kimlik katmanı. Mevcut `crm_contact_identities`'in **üst/ortak** seviyesidir; mevcut tablo korunur.

| Alan | Tip | Zorunlu | Açıklama |
| --- | --- | --- | --- |
| `id` | bigint unsigned PK | evet | Birincil anahtar. |
| `user_id` | FK → users | evet | Tenant izolasyonu. `cascadeOnDelete`. |
| `party_id` | FK → parties | evet | İlişkili party. `cascadeOnDelete`. |
| `store_id` | FK → marketplace_stores | nullable | Pazaryeri mağazası. `nullOnDelete`. |
| `source_type` | string(50) | evet | Kaynak: `manual`, `marketplace`, `crm`, `cargo`, `supplier_import` vb. |
| `identity_kind` | string(40) | evet | Kimlik tipi: `external_customer_id`, `email`, `phone`, `tax_number`, `carrier_code`. |
| `identity_value` | string(191) | evet | Normalize kimlik değeri. |
| `external_id` | string(191) | nullable | Kaynak sistemdeki ham ID (örn. pazaryeri müşteri ID). |
| `confidence` | decimal(5,2) | evet (default 0) | Eşleşme güven skoru. |
| `raw_payload` | json | nullable | Ham kaynak verisi (zengin bilgi). |
| `timestamps` | — | evet | `created_at`, `updated_at`. |

### 3.1. İndeksler / Unique (`party_identities`)

- `unique(['user_id', 'source_type', 'store_id', 'identity_kind', 'identity_value'])` — aynı kaynak+mağaza+tip+değer tekil. `store_id` nullable; MySQL'de nullable kolonlu unique boşları ayrı kabul eder → güvenli.
- `index(['user_id', 'identity_kind', 'identity_value'])` — tip+değer ile party resolve.
- `index(['party_id', 'source_type'])` — party'nin kimlikleri.

### 3.2. Resolve Stratejisi (sıralı eşleştirme)

`PartyIdentityResolver` mevcut `CrmIdentityResolver` sıralı mantığını miras alır:

1. Kaynak kimliği + mağaza (`external_id` + `store_id`).
2. Normalize telefon (`identity_kind=phone`).
3. Normalize e-posta (`identity_kind=email`).
4. Vergi numarası (`identity_kind=tax_number`).
5. Normalize ad + şehir (zayıf sinyal).

Bu sıra, pazaryeri verilerinde sık görülen eksik telefon/farklı adı tolere eder ve mevcut CRM davranışıyla uyumludur.


## 4. `party_addresses` Gerekli mi?

**Karar: İlk fazda gerekli DEĞİL.** Gerekçe:

- Mevcut adres verisi snapshot olarak `channel_orders` (shipment_city/district), `mp_operational_orders` (customer_address), `supply_orders` (adres/ilce/il) ve `legal_entities.address` içinde duruyor.
- İlk party fazında adres doğrudan `parties`'in `city`/`district` alanlarından okunabilir; tam sokak adresi snapshot kaynaklarında kalır.
- İleri fazda (tedarikçi fatura/teslim adresi, kargo şube adresi, çok adresli müşteri) `party_addresses` tablosu eklenebilir; ama bu ilk PR'ı büyütmez.

**Tavsiye:** İlk migration'a `party_addresses` dahil **edilmez**. Model kararında "ileri faz" olarak işaretlenir. Bu, brief'in "büyük refactor yapma" kuralına uyar.

## 5. `crm_contacts.party_id` Eklenecek mi?

**Karar: Evet, nullable olarak.**

- `crm_contacts` tablosuna `party_id` (FK → parties, nullable, `nullOnDelete`) eklenir.
- CRM önce mevcut haliyle çalışmaya devam eder; `party_id` null olduğu sürece CRM davranışı değişmez.
- `CrmIdentityResolver::resolve()` akışı bozulmaz; party bağlama **ayrı bir adımda** (backfill / on-demand resolve) yapılır.
- Bu, brief'in "mevcut CRM akışlarını bozma" kuralına uyar: nullable alan eklemek geriye uyumludur.

### 5.1. Geçiş (backfill) stratejisi (öneri, ilk migration'da değil)

- Backfill **on-demand (resolve sırasında otomatik) DEĞİL**, ayrı artisan command (`party:backfill-from-crm`) olarak **sonraki onayla** yapılacak (Karar #4).
- Mevcut `crm_contacts` kayıtları için her contact'a karşılık bir `parties` kaydı + `customer` rolü oluşturan komut idempotent çalışır: contact'ın `party_id`'si varsa atlar.
- Bu komut ilk PR'a dahil **edilmez**; migration + model + test PR'ından sonra ayrı onayla gelir.

## 6. Mevcut `crm_contact_identities` ile İlişki

**Karar: `crm_contact_identities` korunur; `party_identities` üst katman olur.**

İki yaklaşım vardır; güvenli olanı seçiyoruz:

- **Seçilen (güvenli):** `crm_contact_identities` aynen kalır. `party_identities` CRM'i kapsayan üst katmandır. `crm_contact_identities.contact_id` → `crm_contacts.party_id` → `parties` zinciriyle dolaylı bağ kurulur. CRM resolve akışı (`CrmIdentityResolver`) bozulmaz.
- **Reddedilen (riskli):** `crm_contact_identities`'i `party_identities`'e taşıyıp eski tabloyu silmek — bu mevcut CRM'i bozar ve brief kuralını ihlal eder.

**İlişki diyagramı:**

```
parties (1) ─< party_roles (n)
parties (1) ─< party_identities (n)   [üst/ortak kimlik katmanı]
parties (1) ─< crm_contacts (1..n)     [crm_contacts.party_id nullable]
crm_contacts (1) ─< crm_contact_identities (n)   [korunur, CRM kaynak kimlikleri]
```

**İleri faz (opsiyonel):** `crm_contact_identities`'e `party_identity_id` (nullable FK) eklenerek çift yönlü köprü kurulabilir; ilk fazda gerekmez.

## 7. `party` ile `legal_entities` İlişkisi

**Karar: Birleştirme YOK; `parties.legal_entity_id` (nullable) ile zayıf ilişki.**

Gerekçe (veri otoritesi haritası 10. bölümle uyumlu):

- `legal_entities` = **kendi şirketim** (satıcı/tüzel kişi). Zaten `marketplace_stores`, `channel_orders`, `order_financial_events`, `cargo_carrier_accounts`, `shipments` tarafından kullanılıyor.
- `parties` = **karşı taraf** (müşteri/tedarikçi/pazaryeri/kargo/banka).
- Bu iki kavramı tek modelde birleştirmek "kendi şirketim" ile "müşterim" arasında yanlış tekilleştirmeye yol açar.

**İlişki:** `parties.legal_entity_id` (nullable) → "bu party, hangi şirketimin karşı tarafı". Çok şirketli tenant'larda her `legal_entity`'nin kendi cari kümi ayrışır. Tek şirketli kullanımda null kalabilir (eski davranış).

**Not:** `legal_entities`'e `party_id` eklenmez; `legal_entities` kendi başına bir kimlik otoritesidir ve party'nin "kendi şirketim" rolü olarak temsil edilmesi ilk fazda gerekmez.

## 8. Müşteri / Tedarikçi / Pazaryeri / Kargo / Banka Rolleri

Roller `party_roles.role` ile ayrılır. Bir party birden fazla role sahip olabilir.

| Rol | İlk faz | Karşı taraf örneği | Mevcut veri köprüsü |
| --- | --- | --- | --- |
| `customer` | Evet | Pazaryeri alıcı, manuel müşteri | `crm_contacts` → `party_id` |
| `supplier` | Evet (rol tanımı) | Tedarikçi (yeni) | — (tablo yok, party kurar) |
| `marketplace` | Hayır (ileri faz) | Trendyol/Hepsiburada karşı taraf | `marketplace_stores` (satıcı tarafı), `mp_transactions` |
| `carrier` | Hayır (ileri faz) | Kargo firması | `cargo_carrier_accounts` (ileride nullable `party_id`) |
| `bank` | Hayır (ileri faz) | Banka karşı taraf | — (yok) |

**İlk fazda sadece `customer` rolü için veri akışı kurulur.** Diğer rollerin `party_roles` satırları **oluşturulabilir** (model/rol tanımı hazırdır) ama mevcut veriden backfill yapılmaz. Bu, ilk PR'ı küçük ve güvenli tutar.

### 8.1. Pazaryeri rolü özel durumu

`marketplace_stores` satıcı tarafını (ZOLM kullanıcısının mağazası) temsil eder; pazaryeri **karşı tarafı** değildir. Pazaryeri karşı tarafı (örn. "Trendyol" kurumsal), `party_roles.role=marketplace` + `role_code=trendyol` ile modellenir ve `mp_transactions`/`mp_settlements` mutabakat finansı bu party'ye bağlanır. Bu, ilk fazda **yapılmaz**; rol tanımı hazırdır.


## 9. Geriye Uyumluluk Stratejisi

Bu kararlar, brief'in "mevcut akışları bozma" kuralına birebir uyar:

1. **Nullable geçiş alanları:** `crm_contacts.party_id`, `parties.legal_entity_id`, tüm party FK'ları nullable. Null iken eski davranış aynen çalışır.
2. **Mevcut tablolar silinmez/yeniden adlandırılmaz:** `crm_contacts`, `crm_contact_identities`, `crm_customer_ledger_entries` aynen kalır.
3. **Mevcut servisler bozulmaz:** `CrmIdentityResolver`, `CrmProjectionService`, `CrmCustomerLedgerProjectionService` ilk PR'da değiştirilmez. Party katmanı **paralel** yeni bir resolver/model olarak eklenir.
4. **Unique index'ler güvenli:** `parties` ilk fazda unique index taşımaz (canlı veriyi kırmaz). `party_identities` unique'inde `store_id` nullable olduğu için boş kayıtlar çakışmaz.
5. **`user_id` izolasyonu korunur:** Her yeni tablo `user_id` FK ile tenant izolasyonu taşır; brief'in izolasyon kuralına uyulur.
6. **Feature flag:** `party_core_enabled` flag'i ile party katmanı kademeli açılır; **default `false`** (Karar #5). Kapalıyken CRM/eski davranış aynen çalışır (Yol Haritası Faz 0/Sprint #10).
7. **Pazaryeri akışı bozulmaz:** `channel_orders`/`order_financial_events`/`mp_*` tablolarına ilk PR'da **hiç dokunulmaz**.
8. **Backfill ayrı adım:** Mevcut veriyi party'ye taşıyan artisan komutu ilk PR'a dahil edilmez; migration + model + resolver iskeletinden sonra ayrı onayla gelir.

## 10. İlk Migration Kapsamı (Taslak — Onay Bekliyor)

> **Onay alındı:** Aşağıdaki kapsam, onaylanan kararlarla (bölüm 12) birlikte yazılır. Kapsam: 4 geriye uyumlu migration + 3 model + migration/model testleri. UI, backfill ve mevcut servis entegrasyonu bu PR'da yok.

Önerilen migration dosyaları (hepsi geriye uyumlu, nullable, user_id izolasyonlu):

- `create_parties_table`
- `create_party_roles_table`
- `create_party_identities_table`
- `add_party_id_to_crm_contacts_table`

**Kurallar (brief 3. madde):**

- Migration backward compatible olacak.
- Var olan tablo/kolon silinmeyecek.
- `nullable` geçiş alanları kullanılacak.
- Unique index'ler canlı veriyi kırmayacak şekilde tasarlanacak.
- `user_id` izolasyonu korunacak.

**Yapılmayacaklar (ilk migration):**

- `party_addresses` eklenmeyecek (ileri faz).
- `legal_entities`'e `party_id` eklenmeyecek.
- `channel_orders`/`cargo_carrier_accounts` vb.'ne `party_id` eklenmeyecek (ileri faz).
- Backfill/artisan komutu yazılmayacak.
- Mevcut servisler değiştirilmeyecek.

## 11. Kabul Kriterleri Eşlemesi

Brief'in "Kabul Kriterleri" ile bu dokümanın eşlemesi:

- [x] Mevcut CRM/cari/marketplace finans veri otoritesi haritası çıkarıldı → `docs/party-core-veri-otoritesi-haritasi.md`
- [x] `party` model karar dokümanı yazıldı → bu doküman
- [x] İlk migration taslağı geriye uyumlu şekilde hazırlandı → 4 migration (`parties`, `party_roles`, `party_identities`, `add_party_id_to_crm_contacts`) yazıldı
- [x] Mevcut CRM akışlarını bozacak değişiklik yapılmadı → mevcut `CrmIdentityResolver`, `CrmProjectionService`, `CrmCustomerLedgerProjectionService`, route, UI ve backfill **değiştirilmedi**
- [x] Büyük modül/refactor çalışmasına başlanmadı

### 11.1. Migration/Model/Test Fazı Durumu (tamamlandı)

Kod/test değişikliği yapıldı, ancak **yalnızca onaylı kapsamda**:

- **Migration'lar:** `create_parties_table`, `create_party_roles_table`, `create_party_identities_table`, `add_party_id_to_crm_contacts_table` (hepsi geriye uyumlu, nullable, `user_id` izolasyonlu).
- **Modeller:** `Party`, `PartyRole`, `PartyIdentity` + `CrmContact::party()` ilişkisi (salt okunur belongsTo, geriye uyumlu).
- **Testler:** `PartyCoreMigrationTest` (8 test) + `PartyCoreModelTest` (11 test).

### 11.2. Bu PR'da yapılmayanlar (kapsam dışı)

- **Mevcut CRM servisleri** (`CrmIdentityResolver`, `CrmProjectionService`, `CrmCustomerLedgerProjectionService`) **değiştirilmedi** — party katmanı paralel eklendi (Karar #6).
- **Route / UI / backfill command** yok (brief kapsamı dışında).
- **Feature flag config** (`party_core_enabled`) bu PR'da **kod olarak yok**. Karar #5'te netleşti (default `false`); **sonraki adımda `PartyIdentityResolver` ile birlikte eklenecek**.

### 11.3. Test Sonuçları

Testler Laravel Sail ile çalıştırıldı (`./vendor/bin/sail artisan test`):

| Test dosyası | Sonuç |
| --- | --- |
| `tests/Feature/PartyCoreMigrationTest.php` | 8 passed (52 assertions) |
| `tests/Feature/PartyCoreModelTest.php` | 11 passed (25 assertions) |
| `tests/Feature/CrmWorkspaceTest.php` (bozulmadı doğrulaması) | 5 passed (28 assertions) |
| `tests/Feature/CrmCustomerLedgerTest.php` (bozulmadı doğrulaması) | 4 passed (28 assertions) |

**Toplam: 28 test, 133 assertion — hepsi PASSED.** Mevcut CRM akışları bozulmadığı doğrulandı.

## 12. Açık Sorular — Kapatılan Kararlar

Aşağıdaki kararlar onaylanmış ve migration/model/test kapsamına girilmiştir:

1. **`party_roles` unique kısıtı (KAPATILDI):** `unique(['user_id', 'party_id', 'role'])` olacak. `role_code` unique'e **dahil edilmez**; nullable kalır ve ayrı bir `index(['user_id', 'role_code'])` ile aranır. Bu, nullable kolonlu composite unique'in MySQL'deki belirsiz davranışını önler.
2. **Çok şirketli tenant varsayılanı (KAPATILDI):** `parties.legal_entity_id` **nullable kalacak**. Default/legal_entity atanmayacak. Tek şirketli kullanımda null kalır (eski davranış).
3. **`parties.party_type` değerleri (KAPATILDI):** `person`, `organization`, `unknown`. İlk faz default **`unknown`**. TCKN/vergi no ayrımı ilk fazda resolve'da kullanılmaz; `tax_number` tek alan olarak tutulur.
4. **Backfill tetikleme (KAPATILDI):** Backfill **on-demand (resolve sırasında otomatik) DEĞİL**. Ayrı artisan command (`party:backfill-from-crm`) olarak **sonraki onayla** yapılacak. İlk migration/model/test PR'ında backfill yok.
5. **Feature flag (KAPATILDI):** `party_core_enabled` feature flag **default `false`** olacak. Kapalıyken CRM/eski davranış aynen çalışır.
6. **Mevcut servis dokunulmazlığı (KAPATILDI):** Mevcut `CrmIdentityResolver`, `CrmProjectionService` ve canlı CRM akışları **değiştirilmeyecek**. Party katmanı paralel yeni modeller/migration olarak eklenir.

> Not: `docs/pazaryeri-ayarlar-raporu.md` ve `tests/Feature/MarketplaceSettingsTest.php` ayrı bir iş koludur (Mimo); bu PR kapsamı dışında kalır ve dokunulmaz.

