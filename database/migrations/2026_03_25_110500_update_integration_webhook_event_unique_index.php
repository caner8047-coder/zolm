<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_webhook_events', function (Blueprint $table) {
            $table->dropUnique('integration_webhook_events_provider_external_unique');
            $table->unique(
                ['store_id', 'provider', 'external_event_id'],
                'integration_webhook_events_store_provider_external_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('integration_webhook_events', function (Blueprint $table) {
            $table->dropUnique('integration_webhook_events_store_provider_external_unique');
            $table->unique(
                ['provider', 'external_event_id'],
                'integration_webhook_events_provider_external_unique'
            );
        });
    }
};
