<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Önce olası eski tekrarları deterministik olarak temizle; her readiness
        // çalışması için ilk snapshot kanonik kayıt olarak korunur.
        $duplicates = DB::table('support_production_freeze_snapshots')
            ->select('store_id', 'run_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('store_id', 'run_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('support_production_freeze_snapshots')
                ->where('store_id', $duplicate->store_id)
                ->where('run_id', $duplicate->run_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('support_production_freeze_snapshots', function (Blueprint $table) {
            $table->unique(['store_id', 'run_id'], 'support_freeze_store_run_unique');
        });
    }

    public function down(): void
    {
        Schema::table('support_production_freeze_snapshots', function (Blueprint $table) {
            $table->dropUnique('support_freeze_store_run_unique');
        });
    }
};
