@php
    $money = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $percent = fn ($value) => '%' . number_format((float) $value, 1, ',', '.');
    $number = fn ($value) => number_format((float) $value, 0, ',', '.');
    $summary = $payload['summary'] ?? [];
    $risk = $payload['risk'] ?? [];
    $campaign = $payload['campaign'] ?? [];
    $audit = $payload['audit'] ?? [];
    $actions = collect($payload['actions'] ?? [])->take(5);
    $riskItems = collect($payload['risk_items'] ?? [])->take(4);
    $marketplaces = collect($payload['marketplaces'] ?? [])->take(6);
    $sections = collect($payload['sections'] ?? []);
@endphp

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['subject'] ?? 'ZOLM Pazaryeri Raporu' }}</title>
</head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:Inter,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:760px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 18px;border-bottom:1px solid #e2e8f0;">
                            <div style="display:inline-block;border:1px solid #e2e8f0;background:#f8fafc;border-radius:6px;padding:5px 9px;color:#64748b;font-size:12px;font-family:ui-monospace,Menlo,monospace;">ZOLM Otomatik Rapor</div>
                            <h1 style="margin:14px 0 0;font-size:24px;line-height:1.25;color:#0f172a;">{{ $payload['subject'] ?? 'Pazaryeri Kâr Özeti' }}</h1>
                            <p style="margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.6;">
                                {{ $payload['period']['label'] ?? '' }} dönemi için kâr, risk ve kampanya karar özeti.
                                Hazırlanma: {{ $payload['generated_at'] ?? now()->format('d.m.Y H:i') }}
                            </p>
                        </td>
                    </tr>

                    @if($sections->contains('profit'))
                        <tr>
                            <td style="padding:22px 28px 10px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        @foreach([
                                            ['label' => 'Ciro', 'value' => $money($summary['gross_revenue'] ?? 0), 'color' => '#0f172a'],
                                            ['label' => 'Kâr', 'value' => $money($summary['profit_value'] ?? 0), 'color' => '#047857'],
                                            ['label' => 'Marj', 'value' => $percent($summary['profit_margin_percent'] ?? 0), 'color' => '#0f172a'],
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
                                            ['label' => 'Net alacak', 'value' => $money($summary['net_receivable'] ?? 0), 'color' => '#047857'],
                                            ['label' => 'Sipariş', 'value' => $number($summary['total_orders'] ?? 0), 'color' => '#0f172a'],
                                            ['label' => 'Zarar baskısı', 'value' => $number($summary['loss_order_count'] ?? 0), 'color' => '#be123c'],
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
                    @endif

                    @if($sections->contains('risk'))
                        <tr>
                            <td style="padding:10px 28px;">
                                <div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;padding:16px;">
                                    <div style="color:#0f172a;font-size:16px;font-weight:800;">Risk özeti</div>
                                    <p style="margin:7px 0 12px;color:#64748b;font-size:13px;line-height:1.5;">
                                        {{ $risk['risk_score_label'] ?? 'Hazır' }} skor · {{ $number($summary['risk_open_count'] ?? 0) }} açık risk · {{ $money($summary['risk_impact_total'] ?? 0) }} finansal baskı.
                                    </p>
                                    @forelse($riskItems as $item)
                                        <div style="padding:10px 0;border-top:1px solid #e2e8f0;">
                                            <div style="font-size:14px;font-weight:700;color:#0f172a;">{{ $item['title'] ?? $item['label'] ?? 'Risk sinyali' }}</div>
                                            <div style="margin-top:4px;color:#64748b;font-size:12px;line-height:1.5;">{{ $item['description'] ?? $item['recommendation'] ?? '' }}</div>
                                        </div>
                                    @empty
                                        <div style="padding:10px 0;border-top:1px solid #e2e8f0;color:#047857;font-size:13px;font-weight:700;">Açık kritik risk sinyali yok.</div>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endif

                    @if($sections->contains('campaign'))
                        <tr>
                            <td style="padding:10px 28px;">
                                <div style="border:1px solid #e2e8f0;background:#ffffff;border-radius:8px;padding:16px;">
                                    <div style="color:#0f172a;font-size:16px;font-weight:800;">Kampanya etkisi</div>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:12px;">
                                        <tr>
                                            <td style="color:#64748b;font-size:13px;">Güvenli fırsat</td>
                                            <td align="right" style="color:#047857;font-size:16px;font-weight:800;">{{ $money($campaign['potential_profit'] ?? 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding-top:8px;color:#64748b;font-size:13px;">Risk maruziyeti</td>
                                            <td align="right" style="padding-top:8px;color:#be123c;font-size:16px;font-weight:800;">{{ $money($campaign['risk_exposure'] ?? 0) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif

                    @if($sections->contains('marketplace'))
                        <tr>
                            <td style="padding:10px 28px;">
                                <div style="border:1px solid #e2e8f0;background:#ffffff;border-radius:8px;padding:16px;">
                                    <div style="color:#0f172a;font-size:16px;font-weight:800;">Pazaryeri kırılımı</div>
                                    @forelse($marketplaces as $row)
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:10px 0;border-top:1px solid #e2e8f0;margin-top:10px;">
                                            <tr>
                                                <td style="font-size:13px;font-weight:700;color:#0f172a;">{{ $row['label'] ?? $row['marketplace'] ?? 'Pazaryeri' }}</td>
                                                <td align="right" style="font-size:13px;font-weight:800;color:#047857;">{{ $money($row['profit_value'] ?? $row['profit'] ?? 0) }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:4px;color:#64748b;font-size:12px;">{{ $number($row['order_count'] ?? $row['orders'] ?? 0) }} sipariş</td>
                                                <td align="right" style="padding-top:4px;color:#64748b;font-size:12px;">Marj {{ $percent($row['profit_margin_percent'] ?? $row['margin'] ?? 0) }}</td>
                                            </tr>
                                        </table>
                                    @empty
                                        <p style="margin:10px 0 0;color:#64748b;font-size:13px;">Bu dönem için pazaryeri kırılımı bulunamadı.</p>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endif

                    @if($sections->contains('actions'))
                        <tr>
                            <td style="padding:10px 28px 22px;">
                                <div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;padding:16px;">
                                    <div style="color:#0f172a;font-size:16px;font-weight:800;">Öncelikli aksiyonlar</div>
                                    @forelse($actions as $action)
                                        <div style="padding:10px 0;border-top:1px solid #e2e8f0;margin-top:10px;">
                                            <div style="font-size:14px;font-weight:700;color:#0f172a;">{{ $action['label'] ?? 'Aksiyon' }}</div>
                                            <div style="margin-top:4px;color:#64748b;font-size:12px;line-height:1.5;">{{ $action['description'] ?? '' }}</div>
                                        </div>
                                    @empty
                                        <p style="margin:10px 0 0;color:#047857;font-size:13px;font-weight:700;">Öncelikli aksiyon bulunmuyor.</p>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endif


                    {{-- Geciken Ödemeler Uyarı Bloğu --}}
                    @if(($audit['missing_payment_count'] ?? 0) > 0 || ($audit['cargo_overcharge_count'] ?? 0) > 0)
                        <tr>
                            <td style="padding:10px 28px 10px;">
                                <div style="border:2px solid #fca5a5;background:#fff1f2;border-radius:8px;padding:16px;">
                                    <div style="color:#be123c;font-size:15px;font-weight:800;margin-bottom:10px;">⚠️ Geciken Ödemeler Uyarısı</div>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        @if(($audit['missing_payment_count'] ?? 0) > 0)
                                        <tr>
                                            <td style="color:#64748b;font-size:13px;padding-bottom:8px;">Kayıp ödeme (itiraz bekleyen)</td>
                                            <td align="right" style="color:#be123c;font-size:16px;font-weight:800;padding-bottom:8px;">
                                                {{ $number($audit['missing_payment_count']) }} sipariş · {{ $money($audit['missing_payment_total']) }}
                                            </td>
                                        </tr>
                                        @endif
                                        @if(($audit['cargo_overcharge_count'] ?? 0) > 0)
                                        <tr>
                                            <td style="color:#64748b;font-size:13px;padding-bottom:8px;">Kargo maliyet aşımı</td>
                                            <td align="right" style="color:#ea580c;font-size:16px;font-weight:800;padding-bottom:8px;">
                                                {{ $number($audit['cargo_overcharge_count']) }} sipariş · {{ $money($audit['cargo_overcharge_total']) }}
                                            </td>
                                        </tr>
                                        @endif
                                        @if(($audit['pending_dispute_count'] ?? 0) > 0)
                                        <tr>
                                            <td style="color:#64748b;font-size:13px;">Bekleyen itiraz</td>
                                            <td align="right" style="color:#0f172a;font-size:14px;font-weight:700;">
                                                {{ $number($audit['pending_dispute_count']) }} itiraz işlemde
                                            </td>
                                        </tr>
                                        @endif
                                    </table>
                                    @if(!empty($payload['links']['settlement_audit']))
                                        <div style="margin-top:12px;">
                                            <a href="{{ $payload['links']['settlement_audit'] }}" style="display:inline-block;background:#be123c;color:#ffffff;text-decoration:none;border-radius:6px;padding:9px 14px;font-size:13px;font-weight:700;">Eksik Ödeme Takibine Git →</a>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:20px 28px 28px;border-top:1px solid #e2e8f0;">
                            <a href="{{ $payload['links']['profit_center'] ?? '#' }}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 14px;font-size:13px;font-weight:700;">Kâr Merkezi'ni aç</a>
                            @if(!empty($payload['links']['settlement_audit']))
                                <a href="{{ $payload['links']['settlement_audit'] }}" style="display:inline-block;margin-left:8px;color:#be123c;text-decoration:none;border:1px solid #fca5a5;border-radius:6px;padding:9px 13px;font-size:13px;font-weight:700;">Eksik Ödeme Takibi</a>
                            @endif
                            <a href="{{ $payload['links']['report_settings'] ?? '#' }}" style="display:inline-block;margin-left:8px;color:#334155;text-decoration:none;border:1px solid #e2e8f0;border-radius:6px;padding:9px 13px;font-size:13px;font-weight:700;">Rapor ayarları</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
