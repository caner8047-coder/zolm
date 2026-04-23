<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_intake_items', function (Blueprint $table) {
            $table->string('operator_barcode', 120)->nullable()->after('manual_reference');
            $table->string('suggested_decision', 60)->nullable()->after('decision_status');
            $table->decimal('suggested_confidence', 5, 2)->nullable()->after('suggested_decision');
            $table->text('suggestion_summary')->nullable()->after('suggested_confidence');

            $table->index(['operator_barcode'], 'return_items_operator_barcode_idx');
            $table->index(['suggested_decision'], 'return_items_suggested_decision_idx');
        });

        Schema::table('return_intake_media', function (Blueprint $table) {
            $table->unsignedBigInteger('original_size_bytes')->nullable()->after('size_bytes');
            $table->string('thumbnail_path', 500)->nullable()->after('path');
            $table->unsignedBigInteger('thumbnail_size_bytes')->nullable()->after('original_size_bytes');
            $table->timestamp('optimized_at')->nullable()->after('captured_at');
            $table->json('storage_meta')->nullable()->after('optimized_at');
        });
    }

    public function down(): void
    {
        Schema::table('return_intake_media', function (Blueprint $table) {
            $table->dropColumn([
                'original_size_bytes',
                'thumbnail_path',
                'thumbnail_size_bytes',
                'optimized_at',
                'storage_meta',
            ]);
        });

        Schema::table('return_intake_items', function (Blueprint $table) {
            $table->dropIndex('return_items_operator_barcode_idx');
            $table->dropIndex('return_items_suggested_decision_idx');
            $table->dropColumn([
                'operator_barcode',
                'suggested_decision',
                'suggested_confidence',
                'suggestion_summary',
            ]);
        });
    }
};
