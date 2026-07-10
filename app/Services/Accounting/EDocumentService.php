<?php

namespace App\Services\Accounting;

use App\Models\EDocument;
use App\Models\EDocumentLine;
use App\Models\EDocumentEvent;
use App\Models\SalesOrder;
use App\Models\Party;
use App\Models\PartyIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EDocumentService
{
    private EInvoiceSimulatorProvider $provider;

    public function __construct(EInvoiceSimulatorProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Satış Siparişinden e-Belge Taslağı Oluştur.
     */
    public function createDraft(SalesOrder $order, string $documentType = 'e_archive', array $context = [], ?int $userId = null): EDocument
    {
        $actorUserId = $userId ?? auth()->id();
        if (!$actorUserId) {
            throw new InvalidArgumentException('Kullanıcı context/aktör bilgisi bulunamadı.');
        }

        $order = $order->fresh(['items']);
        if (!$order) {
            throw new InvalidArgumentException('Belirtilen satış siparişi bulunamadı.');
        }

        if ($order->user_id !== $actorUserId) {
            throw new InvalidArgumentException('Belirtilen satış siparişi bu kullanıcıya ait değil.');
        }

        if ($order->status !== 'approved') {
            throw new InvalidArgumentException('Sadece onaylanmış satış siparişleri için e-belge oluşturulabilir.');
        }

        if (!in_array($documentType, ['e_invoice', 'e_archive'], true)) {
            throw new InvalidArgumentException('Geçersiz belge tipi. Sadece e_invoice veya e_archive olabilir.');
        }

        // Alıcı / Müşteri çözümü
        $party = Party::where('user_id', $actorUserId)->findOrFail($order->party_id);

        $buyerName = $party->display_name;

        // Alıcı VKN/TCKN çözümü
        $buyerTaxNumber = $context['buyer_tax_number'] ?? null;
        if (!$buyerTaxNumber) {
            $identity = PartyIdentity::where('party_id', $party->id)
                ->whereIn('identity_kind', ['vkn', 'tckn'])
                ->first();
            if ($identity) {
                $buyerTaxNumber = $identity->identity_value;
            }
        }

        if ($documentType === 'e_invoice' && empty($buyerTaxNumber)) {
            throw new InvalidArgumentException('e-Fatura oluşturmak için alıcı vergi/kimlik numarası (VKN/TCKN) zorunludur.');
        }

        if ($documentType === 'e_archive' && empty($buyerName)) {
            throw new InvalidArgumentException('e-Arşiv fatura oluşturmak için alıcı adı zorunludur.');
        }

        // Diğer alıcı bilgileri
        $buyerTaxOffice = $context['buyer_tax_office'] ?? $party->tax_office ?? null;
        $buyerEmail     = $context['buyer_email'] ?? $party->email ?? null;
        $buyerPhone     = $context['buyer_phone'] ?? $party->phone ?? null;
        $buyerAddress   = $context['buyer_address'] ?? $party->address ?? null;

        $order->load('items');
        $subtotal = 0.00;
        $discount = 0.00;
        $vat      = 0.00;
        $total    = 0.00;

        foreach ($order->items as $item) {
            $lineSubtotal = (float) ($item->quantity * $item->unit_price);
            $lineDiscount = (float) ($item->discount_amount ?: 0.00);

            $vatRate      = (float) ($item->vat_rate ?? 20.00);
            $lineVat      = round(($lineSubtotal - $lineDiscount) * ($vatRate / 100), 2);

            $lineTotal    = round($lineSubtotal - $lineDiscount + $lineVat, 2);

            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
            $vat      += $lineVat;
            $total    += $lineTotal;
        }

        if ((float) $order->total_amount > 0.005) {
            $total = (float) $order->total_amount;
        }

        $sourceKey = $context['source_key'] ?? null;

        // Idempotency kontrolü
        if ($sourceKey) {
            $existingByKey = EDocument::with('lines')->where('user_id', $actorUserId)->where('source_key', $sourceKey)->first();
            if ($existingByKey) {
                // Detaylı payload doğrulaması
                $mismatch = false;

                if ($existingByKey->sales_order_id !== $order->id
                    || $existingByKey->document_type !== $documentType
                    || $existingByKey->legal_entity_id !== $order->legal_entity_id
                    || $existingByKey->party_id !== $order->party_id
                    || $existingByKey->buyer_name !== $buyerName
                    || $existingByKey->buyer_tax_number !== $buyerTaxNumber
                    || $existingByKey->buyer_tax_office !== $buyerTaxOffice
                    || $existingByKey->buyer_email !== $buyerEmail
                    || abs((float) $existingByKey->subtotal_amount - (float) $subtotal) > 0.005
                    || abs((float) $existingByKey->discount_amount - (float) $discount) > 0.005
                    || abs((float) $existingByKey->vat_amount - (float) $vat) > 0.005
                    || abs((float) $existingByKey->total_amount - (float) $total) > 0.005
                    || count($existingByKey->lines) !== count($order->items)
                ) {
                    $mismatch = true;
                }

                if (!$mismatch) {
                    // Satırları teker teker karşılaştır
                    for ($i = 0; $i < count($order->items); $i++) {
                        $eLine = $existingByKey->lines[$i];
                        $item  = $order->items[$i];

                        $lineSubtotal = (float) ($item->quantity * $item->unit_price);
                        $lineDiscount = (float) ($item->discount_amount ?: 0.00);
                        $vatRate      = (float) ($item->vat_rate ?? 20.00);
                        $lineVat      = round(($lineSubtotal - $lineDiscount) * ($vatRate / 100), 2);
                        $lineTotal    = round($lineSubtotal - $lineDiscount + $lineVat, 2);

                        if ($eLine->stock_code !== $item->stock_code
                            || (float) $eLine->quantity !== (float) $item->quantity
                            || abs((float) $eLine->unit_price - (float) $item->unit_price) > 0.005
                            || abs((float) $eLine->discount_rate - (float) ($item->discount_rate ?: 0.00)) > 0.005
                            || abs((float) $eLine->discount_amount - $lineDiscount) > 0.005
                            || abs((float) $eLine->vat_rate - $vatRate) > 0.005
                            || abs((float) $eLine->vat_amount - $lineVat) > 0.005
                            || abs((float) $eLine->line_total - $lineTotal) > 0.005
                        ) {
                            $mismatch = true;
                            break;
                        }
                    }
                }

                if ($mismatch) {
                    throw new InvalidArgumentException('Çakışan source_key ile farklı detaylara sahip bir e-Belge zaten mevcut.');
                }
                return $existingByKey;
            }
        }

        // Halihazırda bu siparişe ait bir fatura var mı? (cancelled olanlar dahil hepsi engellenecek)
        $existing = EDocument::where('sales_order_id', $order->id)->first();
        if ($existing) {
            throw new InvalidArgumentException('Bu satış siparişine ait zaten bir e-belge bulunmaktadır.');
        }

        return DB::transaction(function () use ($order, $documentType, $actorUserId, $sourceKey, $buyerName, $buyerTaxNumber, $buyerTaxOffice, $buyerEmail, $buyerPhone, $buyerAddress, $context, $subtotal, $discount, $vat, $total) {
            $doc = EDocument::create([
                'user_id'                => $actorUserId,
                'sales_order_id'         => $order->id,
                'legal_entity_id'        => $order->legal_entity_id,
                'party_id'               => $order->party_id,
                'warehouse_id'           => $order->warehouse_id,
                'source_key'             => $sourceKey,
                'document_type'          => $documentType,
                'uuid'                   => (string) Str::uuid(),
                'status'                 => 'draft',
                'provider'               => 'simulator',
                'profile_type'           => 'basic',
                'issue_date'             => now()->toDateString(),
                'due_date'               => $order->due_date ?: now()->addDays(7)->toDateString(),
                'currency_code'          => $order->currency_code ?: 'TRY',
                'exchange_rate'          => $order->exchange_rate ?: 1.000000,
                'subtotal_amount'        => $subtotal,
                'discount_amount'        => $discount,
                'vat_amount'             => $vat,
                'total_amount'           => $total,
                'buyer_name'             => $buyerName,
                'buyer_tax_number'       => $buyerTaxNumber,
                'buyer_tax_office'       => $buyerTaxOffice,
                'buyer_email'            => $buyerEmail,
                'buyer_phone'            => $buyerPhone,
                'buyer_address'          => $buyerAddress,
                'provider_request_json'  => $context,
            ]);

            // Kalemleri oluştur
            $order->load('items');
            foreach ($order->items as $item) {
                EDocumentLine::create([
                    'user_id'             => $actorUserId,
                    'e_document_id'       => $doc->id,
                    'sales_order_item_id' => $item->id,
                    'stock_code'          => $item->stock_code,
                    'description'         => $item->description ?: ($item->product_name ?: 'Ürün satışı'),
                    'quantity'            => $item->quantity,
                    'unit_name'           => 'Adet',
                    'unit_price'          => $item->unit_price,
                    'discount_rate'       => $item->discount_rate ?: 0.0000,
                    'discount_amount'     => $item->discount_amount ?: 0.00,
                    'vat_rate'            => $item->vat_rate ?: 20.0000,
                    'vat_amount'          => round((($item->quantity * $item->unit_price) - ($item->discount_amount ?: 0)) * (($item->vat_rate ?: 20.00) / 100), 2),
                    'line_subtotal'       => (float) ($item->quantity * $item->unit_price),
                    'line_total'          => round(($item->quantity * $item->unit_price) - ($item->discount_amount ?: 0) + round((($item->quantity * $item->unit_price) - ($item->discount_amount ?: 0)) * (($item->vat_rate ?: 20.00) / 100), 2), 2),
                ]);
            }

            // Olay günlüğü
            $this->logEvent($doc, 'none', 'draft', 'Taslak e-Belge oluşturuldu.', $actorUserId, ['context' => $context]);

            return $doc;
        });
    }

    /**
     * e-Belgeyi Entegratöre Gönder.
     */
    public function sendToProvider(EDocument $doc, ?int $userId = null): EDocument
    {
        $actorUserId = $userId ?? auth()->id();
        if (!$actorUserId) {
            throw new InvalidArgumentException('Kullanıcı context/aktör bilgisi bulunamadı.');
        }

        if ($doc->user_id !== $actorUserId) {
            throw new InvalidArgumentException('Bu e-Belge belirtilen kullanıcıya ait değil.');
        }

        // Idempotency: Eğer zaten kabul edilmişse mevcut belgeyi dön
        if ($doc->isAccepted()) {
            return $doc;
        }

        if (!$doc->isDraft()) {
            throw new InvalidArgumentException('Sadece taslak (draft) durumundaki belgeler gönderilebilir.');
        }

        return DB::transaction(function () use ($doc, $actorUserId) {
            // Simulator çağrısı
            $response = $this->provider->send($doc);

            $oldStatus = $doc->status;
            $doc->update([
                'status'                 => 'accepted',
                'invoice_number'         => $response['invoice_number'],
                'provider_document_id'   => $response['provider_document_id'],
                'pdf_path'               => $response['pdf_path'],
                'xml_path'               => $response['xml_path'],
                'response_message'       => $response['response_message'],
                'provider_response_json' => $response['response_payload'],
                'sent_at'                => now(),
                'accepted_at'            => now(),
            ]);

            $this->logEvent($doc, $oldStatus, 'accepted', 'Belge entegratör üzerinden GİB portalına başarıyla gönderildi.', $actorUserId, $response);

            return $doc;
        });
    }

    /**
     * e-Belgeyi İptal Et.
     */
    public function cancelDocument(EDocument $doc, string $reason = '', ?int $userId = null): EDocument
    {
        $actorUserId = $userId ?? auth()->id();
        if (!$actorUserId) {
            throw new InvalidArgumentException('Kullanıcı context/aktör bilgisi bulunamadı.');
        }

        if ($doc->user_id !== $actorUserId) {
            throw new InvalidArgumentException('Bu e-Belge belirtilen kullanıcıya ait değil.');
        }

        if ($doc->isCancelled()) {
            throw new InvalidArgumentException('Belge zaten iptal edilmiş.');
        }

        if (!$doc->isAccepted()) {
            throw new InvalidArgumentException('Sadece GİB portalına gönderilmiş (accepted) durumdaki belgeler iptal edilebilir.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('İptal işlemi için bir iptal gerekçesi belirtilmelidir.');
        }

        return DB::transaction(function () use ($doc, $reason, $actorUserId) {
            // Simulator iptal çağrısı
            $response = $this->provider->cancel($doc, $reason);

            $oldStatus = $doc->status;
            $doc->update([
                'status'           => 'cancelled',
                'cancelled_at'     => now(),
                'cancel_reason'    => $reason,
                'response_message' => $response['response_message'],
            ]);

            $this->logEvent($doc, $oldStatus, 'cancelled', 'Belge entegratör üzerinden iptal edildi. Gerekçe: ' . $reason, $actorUserId, $response);

            return $doc;
        });
    }

    /**
     * e-Belge durum değişikliğini günlüğe kaydet.
     */
    protected function logEvent(EDocument $doc, string $from, string $to, string $msg, int $actorUserId, ?array $payload = null): void
    {
        EDocumentEvent::create([
            'user_id'       => $doc->user_id,
            'actor_user_id' => $actorUserId,
            'e_document_id' => $doc->id,
            'status_from'   => $from,
            'status_to'     => $to,
            'message'       => $msg,
            'event_type'    => $from === 'none' ? 'created' : 'status_changed',
            'payload_json'  => $payload,
            'occurred_at'   => now(),
        ]);
    }
}
