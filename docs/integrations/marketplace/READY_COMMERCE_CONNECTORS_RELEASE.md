# E-Ticaret Connector'ları Hazır Kullanım Sürümü

**Tarih:** 2026-07-22  
**Durum:** Hazır  
**Kapsam:** Shopify, ikas, IdeaSoft, Ticimax, T-Soft ve Adobe Commerce / Magento

## Özet

Altı e-ticaret connector'ı entegrasyon seçim ekranında `ready` durumuna geçirildi. Kullanıcı mağazayı oluşturup sağlayıcının verdiği API bilgilerini kaydettiğinde bağlantı readiness kontrolünden geçer, mağaza `configured` olur ve varsayılan senkron profili scheduler tarafından kullanılabilir.

Eski uygulama ve kabul dokümanlarının dosya adlarında geçen `PILOT` ifadesi geliştirme geçmişini belirtir. Kullanıcıya gösterilen yayın durumu ve güncel çalışma kararı bu belge ile ADR-007'dir.

## Kullanıcı akışı

1. Firma seçilir ve kanal mağazası oluşturulur.
2. ZOLM kullanıcıyı doğrudan API bilgileri ekranına geçirir.
3. Sağlayıcıya özel URL, anahtar, secret/token ve gerekiyorsa mağaza/lokasyon kodları kaydedilir.
4. Zorunlu alanlar tamamsa bağlantı `configured` durumuna gelir.
5. Sipariş, ürün, finans ve iade/claim okuma profilleri varsayılan açık çalışır.
6. İlk başarılı senkron bağlantının canlı API erişimini doğrular ve `last_verified_at` kaydını günceller.

IdeaSoft'ta API bilgileri kaydedildikten sonra sağlayıcının zorunlu OAuth onay ekranı bir kez tamamlanır. Bu dış sağlayıcı güvenlik adımı ZOLM tarafından atlanamaz.

## Varsayılan özellikler

| Özellik | Varsayılan |
|---|---:|
| Sipariş okuma | Açık |
| Ürün okuma | Açık |
| Desteklenen finans/ödeme/fatura özeti okuma | Açık |
| Desteklenen iade/claim okuma | Açık |
| Sağlayıcının desteklediği webhook işleme | Açık |
| Fiyat yazma | Kapalı |
| Stok yazma | Kapalı |

Fiyat ve stok işlemleri veri değiştirdiği için API bağlantısının hazır olmasından ayrı değerlendirilir. Kullanıcı ilgili yazma yetkilerini sağlayıcıda açtıktan ve ZOLM senkron profilinde bilerek etkinleştirdikten sonra kullanılabilir.

## Veri modeli ve migration etkisi

- Migration yoktur.
- Yeni mağazalar mevcut `IntegrationSyncProfile` varsayılanlarıyla oluşturulur.
- Mevcut mağazaların kullanıcı tarafından değiştirilmiş sync profilleri otomatik ezilmez.
- Credential değerleri mevcut şifreli bağlantı alanlarında saklanır.

## Geriye uyumluluk

- Mevcut connector, endpoint ve normalizasyon sözleşmeleri değişmedi.
- Fiyat/stok yazmaları otomatik açılmadığı için mevcut veri otoritesi korunur.
- Amazon `access_required` olarak kalır; gerçek connector ve SP-API onboarding tamamlanmadan hazır gösterilmez.
- Adobe Commerce as a Cloud Service farklı IMS adapterı gerektirdiği için Magento PaaS/on-prem bağlantısına dahil değildir.

## Doğrulama

- Registry durum testi altı connector'ın `ready` olduğunu sabitler.
- Livewire form testleri sağlayıcıya özel kimlik alanları ve hazır durumunu doğrular.
- Sync default testi tüm desteklenen salt-okuma akışlarının açık, fiyat/stok yazmalarının kapalı olduğunu doğrular.
- Capability parity testi UI ilanlarıyla gerçek connector kabiliyetlerinin eşit kalmasını sağlar.
- İlgili connector ve ortak senkron regresyon paketi 147 test / 727 assertion ile geçti.
- Yerel entegrasyon ekranında seçim listesinde `Pilot` etiketi kalmadığı doğrulandı.
- 390x844 görünümde yeni buton ve form yatay taşma üretmedi.

## Riskler ve sınırlamalar

- Yanlış veya eksik API bilgileri ilk bağlantı/senkron isteğinde sağlayıcı hatası üretir; ZOLM bunu bağlantı hatası olarak kaydeder.
- Ticimax ve T-Soft'ta ilgili web servis lisansı ve metot/IP yetkileri sağlayıcı tarafında açık olmalıdır.
- IdeaSoft OAuth onayı tamamlanmadan senkron başlamaz.
- Magento bağlantısı yalnız Magento Open Source ve Adobe Commerce PaaS/on-prem içindir.
- Finans yüzeyleri her sağlayıcıda banka/pazaryeri settlement anlamına gelmez; bazıları ödeme veya fatura özetidir.

## Geri alma planı

- Registry durumları tekrar kısıtlı bir duruma alınabilir.
- Okuma sync flag'leri mağaza bazında kapatılabilir.
- Yeni varsayılanlar config üzerinden geri alınabilir; mevcut mağaza profilleri ve veriler silinmez.
- Migration rollback gerekmez.

## Önerilen commit planı

1. `feat: publish commerce connectors as ready`
2. `test: enforce ready provider and read sync defaults`
3. `docs: document ready commerce connector release`

Commit oluşturulmadı; kullanıcıya ait worktree değişiklikleri stage edilmedi.

## Notion taslağı

**Başlık:** E-Ticaret Connector'ları Hazır Kullanım Sürümü  
**Özet:** Shopify, ikas, IdeaSoft, Ticimax, T-Soft ve Magento connector'ları hazır duruma geçirildi; kullanıcı API bilgilerini kaydettikten sonra read-only senkronları kullanabilir.  
**İş ihtiyacı ve kullanıcı etkisi:** Pilot etiketi kaldırıldı; sipariş, ürün, finans ve iade verileri yeni bağlantılarda varsayılan açık.  
**Teknik yaklaşım:** Provider registry `ready`, readiness kontrollü `configured` bağlantı, güvenli sync defaults ve capability parity.  
**Veri modeli / migration:** Migration yok; mevcut şifreli credentials ve sync profile modeli kullanılıyor.  
**Kullanım:** Mağaza oluştur, API bilgilerine geç, sağlayıcı bilgilerini kaydet; IdeaSoft için OAuth onayını tamamla.  
**Yetki / feature flag:** Read-only akışlar açık; fiyat/stok yazmaları bilinçli kullanıcı etkinleştirmesi ister.  
**Test kapsamı:** Registry, Livewire rehberleri, sync defaults, connector parity ve 390 px responsive kontrol.  
**Bilinen sınırlamalar:** Sağlayıcı lisans/scope şartları devam eder; Magento SaaS ayrı adapter gerektirir.  
**Geri alma:** Registry/default config geri alınır; migration yok.  
**Commit / PR:** Henüz oluşturulmadı.  
**Yayın tarihi:** Belirlenecek  
**Sorumlu:** Belirlenecek

## Slack taslağı

🚀 E-ticaret connector'ları hazır kullanıma alındı

- Ne değişti: Shopify, ikas, IdeaSoft, Ticimax, T-Soft ve Magento seçimlerindeki Pilot etiketi kaldırıldı ve provider durumları `ready` yapıldı.
- Kullanıcıya etkisi: Kullanıcı mağazayı oluşturup API bilgilerini kaydettiğinde sipariş, ürün, finans ve iade okuma akışlarını doğrudan kullanabilir.
- Test durumu: Registry/Livewire/default/parity kontrolleri güncellendi; masaüstü ve 390 px mobil form doğrulandı.
- Yayın / feature flag durumu: Read-only akışlar açık; veri değiştiren fiyat/stok işlemleri kullanıcı açana kadar kapalı.
- Dikkat edilmesi gerekenler: IdeaSoft OAuth onayı, Ticimax/T-Soft lisans ve yetkileri, Magento PaaS/on-prem sınırı devam ediyor.
- Dokümantasyon: `docs/integrations/marketplace/READY_COMMERCE_CONNECTORS_RELEASE.md`
- PR / commit: Henüz oluşturulmadı.
