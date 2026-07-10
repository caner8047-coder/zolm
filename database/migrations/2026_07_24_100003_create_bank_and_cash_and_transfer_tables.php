<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Muhasebe hesap planı bağlantısı
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();

            $table->string('bank_name', 120);
            $table->string('branch_name', 120)->nullable();
            $table->string('account_number', 60)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('currency_code', 3)->default('TRY');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'account_id']);
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Muhasebe hesap planı bağlantısı
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('currency_code', 3)->default('TRY');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'account_id']);
        });

        Schema::create('money_transfers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Hangi kasadan/bankadan hangi kasaya/bankaya transfer edildi
            $table->unsignedBigInteger('from_account_id'); // Borç alacak hesapları
            $table->unsignedBigInteger('to_account_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->decimal('amount', 14, 2);
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->date('transfer_date');
            $table->string('description', 500)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_transfers');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('bank_accounts');
    }
};
