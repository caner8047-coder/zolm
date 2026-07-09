<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            // Geriye uyumlu: nullable FK. Mevcut CRM akışlarını bozmaz (Karar #6).
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->index(['user_id', 'party_id'], 'crm_contacts_user_party_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropForeign(['party_id']);
            $table->dropIndex('crm_contacts_user_party_idx');
            $table->dropColumn('party_id');
        });
    }
};
