<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_whatsapp_threads', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('meta_cloud_api');
            $table->string('external_chat_id', 160)->nullable();
            $table->string('sender_phone', 40)->nullable();
            $table->string('sender_name', 160)->nullable();
            $table->string('intake_type', 30)->default('undamaged');
            $table->string('status', 40)->default('collecting');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('analysis_requested_at')->nullable();
            $table->foreignId('return_intake_batch_id')->nullable()->constrained('return_intake_batches')->nullOnDelete();
            $table->foreignId('return_intake_item_id')->nullable()->constrained('return_intake_items')->nullOnDelete();
            $table->json('raw_context_json')->nullable();
            $table->timestamps();

            $table->index(['sender_phone', 'status', 'last_message_at'], 'return_wa_threads_sender_status_idx');
        });

        Schema::create('return_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_whatsapp_thread_id')->constrained('return_whatsapp_threads')->cascadeOnDelete();
            $table->string('external_message_id', 191)->unique();
            $table->string('message_type', 40);
            $table->text('text_content')->nullable();
            $table->text('caption')->nullable();
            $table->string('media_external_id', 191)->nullable();
            $table->string('media_mime_type', 120)->nullable();
            $table->string('media_disk', 40)->nullable();
            $table->string('media_path', 500)->nullable();
            $table->foreignId('return_intake_media_id')->nullable()->constrained('return_intake_media')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['return_whatsapp_thread_id', 'received_at'], 'return_wa_messages_thread_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_whatsapp_messages');
        Schema::dropIfExists('return_whatsapp_threads');
    }
};
