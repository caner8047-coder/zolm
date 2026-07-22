# ZOLM İnsan Kaynakları Yönetim Sistemi — Geliştirme Brifi

**Versiyon:** 1.1
**Tarih:** 2026-07-20
**Teknoloji:** PHP 8.3 / Laravel 12 / Livewire 4 / Tailwind CSS
**Hedef DB:** MySQL 8+ (test: SQLite/MySQL)
**Durum:** Uygulama kodu henüz yazılmamıştır. Bu doküman ürün haritasıdır.

---

## 1. Genel Mimari Kararlar

### 1.1 Mimari: Modüler Monolit

Tüm HR modülleri `app/Modules/Hr/` altında geliştirilecek.

```
app/Modules/Hr/
├── Core/                    # Ortak altyapı (tenant, audit, dosya, yetki)
├── Personel/                # Sürüm 1
├── Organizasyon/            # Sürüm 1
├── Belge/                   # Sürüm 1
├── Portal/                  # Sürüm 1 (çalışan portalı)
├── Izin/                    # Sürüm 1
├── Vardiya/                 # Sürüm 2
├── PDKS/                    # Sürüm 2
├── Puantaj/                 # Sürüm 2
├── FazlaMesai/              # Sürüm 2
├── Bordro/                  # Sürüm 3 + 7
├── Masraf/                  # Sürüm 3
├── Avans/                   # Sürüm 3
├── Zimmet/                  # Sürüm 3
├── Performans/              # Sürüm 4
├── Egitim/                  # Sürüm 4
├── Baglilik/                # Sürüm 4
├── AdayTakip/               # Sürüm 5
├── Onboarding/              # Sürüm 5
├── Offboarding/             # Sürüm 5
├── Ucret/                   # Sürüm 6
├── KadroPlanlama/           # Sürüm 6
├── Analitik/                # Sürüm 6
├── ISGUyum/                 # Sürüm 6
├── DestekMerkezi/           # Sürüm 6
├── HesaplamaAraclari/       # Sürüm 6
├── Asistan/                 # Sürüm 8
├── Shared/                  # Tüm modüller arası paylaşım
│   ├── Events/
│   ├── Listeners/
│   ├── Services/
│   ├── Enums/
│   └── Traits/
└── Database/
    ├── Migrations/
    └── Seeders/
```

### 1.2 Her Modülün İç Yapısı

```
app/Modules/Hr/{ModulAdi}/
├── Models/
├── Actions/                  # Tek amaçlı, test edilebilir aksiyon sınıfları
├── Services/                 # Çok adımlı iş mantığı
├── Livewire/                 # Livewire bileşenleri
│   ├── Forms/
│   └── Tables/
├── Policies/
├── Jobs/
├── Notifications/
├── Reports/                  # PDF/Excel raporlar
├── Tests/
└── Routes/
```

### 1.3 Konvansiyonlar

| Konu | Kural |
|------|-------|
| Model | `app/Modules/Hr/{Modul}/Models/{Model}.php` |
| Livewire | `app/Modules/Hr/{Modul}/Livewire/{Sayfa}.php` |
| View | `resources/views/livewire/hr/{modul-snake}/{sayfa}.blade.php` |
| Migration | `database/migrations/hr/{timestamp}_{tablo}.php` |
| Rota | `/hr/{modul-slug}` prefix |
| Event | `App\Modules\Hr\Shared\Events\{EventAdi}` |

### 1.4 Mevcut Sistem Entegrasyonu

Aynı işi yapan ikinci sistem oluşturulmayacak. Mevcut ZOLM altyapısından yararlanılacak:

| Mevcut ZOLM | Kullanım |
|-------------|----------|
| `App\Models\User` | Korunacak. HR `employee()` ilişki ekleyecek |
| `App\Models\LegalEntity` | Tenant temeli. HR `sgkWorkplaces()`, `branches()` ekleyecek |
| `App\Models\Role` | Genişletilecek. HR rolleri eklenecek |
| `App\Models\ActivityLog` | Tek audit altyapısı. HR için ek metadata alanı eklenecek |
| `App\Models\AppNotification` | HR bildirimleri mevcut sisteme entegre edilecek |
| `App\Http\Middleware\AdminMiddleware` | Rol kontrolü mevcut. Yetki tabanlı kontrol ile genişletilecek |
| `App\Services\ExcelService` | PHPSpreadsheet tabanlı mevcut servis kullanılacak |
| `barryvdh/laravel-dompdf` | PDF üretimi için mevcut paket kullanılacak |
| `App\Http\Controllers\Auth\LoginController` | Mevcut auth sistemi korunacak |

---

## 2. Tenant İzolasyonu (Kritik)

> **Nihai Karar:** Tenant izolasyonu yalnızca `legal_entity_id` foreign key olarak kalmayacak. Uygulama seviyesinde veri sızıntısını engelleyen çok katmanlı koruma kurulacak.

### 2.1 Tenant Context Servisi

```php
// app/Modules/Hr/Core/Services/TenantContext.php
namespace App\Modules\Hr\Core\Services;

class TenantContext
{
    private ?LegalEntity $tenant = null;

    public function set(LegalEntity $tenant): void
    {
        $this->tenant = $tenant;
        // Session'da da tut (queue job için)
        session(['hr_tenant_id' => $tenant->id]);
    }

    public function get(): LegalEntity
    {
        if (!$this->tenant) {
            $id = session('hr_tenant_id');
            if ($id) {
                $this->tenant = LegalEntity::findOrFail($id);
            }
        }
        abort_if(!$this->tenant, 403, 'Tenant context tanımlı değil');
        return $this->tenant;
    }

    public function getId(): int
    {
        return $this->get()->id;
    }

    public function clear(): void
    {
        $this->tenant = null;
        session()->forget('hr_tenant_id');
    }
}
```

### 2.2 HTTP Middleware

```php
// app/Modules/Hr/Core/Http/Middleware/ResolveHrTenant.php
namespace App\Modules\Hr\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Modules\Hr\Core\Services\TenantContext;

class ResolveHrTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->user()
            ->legalEntities()
            ->active()
            ->first();

        abort_unless($tenant, 403, 'Aktif tüzel kişilik bulunamadı');

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
```

### 2.3 Global Scope

```php
// app/Modules/Hr/Core/Traits/BelongsToLegalEntity.php
namespace App\Modules\Hr\Core\Traits;

use App\Modules\Hr\Core\Services\TenantContext;

trait BelongsToLegalEntity
{
    public static function bootBelongsToLegalEntity(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            $tenantId = app(TenantContext::class)->getId();
            $query->where('legal_entity_id', $tenantId);
        });
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('legal_entity_id', app(TenantContext::class)->getId());
    }
}
```

### 2.4 Kontrollü Scope Bypass

```php
// Yetkili servis katmanında, queue job'larda veya import işlemlerinde
$tenantId = app(TenantContext::class)->getId();

// Doğrudan WHERE ile bypass — global scope atlanmaz
$employees = Employee::withoutGlobalScope('tenant')
    ->where('legal_entity_id', $tenantId)
    ->get();
```

### 2.5 Queue Job Tenant Context

```php
// app/Modules/Hr/Core/Jobs/HrJob.php
abstract class HrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $tenantId;

    public function __construct()
    {
        $this->tenantId = app(TenantContext::class)->getId();
    }

    public function handle(): void
    {
        app(TenantContext::class)->set(
            LegalEntity::findOrFail($this->tenantId)
        );
        $this->execute();
    }

    abstract protected function execute(): void;
}
```

### 2.6 Export/Import Tenant Context

Export ve import işlemlerinde tenant doğrulaması zorunlu:
- Export: Sadece mevcut tenant verileri dışa aktarılır
- Import: Yüklenen dosyadaki veriler mevcut tenant'a ait olmalıdır
- Başka tenant ID'si içeren import kayıtları reddedilir

### 2.7 Tenant Bazlı Cache Key

```php
function hrCacheKey(string $key): string
{
    return "hr:" . app(TenantContext::class)->getId() . ":" . $key;
}
```

### 2.8 Tenant Bazlı Dosya Yolu

```php
function hrStoragePath(string $category, string $filename): string
{
    $tenantId = app(TenantContext::class)->getId();
    return "hr/{$tenantId}/{$category}/{$filename}";
}
```

### 2.9 Route Model Binding Koruması

```php
// PersonelPolicy'de
public function view(User $user, Employee $employee): bool
{
    return $employee->legal_entity_id === app(TenantContext::class)->getId()
        && $user->hasPermission('hr.employees.view');
}
```

---

## 3. Tek Ortak Audit Mimarisi (Kritik)

> **Nihai Karar:** Mevcut `ActivityLog` tablosu genişletilerek tek ortak audit altyapısı oluşturulacak. Ayrı `hr_audit_logs` tablosu YARATILMAYACAK.

### 3.1 Mevcut ActivityLog'a Ek Alanlar

```php
// Mevcut activity_logs tablosuna eklenecek migration:
Schema::table('activity_logs', function (Blueprint $table) {
    $table->foreignId('legal_entity_id')->nullable()->after('user_id')
        ->constrained('legal_entities')->nullOnDelete();
    $table->string('module')->nullable()->after('action'); // 'hr', 'marketplace', 'accounting'
    $table->string('subject_type')->nullable()->after('description');
    $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
    $table->json('old_values')->nullable()->after('subject_id');
    $table->json('new_values')->nullable()->after('old_values');
    $table->json('metadata')->nullable()->after('new_values'); // Ek veriler
    $table->string('ip_address', 45)->nullable()->after('metadata');
    $table->string('user_agent')->nullable()->after('ip_address');
    $table->boolean('contains_sensitive_data')->default(false);
});
```

### 3.2 Hassas Veri Maskesi

Aşağıdaki alanlar audit log'da ASLA açık şekilde saklanmaz:

| Alan | İşlem |
|------|-------|
| T.C. Kimlik No | Tamamen hariç tutulur veya `***1234` formatında maskelenir |
| IBAN | Son 4 hanesi gösterilir: `TR** **** **** **** **34` |
| Maaş/Ücret | Hariç tutulur veya `"[MASKED]"` yazılır |
| Sağlık bilgisi | Hariç tutulur |
| Belge içeriği | Hariç tutulur, sadece dosya adı ve kategorisi loglanır |
| Parola/Token | Asla loglanmaz |
| Bordro hesaplama detayları | Kişisel alanlar maskelenir |

### 3.3 Audit Servisi

```php
// app/Modules/Hr/Core/Services/HrAuditService.php
class HrAuditService
{
    private array $sensitiveFields = [
        'national_id', 'national_id_encrypted', 'national_id_hash',
        'iban', 'iban_encrypted', 'iban_hash',
        'gross_salary', 'net_pay', 'total_cost', 'base_salary',
        'bank_name', 'bank_account_number',
        'health_data', 'blood_type',
        'password', 'token', 'api_key',
    ];

    public function log(string $action, Model $subject, ?array $old = null, ?array $new = null): void
    {
        $maskedOld = $old ? $this->maskSensitive($old) : null;
        $maskedNew = $new ? $this->maskSensitive($new) : null;
        $containsSensitive = $this->containsSensitiveData($old, $new);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'legal_entity_id' => app(TenantContext::class)->getId(),
            'module' => 'hr',
            'action' => $action,
            'description' => class_basename($subject) . ' ' . $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'old_values' => $maskedOld,
            'new_values' => $maskedNew,
            'metadata' => ['module' => $subject->getModuleName() ?? 'hr'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'contains_sensitive_data' => $containsSensitive,
        ]);
    }

    private function maskSensitive(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $this->sensitiveFields)) {
                $value = $this->maskValue($key, $value);
            }
        }
        return $data;
    }

    private function maskValue(string $key, mixed $value): string
    {
        return match(true) {
            str_contains($key, 'national_id') => $this->maskNationalId($value),
            str_contains($key, 'iban') => $this->maskIban($value),
            in_array($key, ['gross_salary', 'net_pay', 'total_cost', 'base_salary']) => '[MASKED]',
            str_contains($key, 'health') => '[HARIÇ TUTULDU]',
            in_array($key, ['password', 'token', 'api_key']) => '[ASLA LOGLANMAZ]',
            default => '[MASKED]',
        };
    }

    private function maskNationalId(?string $value): string
    {
        if (!$value) return 'null';
        return '***' . substr($value, -4);
    }

    private function maskIban(?string $value): string
    {
        if (!$value) return 'null';
        return substr($value, 0, 6) . str_repeat('*', strlen($value) - 8) . substr($value, -2);
    }
}
```

---

## 4. Hassas Veri Şifreleme (Kritik)

> **Nihai Karar:** T.C. kimlik, IBAN, maaş ve sağlık verileri açık saklanmayacak. Şifreli alan + hash çifti kullanılacak.

### 4.1 Şifreleme Yaklaşımı

Laravel'in built-in `encrypted` cast'i AES-256-CBC ile çalışır. App key üzerinden şifreleme yapılır.

```php
// Model içinde:
protected function casts(): array
{
    return [
        'national_id_encrypted' => 'encrypted',
        'iban_encrypted' => 'encrypted',
        'gross_salary' => 'encrypted:decimal:2',
    ];
}
```

### 4.2 Arama ve Benzersizlik

Şifreli alanlarda `WHERE` çalışmayacağı için hash kolonu kullanılır:

```php
// Employee oluşturulurken:
$employee->national_id_encrypted = $nationalId;
$employee->national_id_hash = hash('sha256', $nationalId . config('app.key'));
```

```php
// Arama yapılırken:
Employee::where('national_id_hash', hash('sha256', $searchValue . config('app.key')))->first();
```

### 4.3 Benzersizlik Kuralları

`national_id` global UNIQUE DEĞİL. Kurallar:

| Kural | Açıklama |
|-------|----------|
| Aynı tüzel kişilik içinde | `national_id_hash` benzersiz olmalı |
| Farklı tüzel kişilikler | Aynı kişi çalışabilir |
| Yeniden işe alım | Eski kayıtlar `deleted_at` ile soft-delete; yeni kayıt eski hash ile eşleşebilir |
| Composite unique | `UNIQUE(legal_entity_id, national_id_hash)` |

### 4.4 Görüntüleme Maskesi

Arama sonuçlarında ve profil ekranında:
- Tam T.C.: Sadece yetkili kullanıcılar (`hr.employees.view_sensitive`)
- Listeleme: `***1234` formatı
- Export: Sadece tam yetkili kullanıcılar

### 4.5 IBAN için Aynı Yapı

```php
$employee->iban_encrypted = $iban;
$employee->iban_hash = hash('sha256', $iban . config('app.key'));
```

### 4.6 Sağlık Verileri

Sağlık verileri için ayrı erişim politikası:
- `hr.isg.view_health` yetkisi gerektirir
- Sağlık verisi olan alanlar (`blood_type`, sağlık raporları) sadece İSG yetkilisi ve İK müdürü tarafından görülebilir
- İK asistanı sağlık verisi göstermez

---

## 5. İzin Bakiyesi: Hareket Tablosu (Kritik)

> **Nihai Karar:** İzin bakiyesi hesaplanmış/generated column ile DEĞİL, hareket tablosu (ledger) ile yönetilir. `hr_leave_balances` yalnızca hesaplanmış özet/cache olarak kalır.

### 5.1 hr_leave_transactions (Esas Doğruluk Kaynağı)

```sql
CREATE TABLE hr_leave_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(5,1) NOT NULL,          -- pozitif = artış, negatif = azalış
    effective_date DATE NOT NULL,
    source_type VARCHAR(100) NULL,          -- "App\Models\LeaveRequest", "Manual", "System"
    source_id BIGINT UNSIGNED NULL,
    year YEAR NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_leave_txn_emp_type_year (employee_id, leave_type_id, year),
    INDEX idx_leave_txn_entity (legal_entity_id),
    INDEX idx_leave_txn_source (source_type, source_id)
);
```

### 5.2 İşlem Türleri

| transaction_type | amount | Açıklama | source_type |
|-----------------|--------|----------|-------------|
| `entitlement` | +30 | Yıllık hak ediş | `System` |
| `carry_over` | +5 | Devredilen bakiye | `System` |
| `request_usage` | -5 | İzin kullanımı (onaylanan) | `LeaveRequest` |
| `request_refund` | +5 | İzin iptali iadesi | `LeaveRequest` |
| `manual_adjustment` | ±n | Manuel düzeltme | `Manual` |
| `expiry` | -n | Süresi dolan devir bakiyesi | `System` |
| `termination_adjustment` | -n | İşten çıkış düzeltmesi | `Employee` |

### 5.3 hr_leave_balances (Hesaplanmış Özet)

```sql
CREATE TABLE hr_leave_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    year YEAR NOT NULL,
    remaining DECIMAL(5,1) NOT NULL DEFAULT 0,  -- transaction'lardan hesaplanır
    last_calculated_at TIMESTAMP NULL,

    UNIQUE KEY uq_leave_balance (employee_id, leave_type_id, year),
    INDEX idx_leave_bal_entity (legal_entity_id)
);
```

### 5.4 Bakiye Hesaplama

```php
class LeaveBalanceService
{
    public function calculateRemaining(Employee $employee, LeaveType $type, int $year): float
    {
        return LeaveTransaction::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->sum('amount');  -- pozitif ve negatif işlemlerin toplamı
    }

    public function refreshBalance(Employee $employee, LeaveType $type, int $year): void
    {
        $remaining = $this->calculateRemaining($employee, $type, $year);

        LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['remaining' => $remaining, 'last_calculated_at' => now()]
        );
    }
}
```

---

## 6. Güvenli Dosya Sistemi (Kritik)

> **Nihai Karar:** Tüm HR belgeleri private disk üzerinde, tenant izoleli, policy kontrollü ve audit loglu şekilde yönetilir.

### 6.1 Kurallar

| Kural | Açıklama |
|-------|----------|
| Private disk | HR belgeleri `storage/app/private/hr/` altında |
| Public disk KULLANILMAZ | Hiçbir HR belgesi public diskte tutulmaz |
| Doğrudan URL paylaşılmaz | Storage facade üzerinden erişilir |
| İndirme controller/policy | `HrFileController@download` üzerinden |
| İmzalı URL | Kısa süreli (15 dk) imzalı URL ile erişim |
| MIME doğrulama | Yükleme sırasında MIME türü + finfo ile gerçek içerik kontrolü |
| Dosya boyutu | Max 20MB (yapılandırılabilir) |
| Zararlı dosya tarama | Genişletme noktası: `HrFileService@scanFile()` |
| İndirme logu | Her indirme audit log'a yazılır |
| hr_files ilişkisi | Dosya alanları (`document_path` vb.) `hr_files` tablosu üzerinden ilişkilendirilir |

### 6.2 Dosya Yolu Yapısı

```
storage/app/private/hr/
├── {tenant_id}/
│   ├── personnel/           # Özlük belgeleri
│   ├── contracts/           # Sözleşmeler
│   ├── leave/               # İzin belgeleri
│   ├── payroll/             # Bordro PDF'leri
│   ├── expenses/            # Masraf fiş/faturaları
│   ├── training/            # Sertifikalar
│   ├── health/              # Sağlık raporları (ayrı erişim)
│   └── general/             # Diğer belgeler
```

### 6.3 hr_files Tablosu (Tek Doğruluk Kaynağı)

Tüm `document_path`, `cv_path`, `receipt_path`, `certificate_path` alanları kaldırılacak. Tek `hr_files` tablosu kullanılacak:

```sql
CREATE TABLE hr_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    uploader_id BIGINT UNSIGNED NOT NULL,
    subject_type VARCHAR(255) NULL,
    subject_id BIGINT UNSIGNED NULL,
    category VARCHAR(100) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    disk_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    is_verified BOOLEAN DEFAULT false,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_hr_files_subject (subject_type, subject_id),
    INDEX idx_hr_files_entity (legal_entity_id)
);
```

### 6.4 İndirme Akışı

```php
// HrFileController
public function download(HrFile $file)
{
    // 1. Tenant doğrulaması
    abort_if($file->legal_entity_id !== app(TenantContext::class)->getId(), 403);

    // 2. Policy kontrolü
    $this->authorize('download', $file);

    // 3. Audit log
    app(HrAuditService::class)->log('file_downloaded', $file);

    // 4. Dosyayı döndür
    return Storage::disk('private')->download(
        $file->disk_path,
        $file->original_name
    );
}
```

### 6.5 İmzalı URL

```php
public function signedUrl(HrFile $file): string
{
    abort_if($file->legal_entity_id !== app(TenantContext::class)->getId(), 403);
    $this->authorize('view', $file);

    return Storage::disk('private')->temporaryUrl(
        $file->disk_path,
        now()->addMinutes(15)
    );
}
```

---

## 7. Veritabanı Tasarım Kararları

### 7.1 ENUM Kullanımı

> **Nihai Karar:** MySQL ENUM yerine PHP backed enum + VARCHAR kolon kullanılır. Bu taşınabilirlik ve genişletilebilirlik sağlar.

```php
// PHP Enum
enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
```

```php
// Migration
$table->string('status')->default('active');  // ENUM yerine VARCHAR
```

### 7.2 Generated Column Kullanımı

> **Nihai Karar:** GENERATED columns (özellikle `remaining` gibi) kaldırılır. Hesaplama PHP tarafında, cached özet tablolarla desteklenir.

Kaldırılan generated columns:
- `hr_leave_balances.remaining` → Hareket tablosundan hesaplanır
- `hr_payroll_records.daily_rate` → Servis katmanında hesaplanır
- `hr_advances.remaining` → Hareket tablosundan hesaplanır

### 7.3 Foreign Key Davranışları

| Tablo | Foreign Key | onDelete | onUpdate |
|-------|------------|----------|----------|
| hr_employees | legal_entity_id → legal_entities | CASCADE | CASCADE |
| hr_employees | user_id → users | SET NULL | CASCADE |
| hr_employment_records | employee_id → hr_employees | CASCADE | CASCADE |
| hr_employment_records | legal_entity_id → legal_entities | CASCADE | CASCADE |
| hr_branches | legal_entity_id → legal_entities | CASCADE | CASCADE |
| hr_departments | legal_entity_id → legal_entities | CASCADE | CASCADE |
| hr_departments | parent_id → hr_departments | SET NULL | CASCADE |
| hr_positions | legal_entity_id → legal_entities | CASCADE | CASCADE |
| hr_leave_requests | employee_id → hr_employees | CASCADE | CASCADE |
| hr_leave_requests | leave_type_id → hr_leave_types | RESTRICT | CASCADE |
| hr_leave_balances | employee_id → hr_employees | CASCADE | CASCADE |
| hr_leave_transactions | employee_id → hr_employees | CASCADE | CASCADE |
| hr_clock_records | employee_id → hr_employees | CASCADE | CASCADE |
| hr_timesheets | employee_id → hr_employees | CASCADE | CASCADE |
| hr_payroll_records | employee_id → hr_employees | CASCADE | CASCADE |
| hr_payroll_records | payroll_period_id → hr_payroll_periods | CASCADE | CASCADE |
| hr_files | legal_entity_id → legal_entities | CASCADE | CASCADE |
| hr_files | uploader_id → users | RESTRICT | CASCADE |

### 7.4 Migration Sırası (Circular FK Önleme)

```sql
-- 1. Tur: Temel tablolar (FK yok veya sadece legal_entities/users)
001_create_permissions_and_roles.php
002_create_hr_licenses.php
003_create_hr_holidays.php

-- 2. Tur: Organizasyon yapısı (kendi aralarında FK var)
004_create_hr_sgk_workplaces.php
005_create_hr_branches.php
006_create_hr_cost_centers.php

-- 3. Tur: Çalışan (önce employee, sonra employment_records)
007_create_hr_employees.php
008_create_hr_departments.php    -- employee_id FK var
009_create_hr_units.php
010_create_hr_teams.php
011_create_hr_positions.php
012_create_hr_employment_records.php

-- 4. Tur: İzin
013_create_hr_leave_types.php
014_create_hr_leave_policies.php
015_create_hr_leave_balances.php
016_create_hr_leave_transactions.php
017_create_hr_leave_requests.php
018_create_hr_leave_request_approvals.php

-- 5. Tur: Vardiya, PDKS, Puantaj
019_create_hr_shift_templates.php
020_create_hr_shift_plans.php
021_create_hr_shift_assignments.php
022_create_hr_shift_swaps.php
023_create_hr_attendance_devices.php
024_create_hr_clock_records.php
025_create_hr_attendance_anomalies.php
026_create_hr_timesheet_periods.php
027_create_hr_timesheets.php
028_create_hr_timesheet_corrections.php

-- 6. Tur: Bordro
029_create_hr_payroll_periods.php
030_create_hr_payroll_rules.php
031_create_hr_payroll_records.php

-- 7. Tur: Ücret, Yan Haklar
032_create_hr_salary_records.php
033_create_hr_salary_bands.php
034_create_hr_benefits.php
035_create_hr_employee_benefits.php

-- 8. Tur: Performans
036_create_hr_performance_cycles.php
037_create_hr_performance_templates.php
038_create_hr_performance_evaluations.php
039_create_hr_goals.php
040_create_hr_competencies.php
041_create_hr_employee_competencies.php

-- 9. Tur: Aday Takip
042_create_hr_job_postings.php
043_create_hr_candidates.php
044_create_hr_applications.php
045_create_hr_application_stages.php
046_create_hr_job_offers.php

-- 10. Tur: Onboarding/Offboarding
047_create_hr_onboarding_checklists.php
048_create_hr_onboarding_tasks.php
049_create_hr_offboarding_checklists.php
050_create_hr_offboarding_tasks.php

-- 11. Tur: Eğitim
051_create_hr_training_courses.php
052_create_hr_training_sessions.php
053_create_hr_training_enrollments.php
054_create_hr_certificates.php

-- 12. Tur: Masraf, Avans, Zimmet
055_create_hr_expense_categories.php
056_create_hr_expenses.php
057_create_hr_advances.php
058_create_hr_assets.php

-- 13. Tur: Bağlılık
059_create_hr_surveys.php
060_create_hr_survey_responses.php
061_create_hr_recognitions.php

-- 14. Tur: Onay Motoru
062_create_hr_approval_flows.php
063_create_hr_approval_steps.php
064_create_hr_requests.php
065_create_hr_request_approvals.php

-- 15. Tur: Destek, İSG, Analitik, Asistan
066_create_hr_support_tickets.php
067_create_hr_support_messages.php
068_create_hr_health_records.php
069_create_hr_safety_incidents.php
070_create_hr_assistant_queries.php

-- 16. Tur: Dosya (sonda, çünkü subject_type polymorphic)
071_create_hr_files.php

-- 17. Tur: Fazla mesai
072_create_hr_overtime_types.php
073_create_hr_overtime_requests.php

-- 18. Tur: Ek tablolar
074_create_hr_delegations.php
075_create_hr_transfer_history.php
076_create_hr_open_positions.php
077_create_hr_calculation_logs.php

-- 19. Tur: ActivityLog genişletme
078_extend_activity_logs_for_hr.php
```

---

## 8. Veritabanı Şeması

### 8.1 Core (Sürüm 0)

#### `permissions` (mevcut tabloya eklenir)
```sql
-- Mevcut permission tablosu varsa genişletilir, yoksa oluşturulur
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    guard_name VARCHAR(255) NOT NULL DEFAULT 'web',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### `roles` (mevcut tabloya genişletilir)
```sql
ALTER TABLE roles ADD COLUMN guard_name VARCHAR(255) DEFAULT 'web' AFTER slug;
```

#### `role_permission`
```sql
CREATE TABLE role_permission (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

#### `hr_licenses`
```sql
CREATE TABLE hr_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    max_employees INT UNSIGNED NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_hr_license_entity_module (legal_entity_id, module_key),
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE
);
```

#### `hr_holidays`
```sql
CREATE TABLE hr_holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    year YEAR GENERATED ALWAYS AS (YEAR(date)) STORED,
    type VARCHAR(20) NOT NULL DEFAULT 'national',
    is_recurring BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_holiday_entity_date (legal_entity_id, date),
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE
);
```

### 8.2 Personel ve Organizasyon (Sürüm 1)

#### `hr_employees`
```sql
CREATE TABLE hr_employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    employee_number VARCHAR(50) NOT NULL,

    -- Hassas veriler: şifreli + hash
    national_id_encrypted TEXT NOT NULL,
    national_id_hash VARCHAR(64) NOT NULL,

    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    gender VARCHAR(10) NULL,
    date_of_birth DATE NULL,
    marital_status VARCHAR(20) NULL,
    photo_path VARCHAR(500) NULL,
    phone VARCHAR(20) NULL,
    personal_email VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    postal_code VARCHAR(10) NULL,
    emergency_contact_name VARCHAR(200) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    emergency_contact_relation VARCHAR(100) NULL,
    blood_type VARCHAR(5) NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'active',
    hire_date DATE NOT NULL,
    termination_date DATE NULL,
    termination_reason TEXT NULL,
    probation_end_date DATE NULL,
    employment_type VARCHAR(20) NOT NULL DEFAULT 'full_time',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    UNIQUE KEY uq_hr_emp_entity_number (legal_entity_id, employee_number),
    UNIQUE KEY uq_hr_emp_entity_national_hash (legal_entity_id, national_id_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,

    INDEX idx_hr_emp_status (status),
    INDEX idx_hr_emp_hire_date (hire_date)
);
```

#### `hr_employment_records`
```sql
CREATE TABLE hr_employment_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    unit_id BIGINT UNSIGNED NULL,
    team_id BIGINT UNSIGNED NULL,
    position_id BIGINT UNSIGNED NULL,
    manager_id BIGINT UNSIGNED NULL,
    second_manager_id BIGINT UNSIGNED NULL,
    cost_center_id BIGINT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    employment_type VARCHAR(20) NOT NULL DEFAULT 'full_time',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
    INDEX idx_hr_emp_record_dates (start_date, end_date),
    INDEX idx_hr_emp_record_dept (department_id)
);
```

### 8.3 İzin (Sürüm 1)

#### `hr_leave_types`
```sql
CREATE TABLE hr_leave_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    is_paid BOOLEAN DEFAULT true,
    is_active BOOLEAN DEFAULT true,
    max_days_per_year DECIMAL(5,1) NULL,
    allow_negative BOOLEAN DEFAULT false,
    allow_half_day BOOLEAN DEFAULT true,
    allow_hourly BOOLEAN DEFAULT false,
    requires_document BOOLEAN DEFAULT false,
    carry_over BOOLEAN DEFAULT false,
    max_carry_over DECIMAL(5,1) NULL,
    accrual_type VARCHAR(20) NOT NULL DEFAULT 'annual',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_leave_type_entity_code (legal_entity_id, code),
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE
);
```

#### `hr_leave_transactions` (Esas Doğruluk Kaynağı)
```sql
CREATE TABLE hr_leave_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(5,1) NOT NULL,
    effective_date DATE NOT NULL,
    source_type VARCHAR(100) NULL,
    source_id BIGINT UNSIGNED NULL,
    year YEAR NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_leave_txn_emp_type_year (employee_id, leave_type_id, year),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE
);
```

#### `hr_leave_balances` (Cache/Özet)
```sql
CREATE TABLE hr_leave_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    year YEAR NOT NULL,
    remaining DECIMAL(5,1) NOT NULL DEFAULT 0,
    last_calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_leave_balance (employee_id, leave_type_id, year),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE
);
```

#### `hr_leave_requests`
```sql
CREATE TABLE hr_leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_entity_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_half_day VARCHAR(10) NOT NULL DEFAULT 'full',
    end_half_day VARCHAR(10) NOT NULL DEFAULT 'full',
    total_days DECIMAL(5,1) NOT NULL,
    reason TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    replacement_employee_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_hr_leave_emp_dates (employee_id, start_date, end_date),
    INDEX idx_hr_leave_entity_status (legal_entity_id, status),
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE
);
```

> **Not:** Geri kalan tablolar (hr_leave_request_approvals, hr_overtime_types, hr_overtime_requests, hr_shift_*, hr_clock_records, hr_timesheets, hr_payroll_*, hr_salary_*, hr_performance_*, hr_candidates, hr_applications, hr_assets, hr_expenses, hr_advances, vb.) benzer yapıyla oluşturulacak. Detaylı alan tanımları Versiyon 1.0 dokümanında mevcuttur.

---

## 9. Sürüm 0: Temel Altyapı — Tam Dosya Listesi (3-5 Hafta)

### 9.1 Dosya Listesi

```
app/Modules/Hr/
├── Core/
│   ├── HrServiceProvider.php
│   ├── Config/
│   │   └── hr.php
│   ├── Enums/
│   │   ├── HrModuleKey.php
│   │   ├── HrAction.php
│   │   ├── HrApprovalStatus.php
│   │   └── HrEmployeeStatus.php
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── ResolveHrTenant.php
│   │   │   └── HrAuthorize.php
│   │   └── Controllers/
│   │       └── HrFileController.php
│   ├── Services/
│   │   ├── TenantContext.php
│   │   ├── HrAuditService.php
│   │   ├── HrFileService.php
│   │   ├── HrNotificationService.php
│   │   └── HrCalendarService.php
│   ├── Actions/
│   │   ├── StoreHrFileAction.php
│   │   ├── DeleteHrFileAction.php
│   │   └── CalculateWorkingDaysAction.php
│   ├── Traits/
│   │   ├── BelongsToLegalEntity.php
│   │   └── HrAuditLoggable.php
│   ├── Observers/
│   │   └── HrAuditObserver.php
│   ├── Policies/
│   │   ├── HrBasePolicy.php
│   │   └── HrFilePolicy.php
│   ├── Jobs/
│   │   └── HrJob.php                    # Abstract base job
│   ├── Livewire/
│   │   ├── HrDashboard.php
│   │   └── HrSettings.php
│   ├── Routes/
│   │   └── hr.php
│   └── Resources/
│       └── views/
│           └── livewire/hr/
│               ├── dashboard.blade.php
│               └── settings.blade.php

database/
├── migrations/hr/
│   ├── 001_create_permissions_and_roles.php
│   ├── 002_create_hr_licenses.php
│   ├── 003_create_hr_holidays.php
│   └── 078_extend_activity_logs_for_hr.php
├── seeders/
│   └── HrPermissionSeeder.php
└── factories/
    └── Hr/
        ├── EmployeeFactory.php
        └── LeaveTypeFactory.php

tests/
├── Unit/
│   └── Hr/
│       ├── Services/
│       │   ├── TenantContextTest.php
│       │   ├── HrAuditServiceTest.php
│       │   ├── HrFileServiceTest.php
│       │   └── CalculateWorkingDaysTest.php
│       └── Traits/
│           └── BelongsToLegalEntityTest.php
└── Feature/
    └── Hr/
        ├── HrDashboardTest.php
        ├── HrSettingsTest.php
        ├── TenantIsolationTest.php
        ├── HrFileDownloadTest.php
        ├── HrPermissionTest.php
        └── HrLicenseTest.php

config/
└── hr.php
```

### 9.2 HrServiceProvider

```php
// app/Modules/Hr/Core/HrServiceProvider.php
namespace App\Modules\Hr\Core;

use Illuminate\Support\ServiceProvider;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Core\Observers\HrAuditObserver;
use App\Models\ActivityLog;

class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Rotaları kaydet
        $this->loadRoutesFrom(__DIR__ . '/Routes/hr.php');

        // Migration'ları kaydet
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');

        // View'ları kaydet
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'hr');

        // Config
        $this->mergeConfigFrom(__DIR__ . '/Config/hr.php', 'hr');

        // Observer
        ActivityLog::observe(HrAuditObserver::class);

        // Publisher
        $this->publishes([
            __DIR__ . '/Config/hr.php' => config_path('hr.php'),
        ], 'hr-config');
    }
}
```

### 9.3 config/hr.php

```php
<?php
return [
    'modules' => [
        'personel' => ['enabled' => true, 'label' => 'Personel ve Organizasyon'],
        'izin' => ['enabled' => true, 'label' => 'İzin Yönetimi'],
        'vardiya' => ['enabled' => true, 'label' => 'Vardiya'],
        'pdks' => ['enabled' => true, 'label' => 'PDKS'],
        'puantaj' => ['enabled' => true, 'label' => 'Puantaj'],
        'bordro' => ['enabled' => true, 'label' => 'Bordro'],
        'ucret' => ['enabled' => true, 'label' => 'Ücret ve Yan Haklar'],
        'performans' => ['enabled' => true, 'label' => 'Performans'],
        'aday_takip' => ['enabled' => true, 'label' => 'Aday Takip'],
        'egitim' => ['enabled' => true, 'label' => 'Eğitim'],
        'baglilik' => ['enabled' => true, 'label' => 'Çalışan Bağlılığı'],
        'analitik' => ['enabled' => true, 'label' => 'İK Analitiği'],
        'isg' => ['enabled' => true, 'label' => 'İSG ve Uyum'],
    ],

    'file' => [
        'disk' => 'private',
        'max_size_mb' => 20,
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ],
    ],

    'employee_number' => [
        'prefix' => 'EMP',
        'length' => 5,
    ],
];
```

### 9.4 HrPermissionSeeder

```php
// database/seeders/HrPermissionSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HrPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'hr.dashboard.view',
            'hr.settings.manage',
            'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
            'hr.employees.delete', 'hr.employees.view_salary', 'hr.employees.export',
            'hr.org_structure.view', 'hr.org_structure.manage',
            'hr.leaves.view', 'hr.leaves.create', 'hr.leaves.approve',
            'hr.leaves.manage_balance', 'hr.leaves.manage_type',
            'hr.attendance.view', 'hr.attendance.manage', 'hr.attendance.manual_entry',
            'hr.shifts.view', 'hr.shifts.plan', 'hr.shifts.manage',
            'hr.timesheet.view', 'hr.timesheet.confirm', 'hr.timesheet.close',
            'hr.payroll.view', 'hr.payroll.calculate', 'hr.payroll.approve',
            'hr.salary.view', 'hr.salary.manage', 'hr.salary.approve',
            'hr.performance.view', 'hr.performance.evaluate',
            'hr.recruitment.view', 'hr.recruitment.manage_candidates',
            'hr.training.view', 'hr.training.manage',
            'hr.expenses.view', 'hr.expenses.create', 'hr.expenses.approve',
            'hr.advances.view', 'hr.advances.create', 'hr.advances.approve',
            'hr.assets.view', 'hr.assets.manage',
            'hr.analytics.view', 'hr.analytics.export',
            'hr.support.view', 'hr.support.manage',
            'hr.isg.view', 'hr.isg.manage',
            'hr.assistant.query',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Varsayılan roller
        $admin = Role::firstOrCreate(['name' => 'İK Admin', 'slug' => 'hr_admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'İK Müdürü', 'slug' => 'hr_manager', 'guard_name' => 'web']);
        $manager->syncPermissions(array_filter($permissions, fn($p) => !str_contains($p, 'manage_rules')));
    }
}
```

---

## 10. Faz 0 Kabul Kriterleri (Test Edilebilir)

| # | Kriter | Test Sınıfı / Komut |
|---|--------|---------------------|
| 0.1 | `permissions` tablosu oluşturulur | `php artisan migrate --force` + `HrPermissionSeederTest` |
| 0.2 | `role_permission` pivot çalışır | `HrPermissionTest::test_role_can_be_assigned_permissions()` |
| 0.3 | Kullanıcıya doğrudan izin verilebilir | `HrPermissionTest::test_user_can_be_assigned_direct_permission()` |
| 0.4 | Lisanssız modül açılamaz | `HrLicenseTest::test_inactive_module_blocks_access()` |
| 0.5 | Tenant context doğru set edilir | `TenantContextTest::test_set_and_get_tenant()` |
| 0.6 | Global scope tenant filtresi uygular | `BelongsToLegalEntityTest::test_global_scope_filters_by_tenant()` |
| 0.7 | Tenant A kullanıcısı Tenant B kaydını göremez | `TenantIsolationTest::test_user_cannot_access_other_tenant_data()` |
| 0.8 | Route model binding tenant dışı kayıt döndürmez | `TenantIsolationTest::test_route_model_binding_respects_tenant()` |
| 0.9 | Audit log hassas veri içermez | `HrAuditServiceTest::test_audit_log_masks_sensitive_data()` |
| 0.10 | Audit log TC, IBAN, maaş içermez | `HrAuditServiceTest::test_audit_log_excludes_national_id_and_salary()` |
| 0.11 | Dosya başka tenant tarafından indirilemez | `HrFileDownloadTest::test_file_download_blocked_for_other_tenant()` |
| 0.12 | Dosya indirme audit log'a yazılır | `HrFileDownloadTest::test_file_download_is_audited()` |
| 0.13 | Yetkisiz kullanıcı export yapamaz | `HrPermissionTest::test_export_requires_permission()` |
| 0.14 | Migration rollback sorunsuz çalışır | `php artisan migrate:rollback --force` |
| 0.15 | Queue job tenant context ile çalışır | `TenantIsolationTest::test_job_inherits_tenant_context()` |
| 0.16 | HrServiceProvider kayıtlı | `HrServiceProviderTest::test_service_provider_is_registered()` |
| 0.17 | Config dosyası yüklü | `HrServiceProviderTest::test_config_is_merged()` |
| 0.18 | Rotalar `/hr` prefix'inde | `HrRouteTest::test_routes_have_hr_prefix()` |
| 0.19 | Çalışan bakiye hesaplama doğrudur | `CalculateWorkingDaysTest::test_working_days_exclude_holidays()` |
| 0.20 | Mevcut ActivityLog genişletilmiş | `HrAuditServiceTest::test_activity_log_has_hr_columns()` |

### Çalıştırma Komutları

```bash
# Tüm testler
php artisan test --testsuite=Feature --filter="Hr"

# Tenant izolasyon testleri
php artisan test --filter="TenantIsolation"

# Audit testleri
php artisan test --filter="HrAudit"

# Dosya testleri
php artisan test --filter="HrFile"

# Yetki testleri
php artisan test --filter="HrPermission"

# Migration testi
php artisan migrate:fresh --seed && php artisan migrate:rollback
```

---

## 11. Olay Sistemi

```
EmployeeCreated          → Otomatik employee_number üretimi, hoşgeldin bildirimi
EmployeeTransferred      → Eski/pozisyon geçmişi güncelleme, bildirimler
EmployeeTerminated       → Offboarding başlat, hesap kapatma, zimmet iadesi
LeaveApproved            → hr_leave_transactions'a usage ekle, bakiyeyi yenile
ShiftAssigned            → Çalışana bildirim
AttendanceCorrected      → Puantaj güncelle
TimesheetClosed          → Bordro hesaplama tetikle
PayrollApproved          → Ödeme dosyası oluştur, muhasebeye aktar
SalaryChanged            → Ücret geçmişi kaydet, bordroya yansıt
PerformanceCompleted     → Kalibrasyon tetikle, geliştirme planı oluştur
CandidateHired           → Employee oluştur, onboarding başlat
AssetReturned            → Durum güncelle, bildirim
CertificateExpired       → Hatırlatma bildirimi gönder
```

---

## 12. Rota Yapısı

```
/hr                                          → HrDashboard
/hr/settings                                 → HrSettings
/hr/settings/holidays                        → HolidayManager
/hr/settings/approval-flows                  → ApprovalFlowManager

/hr/personnel                                → EmployeeList
/hr/personnel/create                         → EmployeeCreate
/hr/personnel/{id}                           → EmployeeDetail
/hr/personnel/{id}/edit                      → EmployeeEdit
/hr/personnel/import                         → EmployeeImport

/hr/files/{id}/download                      → HrFileController@download
/hr/files/{id}/signed-url                    → HrFileController@signedUrl

/hr/leaves                                   → LeaveList
/hr/leaves/balance                           → LeaveBalanceManager
/hr/leaves/types                             → LeaveTypeManager
/hr/leaves/{id}                              → LeaveDetail
/hr/leaves/my                                → MyLeaves

/hr/shifts                                   → ShiftList
/hr/shifts/plans                             → ShiftPlanList
/hr/shifts/plans/create                      → ShiftPlanCreate

/hr/attendance                               → AttendanceList
/hr/attendance/anomalies                     → AnomalyList

/hr/timesheet                                → TimesheetList
/hr/timesheet/{period}                        → TimesheetDetail

/hr/payroll                                  → PayrollList
/hr/payroll/periods/{id}                      → PayrollRun

/hr/performance                              → PerformanceList
/hr/performance/cycles/{id}                   → CycleDetail
/hr/performance/goals                         → GoalList

/hr/recruitment                              → RecruitmentDashboard
/hr/recruitment/postings                      → PostingList
/hr/recruitment/candidates/{id}               → CandidateDetail

/hr/training                                 → TrainingList
/hr/expenses                                 → ExpenseList
/hr/advances                                 → AdvanceList
/hr/assets                                   → AssetList

/hr/analytics                                → AnalyticsDashboard
/hr/support                                  → SupportTicketList
/hr/tools                                    → CalculatorTools
/hr/portal                                   → EmployeePortal
```

---

## 13. Sürüm 1-8 Kısım Özet

### Sürüm 1: Personel, Organizasyon, Belge, İzin, Portal (10-14 Hafta)
- Çalışan listesi/profil/oluştur/düzenle/düzenle/import
- Organizasyon yapısı (hiyerarşik departman/birim/ekip/pozisyon)
- İzin türü/bakiye (hareket tablosu)/talep/onay akışı
- Belge yükleme/indirme (private disk, policy, audit)
- Çalışan portalı (izin talebi, profil, belge)

### Sürüm 2: Vardiya, PDKS, Puantaj, Fazla Mesai (10-14 Hafta)
- Vardiya şablonları, aylık plan, toplu atama, vardiya takası
- Giriş-çıkış (QR, web, mobil, PIN, NFC, turnike, API)
- Uyuşmazlık motoru (16 tür)
- Günlük puantaj → aylık kapanış
- Fazla mesai talebi, bütçe kontrolü, üretim emri bağlantısı

### Sürüm 3: Bordro Hazırlık, Masraf, Avans, Zimmet (8-12 Hafta)
- Puantaj aktarımı, bordro hazırlık ekranı
- Masraf (fiş/fatura, KDV, proje/sipariş/müşteri bağlantısı)
- Avans (taksitli kesinti, bordroya aktarım)
- Zimmet (stok/demirbaş bağlantısı, dijital kabul)

### Sürüm 4: Performans, Eğitim, Bağlılık (10-14 Hafta)
- 360° değerlendirme, KPI/OKR, yetkinlik matrisi
- Eğitim kataloğu, katılım, sınav, sertifika
- Nabız anketi, eNPS, takdir/thanks

### Sürüm 5: Aday Takip, Onboarding, Offboarding (10-14 Hafta)
- Kanban, görüşme takvimi, puanlama, teklif onayı
- Adaydan çalışana otomatik dönüşüm
- 7/30/60/90 günlük görevler

### Sürüm 6: Ücret, Kadro Bütçesi, Gelişmiş Analitik (10-14 Hafta)
- Ücret geçmişi, toplu zam, ücret bantları, simülasyon
- İK analitiği (turnover, FTE, maliyet dağılımı)
- ZOLM'a özel: ürün başına işçilik maliyeti

### Sürüm 7: Tam Bordro ve Resmî Çıktılar (16-24 Hafta)
- Brüt-net, net-brüt, kümülatif vergi matrahı
- SGK, istisnalar, teşvikler
- MUHSGK çıktısı, banka ödeme dosyası
- Kural sürümü ve hesaplama izi

### Sürüm 8: Mobil, Yapay Zekâ, Entegrasyonlar (12-18 Hafta)
- PWA: giriş-çıkış, QR, izin, masraf, bordro, onay
- İK Asistanı: doğal dil sorguları
- Üretim/stok/CRM/ön muhasebe entegrasyonu

---

## 14. Geliştirme Takvimi

| Sürüm | Kapsam | Tahmini |
|-------|--------|---------|
| 0 | Tenant, yetki, audit, lisans, dosya, bildirim | 3-5 hafta |
| 1 | Personel, organizasyon, belge, izin, portal | 10-14 hafta |
| 2 | Vardiya, PDKS, puantaj, fazla mesai | 10-14 hafta |
| 3 | Bordro hazırlık, masraf, avans, zimmet | 8-12 hafta |
| 4 | Performans, eğitim, bağlılık | 10-14 hafta |
| 5 | Aday takip, onboarding, offboarding | 10-14 hafta |
| 6 | Ücret, kadro bütçesi, gelişmiş analitik | 10-14 hafta |
| 7 | Tam bordro ve resmî çıktılar | 16-24 hafta |
| 8 | Mobil, yapay zekâ, entegrasyonlar | 12-18 hafta |

**Toplam:** Tek geliştirici ile ~18-30 ay, 3-4 kişilik ekip ile ~10-16 ay.

---

## 15. Nihai Mimari Kararlar Özeti

| Karar | Seçim | Gerekçe |
|-------|-------|---------|
| Mimari | Modüler monolit | Laravel 12 uyumlu, ölçeklenebilir |
| Tenant | Multi-layer (middleware + scope + cache + dosya) | Veri sızıntısı engeli |
| Audit | Tek `activity_logs` tablosu + metadata | Mevcut altyapı tekrar kullanılır |
| Hassas veri | Şifreli alan + hash çifti | KVKK uyumu |
| İzin bakiyesi | Hareket tablosu (ledger) | Tutarsızlık engeli |
| Dosya | Private disk + policy + imzalı URL | Güvenli erişim |
| ENUM | PHP backed enum + VARCHAR | Taşınabilirlik |
| Generated col | Kaldırıldı | DB uyumsuzluğu engeli |
| Foreign key | İkinci tur migration ile | Circular FK engeli |
| Mevcut sistem | ActivityLog, User, LegalEntity tekrar kullanılır | İkinci sistem önlenir |

---

**Bu dokümanda uygulama kodu bulunmamaktadır. Kodlama için yönetici onayı gereklidir.**
