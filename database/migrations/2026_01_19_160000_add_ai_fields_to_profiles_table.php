<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->text('ai_prompt')->nullable()->after('output_config');
            $table->string('sample_input_path')->nullable()->after('ai_prompt');
            $table->string('sample_output_path')->nullable()->after('sample_input_path');
            $table->json('ai_generated_rules')->nullable()->after('sample_output_path');
            $table->boolean('is_ai_generated')->default(false)->after('ai_generated_rules');
            $table->enum('status', ['draft', 'analyzing', 'ready', 'error'])->default('ready')->after('is_ai_generated');
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'ai_prompt',
                'sample_input_path',
                'sample_output_path',
                'ai_generated_rules',
                'is_ai_generated',
                'status',
                'error_message',
            ]);
        });
    }
};
