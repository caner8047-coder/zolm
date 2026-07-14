# ZOLM AI Müşteri İletişim Merkezi — Entegrasyon ve Bağlantı Kontrol Raporu

Tarih: 2026-07-14  
Ortam: Lokal geliştirme ortamı (`APP_URL=http://localhost`)

## Sonuç

Modülün kod, rota, komut, menü ve güvenlik katmanları repo içinde mevcut. Ancak mevcut mağaza kayıtlarından `support_channels` kayıtları henüz toplu şekilde oluşturulmamış. Bu yüzden modül UI'da açılıyor olsa bile seçili mağazalarda “aktif iletişim kanalı bulunamadı” durumu beklenen sonuçtur.

Baş mühendis kararı: Modül teknik olarak ayağa kalkmış; operasyonel bağlantı ayağı için güvenli bulk provisioning ve mağaza bazlı sertifikasyon çalıştırılmalıdır.

## Bağlantı Envanteri

| Alan | Sonuç |
|---|---:|
| Toplam mağaza | 3466 |
| Aktif mağaza | 2420 |
| Tanımlı Customer Care support channel | 1 |
| Aktif Customer Care support channel | 0 |
| Aktif WhatsApp hesabı | 0 |
| Support outbound queue bekleyen | 0 |
| Support outbound retry | 0 |
| Support outbound exhausted | 0 |
| Integration delivery dead-letter | 0 |
| Customer Care route sayısı | 30 |
| Customer Care artisan komut sayısı | 39 |

## Provision Edilebilir Kanal Adayları

Bu tablo aktif mağazalardan güvenli varsayılanlarla (`is_enabled=false`, `ai_mode=manual`, `auto_reply=false`) oluşturulabilecek Customer Care kanal adedini gösterir.

| Kaynak | Oluşacak kanal | Aktif mağaza | Mevcut kanal | Aday |
|---|---|---:|---:|---:|
| Trendyol | `trendyol` | 1316 | 1 | 1315 |
| Hepsiburada | `hepsiburada` | 274 | 0 | 274 |
| N11 | `n11` | 73 | 0 | 73 |
| Shopify | `web_chat` | 357 | 0 | 357 |
| WooCommerce | `web_chat` | 184 | 0 | 184 |

Toplam doğrudan aday: 2203 kanal.

## Marketplace Genel Health Özeti

`marketplace:health-check` sonucunda görülen kritik notlar:

- Marketplace V2 açık.
- Listing push açık.
- Order actions açık.
- Aktif mağaza: 2420.
- Hazır bağlantı: 885.
- Smoke test hazır mağaza: 868.
- Eksik alanlı mağaza: 2598.
- Webhook açık mağaza: 935.
- Son 24 saatte başarısız sync kayıtları var.
- Son 24 saatte başarısız sipariş aksiyonları var.
- Local ortam `APP_URL=http://localhost` ve HTTPS değil; production health için doğal uyarıdır.

## Bu Kontrolde Kapatılan Bağlantı Boşlukları

1. `meta_social` kanal anahtarı `SupportChannelManager` içine eklendi.
   - Önce: Provision edilen `meta_social` kanalı gerçek adapter yerine `NullSupportChannelAdapter` tarafına düşebilirdi.
   - Sonra: `meta_social`, `instagram` ve `facebook` adapter ailesiyle çözülür.

2. Meta Social provider alias’ları hizalandı.
   - `meta_social`, `meta`, `instagram`, `facebook` bağlantıları aynı sertifikasyon ve capability akışı tarafından tanınır.

3. Google Business provider alias’ları hizalandı.
   - `google_business`, `google`, `google_reviews` bağlantıları aynı Google Business adapter ve sertifikasyon akışı tarafından tanınır.

4. WhatsApp connector sertifikasyonu düzeltildi.
   - `IntegrationConnection` kaydı beklemek yerine aktif `WaAccount` da geçerli connector binding sayılır.

## Doğrulama

Hedefli entegrasyon test paketi:

```text
Tests: 47 passed (297 assertions)
```

Kapsanan alanlar:

- `CustomerCareConnectorCertificationTest`
- `MetaSocialSupportChannelAdapterTest`
- `GoogleBusinessSupportChannelAdapterTest`
- `WhatsAppSupportChannelAdapterTest`
- `SupportChannelAdapterContractTest`

## Ayağa Kaldırma Sırası

1. System Actor’ın production ortamında hazır olduğundan emin ol.
2. Dry-run çalıştır:

```bash
./vendor/bin/sail artisan customer-care:provision-channels --all
```

3. Beklenen aday sayıları doğruysa gerçek provizyonu çalıştır:

```bash
./vendor/bin/sail artisan customer-care:provision-channels --all --execute
```

4. Oluşan kanallar kapalı gelir. Pilot mağazada Ayarlar ekranından yalnız gerekli kanalı aç.
5. Aynı mağaza için connector sertifikasyonu çalıştır:

```bash
./vendor/bin/sail artisan customer-care:certify-connectors --store=STORE_ID
```

6. Pilot hazırlık kontrolünü çalıştır:

```bash
./vendor/bin/sail artisan customer-care:pilot-readiness --store=STORE_ID
```

7. Otomatik cevap sadece kalite kapıları, golden eval ve circuit breaker temizken açılmalı. Varsayılan çalışma modu manuel/copilot kalmalıdır.

## Baş Mühendis Notu

Şu anki en büyük boşluk kod değil, veri aktivasyonu. Kanalları toplu oluşturup mağaza bazlı sertifikasyon + readiness ile ilerlersek modül kontrollü biçimde üretim pilotuna hazırlanır.
