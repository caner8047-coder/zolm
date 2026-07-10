<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Hiyerarşi
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('account_groups')->nullOnDelete();

            $table->string('code', 20);          // Örn: "1", "10", "100"
            $table->string('name', 180);          // Örn: "Dönen Varlıklar"
            $table->string('type', 30);           // asset / liability / equity / revenue / expense
            $table->string('normal_balance', 10); // debit / credit — bu grubun doğal bakiye tarafı
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta_json')->nullable();

            $table->timestamps();

            // Kullanıcı başına grup kodu tekil
            $table->unique(['user_id', 'code'], 'account_groups_user_code_unique');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_groups');
    }
};
