<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Mazeretsiz Devamsızlık Tespit Tutanağı</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; line-height: 1.4; color: #1e293b; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #b91c1c; padding-bottom: 12px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; text-transform: uppercase; color: #b91c1c; }
        .header p { margin: 4px 0 0 0; font-size: 10px; color: #64748b; }
        .section-title { font-weight: bold; font-size: 12px; background-color: #fef2f2; padding: 6px 10px; border-left: 4px solid #b91c1c; margin-top: 15px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 10px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #334155; width: 30%; }
        .signatures { margin-top: 40px; width: 100%; border-collapse: collapse; }
        .signatures td { border: none; text-align: center; vertical-align: top; width: 33.33%; padding: 0 10px; }
        .signature-box { border-top: 1px dashed #94a3b8; margin-top: 50px; padding-top: 6px; font-weight: bold; font-size: 10px; }
        .legal-note { font-size: 9.5px; color: #7f1d1d; margin-top: 20px; background: #fff5f5; border: 1px solid #fecaca; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>{{ $companyName ?? 'ZOLM İK TEKNOLOJİ A.Ş.' }}</h2>
        <p>MAZERETSİZ İŞE GELMEME (DEVAMSIZLIK) TESPİT TUTANAĞI</p>
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
            <th>Departman / Ünvan</th>
            <td>{{ $employment?->department?->name ?? '-' }} / {{ $employment?->position?->title ?? '-' }}</td>
            <th>T.C. Kimlik No</th>
            <td>*** *** {{ $employee->national_id_last_four }}</td>
        </tr>
    </table>

    <div class="section-title">2. DEVAMSIZLIK TESPİT DETAYLARI</div>
    <table>
        <tr>
            <th>Devamsızlık Tarihi</th>
            <td><strong>{{ \Carbon\Carbon::parse($anomaly->work_date)->format('d.m.Y') }}</strong></td>
            <th>Vardiya / Mesai</th>
            <td>08:30 - 17:30 Standart Mesai</td>
        </tr>
        <tr>
            <th>Tespit Şekli</th>
            <td>PDKS Cihaz Giriş Kaydı Bulunmamaktadır</td>
            <th>Durum Seviyesi</th>
            <td><strong style="color: #b91c1c;">YASAL UYARI / RİSKLİ</strong></td>
        </tr>
        <tr>
            <th>Tespit Açıklaması</th>
            <td colspan="3">Yukarıda bilgileri yer alan çalışanın belirtilen tarihte mesai saatleri içerisinde mazeretsiz ve izinsiz olarak işbaşı yapmadığı işyerinde düzenlenen işbu tutanak ile imza altına alınmıştır.</td>
        </tr>
    </table>

    <div class="legal-note">
        <strong>4857 SAYILI İŞ KANUNU MADDE 25/II-g UYARISI:</strong><br/>
        "İşçinin işverenden izin almaksızın veya haklı bir sebebe dayanmaksızın ardı ardına iki işgünü veya bir ay içinde iki defa herhangi bir tatil gününden sonraki iş günü yahut bir ayda üç işgünü işine devam etmemesi halinde işveren iş akdini haklı nedenle tazminatsız feshetme hakkına sahiptir."
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div>TESPİT EDEN (BİRİM AMİRİ)</div>
                <div class="signature-box">Tarih / İmza</div>
            </td>
            <td>
                <div>ŞAHİT (ÇALIŞAN)</div>
                <div class="signature-box">Tarih / İmza</div>
            </td>
            <td>
                <div>İNSAN KAYNAKLARI</div>
                <div class="signature-box">Tarih / Onay</div>
            </td>
        </tr>
    </table>

</body>
</html>
