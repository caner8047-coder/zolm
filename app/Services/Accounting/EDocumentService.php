<?php

namespace App\Services\Accounting;

use App\Models\EDocument;
use App\Models\EDocumentEvent;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * GİB e-Fatura / e-Arşiv Fatura Yönetim ve Entegratör Servisi.
 *
 * Sorumluluklar:
 * 1. Onaylanmış Satış Belgesinden (SalesOrder) e-Belge taslağı (e_document draft) oluşturma.
 * 2. e-Fatura entegratörüne (simüle edilmiş) belge gönderme ve GİB fatura no atama.
 * 3. e-Belge iptal etme/geçersiz kılma akışı.
 * 4. Fatura durumu olay günlüğü (e_document_events) yönetimi.
 */
class EDocumentService
{
    /**
     * Satış Siparişinden e-Belge Taslağı Oluştur.
     */
    public function createDraft(SalesOrder $order, string $documentType = 'e_archive'): EDocument
    {
        if ($order->status !== 'approved') {
            throw new InvalidArgumentException('Sadece onaylanmış satış siparişleri için e-belge oluşturulabilir.');
        }

        // Halihazırda bu siparişe ait bir fatura var mı?
        $existing = EDocument::where('sales_order_id', $order->id)->first();
        if ($existing) {
            throw new InvalidArgumentException('Bu satış siparişine ait zaten bir e-belge taslağı bulunmaktadır.');
        }

        return DB::transaction(function () use ($order, $documentType) {
            $doc = EDocument::create([
                'user_id'        => $order->user_id,
                'sales_order_id' => $order->id,
                'document_type'  => $documentType,
                'uuid'           => (string) Str::uuid(),
                'status'         => 'draft',
            ]);

            $this->logEvent($doc, 'none', 'draft', 'Taslak e-Belge oluşturuldu.');

            return $doc;
        });
    }

    /**
     * e-Belgeyi Entegratör Servisine Gönder (GİB Gönderimi).
     */
    public function sendToProvider(EDocument $doc): EDocument
    {
        if ($doc->status !== 'draft') {
            throw new InvalidArgumentException('Sadece taslak (draft) durumundaki belgeler gönderilebilir.');
        }

        return DB::transaction(function () use ($doc) {
            // Entegratör entegrasyon simülasyonu
            $gibInvoiceNumber = 'GIB' . now()->format('Y') . str_pad(rand(1, 999999999), 9, '0', STR_PAD_LEFT);

            $oldStatus = $doc->status;
            $doc->update([
                'status'         => 'accepted',
                'invoice_number' => $gibInvoiceNumber,
                'pdf_path'       => 'storage/e-invoices/' . $doc->uuid . '.pdf',
                'xml_path'       => 'storage/e-invoices/' . $doc->uuid . '.xml',
            ]);

            $this->logEvent($doc, $oldStatus, 'accepted', 'GİB Gönderimi başarılı. Fatura No: ' . $gibInvoiceNumber);

            return $doc;
        });
    }

    /**
     * e-Belgeyi İptal Et.
     */
    public function cancelDocument(EDocument $doc, string $reason = ''): EDocument
    {
        if (in_array($doc->status, ['draft', 'cancelled'])) {
            throw new InvalidArgumentException('Taslak veya iptal edilmiş belgeler tekrar iptal edilemez.');
        }

        return DB::transaction(function () use ($doc, $reason) {
            $oldStatus = $doc->status;
            $doc->update([
                'status'           => 'cancelled',
                'response_message' => $reason ?: 'Fatura iptal edildi.',
            ]);

            $this->logEvent($doc, $oldStatus, 'cancelled', 'Fatura İptal Edildi. Gerekçe: ' . $reason);

            return $doc;
        });
    }

    /**
     * e-Belge durum değişikliğini günlüğe kaydet.
     */
    protected function logEvent(EDocument $doc, string $from, string $to, string $msg): void
    {
        EDocumentEvent::create([
            'e_document_id' => $doc->id,
            'status_from'   => $from,
            'status_to'     => $to,
            'message'       => $msg,
        ]);
    }
}
