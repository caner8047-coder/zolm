# Dalga J Kanıt Paketi — Kalıcı Eval Ledger ve AI Governance

Bu doküman, Dalga J kapsamında gerçekleştirilen kalıcı değerlendirme defteri (Evaluation Ledger) ve AI yönetişim altyapısının doğrulama adımlarını ve kanıtlarını içerir.

## 1. Uygulanan Özellikler ve Kapsam
- **Veritabanı Şeması:** Değerlendirmelerin tarihsel takibi için `support_ai_eval_runs` (üst kayıt) ve `support_ai_eval_case_results` (alt durum sonuçları) tabloları oluşturulmuştur.
- **Kalıcı Defter Servisi:** `CustomerCareEvalService` güncellenerek değerlendirme sonuçlarını doğrudan veritabanına yazması sağlanmış, pilot readiness kontrolleri için cache yerine DB birincil kaynak yapılmıştır.
- **KVKK / PII Maskeleme:** Değerlendirme sırasında üretilen AI yanıtları veritabanına yazılmadan önce `PiiRedactor` servisi aracılığıyla maskelenmektedir (E-posta, Telefon, TC No vb.).
- **Model Yönetimi:** `GeminiCustomerCareAiAdapter` güncellenerek kullanılan Gemini modelinin sürüm ve tip bilgilerini (`getModel()`) dışarıya aktarması sağlanmıştır. Deftere model adı kalıcı olarak işlenmektedir.
- **Konsol Arayüzü:** `php artisan customer-care:run-eval {store_id}` komutu yazılarak değerlendirmelerin CLI üzerinden de manuel tetiklenebilmesi sağlanmıştır.

## 2. Test Sonuçları (SupportAiEvalLedgerTest)
İlgili test paketi (`tests/Feature/CustomerCare/SupportAiEvalLedgerTest.php`) yazılmış ve başarıyla geçmiştir.

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/SupportAiEvalLedgerTest.php --compact
```

### Geçen Test Senaryoları
1. `test_it_saves_eval_run_and_cases_to_database`: Değerlendirme yapıldığında tüm runs ve case tablolarının DB'ye başarıyla kaydedildiği ve ilişkilerin kurulduğu doğrulanmıştır.
2. `test_pii_is_masked_in_case_results`: Yapay zekanın ürettiği kişisel verilerin (email, telefon, TC kimlik no) defter kaydedilmeden önce maskelendiği doğrulanmıştır.
3. `test_readiness_status_based_on_evaluation_results`: Skorun 80 barajının üstünde olması durumunda readiness onayının verildiği, altında kalması durumunda başarısız sayıldığı doğrulanmıştır.
4. `test_readiness_fails_if_evaluation_is_stale`: Son değerlendirme tarihinden itibaren 7 gün (veya konfigüre edilen gün sayısı) geçmişse sonucun "eski" olarak işaretlenip pilot onayının kaldırıldığı doğrulanmıştır.
5. `test_tenant_isolation_in_golden_evaluation`: Bir mağazaya ait eval sonuçlarının diğer mağazalar/kiracılar tarafından görülemediği doğrulanmıştır.

---
**Durum:** Başarılı (Wave J Teslime Hazır)
