# ZOLM AI Müşteri İletişim Merkezi — Dalga AN/AO/AP Kalite Kapısı 02

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Kapsam:** Dalga AN — Customer Success, Dalga AO — Experimentation Lab, Dalga AP — Security Assurance  
**Karar:** ❌ **Kabul verilmedi — P0/P1 revizyon gerekli**

Dalga AN/AO/AP teslimatı bu kez repo içinde görünür durumda: route, command, model, migration, servis, test ve kanıt paketleri eklendi. Hedef testler ve Customer Care test paketi yeşil. Ancak bağımsız kod incelemesinde testlerin yakalamadığı iki P0 güvenlik/doğruluk açığı ve iki P1 dokümantasyon/komut davranışı problemi bulundu.

---

## Bağımsız Doğrulama Kanıtları

Çalıştırılan kontroller:

```bash
git diff --check
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareLaunchTest.php \
  tests/Feature/CustomerCare/CustomerCareReconciliationTest.php \
  tests/Feature/CustomerCare/CustomerCareReleaseTest.php \
  tests/Feature/CustomerCare/CustomerCareSuccessTest.php \
  tests/Feature/CustomerCare/CustomerCareExperimentTest.php \
  tests/Feature/CustomerCare/CustomerCareSecurityTest.php \
  --no-coverage --compact
npm run build
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
```

Sonuçlar:

- `git diff --check`: ✅ temiz
- AK/AL/AM + AN/AO/AP hedef testleri: ✅ `37 passed / 72 assertions`
- `npm run build`: ✅ başarılı
- Customer Care test paketi: ✅ `340 passed / 1123 assertions`
- Route listesi: ✅ `success`, `experiments`, `security` rotaları mevcut
- Command listesi: ✅ `success-snapshot`, `run-experiment`, `compare-release`, `security-audit`, `evidence-pack` komutları mevcut
- Kanıt paketleri: ✅ `dalga-an/ao/ap-kanit-paketi.md` dosyaları mevcut
- Threat model: ✅ `docs/customer-care/security-threat-model.md` mevcut

---

## P0-1 — AO Artifact Version Store Scope Eksik

**Dosya:** `app/Services/Support/CustomerCareExperimentService.php`

### Sorun

`runExperiment()` ve `compareRelease()` içinde artifact version kayıtları doğrudan global ID ile yükleniyor:

```php
$version = SupportArtifactVersion::find($variant->artifact_version_id);

$current   = SupportArtifactVersion::find($currentId);
$candidate = SupportArtifactVersion::find($candidateId);
```

Bu, deneyin veya release karşılaştırmasının seçili `store_id` sınırına ait olmayan bir `SupportArtifactVersion` ile çalışmasına izin verebilir. `TenantContext::enforceStoreAccess($storeId, $user)` kullanıcının store’a erişimini doğrular; ancak artifact ID’nin aynı store’a ait olduğunu doğrulamaz.

### Risk

- Cross-store prompt/policy/knowledge artifact’i deney bağlamına girebilir.
- Başka mağazanın artifact’i winner candidate olarak önerilebilir.
- Release/experiment kararları tenant sınırını ihlal edebilir.

### Zorunlu düzeltme

Artifact version yüklemeleri store scoped olmalı:

```php
SupportArtifactVersion::where('store_id', $storeId)->findOrFail($id);
```

veya eşdeğer fail-closed resolver kullanılmalı.

### Zorunlu testler

En az şu testler eklenmeli:

- `test_run_experiment_rejects_variant_artifact_from_another_store`
- `test_compare_release_rejects_current_artifact_from_another_store`
- `test_compare_release_rejects_candidate_artifact_from_another_store`

---

## P0-2 — AP Security Audit Dry-Run DB Mutasyonu Yapıyor

**Dosya:** `app/Services/Support/CustomerCareSecurityService.php`  
**Dosya:** `app/Console/Commands/CustomerCareSecurityCommand.php`

### Sorun

Komut imzası ve çıktı metni dry-run için mutasyon yapılmayacağını söylüyor:

```php
{--dry-run : Denetim çalıştırılır ama mutasyon yapılmaz (varsayılan)}
```

Ancak `CustomerCareSecurityService::runAudit($storeId, true, $user)` dry-run modunda bile şunları yazıyor:

- `support_security_audit_runs`
- `support_security_evidence_items`
- `support_security_findings`

Mevcut test de bunu doğruluyor:

```php
audit_run_creates_findings_and_evidence()
```

ve dry-run için yalnız exception atmamasını kontrol ediyor; mutasyon yapmamasını kontrol etmiyor.

### Risk

- Dry-run komutları production’da kalıcı audit/finding/evidence kirliliği yaratır.
- “Dry-run” güvenlik sözleşmesi bozulur.
- Denetim raporları gerçek execute çalışmasıyla dry-run simülasyonunu ayırt etmekte zorlanır.

### Zorunlu düzeltme

İki kabul edilebilir seçenek var; birini seç:

1. **Gerçek dry-run:** `--execute` yoksa DB’ye hiçbir kayıt yazma; geçici DTO/array döndür ve terminalde raporla.
2. **Kalıcı audit run istiyorsan:** Komut ve prompt dilini değiştir; `dry_run` kayıtları ayrı statüde kalıcı ledger olarak tasarlanmalı. Ancak mevcut prompt açıkça “Dry-run audit DB mutasyonu yapmaz” dediği için tercih edilen yol 1’dir.

### Zorunlu test

- `test_security_audit_dry_run_does_not_persist_run_findings_or_evidence`
- `test_security_audit_execute_persists_run_findings_and_evidence`

---

## P1-1 — AN Success Snapshot Dry-Run Gerçek Hesaplama Yapmıyor

**Dosya:** `app/Console/Commands/CustomerCareSuccessCommand.php`

### Sorun

Komut dry-run için “snapshot hesaplanır ama kaydedilmez” diyor. Ancak `processStore()` dry-run modunda `CustomerCareSuccessService::computeSnapshot()` veya eşdeğer hesaplama yolunu çağırmıyor; sadece bilgi mesajı yazıyor:

```php
if ($dryRun) {
    $this->info("[Store {$storeId}] Snapshot hesaplandı (dry-run, kaydedilmedi).");
} else {
    $snapshot = $service->computeSnapshot($storeId, $systemUser);
}
```

### Risk

- Dry-run readiness/success sinyali gerçek health score hesaplamadan “hesaplandı” der.
- Operasyon ekibi dry-run çıktısına güvenemez.

### Zorunlu düzeltme

`CustomerCareSuccessService` içinde persist etmeyen hesaplama metodu ayrılmalı:

- `calculateSnapshotData($storeId, $user): array`
- `computeSnapshot()` bu metodu kullanıp persist etmeli.
- Komut dry-run bu hesaplamayı yapıp sonucu yazmalı ama DB’ye kayıt atmamalı.

### Zorunlu test

- `test_success_snapshot_dry_run_computes_health_without_persisting`

---

## P1-2 — `walkthrough.md` Güncellenmemiş

**Dosya:** `walkthrough.md`

### Sorun

Teslim raporu `walkthrough.md` güncellendi diyor; fakat dosya hâlâ eski “ZOLM Kurulum ve Çalıştırma Rehberi” içeriğini taşıyor. AN/AO/AP veya AK/AL/AM teslimat özeti yok.

### Zorunlu düzeltme

`walkthrough.md` şu başlıklarla güncellenmeli:

- AK/AL/AM kısa özet ve test sonuçları
- AN/AO/AP kısa özet ve test sonuçları
- Route/command/scheduler kanıtları
- Build ve `git diff --check` kanıtları
- Bilinen P2 notlar

---

## P2 — PHPUnit Doc-Comment Metadata Warning

Yeni testlerde `/** @test */` doc-comment metadata kullanımı PHPUnit 12 için deprecation warning üretiyor.

### Öneri

Yeni testlerde attribute kullan:

```php
#[Test]
public function ...
```

Bu kabulü tek başına bloke etmez; ancak yeni testler modern PHPUnit standardına taşınmalı.

---

## Antigravity İçin Revizyon Talimatı

```text
/Volumes/TWINMOS/zolm reposunda Dalga AN/AO/AP Kalite Kapısı 02 revizyonunu uygula.

Önce şu dosyayı tamamen oku:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-anaoap-kalite-kapisi-02.md

Yalnız bu kalite kapısındaki P0/P1/P2 maddelerini düzelt.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Yeni dalga başlatma.

Özellikle:
1. CustomerCareExperimentService artifact version yüklemelerini store scoped yap.
2. Security audit dry-run DB mutasyonunu kaldır veya execute-only persist yap.
3. Success snapshot dry-run gerçek hesaplama yapıp persist etmesin.
4. walkthrough.md dosyasını AK/AL/AM + AN/AO/AP teslimat özetiyle güncelle.
5. Yeni testlerde mümkünse PHPUnit attribute kullan.

Kanıt olarak şunları ver:
- git diff --check
- npm run build
- route:list --name=customer-care
- list customer-care --raw
- schedule:list
- hedef testler
- tests/Feature/CustomerCare tam paket sonucu
- mümkünse full suite sonucu
```

---

## Sonuç

Dalga AN/AO/AP teslimatı bu haliyle kabul edilmedi.

**Olumlu:** Modüller artık repo içinde, route/command/test/kanıt paketleri mevcut ve testler yeşil.  
**Blokaj:** AO store-scoped artifact guard eksik ve AP dry-run kalıcı DB mutasyonu yapıyor.  
**Sonraki adım:** KG02 revizyonu sonrası yeniden baş mühendis incelemesi.
