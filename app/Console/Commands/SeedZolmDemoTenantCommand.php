<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\Role;
use App\Models\User;
use App\Services\Demo\ZolmDemoTenantAuditor;
use App\Services\Demo\ZolmDemoTenantResetter;
use App\Services\Demo\ZolmDemoTenantSeeder;
use Database\Seeders\DefaultProfileSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SeedZolmDemoTenantCommand extends Command
{
    protected $signature = 'zolm:demo:seed
        {--email=mockdata1@zolm.test : Demo kullanıcı e-posta adresi}
        {--password=password : Yalnız local/testing için demo parolası}
        {--reset : Bu e-postaya ait mevcut demo kullanıcıyı ve tenant verisini silip yeniden kur}
        {--allow-shared-db : Başka kullanıcılar bulunan geliştirme DB uyarısını onayla}';

    protected $description = 'ZOLM için güvenli, idempotent ve tüm ana modüllere yayılan demo tenant verisi oluşturur.';

    public function handle(
        ZolmDemoTenantSeeder $seeder,
        ZolmDemoTenantAuditor $auditor,
        ZolmDemoTenantResetter $resetter,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Güvenlik nedeniyle bu komut yalnız local veya testing ortamında çalışır. --force kaçış yolu yoktur.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! Str::endsWith($email, '@zolm.test')) {
            $this->error('Demo kullanıcı e-postası @zolm.test ile biten geçerli bir adres olmalıdır.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 8) {
            $this->error('Demo parolası en az 8 karakter olmalıdır.');

            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();
        if ($this->option('reset') && $existingUser) {
            $this->warn("Yalnız {$email} kullanıcısı ve ona bağlı tenant verisi siliniyor.");

            try {
                DB::transaction(fn () => $resetter->reset($existingUser));
            } catch (Throwable $exception) {
                $this->error('Güvenli reset tamamlanamadı: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        $otherUserCount = User::where('email', '!=', $email)->count();
        if ($otherUserCount > 0 && ! $this->option('allow-shared-db')) {
            $this->warn("Bu DB'de {$otherUserCount} başka kullanıcı var. Veri yazımı tenant-scoped olsa da admin ekranlarının tam izolasyon testi için ayrı demo DB önerilir.");
            $this->line('Uyarıyı bilinçli onaylamak için sonraki çalıştırmalarda --allow-shared-db kullanabilirsiniz.');
        }

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Yönetici']);
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'ZOLM Mockdata Demo Yöneticisi',
                'password' => $password,
                'role_id' => $adminRole->id,
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $user->forceFill(['email_verified_at' => now()])->saveQuietly();

        $this->info("Demo tenant hazırlanıyor: {$user->email} (#{$user->id})");

        $accountingExit = $this->call('accounting:seed-demo', ['--user' => $user->id]);
        if ($accountingExit !== self::SUCCESS) {
            $this->error('Muhasebe demo omurgası oluşturulamadı; diğer modüllere geçilmedi.');

            return self::FAILURE;
        }

        $legalEntity = LegalEntity::where('user_id', $user->id)
            ->where('tax_number', '1234567890')
            ->first()
            ?? LegalEntity::where('user_id', $user->id)->first();

        if (! $legalEntity) {
            $this->error('Demo firma kaydı bulunamadı.');

            return self::FAILURE;
        }

        $legalEntity->update([
            'name' => 'ZOLM Mockdata Mobilya A.Ş.',
            'tax_office' => 'Kadıköy Demo Vergi Dairesi',
            'phone' => '+90 555 000 00 99',
            'email' => 'firma@mockdata1.zolm.test',
            'address' => 'ZOLM sentetik demo adresi, Denizli',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        (new DefaultProfileSeeder)->runForUser((int) $user->id);
        $moduleResults = $seeder->seed($user->fresh(), $legalEntity->fresh());

        $this->newLine();
        $this->table(
            ['Modül', 'Durum', 'Kayıt', 'Açıklama'],
            collect($moduleResults)->map(fn (array $result, string $module): array => [
                $module,
                $result['status'],
                $result['records'],
                $result['detail'],
            ])->values()->all()
        );

        $audit = $auditor->audit($email, $password);
        $this->renderAudit($audit['findings']);

        $moduleFailure = collect($moduleResults)->contains(fn (array $result): bool => $result['status'] === 'failed');
        if ($moduleFailure || ! $audit['healthy']) {
            $this->error('Demo tenant kısmen oluştu; başarısız kontrolleri düzeltmeden tam sistem testi yapmayın.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Demo tenant hazır. Giriş: '.$email.' / '.$password);
        $this->warn('Mock audit başarısı gerçek pazaryeri API bağlantısı kanıtı değildir; canlı/sandbox testi ayrı ve opt-in yapılmalıdır.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{area: string, status: string, detail: string}>  $findings
     */
    private function renderAudit(array $findings): void
    {
        $this->newLine();
        $this->line('Demo tenant sağlık raporu');
        $this->table(
            ['Alan', 'Durum', 'Detay'],
            collect($findings)->map(fn (array $finding): array => [
                $finding['area'],
                strtoupper($finding['status']),
                $finding['detail'],
            ])->all()
        );
    }
}
