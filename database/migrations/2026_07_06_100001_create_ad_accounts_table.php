<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace', 50)->default('trendyol');
            $table->string('account_name', 255);
            $table->string('external_account_id', 100)->nullable();
            $table->string('currency_code', 3)->default('TRY');
            $table->string('timezone', 50)->default('Europe/Istanbul');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_accounts');
    }
};
