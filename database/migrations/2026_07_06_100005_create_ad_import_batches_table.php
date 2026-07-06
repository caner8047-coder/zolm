<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->nullable()->constrained('ad_accounts')->nullOnDelete();
            $table->string('channel_code', 50);
            $table->string('import_type', 50);
            $table->string('status', 20)->default('uploaded');
            $table->date('report_period_start');
            $table->date('report_period_end');
            $table->timestamp('exported_at')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_filename', 500);
            $table->string('storage_path', 500);
            $table->string('file_hash', 64);
            $table->string('source_fingerprint', 128)->nullable();
            $table->foreignId('campaign_id_context')->nullable()->constrained('ad_campaigns')->nullOnDelete();
            $table->foreignId('duplicate_of_batch_id')->nullable()->constrained('ad_import_batches')->nullOnDelete();
            $table->integer('row_count')->default(0);
            $table->integer('valid_row_count')->default(0);
            $table->integer('invalid_row_count')->default(0);
            $table->json('error_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'channel_code', 'created_at']);
            $table->unique(['user_id', 'source_fingerprint'], 'ad_import_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_import_batches');
    }
};
