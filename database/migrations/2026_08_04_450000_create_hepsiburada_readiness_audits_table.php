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
        Schema::create('hepsiburada_readiness_audits', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id', 120)->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->unsignedBigInteger('acting_user_id')->nullable();
            $table->unsignedBigInteger('tenant_user_id')->nullable();
            $table->string('release_sha', 120)->nullable();
            $table->string('runtime_id', 120)->nullable();
            $table->string('operation', 50);
            $table->boolean('confirm_read')->default(false);
            $table->boolean('rollout_gate')->default(false);
            $table->boolean('http_attempted')->default(false);
            $table->integer('http_status')->nullable();
            $table->string('provider_error_code', 100)->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('item_count')->nullable();
            $table->integer('db_mutation_count')->nullable();
            $table->string('decision', 100);
            $table->timestamp('created_at')->nullable();

            $table->foreign('store_id')
                ->references('id')
                ->on('marketplace_stores')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hepsiburada_readiness_audits');
    }
};
