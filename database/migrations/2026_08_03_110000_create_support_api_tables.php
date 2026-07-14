<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enterprise API Scoped Access (Dalga AR)
        Schema::create('support_api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('client_id', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('support_api_clients')->cascadeOnDelete();
            $table->string('token_prefix', 20); // örn. cc_
            $table->string('token_hash', 64)->unique(); // token plain halinin sha256 hash'i
            $table->json('scopes')->nullable(); // ['conversations:read', 'messages:read'...]
            $table->json('store_ids')->nullable(); // bu tokenın erişebileceği store_id'ler
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_api_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->nullable()->constrained('support_api_clients')->nullOnDelete();
            $table->foreignId('api_token_id')->nullable()->constrained('support_api_tokens')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('method', 10);
            $table->string('uri', 255);
            $table->unsignedInteger('response_status');
            $table->string('ip_address', 45)->nullable();
            $table->text('request_payload_redacted')->nullable(); // PII içermeyen redacted log
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_api_access_logs');
        Schema::dropIfExists('support_api_tokens');
        Schema::dropIfExists('support_api_clients');
    }
};
