<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->timestamp('erp_pushed_at')->nullable()->after('is_reconciled');
            $table->enum('erp_status', ['pending', 'retry', 'success', 'failed'])->nullable()->after('erp_pushed_at');
            $table->text('erp_response')->nullable()->after('erp_status')->comment('Webhook yanıtı veya hata logu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->dropColumn(['erp_pushed_at', 'erp_status', 'erp_response']);
        });
    }
};
