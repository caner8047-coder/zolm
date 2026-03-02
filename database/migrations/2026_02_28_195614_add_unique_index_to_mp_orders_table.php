<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mükerrer sipariş kaydını veritabanı seviyesinde engelleyen UNIQUE INDEX.
     * Aynı order_number + barcode + period_id kombinasyonu artık çiftlenemez.
     */
    public function up(): void
    {
        // Önce mevcut mükerrer kayıtları temizle (en düşük ID'liyi tut)
        $dupes = DB::table('mp_orders')
            ->select('order_number', 'barcode', 'period_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('order_number', 'barcode', 'period_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            DB::table('mp_orders')
                ->where('order_number', $dupe->order_number)
                ->where('period_id', $dupe->period_id)
                ->where(function ($q) use ($dupe) {
                    if ($dupe->barcode) {
                        $q->where('barcode', $dupe->barcode);
                    } else {
                        $q->where(function ($sq) {
                            $sq->whereNull('barcode')->orWhere('barcode', '');
                        });
                    }
                })
                ->where('id', '!=', $dupe->keep_id)
                ->delete();
        }

        // Boş barkodları NULL olarak standartlaştır (UNIQUE index için)
        DB::table('mp_orders')->where('barcode', '')->update(['barcode' => null]);

        Schema::table('mp_orders', function (Blueprint $table) {
            $table->unique(['order_number', 'barcode', 'period_id'], 'mp_orders_unique_order_barcode_period');
        });
    }

    public function down(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->dropUnique('mp_orders_unique_order_barcode_period');
        });
    }
};
