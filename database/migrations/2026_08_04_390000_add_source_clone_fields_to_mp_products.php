<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            $table->unsignedBigInteger('source_user_id')->nullable()->after('import_source');
            $table->unsignedBigInteger('source_product_id')->nullable()->after('source_user_id');
            $table->string('clone_reason')->nullable()->after('source_product_id');
            $table->string('clone_correlation_id')->nullable()->after('clone_reason');
            $table->timestamp('cloned_at')->nullable()->after('clone_correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            $table->dropColumn([
                'source_user_id',
                'source_product_id',
                'clone_reason',
                'clone_correlation_id',
                'cloned_at',
            ]);
        });
    }
};
