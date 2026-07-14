<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_connector_certification_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('channel_key')->index();
            $table->unsignedBigInteger('certified_by')->nullable()->index();
            $table->string('status'); // pass, warn, fail
            $table->timestamp('certified_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('support_connector_certification_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->string('check_name');
            $table->string('status'); // pass, fail, warn
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_connector_certification_checks');
        Schema::dropIfExists('support_connector_certification_runs');
    }
};
