<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Etiket - {{ $order->siparis_no }}</title>
    <style>
        @page { margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; }
        .label { padding: 10px; }
        .header { text-align: center; border-bottom: 2px solid #6B665A; padding-bottom: 10px; margin-bottom: 15px; }
        .logo { height: 35px; }
        .name { font-size: 16px; font-weight: bold; margin-bottom: 8px; }
        .addr { font-size: 12px; line-height: 1.5; margin-bottom: 6px; }
        .phone { font-size: 14px; font-weight: bold; margin-bottom: 15px; }
        .products-header { 
            background: #10b981; 
            color: #fff; 
            padding: 6px 10px; 
            font-size: 11px; 
            font-weight: bold; 
            text-transform: uppercase;
            margin-bottom: 0;
        }
        .product { 
            border: 2px solid #10b981; 
            border-top: none;
            padding: 10px 12px; 
            margin-bottom: 0; 
        }
        .product:not(:last-child) {
            border-bottom: 1px dashed #ccc;
        }
        .product-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 4px;
        }
        .product-item:last-child { margin-bottom: 0; }
        .pname { font-size: 11px; line-height: 1.4; word-wrap: break-word; flex: 1; }
        .qty { 
            font-size: 14px; 
            font-weight: bold; 
            background: #f3f4f6;
            padding: 4px 10px;
            border-radius: 4px;
            margin-left: 10px;
            white-space: nowrap;
        }
        .total-qty {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            padding: 12px;
            border: 2px solid #10b981;
            border-top: none;
            background: #f0fdf4;
        }
        .orderno { background: #333; color: #fff; text-align: center; padding: 10px; font-size: 16px; font-weight: bold; letter-spacing: 1px; margin-top: 15px; }
        .footer { text-align: center; font-size: 10px; color: #666; margin-top: 10px; }
        .item-count { 
            background: #10b981; 
            color: #fff; 
            padding: 2px 8px; 
            border-radius: 10px; 
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="header">
            <img src="{{ public_path('zemhome-logo.png') }}" class="logo" alt="ZemHome">
        </div>
        
        <div class="name">{{ $order->musteri_adi }}</div>
        <div class="addr">
            {{ $order->adres }}<br>
            {{ $order->ilce }} / {{ $order->il }}
        </div>
        <div class="phone">📞 {{ $order->telefon }}</div>
        
        <div class="products-header">
            Ürünler <span class="item-count">{{ $orderItems->count() }}</span>
        </div>
        
        @foreach($orderItems as $index => $item)
        <div class="product">
            <div class="product-item">
                <span class="pname">{{ $index + 1 }}. {{ $item->urun_adi }}</span>
                <span class="qty">{{ $item->adet }} ADET</span>
            </div>
        </div>
        @endforeach
        
        <div class="total-qty">
            TOPLAM: {{ $orderItems->sum('adet') }} ADET
        </div>
        
        <div class="orderno">#{{ $order->siparis_no }}</div>
    </div>
</body>
</html>
