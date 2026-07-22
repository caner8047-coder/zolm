<?php

namespace Tests\Feature;

use App\Console\Commands\AccountingSeedDemoCommand;
use App\Console\Commands\SeedAccountingDemoCommand;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DefaultProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoSeedInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_demo_and_legacy_foundation_commands_have_distinct_names(): void
    {
        $this->assertSame('accounting:seed-demo', (new SeedAccountingDemoCommand)->getName());
        $this->assertSame('accounting:seed-foundation', (new AccountingSeedDemoCommand)->getName());
    }

    public function test_legacy_foundation_command_cannot_create_a_demo_user_in_production(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $exitCode = Artisan::call('accounting:seed-foundation');

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString(
                'Production ortamında --user zorunludur; otomatik demo kullanıcı oluşturulmaz.',
                Artisan::output()
            );
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseMissing('users', ['email' => 'demo@zolm.com']);
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_default_profiles_can_be_seeded_for_a_requested_user_idempotently(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $seeder = new DefaultProfileSeeder;

        $seeder->runForUser($user->id);
        $seeder->runForUser($user->id);

        $this->assertSame(2, Profile::where('user_id', $user->id)->count());
        $this->assertSame(0, Profile::where('user_id', $otherUser->id)->count());
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'name' => 'Varsayılan Üretim',
            'type' => 'production',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'name' => 'Varsayılan Operasyon',
            'type' => 'operation',
            'is_default' => true,
        ]);
    }

    public function test_default_profile_seeder_keeps_the_existing_admin_email_behavior(): void
    {
        $admin = User::factory()->create(['email' => 'admin@zolm.test']);
        $otherUser = User::factory()->create();

        (new DefaultProfileSeeder)->run();

        $this->assertSame(2, Profile::where('user_id', $admin->id)->count());
        $this->assertSame(0, Profile::where('user_id', $otherUser->id)->count());
    }
}
