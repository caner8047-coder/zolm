# Kar Merkezi Hesap Dogrulugu

Bu dokuman Kar Merkezi metriklerinin hangi kaynaktan beslendigini ve hangi edge-case davranislarinin korunmasi gerektigini tanimlar.

## Kaynak Onceligi

- Ciro: order-level profit snapshot varsa `gross_revenue`, yoksa siparis kalemlerinin `gross_amount` toplami.
- Net alacak: snapshot varsa `net_receivable`, yoksa finans olaylarindaki signed seller revenue ve kesinti hareketleri toplami.
- Kesinti toplamı: snapshot varsa komisyon/kargo/hizmet/stopaj alanlari, yoksa finans olaylarindaki debit hareketlerinin pozitif toplam karsiligi.
- Kâr: snapshot `profit_state=confirmed` ise `confirmed_profit`, aksi durumda `estimated_profit`. Snapshot yoksa kâr degeri `0` kabul edilir ve siparis `snapshot_missing` olarak isaretlenir.
- Finans kapsama: siparise bagli kesinlesmis en az bir finans olayi varsa hazir, yoksa bekleyen.

## Finans Event Tipleri

Kar Merkezi reconciliation hesabi normalize pazaryeri event tiplerinin yaninda eski/connector bazli tipleri de okur:

- Satici geliri neti: `seller_revenue`, `sale`, `capture`, `refund`, `void`
- Hizmet/odeme kesintisi: `service_fee`, `deduction_invoice`, `fee`
- Komisyon: `commission`
- Kargo kesintisi: `cargo`, `return_cargo`
- Stopaj: `withholding`
- Reklam: `advertising`, `advertising_fee`, `ads`
- Ceza: `penalty`, `operational_penalty`
- Erken odeme: `early_payment_fee`
- Uluslararasi hizmet: `international_service_fee`, `international_operation_fee`
- Kampanya indirimi: `campaign_discount`, `marketplace_discount`
- Diger finansal gider: `other_invoice`, `other_financial_cost`

Kategori sozlugu `FinancialEventClassifier`, tutar toplama ve ters kayit netlestirmesi
`MarketplaceCostBreakdownService` tarafindan tek merkezden yonetilir. Yeni gider tipleri
snapshot semasi genisletilmeden ilk asamada `service_fee_total` icine katlanir; detay
kategori bilgisi servis sonucunda korunur.

Finans olayi hazir sayilmak icin hem yukaridaki kesinlestirici event tiplerinden birine sahip olmali hem de status alani kesinlesmemis bir durumda olmamalidir. `pending`, `draft`, `authorized`, `authorization`, `failed`, `failure`, `error`, `cancelled`, `canceled`, `declined` ve `expired` statusleri finans kapsamasi, net alacak ve kesinti metriklerine dahil edilmez.

Bu nedenle Shopify tarafindan gelen `authorization` hareketi veya statusu `pending` olan `sale`/`fee` hareketi siparisi `finance_ready` yapmaz; karar kuyrugunda "Finans bekliyor" olarak kalir.

Tutar isareti `direction` ile belirlenir:

- `credit`: pozitif gelir veya kesinti iadesi.
- `debit`: negatif gelir hareketi, iade veya kesinti.

Bu nedenle iade/iptal hareketleri `refund` ya da `seller_revenue` tipiyle `debit` geldiginde net alacagi azaltir. Komisyon iadesi `commission` tipiyle `credit` geldiginde net komisyon kesintisini azaltir.

Snapshot uretiminde kesinlesmis finans varsa confirmed net alacak formulu:

`seller_revenue_net - commission_total - cargo_total - service_fee_total - withholding_total`

## KDV Etkisi

V2 snapshot tarafinda KDV etkisi `MpSettingsService::isKdvEnabled()` kapaliysa `0` kalir. Varsayilan davranis budur.

KDV hesaplama aciksa:

- Satis KDV'si brüt satis tutarinin icindeki KDV olarak hesaplanir.
- Satis KDV oranı sirasi: `channel_order_items.vat_rate`, eslesmis `mp_products.vat_rate`, sistem varsayilani `tax.default_product_vat_rate`.
- Oran `1`, `10`, `20` gibi yuzde veya `0.01`, `0.10`, `0.20` gibi decimal gelebilir; hesap motoru ikisini de normalize eder.
- Iptal/iade yasam dongusu durumlarinda satis KDV'si `0` kabul edilir.
- Gider KDV'si ilk fazda komisyon ve kargo kesintilerinden hesaplanir.
- Gider KDV oranı `tax.expense_vat_rate` ayarindan okunur.

Snapshot `vat_effect` alani:

`sales_vat - expense_vat`

Pozitif deger kârı azaltan KDV yuku, negatif deger kâra eklenen KDV avantajidir. Hem `estimated_profit` hem `confirmed_profit` bu `vat_effect` degeri dusulerek hesaplanir.

## Stopaj Etkisi

V2 snapshot tarafinda stopaj iki kaynakla hesaplanir:

- Pratik stopaj: kesinlesmis finans event'leri icinde `withholding` varsa bu tutar kullanilir.
- Teorik stopaj: `tax.estimated_withholding_enabled` aciksa ve pratik stopaj yoksa hesaplanir.

Teorik stopaj varsayilan olarak kapali tutulur. Acildiginda stopaj matrahi siparis satirlarinin KDV haric bazindan uretilir:

`sum(line_gross / (1 + line_vat_rate)) * tax.stopaj_rate`

KDV orani fallback sirasi KDV etkisiyle aynidir: item KDV orani, eslesmis urun KDV orani, sistem varsayilani. Iptal/iade yasam dongusu durumlarinda teorik stopaj uretilmez.

Snapshot `withholding_total` alani pratik event varsa pratik tutari, yoksa teorik stopaj aciksa teorik tutari tasir. Bu deger `net_receivable`, `estimated_profit`, `confirmed_profit` ve Kar Merkezi kesinti kiriliminda dikkate alinir.

## Snapshot Backfill

KDV/stopaj, maliyet veya finans sozlugu degisikliklerinden sonra mevcut V2 siparisleri icin order-level snapshot yeniden hesaplanabilir:

```bash
php artisan marketplace:backfill-profit-snapshots --store=123 --missing
php artisan marketplace:backfill-profit-snapshots --store=123 --from=2026-06-01 --to=2026-06-30
php artisan marketplace:backfill-profit-snapshots --store=123 --order=ORDER-123
php artisan marketplace:backfill-profit-snapshots --marketplace=trendyol --limit=500 --dry-run
```

Guvenlik kurallari:

- Filtresiz tum sistemi calistirmak icin `--all` zorunludur.
- `--dry-run` aday siparisleri gosterir, snapshot yazmaz.
- `--missing` sadece order-level snapshot olmayan siparisleri hesaplar.
- `--limit` ve `--chunk` buyuk backfill islerinde kademeli ilerlemek icin kullanilir.

## Fiyatlandirma Simulasyonu

`MarketplacePricingSimulationService` urun karti ve kanal listing verisini degistirmeden
senaryo hesaplar. Pazaryerine fiyat gonderimi bu fazda yapilmaz.

Net kar formulu:

`satis fiyati - komisyon - urun maliyeti - ambalaj - kargo - hizmet - reklam - iade rezervi - diger gider - stopaj - net KDV`

- Komisyon, hizmet, reklam ve iade rezervi satis fiyati uzerinden oranlanir.
- Iade rezervine beklenen iade kargo etkisi de oranli olarak eklenir.
- KDV aciksa satis KDV'si ile maliyet/gider KDV kredileri ayrilir.
- Mikro ihracat seciminde satis KDV'si `0` kabul edilir.
- Stopaj mikro ihracattan otomatik turetilmez; gercek pazaryeri davranisina gore ayri kontrol edilir.
- Basabas ve hedef fiyat, ayni hesap motoru uzerinde ikili arama ile bulunur.
- Senaryolar `marketplace_pricing_scenarios` tablosuna input ve sonuc JSON'u ile kaydedilir.
- Public `/tools/trendyol-kar-hesaplama` araci ayni motoru kullanir ancak veritabanina senaryo yazmaz.
- Public Livewire guncellemeleri sadece izin verilen public component icin auth olmadan calisir; diger Livewire component'leri update endpoint'inde auth korumasini surdurur.

## Mutabakat Durumu

- `waiting`: finans olayi yok.
- `snapshot_missing`: finans olayi var ancak order-level profit snapshot yok.
- `aligned`: kâr ve kesinti farki 10 TL veya altinda.
- `minor`: farklar 50 TL ya da cironun %3 esigi icinde.
- `material`: yukaridaki esikleri asan fark.

## Maliyet Hazirligi

Bir siparis kalemi maliyet hesabina hazir sayilmak icin:

- Urun karti ile eslesmis olmali.
- `cogs` degeri 0'dan buyuk olmali.
- `packaging_cost` degeri 0'dan buyuk olmali.

COGS veya ambalaj maliyetinden biri eksikse satir `missing_cost_lines` icinde sayilir. Urun eslesmesi yoksa `unmatched_lines` icinde sayilir.

## Korunan Test Senaryolari

- Snapshot olmayan ama finans olayi olan sipariste ciro ve kesintiler finans/kalem kaynaklarindan turetilir.
- Snapshot olmayan siparis `snapshot_missing` durumuna duser ve karar kuyrugunda "Kâr kaydı eksik" nedeniyle gorunur.
- Snapshot olmayan iadeli sipariste `sale`, `refund`, `fee` ve ters yonlu `commission` hareketleri net alacak/kesinti hesabina dahil edilir.
- `authorization` veya statusu `pending` olan finans olaylari siparisi finans hazir saymaz, net alacak/kesinti metriklerini artirmaz ve `snapshot_missing` alarmi uretmez.
- Confirmed snapshot uretiminde komisyon kesintisi net alacaktan dusulur; sadece kargo/hizmet/stopaj degil tum ana kesintiler dikkate alinir.
- KDV hesaplama kapaliyken `vat_effect=0` kalir; acikken item KDV orani ve urun KDV fallback'i snapshot kârini duzeltir.
- Pratik `withholding` event'i teorik stopajdan onceliklidir; teorik stopaj sadece `tax.estimated_withholding_enabled` acikken ve event yokken uretilir.
- Snapshot backfill komutu dry-run modunda yazmaz, `--missing` mevcut snapshot'lari ellemez ve `--order` filtresi sadece hedef siparisi yeniden hesaplar.
- Ambalaj maliyeti eksik urunler maliyet hazirliginda eksik sayilir.
- COGS/ambalaj eksikleri urun performans listesinde `Eksik` etiketi ve "Maliyeti tamamla" aksiyon ipucuyla gorunur.
