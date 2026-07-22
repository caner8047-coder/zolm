<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_price_canary_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('readiness_evaluation_id', 64)->nullable()->index();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('approval_scope', 40)->default('single_product'); // single_product, three_products
            $table->json('approved_product_ids')->nullable(); // json array of barcodes
            $table->string('approval_reason')->nullable();
            
            $table->json('shadow_report_snapshot')->nullable();
            $table->json('readiness_snapshot')->nullable();
            
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->cascadeOnDelete();
            
            $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected, expired, revoked, consumed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_price_canary_approvals');
    }
};
