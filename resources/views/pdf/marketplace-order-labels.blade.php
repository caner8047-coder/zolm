<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kargo Etiketleri</title>
    <style>
        @page { margin: 14px; }
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
            border-radius: 12px;
            padding: 14px;
        }
        .header,
        .meta-row,
        .recipient-row {
            width: 100%;
        }
        .box {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            background: #ffffff;
        }
        .soft-box {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f8fafc;
        }
        .eyebrow {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin-top: 6px;
        }
        .muted {
            color: #64748b;
        }
        .recipient-name {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .address {
            font-size: 13px;
            line-height: 1.55;
        }
        .barcode {
            margin-top: 14px;
            text-align: center;
        }
        .barcode img {
            width: 100%;
            max-width: 320px;
            height: auto;
        }
        .chips {
            margin-top: 10px;
        }
        .chip {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 10px;
            color: #334155;
        }
        .items {
            margin-top: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .item {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .item:last-child {
            border-bottom: 0;
        }
        .item-name {
            font-weight: 700;
            margin-bottom: 4px;
        }
        .footer {
            margin-top: 12px;
            color: #64748b;
            font-size: 10px;
            line-height: 1.5;
        }
        .spacer {
            height: 10px;
        }
    </style>
</head>
<body>
@foreach($documents as $document)
    @php
        $template = $settings['template'] ?? 'courier';
        $isCompact = $template === 'compact';
        $isMinimal = $template === 'minimal';
    @endphp
    <div class="page">
        <div class="sheet">
            <table class="header" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: {{ $isCompact ? '58%' : '66%' }}; vertical-align: top;">
                        <div class="box">
                            <div class="eyebrow">Kargo Etiketi</div>
                            <div class="title">
                                {{ $isMinimal ? 'Minimal Sevkiyat' : ($isCompact ? 'Kompakt Etiket' : ($document['packageNumber'] ?: $document['order']->order_number)) }}
                            </div>
                            <div style="margin-top: 8px;" class="muted">
                                Sipariş: {{ $document['order']->order_number }}
                                @if(($settings['show_marketplace'] ?? true) && filled($document['marketplaceLabel'] ?? null))
                                    <br>{{ $document['marketplaceLabel'] }}
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="width: {{ $isCompact ? '42%' : '34%' }}; vertical-align: top; padding-left: 10px;">
                        <div class="soft-box">
                            <div class="eyebrow">Lojistik</div>
                            <div style="margin-top: 8px; font-weight: 700;">{{ $document['cargoCompany'] ?: 'Kargo bilgisi bekleniyor' }}</div>
                            @if(($settings['show_tracking_number'] ?? true) && filled($document['trackingNumber'] ?? null))
                                <div style="margin-top: 6px;" class="muted">{{ $document['trackingNumber'] }}</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

            <div class="spacer"></div>

            <div class="soft-box">
                <div class="eyebrow">Alıcı</div>
                <div class="recipient-name" style="font-size: {{ $isCompact ? '19px' : ($isMinimal ? '18px' : '22px') }};">
                    {{ $document['recipientName'] ?: 'Müşteri bilgisi yok' }}
                </div>
                @if(($settings['show_customer_phone'] ?? true) && filled($document['customerPhone'] ?? null))
                    <div style="margin-bottom: 8px; font-size: 13px;">{{ $document['customerPhone'] }}</div>
                @endif
                <div class="address">{{ $document['shipmentAddress'] ?: 'Teslimat adresi bulunamadı.' }}</div>
            </div>

            @if(($settings['show_sender'] ?? true) && filled($document['sender']['name'] ?? null) && !$isMinimal)
                <div class="spacer"></div>
                <div class="box">
                    <div class="eyebrow">Gönderici</div>
                    <div style="margin-top: 8px; font-weight: 700;">{{ $document['sender']['name'] }}</div>
                    @if(filled($document['sender']['phone'] ?? null))
                        <div style="margin-top: 4px;" class="muted">{{ $document['sender']['phone'] }}</div>
                    @endif
                    @if(filled($document['sender']['taxNumber'] ?? null))
                        <div style="margin-top: 4px;" class="muted">VKN/TCKN: {{ $document['sender']['taxNumber'] }}</div>
                    @endif
                    @if(filled($document['sender']['address'] ?? null))
                        <div style="margin-top: 6px;" class="muted">{{ $document['sender']['address'] }}</div>
                    @endif
                </div>
            @endif

            <div class="barcode">
                <img src="{{ $document['barcodeDataUri'] }}" alt="Kargo barkodu">
            </div>

            @if(($settings['show_item_summary'] ?? true) && $document['itemSummary']->isNotEmpty())
                <div class="chips">
                    @foreach($document['itemSummary'] as $itemSummary)
                        <span class="chip">{{ $itemSummary }}</span>
                    @endforeach
                </div>
            @endif

            @if(($settings['show_items'] ?? true) && collect($document['items'])->isNotEmpty() && !$isMinimal && !$isCompact)
                <div class="items">
                    @foreach($document['items'] as $item)
                        <div class="item">
                            <div class="item-name">{{ $item->product_name ?: 'Ürün adı yok' }}</div>
                            <div class="muted">
                                Adet: {{ (int) ($item->quantity ?? 1) }}
                                @if(filled($item->stock_code ?? null))
                                    | Stok: {{ $item->stock_code }}
                                @endif
                                @if(filled($item->barcode ?? null))
                                    | Barkod: {{ $item->barcode }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(($settings['show_sender'] ?? true) && filled($document['sender']['name'] ?? null) && $isMinimal)
                <div class="footer" style="margin-top: 10px;">
                    Gönderici: {{ $document['sender']['name'] }}
                    @if(filled($document['sender']['phone'] ?? null))
                        | {{ $document['sender']['phone'] }}
                    @endif
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
