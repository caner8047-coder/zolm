<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_queries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->text('query_text');
            $table->text('response_text')->nullable();
            $table->string('status', 30)->default('pending'); // pending, completed, failed

            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_saved_questions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('title', 200);
            $table->text('query_text');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_saved_questions');
        Schema::dropIfExists('assistant_queries');
    }
};
