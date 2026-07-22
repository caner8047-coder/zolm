<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 9px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 14px; }
        .summary { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .summary td { border: 1px solid #e2e8f0; padding: 8px; }
        .summary strong { display: block; font-size: 14px; margin-top: 3px; }
        table.ledger { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .ledger th { background: #f8fafc; text-align: left; font-size: 8px; padding: 6px 4px; border: 1px solid #e2e8f0; }
        .ledger td { padding: 5px 4px; border: 1px solid #e2e8f0; word-wrap: break-word; }
        .note { margin-top: 10px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Oluşturulma: {{ $generated_at->format('d.m.Y H:i') }}</div>
    <table class="summary"><tr>@foreach($summary as $label => $value)<td>{{ $label }}<strong>{{ $value }}</strong></td>@endforeach</tr></table>
    <table class="ledger">
        <thead><tr>@foreach(array_keys($rows->first()) as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
        <tbody>@foreach($rows as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
    <p class="note">{{ $method_note }}</p>
</body>
</html>
