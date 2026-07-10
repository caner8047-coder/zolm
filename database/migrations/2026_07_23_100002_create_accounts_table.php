<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Hesap grubu (opsiyonel — TDHP gruplama için)
            $table->unsignedBigInteger('account_group_id')->nullable();
            $table->foreign('account_group_id')->references('id')->on('account_groups')->nullOnDelete();

            // Legal entity bazlı hesap ayrımı (çok şirketli tenant)
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();

            $table->string('code', 30);           // Örn: "100", "320", "600"
            $table->string('name', 180);          // Örn: "Kasa", "Satıcılar", "Yurt İçi Satışlar"
            $table->string('type', 30);           // asset / liability / equity / revenue / expense
            $table->string('normal_balance', 10); // debit / credit
            $table->string('currency_code', 3)->default('TRY');

            // Muhasebe davranışı
            $table->boolean('is_bank_account')->default(false);   // Banka hesabı mı?
            $table->boolean('is_cash_account')->default(false);   // Kasa hesabı mı?
            $table->boolean('is_ar_account')->default(false);     // Alacak hesabı mı?
            $table->boolean('is_ap_account')->default(false);     // Borç hesabı mı?
            $table->boolean('is_system')->default(false);         // Sistem tarafından yönetilen (seed)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            // Kullanıcı başına hesap kodu tekil (legal_entity dahil olmayabilir, nullable olduğu için index yeterli)
            $table->unique(['user_id', 'code'], 'accounts_user_code_unique');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'legal_entity_id']);
            $table->index(['user_id', 'account_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
