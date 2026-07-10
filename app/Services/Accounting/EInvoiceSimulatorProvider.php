<?php

namespace App\Services\Accounting;

use App\Models\EDocument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EInvoiceSimulatorProvider
{
    /**
     * Faturayı simüle entegratöre gönder ve kabul sonucu al.
     */
    public function send(EDocument $document): array
    {
        if (!$document->isDraft()) {
            throw new InvalidArgumentException('Sadece taslak durumundaki belgeler gönderilebilir.');
        }

        $userId = $document->user_id;
        $year = $document->issue_date ? $document->issue_date->year : now()->year;
        $docType = $document->document_type; // e_invoice, e_archive

        // Sıradaki fatura numarasını al
        $invoiceNumber = $this->nextInvoiceNumber($userId, $year, $docType);

        $uuid = $document->uuid ?: \Illuminate\Support\Str::uuid()->toString();

        return [
            'success'              => true,
            'invoice_number'       => $invoiceNumber,
            'provider_document_id' => 'PRV-' . $uuid,
            'pdf_path'             => '/storage/e-documents/' . $userId . '/' . $invoiceNumber . '.pdf',
            'xml_path'             => '/storage/e-documents/' . $userId . '/' . $invoiceNumber . '.xml',
            'response_message'     => 'Belge başarıyla GİB/Entegratör sistemine iletildi ve onaylandı.',
            'request_payload'      => [
                'uuid'           => $uuid,
                'document_type'  => $docType,
                'buyer_name'     => $document->buyer_name,
                'buyer_tax'      => $document->buyer_tax_number,
                'total_amount'   => (float) $document->total_amount,
            ],
            'response_payload'     => [
                'status'         => 'SUCCEED',
                'gib_code'       => '1000',
                'gib_description'=> 'Onaylandı',
            ],
        ];
    }

    /**
     * Faturayı iptal et.
     */
    public function cancel(EDocument $document, string $reason): array
    {
        if (empty($reason)) {
            throw new InvalidArgumentException('İptal gerekçesi boş olamaz.');
        }

        return [
            'success'          => true,
            'response_message' => 'Belge entegratör üzerinden başarıyla iptal edildi. Gerekçe: ' . $reason,
            'response_payload' => [
                'status'         => 'CANCELLED',
                'cancelled_at'   => now()->toDateTimeString(),
                'cancel_reason'  => $reason,
            ],
        ];
    }

    /**
     * Sıradaki fatura numarasını artan sıralı ve lock mekanizması ile üretir.
     */
    public function nextInvoiceNumber(int $userId, int $year, string $documentType): string
    {
        $prefix = $documentType === 'e_invoice' ? 'GIB' : 'ARS';

        return DB::transaction(function () use ($userId, $year, $documentType, $prefix) {
            $getSequence = function () use ($userId, $year, $documentType) {
                return DB::table('e_document_sequences')
                    ->where('user_id', $userId)
                    ->where('year', $year)
                    ->where('document_type', $documentType)
                    ->lockForUpdate()
                    ->first();
            };

            $sequence = $getSequence();

            if (!$sequence) {
                // İlk sıra
                $lastNumber = 1;
                try {
                    DB::table('e_document_sequences')->insert([
                        'user_id'       => $userId,
                        'year'          => $year,
                        'document_type' => $documentType,
                        'prefix'        => $prefix,
                        'last_number'   => $lastNumber,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Eşzamanlı çalışan başka bir process insert etmiş olmalı, tekrar kilitleyerek oku
                    $sequence = $getSequence();
                    if ($sequence) {
                        $lastNumber = $sequence->last_number + 1;
                        DB::table('e_document_sequences')
                            ->where('id', $sequence->id)
                            ->update([
                                'last_number' => $lastNumber,
                                'updated_at'  => now(),
                            ]);
                    }
                }
            } else {
                $lastNumber = $sequence->last_number + 1;
                DB::table('e_document_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_number' => $lastNumber,
                        'updated_at'  => now(),
                    ]);
            }

            // 16 Haneli fatura numarası şablonu: {PREFIX}{YEAR}{9_HANELI_SIRA}
            // Örnek: GIB2026000000001
            $formattedNumber = str_pad((string) $lastNumber, 9, '0', STR_PAD_LEFT);

            return $prefix . $year . $formattedNumber;
        });
    }
}
