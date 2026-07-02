<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mp_profit_action_items') || ! Schema::hasTable('mp_profit_action_events')) {
            return;
        }

        DB::table('mp_profit_action_items')
            ->select(['id', 'user_id', 'status', 'created_at', 'updated_at'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('mp_profit_action_events')
                    ->whereColumn('mp_profit_action_events.mp_profit_action_item_id', 'mp_profit_action_items.id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $rows = [];

                foreach ($items as $item) {
                    $rows[] = [
                        'mp_profit_action_item_id' => $item->id,
                        'user_id' => $item->user_id,
                        'event_type' => 'created',
                        'from_status' => null,
                        'to_status' => $item->status,
                        'meta_json' => json_encode(['source' => 'backfill'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $item->created_at ?: now(),
                        'updated_at' => $item->updated_at ?: now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('mp_profit_action_events')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mp_profit_action_events')) {
            return;
        }

        DB::table('mp_profit_action_events')
            ->where('event_type', 'created')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.source')) = 'backfill'")
            ->delete();
    }
};
