<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Performans Düşüklüğü Yazılı Savunma İstem Formu</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; line-height: 1.5; margin: 30px; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; text-transform: uppercase; color: #0f172a; }
        .subtitle { font-size: 9pt; color: #64748b; margin-top: 4px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .meta-table td { padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 10pt; }
        .meta-label { font-weight: bold; background-color: #f8fafc; width: 30%; color: #334155; }
        .content-box { border: 1px solid #cbd5e1; padding: 15px; background-color: #f8fafc; margin-bottom: 20px; border-radius: 4px; }
        .law-notice { font-size: 9pt; color: #475569; font-style: italic; margin-bottom: 15px; }
        .signature-table { width: 100%; margin-top: 40px; }
        .signature-cell { width: 50%; text-align: center; vertical-align: top; }
        .sig-box { height: 60px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">YAZILI SAVUNMA İSTEM FORMU</div>
        <div class="subtitle">(4857 Sayılı İş Kanunu m.19 Uyarınca Performans Değerlendirme Savunma Talebi)</div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Tarih:</td>
            <td>{{ date('d.m.Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Çalışan Adı Soyadı:</td>
            <td>{{ $employee_name }}</td>
        </tr>
        <tr>
            <td class="meta-label">T.C. Kimlik No / Sicil No:</td>
            <td>{{ $national_id }} / {{ $employee_number }}</td>
        </tr>
        <tr>
            <td class="meta-label">Departman / Görev:</td>
            <td>{{ $department }} / {{ $job_title }}</td>
        </tr>
        <tr>
            <td class="meta-label">Değerlendirme Dönemi:</td>
            <td>{{ $evaluation_period }}</td>
        </tr>
    </table>

    <div class="law-notice">
        <strong>Sayın {{ $employee_name }},</strong><br>
        4857 Sayılı İş Kanunu'nun 19. maddesi uyarınca; hakkındaki iddialara ve performans düşüklüğüne karşı savunması alınmadan çalışanın iş sözleşmesi feshedilemez. Şirketimizde gerçekleştirilen {{ $evaluation_period }} dönemi performans değerlendirmeniz sonucunda aşağıdaki hususlar tespit edilmiştir.
    </div>

    <div class="content-box">
        <strong>Tespit Edilen Performans Düşüklüğü ve Gerekçeler:</strong>
        <p style="margin-top: 8px;">{{ $performance_notes ?: 'Belirlenen hedef ve KPI beklentilerinin altında kalınması, iş kalitesi ve verimlilik standartlarının sağlanamaması.' }}</p>
    </div>

    <p style="font-size: 10pt;">
        Yukarıda belirtilen performans yetersizliği ve gerekçelere ilişkin <strong>en geç 3 (üç) iş günü içerisinde</strong> yazılı savunmanızı İnsan Kaynakları Departmanına sunmanızı rica ederiz. Belirtilen süre içerisinde geçerli bir mazeret bildirmeksizin savunma vermemeniz halinde savunma hakkınızdan feragat etmiş sayılacağınızı bildiririz.
    </p>

    <table class="signature-table">
        <tr>
            <td class="signature-cell">
                <strong>İşveren / İK Yetkilisi</strong><br>
                İmza / Kaşe
                <div class="sig-box"></div>
            </td>
            <td class="signature-cell">
                <strong>Tebliğ Alan Çalışan</strong><br>
                Tarih: ..... / ..... / 20...<br>
                İmza
                <div class="sig-box"></div>
            </td>
        </tr>
    </table>
</body>
</html>
