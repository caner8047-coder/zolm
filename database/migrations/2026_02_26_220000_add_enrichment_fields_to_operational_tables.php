<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trendyol Excel'indeki tüm alanları sisteme almak için
     * hem master (orders) hem detail (items) tablolarını genişletir.
     */
    public function up(): void
    {
        Schema::table('mp_operational_orders', function (Blueprint $table) {
            // Ek Tarih Alanları
            $table->dateTime('deadline_date')->nullable()->after('delivery_date')->comment('Termin Süresinin Bittiği Tarih');
            $table->dateTime('cargo_delivery_date')->nullable()->after('deadline_date')->comment('Kargoya Teslim Tarihi');
            $table->dateTime('invoice_date')->nullable()->after('invoice_number')->comment('Fatura Tarihi');
            
            // Lojistik Ek Alanlar
            $table->string('cargo_code')->nullable()->after('tracking_number')->comment('Kargo Kodu');
            $table->string('alt_delivery_status')->nullable()->after('status')->comment('Alternatif Teslimat Statüsü');
            $table->string('second_delivery_status')->nullable()->after('alt_delivery_status')->comment('2.Teslimat Paketi Statüsü');
            $table->string('second_tracking_number')->nullable()->after('second_delivery_status')->comment('2.Teslimat Takip Numarası');
            
            // Fatura Ek Alanlar
            $table->text('billing_address')->nullable()->after('customer_address')->comment('Fatura Adresi');
            $table->string('billing_name')->nullable()->after('billing_address')->comment('Alıcı - Fatura Adresi');
            $table->string('is_corporate_invoice')->nullable()->after('invoice_number')->comment('Kurumsal Faturalı Sipariş (Evet/Hayır)');
            $table->string('is_invoiced')->nullable()->after('is_corporate_invoice')->comment('Fatura Kesildi mi (Evet/Hayır)');
            
            // Müşteri Ek Bilgiler
            $table->string('email')->nullable()->after('customer_phone')->comment('E-Posta');
            $table->string('customer_age')->nullable()->after('email')->comment('Yaş Aralığı');
            $table->string('customer_gender')->nullable()->after('customer_age')->comment('Cinsiyet');
            $table->string('customer_order_count')->nullable()->after('customer_gender')->comment('Müşteri Sipariş Adedi (1.Sipariş vb)');
            
            // Ülke
            $table->string('country')->nullable()->after('customer_district')->comment('Ülke');
        });

        Schema::table('mp_operational_order_items', function (Blueprint $table) {
            // Ürün Ek Alanlar
            $table->string('brand')->nullable()->after('product_name')->comment('Marka');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('discount_amount')->comment('Komisyon Oranı (%)');
            $table->decimal('trendyol_discount', 10, 2)->default(0)->after('discount_amount')->comment('Trendyol İndirim Tutarı');
            $table->decimal('billable_amount', 10, 2)->default(0)->after('trendyol_discount')->comment('Faturalanacak Tutar');
            $table->string('boutique_number')->nullable()->after('billable_amount')->comment('Butik Numarası');
            
            // Desi & Kargo
            $table->decimal('cargo_desi', 8, 2)->nullable()->after('boutique_number')->comment('Kargodan alınan desi');
            $table->decimal('calculated_desi', 8, 2)->nullable()->after('cargo_desi')->comment('Hesapladığım desi');
            $table->string('invoiced_cargo_amount')->nullable()->after('calculated_desi')->comment('Faturalanan Kargo Tutarı');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_operational_orders', function (Blueprint $table) {
            $table->dropColumn([
                'deadline_date', 'cargo_delivery_date', 'invoice_date',
                'cargo_code', 'alt_delivery_status', 'second_delivery_status', 'second_tracking_number',
                'billing_address', 'billing_name', 'is_corporate_invoice', 'is_invoiced',
                'email', 'customer_age', 'customer_gender', 'customer_order_count',
                'country',
            ]);
        });

        Schema::table('mp_operational_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'brand', 'commission_rate', 'trendyol_discount', 'billable_amount',
                'boutique_number', 'cargo_desi', 'calculated_desi', 'invoiced_cargo_amount',
            ]);
        });
    }
};
