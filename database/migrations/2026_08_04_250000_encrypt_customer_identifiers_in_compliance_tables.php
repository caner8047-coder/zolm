<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('support_consent_records', 'scr_store_id_index')) {
            Schema::table('support_consent_records', function (Blueprint $table): void {
                $table->index('store_id', 'scr_store_id_index');
            });
        }
        if (Schema::hasIndex('support_consent_records', 'store_cust_channel_consent_unique')) {
            Schema::table('support_consent_records', function (Blueprint $table): void {
                $table->dropUnique('store_cust_channel_consent_unique');
            });
        }
        Schema::table('support_consent_records', function (Blueprint $table): void {
            $table->text('customer_id')->change();
        });
        if (!Schema::hasColumn('support_consent_records', 'customer_hash')) {
            Schema::table('support_consent_records', function (Blueprint $table): void {
                $table->char('customer_hash', 64)->nullable()->after('customer_id');
            });
        }
        if (!Schema::hasIndex('support_consent_records', 'scr_store_hash_channel_type_unique')) {
            Schema::table('support_consent_records', function (Blueprint $table): void {
                $table->unique(['store_id', 'customer_hash', 'channel_key', 'consent_type'], 'scr_store_hash_channel_type_unique');
            });
        }

        if (!Schema::hasIndex('support_legal_holds', 'slh_store_id_index')) {
            Schema::table('support_legal_holds', function (Blueprint $table): void {
                $table->index('store_id', 'slh_store_id_index');
            });
        }
        if (Schema::hasIndex('support_legal_holds', 'store_customer_hold_unique')) {
            Schema::table('support_legal_holds', function (Blueprint $table): void {
                $table->dropUnique('store_customer_hold_unique');
            });
        }
        Schema::table('support_legal_holds', function (Blueprint $table): void {
            $table->text('customer_id')->change();
        });
        if (!Schema::hasColumn('support_legal_holds', 'customer_hash')) {
            Schema::table('support_legal_holds', function (Blueprint $table): void {
                $table->char('customer_hash', 64)->nullable()->after('customer_id');
            });
        }
        if (!Schema::hasIndex('support_legal_holds', 'slh_store_customer_hash_unique')) {
            Schema::table('support_legal_holds', function (Blueprint $table): void {
                $table->unique(['store_id', 'customer_hash'], 'slh_store_customer_hash_unique');
            });
        }

        Schema::table('support_data_subject_requests', function (Blueprint $table): void {
            $table->text('customer_id')->change();
        });
        if (!Schema::hasColumn('support_data_subject_requests', 'customer_hash')) {
            Schema::table('support_data_subject_requests', function (Blueprint $table): void {
                $table->char('customer_hash', 64)->nullable()->after('customer_id');
                $table->index('customer_hash', 'support_data_subject_requests_customer_hash_index');
            });
        }

        foreach (['support_consent_records', 'support_legal_holds', 'support_data_subject_requests'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $plain = $this->decryptOrPlain((string) $row->customer_id);
                    DB::table($table)->where('id', $row->id)->update([
                        'customer_id' => Crypt::encryptString($plain),
                        'customer_hash' => hash('sha256', $plain),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['support_consent_records', 'support_legal_holds', 'support_data_subject_requests'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) DB::table($table)->where('id', $row->id)->update(['customer_id' => $this->decryptOrPlain((string) $row->customer_id)]);
            });
        }
        if (Schema::hasColumn('support_data_subject_requests', 'customer_hash')) {
            Schema::table('support_data_subject_requests', function (Blueprint $table): void {
                $table->dropIndex('support_data_subject_requests_customer_hash_index');
                $table->dropColumn('customer_hash');
            });
        }

        if (Schema::hasIndex('support_legal_holds', 'slh_store_customer_hash_unique')) {
            Schema::table('support_legal_holds', fn (Blueprint $table) => $table->dropUnique('slh_store_customer_hash_unique'));
        }
        if (Schema::hasColumn('support_legal_holds', 'customer_hash')) {
            Schema::table('support_legal_holds', fn (Blueprint $table) => $table->dropColumn('customer_hash'));
        }
        if (!Schema::hasIndex('support_legal_holds', 'store_customer_hold_unique')) {
            Schema::table('support_legal_holds', fn (Blueprint $table) => $table->unique(['store_id', 'customer_id'], 'store_customer_hold_unique'));
        }
        if (Schema::hasIndex('support_legal_holds', 'slh_store_id_index')) {
            Schema::table('support_legal_holds', fn (Blueprint $table) => $table->dropIndex('slh_store_id_index'));
        }

        if (Schema::hasIndex('support_consent_records', 'scr_store_hash_channel_type_unique')) {
            Schema::table('support_consent_records', fn (Blueprint $table) => $table->dropUnique('scr_store_hash_channel_type_unique'));
        }
        if (Schema::hasColumn('support_consent_records', 'customer_hash')) {
            Schema::table('support_consent_records', fn (Blueprint $table) => $table->dropColumn('customer_hash'));
        }
        if (!Schema::hasIndex('support_consent_records', 'store_cust_channel_consent_unique')) {
            Schema::table('support_consent_records', fn (Blueprint $table) => $table->unique(['store_id', 'customer_id', 'channel_key', 'consent_type'], 'store_cust_channel_consent_unique'));
        }
        if (Schema::hasIndex('support_consent_records', 'scr_store_id_index')) {
            Schema::table('support_consent_records', fn (Blueprint $table) => $table->dropIndex('scr_store_id_index'));
        }
    }

    private function decryptOrPlain(string $value): string
    {
        try { return Crypt::decryptString($value); } catch (\Throwable) { return $value; }
    }
};
