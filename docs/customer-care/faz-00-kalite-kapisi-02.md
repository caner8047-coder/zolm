# Faz 0 Kalite Kapısı 02 — Küçük Tutarlılık Revizyonu

**Karar:** `REVİZYON GEREKLİ — KÜÇÜK/TUTARLILIK`  
**Faz 1 durumu:** Bu düzeltmeler tamamlanana kadar kapalı  
**İnceleyen:** Codex / Baş mühendis  
**Tarih:** 2026-07-11

## Genel değerlendirme

Faz 0 Revizyon 01'de istenen 10 ana düzeltme büyük ölçüde doğru uygulanmıştır. Raporun teknik yönü kabul edilebilir duruma gelmiştir. Ancak eski sürümden kalan aşağıdaki çelişkiler temizlenmeden belge source-of-truth olarak onaylanmayacaktır.

Antigravity yalnız `docs/customer-care/00-mevcut-durum-dogrulama.md` dosyasını değiştirecektir. Uygulama kodu, test, config, migration, route veya başka doküman değiştirilmeyecektir.

## Zorunlu düzeltmeler

### 1. Eski `releaseHandoff()` boşluk satırını kaldır

“Oluşturulması Gerçekten Gereken Yeni Parçalar” tablosindeki aşağıdaki satır artık yanlıştır:

```text
Human ownership lock serbest bırakma | releaseHandoff() eksik
```

Bunu şu anlamla değiştir:

```text
Human ownership state machine | resolve() fonksiyonel geri bırakma yapıyor; ayrı yetki/policy, concurrency ve auditli release kararı eksik
```

### 2. Yinelenen `MarketplaceQuestion*` satırını kaldır

“Yeniden Kullanılacak Sınıf ve Tablolar” bölümünde aynı `MarketplaceQuestion* ailesi` satırı iki kez bulunuyor. Tek satır bırak.

### 3. `Faz 1'e Geçişi Engelleyen Açık Kararlar` bölümünü mimari kararlarla uzlaştır

Bu bölümde aşağıdaki maddeler artık “Codex onayı bekleniyor” diye gösterilemez:

- `support_*` canonical kararı — Section 21 ile onaylandı.
- MarketplaceQuestion projection modeli — Section 21 ile idempotent projection olarak onaylandı.
- Başlangıç güven eşiği — şimdi seçilmeyeceği, Faz 9–10'da kalibre edileceği onaylandı.
- Database queue/Redis — şimdilik database queue korunacağı onaylandı.
- KVKK alt işleyici belgesi — Faz 1 dokümantasyonunu değil, gerçek veriyle provider/pilotu bloklar.
- Organization kararı — Faz 1 ADR çıktısıdır; Faz 1'e başlamanın ön koşulu değildir.

Bu bölümü iki alt listeye dönüştür:

1. `Faz 1 için onaylanmış başlangıç kararları`
2. `Faz 1 içinde ADR ile sonuçlandırılacak kararlar`

Yinelenen `#1 support_*` satırını da kaldır.

### 4. WhatsApp AI provider satırını doğru sınıflandır

“Yeniden Kullanılacak Sınıf ve Tablolar” bölümündeki:

```text
GeminiAiProvider + AiProviderInterface | Değiştirilebilir provider contract
```

ifadesi yanıltıcıdır. Şu an interface WhatsApp namespace'indedir ve container FakeAiProvider'a bağlıdır.

Doğru ifade:

> WhatsApp'a özel mevcut provider implementasyonu/adapter adayı; canonical CustomerCare contractı değildir. HTTP implementasyonu tekrar yazılmadan Faz 1'de generic contract arkasına alınabilir. Mevcut DI binding production için fail-open risklidir.

### 5. `SupportChannelAdapterInterface` durumunu “tam contract” diye işaretleme

Interface mevcut ve yeniden kullanılabilir bir başlangıçtır ancak array tabanlı sonuçlar, normalized error, typed DTO, idempotency/send sonucu ve webhook/polling ayrımı eksiktir.

Durumu:

```text
✅ Mevcut minimal contract / güçlendirilecek
```

olarak değiştir.

### 6. En kritik bulgulara senkron SupportReply riskini geri ekle

`SupportReplyService` senkron ve retry-free olduğundan production güvenilirliği için kritik kalır. “En Kritik 6” başlığını gerekirse “En Kritik 7” yaparak şu bulguyu ekle:

> `SupportReplyService` haricî gönderimi request akışında senkron yapıyor; generic dispatch/outbox ve retry olmadan güvenilir production gönderimi sağlayamaz.

## Teslimat

Antigravity:

- Yalnız `docs/customer-care/00-mevcut-durum-dogrulama.md` dosyasını günceller.
- Yukarıdaki altı düzeltmeyi uygular.
- `git status --short` çıktısını verir.
- Uygulama koduna dokunulmadığını teyit eder.
- Faz 1'e geçmeden durur.
