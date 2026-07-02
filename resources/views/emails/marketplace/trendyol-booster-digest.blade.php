@php
    $number = fn ($value) => number_format((float) $value, 0, ',', '.');
    $counts = $payload['counts'] ?? [];
    $notifications = collect($payload['notifications'] ?? [])->take(12);
    $period = $payload['period'] ?? [];
@endphp

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['subject'] ?? 'ZOLM Trendyol Booster Özeti' }}</title>
</head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:Inter,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:760px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 18px;border-bottom:1px solid #e2e8f0;">
                            <div style="display:inline-block;border:1px solid #e2e8f0;background:#f8fafc;border-radius:6px;padding:5px 9px;color:#64748b;font-size:12px;font-family:ui-monospace,Menlo,monospace;">ZOLM Trendyol Booster</div>
                            <h1 style="margin:14px 0 0;font-size:24px;line-height:1.25;color:#0f172a;">{{ $payload['subject'] ?? 'Booster karar özeti' }}</h1>
                            <p style="margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.6;">
                                {{ $period['label'] ?? 'Son Booster olayları' }} için fiyat, stok, rakip ve kelime sinyalleri.
                                Hazırlanma: {{ $payload['generated_at'] ?? now()->format('d.m.Y H:i') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 28px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    @foreach([
                                        ['label' => 'Toplam sinyal', 'value' => $number($counts['total'] ?? 0), 'color' => '#0f172a'],
                                        ['label' => 'Fiyat', 'value' => $number($counts['price'] ?? 0), 'color' => '#0369a1'],
                                        ['label' => 'Stok', 'value' => $number($counts['stock'] ?? 0), 'color' => '#b45309'],
                                    ] as $card)
                                        <td width="33.3%" style="padding:0 6px 12px 0;">
                                            <div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;padding:14px;">
                                                <div style="color:#64748b;font-size:12px;font-weight:700;">{{ $card['label'] }}</div>
                                                <div style="margin-top:8px;color:{{ $card['color'] }};font-size:20px;font-weight:800;">{{ $card['value'] }}</div>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach([
                                        ['label' => 'Rakip', 'value' => $number($counts['competitor'] ?? 0), 'color' => '#7c3aed'],
                                        ['label' => 'Kelime', 'value' => $number($counts['keyword'] ?? 0), 'color' => '#047857'],
                                        ['label' => 'Dikkat', 'value' => $number($counts['warning'] ?? 0), 'color' => '#be123c'],
                                    ] as $card)
                                        <td width="33.3%" style="padding:0 6px 12px 0;">
                                            <div style="border:1px solid #e2e8f0;background:#ffffff;border-radius:8px;padding:14px;">
                                                <div style="color:#64748b;font-size:12px;font-weight:700;">{{ $card['label'] }}</div>
                                                <div style="margin-top:8px;color:{{ $card['color'] }};font-size:18px;font-weight:800;">{{ $card['value'] }}</div>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 28px 22px;">
                            <div style="border:1px solid #e2e8f0;background:#ffffff;border-radius:8px;padding:16px;">
                                <div style="color:#0f172a;font-size:16px;font-weight:800;">Son Booster sinyalleri</div>

                                @forelse($notifications as $notification)
                                    <div style="padding:12px 0;border-top:1px solid #e2e8f0;margin-top:10px;">
                                        <div style="font-size:14px;font-weight:800;color:#0f172a;">{{ $notification['title'] ?? 'Booster sinyali' }}</div>
                                        <div style="margin-top:5px;color:#64748b;font-size:13px;line-height:1.5;">{{ $notification['body'] ?? '' }}</div>
                                        <div style="margin-top:6px;color:#94a3b8;font-size:12px;">{{ $notification['created_at_label'] ?? '' }}</div>
                                    </div>
                                @empty
                                    <p style="margin:10px 0 0;color:#047857;font-size:13px;font-weight:700;">Gönderilecek yeni Booster sinyali bulunmuyor.</p>
                                @endforelse
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 28px 28px;border-top:1px solid #e2e8f0;">
                            <a href="{{ $payload['links']['booster'] ?? '#' }}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 14px;font-size:13px;font-weight:700;">Trendyol Booster'ı aç</a>
                            <span style="display:inline-block;margin-left:8px;color:#64748b;font-size:12px;">Bu mail yalnızca daha önce mail özetine girmemiş sinyallerden oluşturuldu.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
