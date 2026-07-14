<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_data_subject_requests', function (Blueprint $table): void {
            $table->foreignId('approval_request_id')
                ->nullable()
                ->after('request_type')
                ->constrained('support_approval_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_data_subject_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approval_request_id');
        });
    }
};
