#!/usr/bin/env python3
"""
Trendyol komisyon PDF parser.
pdftotext -layout çıktısını regex ile parse eder.
Multibyte sorununu encode/decode ile aşar.
Çıktı: JSON array
"""
import sys, re, json

def extract_rate(s):
    m = re.search(r'(\d{1,2}[.,]\d{1,2})\s*%', s)
    if m: return float(m.group(1).replace(',','.'))
    m = re.search(r'(\d{1,2})\s*%', s)
    if m: return float(m.group(1))
    return None

def col(line, start, end):
    """Byte-safe substring using encoded bytes."""
    b = line.encode('utf-8')
    # Karakter pozisyonu yerine görsel pozisyon
    # Basit yaklaşım: boşluklar sabit genişlikte
    chars = list(line)
    return ''.join(chars[start:end]).strip()

def parse_line_by_regex(line):
    """
    Satır regex ile parse edilir:
    Başında rakam, sonra boşlukla ayrılmış bloklar, ardından oran.
    """
    m = re.match(
        r'^\s{0,4}(\d{1,4})\s{3,}'     # Sıra no
        r'(\S.*?)\s{3,}'                # Kategori
        r'(\S.*?)\s{3,}'                # Alt kategori
        r'(\S.*?)\s{2,}'               # Ürün grubu
        r'(\d+)\s+'                    # Vade
        r'(\d{1,2}[.,]\d{1,2}%)',      # Komisyon
        line
    )
    if not m:
        return None
    
    cat_raw    = m.group(2).strip()
    sub_raw    = m.group(3).strip()
    grp_raw    = m.group(4).strip()
    maturity   = int(m.group(5))
    comm_str   = m.group(6)
    
    commission = extract_rate(comm_str)
    if not commission or commission <= 0:
        return None
    
    # Kalan kısımdan sv5/sv4 oranlarını al
    rest = line[m.end():]
    rates = re.findall(r'(\d{1,2}[.,]\d{1,2})%', rest)
    rates = [float(r.replace(',','.')) for r in rates if float(r.replace(',','.')) > 0]
    
    # İlk oran genellikle başka satıcı oranı, sonraki sv5/sv4
    lv5 = rates[0] if len(rates)>0 else None
    lv4 = rates[1] if len(rates)>1 else None
    lv3 = rates[2] if len(rates)>2 else None
    lv2 = rates[3] if len(rates)>3 else None
    lv1 = rates[4] if len(rates)>4 else None
    
    return {
        'category_name':     cat_raw,
        'sub_category_name': sub_raw,
        'product_group':     grp_raw,
        'maturity_days':     maturity,
        'commission_rate':   commission,
        'level_5_rate':      lv5,
        'level_4_rate':      lv4,
        'level_3_rate':      lv3,
        'level_2_rate':      lv2,
        'level_1_rate':      lv1,
    }

def main():
    pdf_path = sys.argv[1]
    import subprocess
    result = subprocess.run(
        ['pdftotext', '-layout', pdf_path, '-'],
        capture_output=True
    )
    if result.returncode != 0:
        print(json.dumps({'ok': False, 'error': result.stderr.decode('utf-8', errors='replace'), 'rows': []}))
        return
    
    text = result.stdout.decode('utf-8', errors='replace')
    lines = text.split('\n')
    
    merged = {}
    current = None
    
    for line in lines:
        if re.match(r'^\s{0,4}\d{1,4}\s{3,}', line):
            if current:
                k = current['category_name']+'|||'+current['sub_category_name']+'|||'+current['product_group']
                if k not in merged or current['commission_rate'] > merged[k]['commission_rate']:
                    merged[k] = current
            current = parse_line_by_regex(line)
        elif current and line.strip() and not re.search(r'\d%', line):
            # Ürün grubu devam satırı
            pass
    
    if current:
        k = current['category_name']+'|||'+current['sub_category_name']+'|||'+current['product_group']
        if k not in merged:
            merged[k] = current
    
    rows = list(merged.values())
    print(json.dumps({'ok': True, 'rows': rows, 'total': len(rows), 'error': None}))

main()
