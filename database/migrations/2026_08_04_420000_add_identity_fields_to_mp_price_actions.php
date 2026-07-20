<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiyat aksiyonu kimlik doğrulaması için zorunlu idempotency ve correlation alanları.
     * Bu alanlar TrendyolConnector::pushPrice() write guard tarafından zorunlu kılınmaktadır.
     */
    public function up(): void
    {
        Schema::table('mp_price_actions', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->index()->after('batch_request_id');
            $table->string('correlation_id', 128)->nullable()->index()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('mp_price_actions', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'correlation_id']);
        });
    }
};
