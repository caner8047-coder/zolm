<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İzin Talep ve Onay Formu</title>
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
        .signatures td { border: none; text-align: center; vertical-align: top; width: 33.33%; padding: 0 10px; }
        .signature-box { border-top: 1px dashed #94a3b8; margin-top: 50px; padding-top: 6px; font-weight: bold; font-size: 10px; }
        .legal-note { font-size: 9px; color: #64748b; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-style: italic; }
    </style>
</head>
<body>

    <div class="header">
        <h2>{{ $companyName ?? 'ZOLM İK TEKNOLOJİ A.Ş.' }}</h2>
        <p>RESMİ İZİN TALEP VE ONAY FORMU (4857 SAYILI İŞ KANUNU UYARINCA)</p>
    </div>

    <div class="section-title">1. ÇALIŞAN KİMLİK VE ÖZLÜK BİLGİLERİ</div>
    <table>
        <tr>
            <th>Adı Soyadı</th>
            <td>{{ $employee->first_name }} {{ $employee->last_name }}</td>
            <th>Sicil No</th>
            <td>{{ $employee->employee_number }}</td>
        </tr>
        <tr>
            <th>Departman</th>
            <td>{{ $employment?->department?->name ?? '-' }}</td>
            <th>Pozisyon / Ünvan</th>
            <td>{{ $employment?->position?->title ?? '-' }}</td>
        </tr>
        <tr>
            <th>T.C. Kimlik No</th>
            <td>*** *** {{ $employee->national_id_last_four }}</td>
            <th>İşe Başlama Tarihi</th>
            <td>{{ $employment?->start_date?->format('d.m.Y') ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">2. İZİN TALEP DETAYLARI</div>
    <table>
        <tr>
            <th>İzin Türü</th>
            <td colspan="3"><strong>{{ $leaveRequest->leaveType->name }}</strong></td>
        </tr>
        <tr>
            <th>İzin Başlangıç Tarihi</th>
            <td>{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('d.m.Y') }}</td>
            <th>İzin Bitiş Tarihi</th>
            <td>{{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <th>Talep Edilen Süre</th>
            <td>{{ $leaveRequest->requested_amount }} {{ $leaveRequest->unit === 'day' ? 'Gün' : 'Saat' }}</td>
            <th>Göreve Başlama Tarihi</th>
            <td>{{ \Carbon\Carbon::parse($leaveRequest->end_date)->addDay()->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <th>İzin Adresi ve İletişim</th>
            <td colspan="3">{{ $leaveRequest->reason ?? 'İzin süresince ikamet adresinde bulunulacaktır.' }}</td>
        </tr>
    </table>

    <div class="section-title">3. ONAY VE TESLİM BİLGİLERİ</div>
    <table>
        <tr>
            <th>İzin Durumu</th>
            <td><strong style="color: #059669;">ONAYLANDI</strong></td>
            <th>Onay Tarihi</th>
            <td>{{ \Carbon\Carbon::now()->format('d.m.Y') }}</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div>İzin İsteyen Çalışan</div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px;">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                <div class="signature-box">İmza / Tarih</div>
            </td>
            <td>
                <div>Departman Yöneticisi</div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px;">{{ $employment?->manager?->first_name ?? 'Yönetici' }} {{ $employment?->manager?->last_name ?? 'Onayı' }}</div>
                <div class="signature-box">İmza / Onay</div>
            </td>
            <td>
                <div>İnsan Kaynakları</div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px;">İşveren / İK Yetkilisi</div>
                <div class="signature-box">İmza / Mühür</div>
            </td>
        </tr>
    </table>

    <div class="legal-note">
        * Bu form 4857 Sayılı İş Kanunu'nun 53-60. maddeleri ve Yıllık Ücretli İzin Yönetmeliği hükümlerine uygun olarak düzenlenmiştir. İzin süresince başka bir işte çalışılması yasaktır. Çalışan izin bitimini takip eden ilk iş günü mesai saatinde görevine başlamayı kabul ve taahhüt eder.
    </div>

</body>
</html>
