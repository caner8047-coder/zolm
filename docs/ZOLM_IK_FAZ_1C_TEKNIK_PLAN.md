# ZOLM İK Faz 1C — İzin Yönetimi Teknik Uygulama Planı

**Başlangıç checkpoint'i:** `hr-phase-1b.1` (`7a81524`)  
**Hedef checkpoint:** `hr-phase-1c`  
**Faz sınırı:** İzin türleri, politikalar, bakiye ledger'ı, talepler, onay ve çalışan izin yüzeyi. Vardiya, PDKS, puantaj ve bordro kapsam dışıdır.

## Mevcut sistemle bağlanacak noktalar

| Mevcut parça | Faz 1C kullanımı |
|---|---|
| `TenantContext`, `BelongsToLegalEntity` | Her sorgu/action/job tenant kapsamında |
| `HrCalendarService`, `CalculateWorkingDaysAction` | Gün bazlı izin süresi; Faz 1C içinde saatlik/yarım gün genişletmesi |
| `HrEmployee`, `HrEmploymentRecord` | Çalışan, aktif çalışma kaydı, yönetici/departman/pozisyon kapsamı |
| `HrBasePolicy`, `HrAuthorize`, `RequireHrModule` | Policy + route middleware standardı |
| `ActivityLog`, `HrAuditService`, `AppNotification` | Hassas veri içermeyen audit ve idempotent bildirim |
| `HrJob` | Tenant context kuran, retry-safe zamanlanmış işler |
| `HrSettings` / `HrHoliday` | Şirket tatil takvimi; ikinci takvim oluşturulmayacak |

## Batch 1 — Veri çekirdeği ve yetki

### Dosyalar

- Beş migration: `hr_leave_types`, `hr_leave_policies`, `hr_leave_balances`, `hr_leave_transactions`, `hr_leave_requests`.
- Enumlar: `LeaveUnit`, `LeaveRequestStatus`, `LeaveTransactionType`, `LeavePolicyScope`, `LeaveApprovalStatus`.
- Modeller, ilişkiler, factories ve `HrLeave*Policy` sınıfları.
- İzin permission'larının genişletilmesi ve `HrServiceProvider` policy kayıtları.

### Veri kararları

- Bakiye tablosu dönem başına hızlı okuma cache'idir; doğruluk kaynağı `hr_leave_transactions` ledger'ıdır.
- `source_type + source_id + transaction_type` tenant/employee/type/period kapsamında benzersizdir. Retry çift hareket üretmez.
- Policy kapsamı employee'nin aktif employment record'undan çözümlenir. Aynı öncelikte iki aktif policy kaydedilemez.
- Onaylanmış talep satırı düzenlenmez. İptal, ters kullanım hareketi; değişiklik ise yeni talep/revizyon üretir.

### Batch kabulü

- Migration ileri/geri çalışır; Faz 0–1B.1 tabloları korunur.
- Tenant dışı çalışan, izin türü veya policy bağlanamaz.
- Normal admin, HR leave izni olmadan policy bypass yapamaz.

## Batch 2 — Kural, ledger ve onay akışı

### Servis ve action'lar

- `LeavePolicyResolver`: şirket → şube → departman → pozisyon → employment type en spesifik seçim.
- `LeaveDurationCalculator`: çalışma günü, tatil, yarım gün/saatlik süre ve çakışma kontrolü.
- `LeaveBalanceService`: hak ediş, kullanım, iptal, düzeltme; transaction içinde ledger + cached balance güncellemesi.
- `CreateLeaveRequestAction`, `ApproveLeaveRequestAction`, `RejectLeaveRequestAction`, `CancelLeaveRequestAction`, `AdjustLeaveBalanceAction`.

### Domain event'leri

- `LeaveRequested`, `LeaveApproved`, `LeaveRejected`, `LeaveCancelled`, `LeaveBalanceAdjusted`.
- Commit sonrası yayın; payload yalnızca tenant, employee, request/transaction, actor, occurred-at ve güvenli metadata içerir.
- Audit, notification ve dashboard cache listener'ları idempotent olur. Gelecek Vardiya/Puantaj modülleri yalnızca bu event'leri dinler.

### Onay modeli

İlk Faz 1C akışı: talep → aktif yönetici → (policy gerektirirse) HR. `HrEmploymentRecord::manager_employee_id` kullanılır; manager yoksa HR adımı bekler. Genel çok modüllü approval engine, Faz 1C sonrası ortaklaştırılır; burada yalnız izin tablosuna doğrudan ve sınırlı akış snapshot'ı yazılır.

### Batch kabulü

- Yetersiz bakiye, çakışan tarih, zorunlu belge ve geçersiz saat aralığı reddedilir.
- Aynı talep iki kez onaylansa bile tek usage ledger hareketi oluşur.
- İptal, tek ters hareket oluşturur; onaylanmış talep sessizce mutate edilemez.
- Tenant context temizlenir; event/job tekrarları çift bildirim üretmez.

## Batch 3 — Livewire ürün yüzeyleri

| Yüzey | Bileşen | Rota |
|---|---|---|
| İzin dashboard/listesi | `LeaveList` | `/hr/leaves` |
| Talep oluştur/detay | `LeaveRequestForm`, `LeaveRequestDetail` | `/hr/leaves/create`, `/hr/leaves/{request}` |
| Onay kutusu | `LeaveApprovalInbox` | `/hr/leaves/approvals` |
| Bakiye ledger'ı | `LeaveBalanceManager` | `/hr/leaves/balances` |
| İzin türleri | `LeaveTypeList`, `LeaveTypeForm` | `/hr/settings/leave-types` |
| Politikalar | `LeavePolicyList`, `LeavePolicyForm` | `/hr/settings/leave-policies` |
| Çalışan profili | `EmployeeDetail` izin sekmesi | `/hr/personnel/{employee}` |

Tüm listeler: active-filter görünümü, arama, kolon seçimi, sorting, responsive kart, loading/empty/error ve gerçek sayaçlarla yapılır. Tasarım ZOLM açık panel standardına uyar.

## Batch 4 — Test, export ve checkpoint kapanışı

### Hedef test sınıfları

- `LeaveTypeCrudTest`, `LeavePolicyResolverTest`, `LeaveBalanceLedgerTest`
- `LeaveDurationCalculatorTest`, `LeaveRequestWorkflowTest`, `LeaveApprovalAuthorizationTest`
- `LeaveTenantIsolationTest`, `LeaveCancellationTest`, `LeaveDashboardMetricsTest`
- `LeaveExportTest`, `EmployeeProfileLeavesTabTest`, `HrPhase1CMigrationsRollbackTest`

### Kapanış komutları

1. `php artisan optimize:clear`, `php artisan view:cache`, `php artisan view:clear`
2. Hedef sınıflar `--testdox`, ardından `php artisan test --filter=Hr`
3. İzole test DB'de migration forward + Faz 1C rollback
4. `git diff --check`; kullanıcıya ait dosyaları stage etmeden değişiklik envanteri
5. Sadece tam kapsam geçerse yeni commit ve `hr-phase-1c` annotated tag

## Açık kararlar (kod başlamadan varsayılan seçilir)

| Konu | Faz 1C varsayılanı |
|---|---|
| İzin hak edişi | Policy'de sabit yıllık gün/hak ediş, manuel adjustment; kıdem matrisi sonraki batch'te genişletilir |
| Onay | Yönetici + opsiyonel HR; paralel, tutar koşulu ve vekâlet ortak motor fazına bırakılır |
| Ücretsiz izin | Talep olarak kaydedilir, annual balance tüketmez; bordro etkisi event metadata'dır |
| Doktor raporu | Belge modülüne link verir; sağlık dosyasını izin listesinde göstermez |
| Hesaplama | Günlük izin için mevcut tenant tatil takvimi ve hafta sonu; çalışma takvimi/shift bağımlılığı yok |

