#!/usr/bin/env python3
"""
Trendyol Kargo Fiyatları PDF parser.
pdftotext -layout çıktısını parse eder.
"""
import sys, re, json, subprocess

def extract_rate(s):
    m = re.search(r'(\d{1,4}[.,]\d{1,2})', s)
    if m: return float(m.group(1).replace(',','.'))
    m = re.search(r'(\d{1,4})', s)
    if m: return float(m.group(1))
    return None

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'ok': False, 'error': 'PDF path is missing', 'rows': []}))
        return

    pdf_path = sys.argv[1]
    result = subprocess.run(
        ['pdftotext', '-layout', pdf_path, '-'],
        capture_output=True
    )
    if result.returncode != 0:
        print(json.dumps({'ok': False, 'error': result.stderr.decode('utf-8', errors='replace'), 'rows': []}))
        return
    
    text = result.stdout.decode('utf-8', errors='replace')
    lines = text.split('\n')
    
    # Varsayılan Kargo şirketleri sırası (Trendyol PDF'lerindeki yaygın sıra)
    # 1: TEX, 2: PTT, 3: Aras, 4: Sürat, 5: Yurtiçi, 6: MNG, 7: Kolay Gelsin vb.
    # Burada genel bir regex ile satırdaki tüm float'ları yakalıyoruz.
    
    rows = []
    
    for line in lines:
        # Satır başında bir Desi (sayı) ile başlıyorsa ve en az 3-4 kargo bedeli varsa
        if re.match(r'^\s{0,5}\d{1,3}\s{2,}', line):
            # Sayıları çıkar
            parts = re.findall(r'(\d{1,4}[.,]\d{2}|\d{1,3})', line)
            
            if len(parts) >= 5:
                desi = int(parts[0])
                # Sonraki değerler fiyatlar. PDF sütunlarına göre bunları isimlendirmek gerekiyor.
                # Gerçek PDF formatı incelendiğinde harita güncellenebilir.
                # Şimdilik örnek bir mapping:
                rates = [extract_rate(p) for p in parts[1:]]
                
                companies = ['TEX', 'PTT', 'Aras', 'Sürat', 'Kolay Gelsin', 'DHL', 'Yurtiçi']
                
                for idx, c in enumerate(companies):
                    if idx < len(rates) and rates[idx]:
                        rows.append({
                            'cargo_company': c,
                            'desi': desi,
                            'price': rates[idx]
                        })

    print(json.dumps({'ok': True, 'rows': rows, 'total': len(rows), 'error': None}))

if __name__ == '__main__':
    main()
