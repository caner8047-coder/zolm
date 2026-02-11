<?php

namespace App\Http\Controllers;

use App\Models\SupplyOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SupplyLabelController extends Controller
{
    /**
     * Bir sipariş için kargo etiketi PDF oluştur
     * Aynı sipariş numarasındaki TÜM ürünleri gösterir
     */
    public function download(int $id)
    {
        $order = SupplyOrder::findOrFail($id);
        
        // Aynı sipariş numarasındaki tüm ürünleri getir
        $orderItems = SupplyOrder::where('siparis_no', $order->siparis_no)->get();

        $pdf = Pdf::loadView('pdf.supply-label', [
            'order' => $order, // Ana bilgiler için (müşteri, adres vs.)
            'orderItems' => $orderItems, // Tüm ürünler
        ]);

        // Kağıt boyutu: A5 (A4'ün yarısı - 148x210mm)
        $pdf->setPaper('a5', 'portrait');

        $filename = 'etiket-' . $order->siparis_no . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Toplu etiket oluştur (seçili siparişler için)
     */
    public function downloadBulk(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            return back()->with('error', 'Lütfen en az bir sipariş seçin.');
        }

        $orders = SupplyOrder::whereIn('id', $ids)->get();

        $pdf = Pdf::loadView('pdf.supply-label-bulk', [
            'orders' => $orders,
        ]);

        $pdf->setPaper([0, 0, 283.46, 425.20], 'portrait');

        return $pdf->download('etiketler-toplu.pdf');
    }
}
