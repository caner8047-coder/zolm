<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_campaign_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->string('payment_type', 20);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('amount_ex_vat', 12, 2)->nullable();
            $table->decimal('vat_amount', 12, 2)->nullable();
            $table->decimal('amount_inc_vat', 12, 2)->nullable();
            $table->string('payment_status', 20)->default('estimated');
            $table->string('source', 20)->default('panel');
            $table->timestamps();
            $table->index(['campaign_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_campaign_payments');
    }
};
