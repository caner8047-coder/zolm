# ADR 006: Human Ownership State Machine

## Durum
Accepted

## Bağlam
ZOLM Müşteri İletişim Merkezi'nde en kritik gereksinimlerden biri, bir temsilci (insan) konuşmayı devraldığında AI'ın araya girip otomatik yanıt göndermesini kesin olarak engellemek ve kilit oluşturmaktır. WhatsApp modülünde `WaConversation.ai_status = 'handed_off'` ve `WaHandoff` kayıtları ile bu kontrol sağlanmaktadır. Ancak konuşma durumu, otomasyon modu ve sahiplik kavramları tek bir durum makinesinde birleştiği için karmaşa yaratmaktadır.

## Karar
- Konuşma durumu (Conversation Status), Temsilci Ataması (Ownership) ve Otomasyon Modu (Automation Mode) 3 bağımsız eksen olarak ayrılacaktır:
  1. **Conversation Lifecycle (Konuşma Yaşam Döngüsü):** `open` (açık), `pending` (beklemede), `resolved` (çözüldü), `closed` (kapatıldı) ve `snoozed` (ertelenmiş) durumlarını içerir.
  2. **Ownership (Sahiplik):** `unassigned` (atanmamış), `ai` (yapay zeka sahipliği), `human` (insan temsilci sahipliği) durumlarını içerir. İnsan sahipliğinde, atanan temsilci kimliği (owner_id) ve kilit bilgisi tutulur.
  3. **Automation Mode (Otomasyon Modu):** `manual` (tamamen temsilci yönetiminde), `copilot` (AI taslak hazırlar, temsilci onaylar), `automatic` (AI doğrudan yanıt verir) durumlarını içerir.
- **Kritik Kural:** İnsan sahipliği kilidi (`Ownership = human`), conversation/kanal otomasyon modunun önüne geçer. Bir konuşma insana atandığında veya handoff başlatıldığında, kanal otomasyon modu `automatic` olsa dahi AI doğrudan yanıt gönderemez ve devre dışı kalır.
- `resolve` işlemi konuşmanın yaşam döngüsünü `resolved` durumuna getirir; sahipliği AI'a bırakmakla (`releaseToAi`) aynı işlem değildir.
- Sahipliği AI'a bırakma (`releaseToAi` veya eşdeğer işlem) ayrı, yetkili (policy-controlled), denetlenebilir (audited) ve concurrency-safe bir eylemdir.
- Devralma (claim), bırakma (release), resolve ve reopen işlemleri optimistic lock (sürüm sütunu) veya atomik koşullu veritabanı güncellemeleri (`UPDATE ... WHERE version = X` vb.) ile yarış durumlarına (race conditions) karşı korunacaktır.
- Bu mimarinin kod uygulaması sonraki fazlarda gerçekleştirilecektir. Faz 1 kapsamında herhangi bir model veya veritabanı migration kodu yazılmayacaktır.

## Alternatifler
- **Tek Eksenli ai_status Kullanımı:** Sadece `active` / `handed_off` / `resolved` bayrakları ile devam etmek. Bu, temsilcinin konuşmayı kapatıp kapatmadığı veya AI modunun copilot mu yoksa manual mi olduğunu tek sütunda birleştirdiği için kural çelişkilerine yol açtığından reddedilmiştir.

## Sonuçlar ve trade-offlar
- **Artılar:** İnsan sahipliği kilidi ve gönderim öncesi zorunlu kontroller, temsilci ile AI'ın aynı anda yanıt verme riskini azaltır; durum yönetimi açık eksenlere ayrılır; atomik güncellemeler yarış durumlarına karşı koruma sağlar.
- **Eksiler:** Veritabanında ve uygulama katmanında 3 ayrı durum alanının kontrol edilmesi ve saklanması gerekmektedir.

## Geriye uyumluluk
Mevcut `WaConversation.ai_status` alanları ve `WaHandoff` modeli, yeni durum makinesi Faz 2 ve Faz 3 kapsamında birleşik `support_conversations` tablosuna aktarılana kadar geriye dönük çalışmaya devam edecektir.

## Güvenlik/KVKK etkisi
Handoff eylemlerinin ve atamaların kimin tarafından yapıldığının audit loglarında tutulması, kurumsal yetki denetimi ve veri güvenliği açısından zorunludur.

## İlgili ZCC gereksinimleri
- ZCC-003 (Güven, risk ve sessiz insan devri)
- ZCC-004 (Üç yanıt modu)

## Uygulama fazı
Faz 2 ve Faz 3
