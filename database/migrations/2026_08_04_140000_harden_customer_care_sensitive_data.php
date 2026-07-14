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
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->text('external_customer_id')->nullable()->change();
        });
        if (!Schema::hasColumn('support_conversations', 'external_customer_hash')) {
            Schema::table('support_conversations', function (Blueprint $table): void {
                $table->char('external_customer_hash', 64)->nullable()->after('external_customer_id');
                $table->index('external_customer_hash', 'support_conversations_external_customer_hash_index');
            });
        }

        // MySQL foreign key'i eski unique store_id indeksini kullanıyor olabilir.
        // Önce eşdeğer bir non-unique indeks oluştur; aksi halde DROP INDEX 1553
        // hatasıyla migration yarıda kalır. Koşullar, MySQL'in DDL sonrası kısmi
        // migration durumunda güvenli yeniden çalıştırmaya da izin verir.
        if (!Schema::hasIndex('integration_connections', 'integration_connections_store_id_index')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->index('store_id', 'integration_connections_store_id_index');
            });
        }
        if (Schema::hasIndex('integration_connections', 'integration_connections_store_id_unique')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->dropUnique('integration_connections_store_id_unique');
            });
        }
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->text('webhook_secret')->nullable()->change();
        });
        if (!Schema::hasIndex('integration_connections', 'integration_connections_store_provider_unique')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->unique(['store_id', 'provider'], 'integration_connections_store_provider_unique');
            });
        }

        DB::table('support_conversations')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $plain = $this->decryptOrPlain($row->external_customer_id);
                DB::table('support_conversations')->where('id', $row->id)->update([
                    'external_customer_id' => $plain !== null ? Crypt::encryptString($plain) : null,
                    'external_customer_hash' => filled($plain) ? hash('sha256', $plain) : null,
                ]);
            }
        });

        DB::table('support_ai_runs')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('support_ai_runs')->where('id', $row->id)->update([
                    'prompt_raw' => $this->encryptNullable($row->prompt_raw),
                    'response_raw' => $this->encryptNullable($row->response_raw),
                ]);
            }
        });

        DB::table('integration_connections')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('integration_connections')->where('id', $row->id)->update([
                    'webhook_secret' => $this->encryptNullable($row->webhook_secret),
                ]);
            }
        });

        DB::table('support_messages')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('support_messages')->where('id', $row->id)->update([
                    'body_encrypted' => $this->encryptNullable($row->body_encrypted),
                    'body_preview' => $this->maskPii($row->body_preview),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('support_conversations')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('support_conversations')->where('id', $row->id)->update([
                    'external_customer_id' => $this->decryptOrPlain($row->external_customer_id),
                ]);
            }
        });
        DB::table('support_ai_runs')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('support_ai_runs')->where('id', $row->id)->update([
                    'prompt_raw' => $this->decryptOrPlain($row->prompt_raw),
                    'response_raw' => $this->decryptOrPlain($row->response_raw),
                ]);
            }
        });
        DB::table('integration_connections')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('integration_connections')->where('id', $row->id)->update([
                    'webhook_secret' => $this->decryptOrPlain($row->webhook_secret),
                ]);
            }
        });

        if (Schema::hasIndex('integration_connections', 'integration_connections_store_provider_unique')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->dropUnique('integration_connections_store_provider_unique');
            });
        }
        if (!Schema::hasIndex('integration_connections', 'integration_connections_store_id_unique')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->unique('store_id', 'integration_connections_store_id_unique');
            });
        }
        if (Schema::hasIndex('integration_connections', 'integration_connections_store_id_index')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->dropIndex('integration_connections_store_id_index');
            });
        }
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->string('webhook_secret', 120)->nullable()->change();
        });
        if (Schema::hasColumn('support_conversations', 'external_customer_hash')) {
            Schema::table('support_conversations', function (Blueprint $table): void {
                $table->dropIndex('support_conversations_external_customer_hash_index');
                $table->dropColumn('external_customer_hash');
            });
        }
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->string('external_customer_id', 120)->nullable()->change();
        });
    }

    private function encryptNullable(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->isEncrypted($value) ? $value : Crypt::encryptString($value);
    }

    private function decryptOrPlain(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function maskPii(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags($value);
        $value = preg_replace('/([A-Z0-9._%+-])[A-Z0-9._%+-]*(@[A-Z0-9.-]+\.[A-Z]{2,})/iu', '$1****$2', $value);
        $value = preg_replace('/\b(\d{2})\d{7}(\d{2})\b/u', '$1*******$2', $value);
        $value = preg_replace('/\b(0?5\d{2})[\s.-]*\d{3}[\s.-]*(\d{2})[\s.-]*(\d{2})\b/u', '$1 *** $2 $3', $value);

        return mb_substr($value, 0, 100);
    }
};
