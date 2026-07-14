<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_artifact_versions', function (Blueprint $table): void {
            $table->foreignId('release_package_id')
                ->nullable()
                ->after('is_current')
                ->constrained('support_release_packages')
                ->nullOnDelete();
            $table->index(
                ['store_id', 'artifact_type', 'artifact_id', 'release_package_id'],
                'artifact_release_package_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('support_artifact_versions', function (Blueprint $table): void {
            $table->dropIndex('artifact_release_package_index');
            $table->dropConstrainedForeignId('release_package_id');
        });
    }
};
