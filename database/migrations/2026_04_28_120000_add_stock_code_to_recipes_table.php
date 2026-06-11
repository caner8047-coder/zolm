<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recipes', 'stock_code')) {
            return;
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('stock_code', 100)->nullable()->after('mp_product_id');
            $table->index(['user_id', 'stock_code'], 'recipes_user_stock_code_idx');
        });

        if (Schema::hasColumn('mp_products', 'stock_code')) {
            DB::table('recipes')
                ->select(['id', 'mp_product_id'])
                ->whereNull('stock_code')
                ->whereNotNull('mp_product_id')
                ->orderBy('id')
                ->chunkById(500, function ($recipes): void {
                    $productStockCodes = DB::table('mp_products')
                        ->whereIn('id', $recipes->pluck('mp_product_id')->filter()->unique()->values())
                        ->whereNotNull('stock_code')
                        ->pluck('stock_code', 'id');

                    foreach ($recipes as $recipe) {
                        $stockCode = trim((string) ($productStockCodes->get($recipe->mp_product_id) ?? ''));

                        if ($stockCode === '') {
                            continue;
                        }

                        DB::table('recipes')
                            ->where('id', $recipe->id)
                            ->update(['stock_code' => $stockCode]);
                    }
                });
        }

        $factoryRecipePath = base_path('fabrika/recete_list.json');
        if (is_file($factoryRecipePath)) {
            $payload = json_decode((string) file_get_contents($factoryRecipePath), true);
            $rows = is_array($payload) ? ($payload['data'] ?? []) : [];
            $seen = [];

            foreach ($rows as $row) {
                $stockCode = trim((string) ($row['Mamul Kodu'] ?? ''));
                $recipeName = trim((string) ($row['Mamul Açıklaması'] ?? ''));
                $recipeNo = trim((string) ($row['Reçete No'] ?? '1'));

                if ($stockCode === '' || $recipeName === '') {
                    continue;
                }

                $version = 'v' . ($recipeNo !== '' ? $recipeNo : '1');
                $key = $recipeName . '|' . $version;
                if (isset($seen[$key])) {
                    continue;
                }

                DB::table('recipes')
                    ->whereNull('stock_code')
                    ->where('name', $recipeName)
                    ->where('version', $version)
                    ->update(['stock_code' => $stockCode]);

                $seen[$key] = true;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('recipes', 'stock_code')) {
            return;
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex('recipes_user_stock_code_idx');
            $table->dropColumn('stock_code');
        });
    }
};
