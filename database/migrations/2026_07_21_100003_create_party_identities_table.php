<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('source_type', 50);
            $table->string('identity_kind', 40);
            $table->string('identity_value', 191);
            $table->string('external_id', 191)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            // store_id nullable; SQLite/MySQL NULL'ları distinct kabul eder → güvenli.
            $table->unique(['user_id', 'source_type', 'store_id', 'identity_kind', 'identity_value'], 'party_identities_user_source_store_kind_value_unique');
            $table->index(['user_id', 'identity_kind', 'identity_value'], 'party_identities_user_kind_value_idx');
            $table->index(['party_id', 'source_type'], 'party_identities_party_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_identities');
    }
};
