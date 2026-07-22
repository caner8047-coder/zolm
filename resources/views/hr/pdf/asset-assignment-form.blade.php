<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Zimmet Teslim ve Tesellüm Tutanağı</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; line-height: 1.4; color: #1e293b; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; text-transform: uppercase; color: #0f172a; }
        .header p { margin: 4px 0 0 0; font-size: 10px; color: #64748b; }
        .section-title { font-weight: bold; font-size: 12px; background-color: #f1f5f9; padding: 6px 10px; border-left: 4px solid #0f172a; margin-top: 15px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 10px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #334155; width: 30%; }
        .signatures { margin-top: 40px; width: 100%; border-collapse: collapse; }
        .signatures td { border: none; text-align: center; vertical-align: top; width: 50%; padding: 0 20px; }
        .signature-box { border-top: 1px dashed #94a3b8; margin-top: 50px; padding-top: 6px; font-weight: bold; font-size: 10px; }
        .terms { font-size: 9.5px; color: #475569; margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>{{ $companyName ?? 'ZOLM İK TEKNOLOJİ A.Ş.' }}</h2>
        <p>DEMİRBAŞ VE ŞİRKET EKİPMANI ZİMMET TESLİM VE TESELLÜM TUTANAĞI</p>
    </div>

    <div class="section-title">1. TESLİM ALAN ÇALIŞAN BİLGİLERİ</div>
    <table>
        <tr>
            <th>Adı Soyadı</th>
            <td>{{ $employee->first_name }} {{ $employee->last_name }}</td>
            <th>Sicil No</th>
            <td>{{ $employee->employee_number }}</td>
        </tr>
        <tr>
            <th>Departman / Ünvan</th>
            <td>{{ $employment?->department?->name ?? '-' }} / {{ $employment?->position?->title ?? '-' }}</td>
            <th>T.C. Kimlik No</th>
            <td>*** *** {{ $employee->national_id_last_four }}</td>
        </tr>
    </table>

    <div class="section-title">2. ZİMMET EDİLEN DEMİRBAŞ / EKİPMAN BİLGİLERİ</div>
    <table>
        <tr>
            <th>Demirbaş Kodu</th>
            <td><strong>{{ $asset->asset_code }}</strong></td>
            <th>Kategori</th>
            <td>{{ $asset->category->name }}</td>
        </tr>
        <tr>
            <th>Ekipman Adı / Modeli</th>
            <td colspan="3"><strong>{{ $asset->name }}</strong> ({{ $asset->brand }} {{ $asset->model }})</td>
        </tr>
        <tr>
            <th>Seri No / Plaka</th>
            <td><strong>{{ $asset->serial_number ?? '-' }}</strong></td>
            <th>Teslim Tarihi</th>
            <td>{{ \Carbon\Carbon::parse($assignment->assigned_at)->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <th>Teslim Notu / Açıklama</th>
            <td colspan="3">{{ $assignment->assignment_note ?? 'Eksiksiz, çalışır durumda ve hasarsız olarak teslim edilmiştir.' }}</td>
        </tr>
    </table>

    <div class="terms">
        <strong>KULLANIM VE İADE ŞARTLARI:</strong><br/>
        1. İşbu tutanakla teslim edilen demirbaş ve teçhizat şirket işlerinin yürütülmesi amacıyla tahsis edilmiştir.<br/>
        2. Çalışan, demirbaşı özenle kullanmayı, 3. kişilere devretmemeyi ve şirket veri güvenliği kurallarına uymayı kabul eder.<br/>
        3. İş akdinin herhangi bir nedenle sona ermesi halinde, söz konusu demirbaş eksiksiz ve çalışır vaziyette İK / Bilgi İşlem birimine iade edilecektir.
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div>TESLİM EDEN (ŞİRKET YETKİLİSİ)</div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px;">İmza / Kaşe</div>
                <div class="signature-box">Tarih / İmza</div>
            </td>
            <td>
                <div>TESLİM ALAN (ÇALIŞAN)</div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px;">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                <div class="signature-box">Tarih / İmza</div>
            </td>
        </tr>
    </table>

</body>
</html>
