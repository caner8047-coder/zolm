<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mp_audit_logs tablosuna itiraz yönetimi alanları ekler.
 * Geriye uyumlu: mevcut kayıtlar için dispute_status = null (itiraz edilmemiş).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_audit_logs', function (Blueprint $table) {
            // İtiraz durumu: null=itiraz edilmemiş, pending=beklemede, accepted=kabul edildi, rejected=reddedildi
            $table->string('dispute_status', 20)->nullable()->after('status');
            // İtiraz tarihi
            $table->timestamp('disputed_at')->nullable()->after('dispute_status');
            // İtiraz notu (kullanıcı açıklaması)
            $table->text('dispute_note')->nullable()->after('disputed_at');
            // İtiraz çözüm notu
            $table->text('dispute_resolution')->nullable()->after('dispute_note');
            // İtiraz çözüm tarihi
            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_resolution');

            // İndeks: itiraz edilen kayıtları hızlı çekmek için
            $table->index(['dispute_status', 'period_id'], 'idx_audit_dispute_status_period');
        });
    }

    public function down(): void
    {
        Schema::table('mp_audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_dispute_status_period');
            $table->dropColumn([
                'dispute_status',
                'disputed_at',
                'dispute_note',
                'dispute_resolution',
                'dispute_resolved_at',
            ]);
        });
    }
};
