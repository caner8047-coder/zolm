<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compensations', function (Blueprint $table) {
            $table->foreignId('responsible_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->after('durum');
            $table->string('carrier_case_no')->nullable()->after('kargo_referans_no');
            $table->decimal('collected_amount', 12, 2)->default(0)->after('onaylanan_tutar');
            $table->date('payment_date')->nullable()->after('sonuc_tarihi');
            $table->timestamp('first_action_at')->nullable()->after('payment_date');
            $table->timestamp('last_action_at')->nullable()->after('first_action_at');
            $table->date('next_action_at')->nullable()->after('last_action_at');
            $table->text('internal_note')->nullable()->after('aciklama');
            $table->text('resolution_note')->nullable()->after('internal_note');

            $table->index(['durum', 'talep_tarihi'], 'comp_durum_talep_tarihi_idx');
            $table->index(['cargo_company', 'durum'], 'comp_company_durum_idx');
            $table->index(['responsible_user_id', 'durum'], 'comp_responsible_durum_idx');
            $table->index(['payment_date'], 'comp_payment_date_idx');
        });

        DB::table('compensations')->update([
            'responsible_user_id' => DB::raw('COALESCE(responsible_user_id, user_id)'),
            'priority' => DB::raw("COALESCE(NULLIF(priority, ''), 'normal')"),
            'talep_tarihi' => DB::raw('COALESCE(talep_tarihi, tarih, DATE(created_at))'),
            'first_action_at' => DB::raw('COALESCE(first_action_at, created_at)'),
            'last_action_at' => DB::raw('COALESCE(last_action_at, updated_at, created_at)'),
        ]);

        DB::table('compensations')
            ->whereIn('durum', ['odendi', 'odeme_bekleniyor'])
            ->whereNull('payment_date')
            ->update([
                'payment_date' => DB::raw('COALESCE(sonuc_tarihi, DATE(updated_at), DATE(created_at))'),
            ]);
    }

    public function down(): void
    {
        Schema::table('compensations', function (Blueprint $table) {
            $table->dropIndex('comp_durum_talep_tarihi_idx');
            $table->dropIndex('comp_company_durum_idx');
            $table->dropIndex('comp_responsible_durum_idx');
            $table->dropIndex('comp_payment_date_idx');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn([
                'priority',
                'carrier_case_no',
                'collected_amount',
                'payment_date',
                'first_action_at',
                'last_action_at',
                'next_action_at',
                'internal_note',
                'resolution_note',
            ]);
        });
    }
};
