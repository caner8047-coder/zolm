<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_revenue_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('source_hash', 64)->nullable();
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('unchanged_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('sheet_count')->default(0);
            $table->timestamp('imported_at');
            $table->json('months')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'imported_at']);
        });

        Schema::create('production_revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_revenue_import_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('sheet_name', 80)->nullable();
            $table->decimal('revenue', 14, 2)->default(0);
            $table->string('note', 160)->nullable();
            $table->string('status', 30)->default('recorded');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('work_date');
            $table->index(['status', 'work_date']);
            $table->index(['sheet_name', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_revenue_entries');
        Schema::dropIfExists('production_revenue_imports');
    }
};
