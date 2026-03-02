<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Merkezi Ayar Deposu
     * Kullanıcı bazlı JSON settings — tüm finansal kurallar, toleranslar,
     * mutabakat eşikleri tek merkezden yönetilir.
     */
    public function up(): void
    {
        Schema::create('mp_accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_accounting_settings');
    }
};
