# ZOLM AI Müşteri İletişim Merkezi — Faz 1 Kabul Kararı

## Karar

**FAZ 1: KABUL EDİLDİ**

**FAZ 2: HENÜZ UYGULAMAYA AÇILMADI**

Faz 1 Kalite Kapısı 01 kapsamındaki mimari ve test revizyonları gerçek dosya içerikleri, diff ve bağımsız test çalıştırması üzerinden doğrulanmıştır.

## Kabul Edilen Çıktılar

- Altı mimari karar kaydı oluşturuldu ve kapsamları netleştirildi.
- Customer Care ana ve alt özellik bayrakları güvenli biçimde varsayılan kapalı tanımlandı.
- Bilinmeyen alt özellik anahtarları dahil feature middleware fail-closed davranıyor.
- Customer Care route'u auth ve feature middleware arkasında bulunuyor.
- Menüsüz, verisiz ve otomatik işlem yapmayan minimal Livewire giriş ekranı oluşturuldu.
- Varsayılan otomasyon modu `manual`, otomatik yanıt varsayılanı `false` olarak korundu.
- Faz 1 kapsamında migration, model, adapter, generic outbox, AI çağrısı, provider binding, sidebar girişi veya otomatik cevap eklenmedi.

## Kabul Edilen Mimari Yönler

1. `support_*`, birleşik inbox projection çekirdeğidir; kanal kayıtları source-of-truth olarak korunur.
2. Tenant/organizasyon modeli Faz 2'de kesinleştirilmeden `store_id` kanonik tenant kimliği sayılmaz ve global scope eklenmez.
3. `support_messages` iş kaydıdır; generic teslimat yaşam döngüsü ileride `support_dispatches` ve append-only `support_dispatch_attempts` ile kurulur.
4. Customer Care AI sınırı provider bağımsız ve production'da fail-closed olacaktır; kod uygulaması Faz 6'dır.
5. Generic bilgi merkezi modeli henüz `Proposed` durumundadır; Faz 7 öncesi kalite kapısında karara bağlanacaktır.
6. Conversation lifecycle, ownership ve automation mode birbirinden bağımsız üç eksendir; human ownership kilidi otomatik gönderimin önüne geçer.

## Baş Mühendis Editoryal Temizliği

Kalite kapısı sonrası uygulama davranışını değiştirmeyen dört dokümantasyon tutarlılığı düzeltildi:

- ADR-001'de projection yaklaşımına mutlak tenant güvenliği atfeden eski ifade kaldırıldı.
- ADR-002'de Faz 1/Faz 2 zamanlama cümlesi ve mutlak izolasyon iddiası düzeltildi.
- ADR-005'te `store_id`'yi veritabanı seviyesinde zorunlu tenant anahtarı sayan, ADR-002 ile çelişkili ifade kaldırıldı.
- ADR-006'da concurrency ve insan/AI çakışmasına ilişkin mutlak garanti dili risk azaltıcı kontroller olarak düzeltildi.

## Bağımsız Doğrulama

```text
CustomerCareFeatureTest: 8 passed
WhatsApp SupportChannelTest: 10 passed, 1 risky
Toplam: 18 passed, 1 risky, 37 assertions
git diff --check: PASS
customer-care route: auth -> customer-care.feature:inbox_enabled
```

Risky test, Faz 0'da kayıt altına alınan `whatsapp raw payload not in support message` testindeki boş assertion problemidir. Faz 1 kabulünü engellemez; ilgili düzeltme kendi kapsamına alınmalıdır.

## Sonraki Kapı

Faz 2 başlamadan önce ayrı ve bağlayıcı bir Antigravity promptu hazırlanacaktır. Faz 2'nin temel amacı tenant/organizasyon sınırını karara bağlamak, authorization yaklaşımını tasarlamak ve çapraz-tenant negatif güvenlik testlerini kurmaktır. Faz 2 kapsamı hazırlanmadan migration veya production veri modeli değişikliği yapılmayacaktır.
