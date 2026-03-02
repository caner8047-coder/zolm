<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Sipariş Kayıtları
     * Her satır bir siparişi temsil eder. 5N1K analizinin kalbi.
     */
    public function up(): void
    {
        Schema::create('mp_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('mp_periods')->cascadeOnDelete();

            // Sipariş tanımlayıcıları
            $table->string('order_number', 50)->index();       // Sipariş No (upsert anahtarı)
            $table->string('barcode', 50)->nullable()->index(); // Barkod (kısmi iade eşleşmesi)
            $table->string('stock_code', 50)->nullable()->index(); // Stok kodu (product_costs eşleşmesi)

            // Ürün bilgileri
            $table->string('product_name')->nullable();
            $table->integer('quantity')->default(1);

            // Tarihler
            $table->datetime('order_date')->nullable();        // Sipariş tarihi
            $table->datetime('delivery_date')->nullable();     // Teslim tarihi
            $table->date('payment_date')->nullable();           // Hakediş/ödeme tarihi (vade)

            // Durum: Teslim Edildi, İade Edildi, İptal Edildi, Kargoda
            $table->string('status', 30)->default('Kargoda')->index();

            // Finansal tutarlar (KDV dahil)
            $table->decimal('list_price', 12, 2)->default(0);       // Ürün liste fiyatı
            $table->decimal('sale_price', 12, 2)->default(0);       // İndirimli satış fiyatı
            $table->decimal('gross_amount', 12, 2)->default(0);     // Brüt satış tutarı (KDV dahil)
            $table->decimal('discount_amount', 12, 2)->default(0);  // Toplam indirim
            $table->decimal('campaign_discount', 12, 2)->default(0); // Kampanya/kupon indirimi

            // Komisyon
            $table->decimal('commission_rate', 5, 2)->default(0);    // Komisyon oranı (%)  — geri hesaplanan
            $table->decimal('commission_amount', 12, 2)->default(0); // Komisyon tutarı (TL)
            $table->decimal('commission_tax', 12, 2)->default(0);    // Komisyon KDV (%20 sabit)

            // Kargo
            $table->string('cargo_company', 50)->nullable();         // TEX, Aras, PTT, Sürat, Yurtiçi
            $table->decimal('cargo_desi', 8, 2)->nullable();         // Desi değeri
            $table->decimal('cargo_amount', 12, 2)->default(0);      // Kargo kesintisi
            $table->decimal('cargo_tax', 12, 2)->default(0);         // Kargo KDV

            // Diğer kesintiler
            $table->decimal('service_fee', 12, 2)->default(0);       // Hizmet bedeli
            $table->decimal('withholding_tax', 12, 2)->default(0);   // Stopaj (%1)
            $table->decimal('net_hakedis', 12, 2)->default(0);       // Net hakediş (Trendyol hesaplaması)

            // Birim iktisadı — sipariş anındaki snapshot
            $table->decimal('product_vat_rate', 4, 2)->default(20);  // Ürün KDV oranı (%1, %10, %20)
            $table->decimal('cogs_at_time', 12, 2)->nullable();      // Sipariş anı üretim maliyeti
            $table->decimal('packaging_cost_at_time', 12, 2)->nullable(); // Sipariş anı ambalaj maliyeti

            // Hesaplanan alanlar
            $table->decimal('calculated_net_profit', 12, 2)->nullable(); // Hesaplanan gerçek net kâr
            $table->boolean('is_flagged')->default(false)->index();       // Audit engine tarafından işaretlendi mi?

            // Kaynak takibi
            $table->json('raw_data')->nullable(); // Orijinal Excel satırı

            $table->timestamps();

            // Composite index: upsert ve arama performansı
            $table->index(['order_number', 'barcode'], 'mp_orders_upsert_idx');
            $table->index(['period_id', 'status'], 'mp_orders_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_orders');
    }
};
