<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_reply_macros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('title');
            $table->text('body');
            $table->string('category')->index();
            $table->string('channel_scope')->nullable();
            $table->string('language')->default('tr');
            $table->boolean('is_active')->default(true);
            $table->json('variables_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('support_reply_macro_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('macro_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body_before')->nullable();
            $table->text('body_after');
            $table->string('action')->default('created'); // created, updated, deleted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reply_macro_versions');
        Schema::dropIfExists('support_reply_macros');
    }
};
