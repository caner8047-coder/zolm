<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İrsaliyeler</title>
    <style>
        @page { margin: 24px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        .page {
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .sheet {
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 18px;
        }
        .eyebrow {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .title {
            margin-top: 6px;
            font-size: 22px;
            font-weight: 700;
        }
        .muted {
            color: #64748b;
        }
        .panel {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 14px;
            background: #ffffff;
        }
        .soft-panel {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            background: #f8fafc;
        }
        .meta-table,
        .two-col,
        .items-table {
            width: 100%;
        }
        .items-table {
            border-collapse: collapse;
            margin-top: 14px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            vertical-align: top;
            text-align: left;
        }
        .items-table th {
            background: #f8fafc;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
        }
        .barcode {
            text-align: center;
        }
        .barcode img {
            width: 100%;
            max-width: 320px;
            height: auto;
        }
        .footer {
            margin-top: 14px;
            font-size: 10px;
            color: #64748b;
            line-height: 1.55;
        }
        .signature {
            margin-top: 16px;
            border: 1px dashed #94a3b8;
            border-radius: 10px;
            padding: 14px;
            min-height: 78px;
        }
    </style>
</head>
<body>
@foreach($documents as $document)
    @php
        $itemCount = collect($document['items'])->sum(fn ($item) => (int) ($item->quantity ?? 1));
        $template = $document['template'] ?? ($settings['template'] ?? 'classic');
        $isCompact = $template === 'compact';
        $isWarehouse = $template === 'warehouse';
    @endphp
    <div class="page">
        <div class="sheet">
            <table class="meta-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 62%; vertical-align: top;">
                        <div class="panel">
                            <div class="eyebrow">Sevk Belgesi</div>
                            <div class="title">
                                {{ $isWarehouse ? 'Depo Sevk Fişi' : ($isCompact ? 'Kompakt İrsaliye' : 'İrsaliye') }}
                            </div>
                            <div style="margin-top: 10px;" class="muted">
                                Sipariş: {{ $document['order']->order_number }}<br>
                                Paket: {{ $document['package']?->package_number ?: $document['order']->order_number }}<br>
                                Tarih: {{ optional($document['order']->ordered_at)->format('d.m.Y H:i') ?: now()->format('d.m.Y H:i') }}
                                @if(($settings['show_marketplace'] ?? true) && filled($document['marketplaceLabel'] ?? null))
                                    <br>Mağaza: {{ $document['marketplaceLabel'] }}
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="width: 38%; vertical-align: top; padding-left: 12px;">
                        <div class="soft-panel">
                            <div class="eyebrow">Sevkiyat</div>
                            <div style="margin-top: 8px; font-weight: 700;">{{ $document['cargoCompany'] ?: 'Kargo bilgisi bekleniyor' }}</div>
                            <div style="margin-top: 6px;" class="muted">Takip: {{ $document['trackingNumber'] ?: '-' }}</div>
                            <div style="margin-top: 6px;" class="muted">Toplam adet: {{ $itemCount }}</div>
                            @if($isWarehouse)
                                <div style="margin-top: 6px;" class="muted">Şablon: Depo operasyon</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

            <table class="two-col" cellpadding="0" cellspacing="0" style="margin-top: 14px;">
                <tr>
                    @if(($settings['show_sender'] ?? true) && filled($document['sender']['name'] ?? null))
                        <td style="width: 50%; vertical-align: top; padding-right: 6px;">
                            <div class="panel">
                                <div class="eyebrow">Gönderici</div>
                                <div style="margin-top: 8px; font-size: 15px; font-weight: 700;">{{ $document['sender']['name'] }}</div>
                                @if(filled($document['sender']['phone'] ?? null))
                                    <div style="margin-top: 5px;">{{ $document['sender']['phone'] }}</div>
                                @endif
                                @if(filled($document['sender']['taxNumber'] ?? null))
                                    <div style="margin-top: 5px;" class="muted">VKN/TCKN: {{ $document['sender']['taxNumber'] }}</div>
                                @endif
                                @if(filled($document['sender']['address'] ?? null))
                                    <div style="margin-top: 8px;" class="muted">{{ $document['sender']['address'] }}</div>
                                @endif
                            </div>
                        </td>
                    @endif
                    <td style="width: 50%; vertical-align: top; padding-left: 6px;">
                        <div class="panel">
                            <div class="eyebrow">Alıcı</div>
                            <div style="margin-top: 8px; font-size: 15px; font-weight: 700;">{{ $document['recipientName'] ?: 'Müşteri bilgisi yok' }}</div>
                            @if(($settings['show_customer_phone'] ?? true) && filled($document['customerPhone'] ?? null))
                                <div style="margin-top: 5px;">{{ $document['customerPhone'] }}</div>
                            @endif
                            @if(($settings['show_billing_info'] ?? true) && filled($document['billingName'] ?? null))
                                <div style="margin-top: 5px;" class="muted">{{ $document['billingName'] }}</div>
                            @endif
                            @if(($settings['show_billing_info'] ?? true) && filled($document['billingTaxNumber'] ?? null))
                                <div style="margin-top: 5px;" class="muted">Vergi No: {{ $document['billingTaxNumber'] }}</div>
                            @endif
                            <div style="margin-top: 8px;" class="muted">{{ $document['shipmentAddress'] ?: 'Teslimat adresi bulunamadı.' }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            @if(($settings['show_barcode'] ?? true) && filled($document['barcodeDataUri'] ?? null))
                <div class="soft-panel" style="margin-top: 14px;">
                    <div class="eyebrow">{{ $isWarehouse ? 'Depo Barkodu' : 'Paket Barkodu' }}</div>
                    <div class="barcode" style="margin-top: 10px;">
                        <img src="{{ $document['barcodeDataUri'] }}" alt="Paket barkodu">
                    </div>
                </div>
            @endif

            @if(($settings['show_items'] ?? true) && collect($document['items'])->isNotEmpty())
                <table class="items-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th style="width: 8%;">#</th>
                            <th style="width: 42%;">Ürün</th>
                            <th style="width: 16%;">Adet</th>
                            <th style="width: {{ $isCompact ? '17%' : '17%' }};">Stok Kodu</th>
                            <th style="width: {{ $isCompact ? '17%' : '17%' }};">Barkod</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($document['items'] as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->product_name ?: 'Ürün adı yok' }}</td>
                                <td>{{ (int) ($item->quantity ?? 1) }}</td>
                                <td>{{ $item->stock_code ?: '-' }}</td>
                                <td>{{ $item->barcode ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(($settings['show_signature_area'] ?? true))
                <div class="signature">
                    <div class="eyebrow">Teslim / İmza</div>
                    <div style="margin-top: 10px;" class="muted">
                        {{ $isWarehouse ? 'Depo çıkışı kontrol edilmiştir.' : 'Ürünler eksiksiz ve sağlam olarak teslim alınmıştır.' }}
                    </div>
                </div>
            @endif

            @if(filled($settings['footer_note'] ?? null))
                <div class="footer">{{ $settings['footer_note'] }}</div>
            @endif
        </div>
    </div>
@endforeach
</body>
</html>
