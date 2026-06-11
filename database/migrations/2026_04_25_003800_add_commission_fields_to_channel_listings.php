<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_listings', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('list_price');
            }

            if (!Schema::hasColumn('channel_listings', 'commission_source')) {
                $table->string('commission_source', 40)->nullable()->after('commission_rate');
            }

            if (!Schema::hasColumn('channel_listings', 'commission_synced_at')) {
                $table->timestamp('commission_synced_at')->nullable()->after('commission_source');
            }
        });

        $this->backfillExistingCommissionRates();
    }

    public function down(): void
    {
        Schema::table('channel_listings', function (Blueprint $table) {
            foreach (['commission_synced_at', 'commission_source', 'commission_rate'] as $column) {
                if (Schema::hasColumn('channel_listings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillExistingCommissionRates(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('channel_order_items')) {
            DB::statement(<<<'SQL'
                UPDATE channel_listings cl
                JOIN (
                    SELECT
                        channel_listing_id,
                        ROUND(AVG(commission_rate), 2) AS commission_rate
                    FROM channel_order_items
                    WHERE channel_listing_id IS NOT NULL
                        AND commission_rate IS NOT NULL
                        AND commission_rate BETWEEN 0 AND 100
                    GROUP BY channel_listing_id
                ) item_commissions ON item_commissions.channel_listing_id = cl.id
                SET
                    cl.commission_rate = item_commissions.commission_rate,
                    cl.commission_source = 'order_item_backfill',
                    cl.commission_synced_at = NOW()
                WHERE cl.commission_rate IS NULL
            SQL);
        }

        if (Schema::hasTable('order_financial_events') && Schema::hasTable('channel_order_items')) {
            DB::statement(<<<'SQL'
                UPDATE channel_listings cl
                JOIN (
                    SELECT
                        coi.channel_listing_id,
                        ROUND(AVG(
                            (ABS(ofe.amount) / NULLIF(
                                COALESCE(
                                    NULLIF(coi.billable_amount, 0),
                                    NULLIF(coi.gross_amount, 0),
                                    (COALESCE(coi.unit_price, 0) * COALESCE(coi.quantity, 0))
                                ),
                                0
                            )) * 100
                        ), 2) AS commission_rate
                    FROM order_financial_events ofe
                    JOIN channel_order_items coi ON coi.id = ofe.channel_order_item_id
                    WHERE ofe.event_type = 'commission'
                        AND coi.channel_listing_id IS NOT NULL
                    GROUP BY coi.channel_listing_id
                    HAVING commission_rate BETWEEN 0 AND 100
                ) financial_commissions ON financial_commissions.channel_listing_id = cl.id
                SET
                    cl.commission_rate = financial_commissions.commission_rate,
                    cl.commission_source = 'financial_backfill',
                    cl.commission_synced_at = NOW()
                WHERE cl.commission_rate IS NULL
            SQL);
        }

        if (Schema::hasTable('marketplace_stores')) {
            DB::statement(<<<'SQL'
                UPDATE channel_listings cl
                JOIN marketplace_stores ms ON ms.id = cl.store_id
                SET
                    cl.commission_rate = 0,
                    cl.commission_source = 'marketplace_default',
                    cl.commission_synced_at = NOW()
                WHERE cl.commission_rate IS NULL
                    AND LOWER(ms.marketplace) = 'woocommerce'
            SQL);
        }
    }
};
