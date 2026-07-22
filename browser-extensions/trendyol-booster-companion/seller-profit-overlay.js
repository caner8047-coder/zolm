/**
 * ZOLM Seller Profit Overlay
 * Ürün listesi, indirim/kupon ve reklam yüzeylerinde kayıtlı maliyetlerle
 * tahmini birim ekonomi gösterir. Fiyatlandırma, kampanya ve sipariş sayfaları
 * kendi daha ayrıntılı adaptörleri tarafından yönetilir.
 */
(function () {
  'use strict';

  const SCRIPT_ID = 'zolm-seller-profit-overlay';
  const VERSION = chrome.runtime.getManifest().version;
  const MAX_ROWS = 80;
  if (window[SCRIPT_ID] === VERSION) return;
  window[SCRIPT_ID] = VERSION;

  const excluded = /\/pricing\/|\/promotions\/campaigns\/details\/|\/orders\/shipment-packages\//i;
  if (excluded.test(location.pathname)) return;

  let marginLow = 5;
  let marginHigh = 20;
  let serviceFeeFixed = 9.33;
  let withholdingTaxEnabled = false;
  let scanRunning = false;
  let queued = false;
  let timer = null;

  const clean = (value) => String(value || '').replace(/\s+/g, ' ').trim();
  const normalize = (value) => clean(value).replace(/[İı]/g, 'I').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toUpperCase();

  function pageKind() {
    const path = location.pathname;
    if (/advert|reklam|ads|sponsored/i.test(path)) return 'ads';
    if (/coupon|kupon|discount|indirim|promotion/i.test(path)) return 'discount';
    return 'products';
  }

  function number(value) {
    let normalized = String(value || '').replace(/\s|₺|TL/gi, '');
    const comma = normalized.lastIndexOf(',');
    const dot = normalized.lastIndexOf('.');
    if (comma > dot) normalized = normalized.replace(/\./g, '').replace(',', '.');
    else if (dot > comma && comma >= 0) normalized = normalized.replace(/,/g, '');
    else if (comma >= 0) normalized = normalized.replace(',', '.');
    const parsed = Number.parseFloat(normalized.replace(/[^0-9.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function money(value) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(value || 0));
  }

  function identifier(text, pattern) {
    return clean(text.match(pattern)?.[1] || '');
  }

  function findRows() {
    const selectors = ['table tbody tr', '[role="row"]', '[data-testid*="product"]', '[class*="product-card"]', '[class*="ProductCard"]'];
    for (const selector of selectors) {
      const rows = Array.from(document.querySelectorAll(selector)).filter((row) => {
        if (row.querySelector('[data-zolm-seller-profit]')) return false;
        const text = clean(row.innerText || row.textContent || '');
        return text.length > 20
          && (/Barkod\s*:/i.test(text) || /(?:Model|Stok)\s+Kod[u:]?\s*:/i.test(text) || /\b\d{8,14}\b/.test(text))
          && (/₺|\bTL\b/i.test(text));
      });
      if (rows.length > 0) return rows.slice(0, MAX_ROWS);
    }
    return [];
  }

  function parseRow(row) {
    const text = clean(row.innerText || row.textContent || '');
    const barcode = identifier(text, /Barkod\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/i) || identifier(text, /\b(\d{8,14})\b/);
    const modelCode = identifier(text, /Model\s+Kod[u:]?\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/i);
    const stockCode = identifier(text, /Stok\s+Kod[u:]?\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/i);
    const labelledPrice = text.match(/(?:Satış|Güncel|İndirimli|Ürün)\s+Fiyat[ıi]?\s*:?\s*(?:₺\s*)?([\d.,]+)/i);
    const priceMatches = Array.from(text.matchAll(/(?:₺\s*)?([\d.]+(?:,\d{1,2})?)\s*(?:TL|₺)/gi)).map((match) => number(match[1])).filter((value) => value > 0);
    const salePrice = labelledPrice ? number(labelledPrice[1]) : (priceMatches[0] || 0);
    const sellerDiscountMatch = text.match(/(?:Satıcı\s+(?:payı|indirimi)|Kupon\s+satıcı\s+payı)\s*:?\s*(?:₺\s*)?([\d.,]+)/i);

    return { row, barcode, modelCode, stockCode, salePrice, sellerDiscount: number(sellerDiscountMatch?.[1] || 0) };
  }

  function lookup(rows) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({
        type: 'ZOLM_PRICING_COST_LOOKUP',
        barcodes: rows.map((row) => row.barcode).filter(Boolean),
        model_codes: rows.map((row) => row.modelCode).filter(Boolean),
        stock_codes: rows.map((row) => row.stockCode).filter(Boolean),
      }, (response) => {
        if (chrome.runtime.lastError || !response?.ok) {
          reject(new Error(response?.message || chrome.runtime.lastError?.message || 'Maliyet eşleştirmesi yapılamadı.'));
          return;
        }
        resolve(response.products || {});
      });
    });
  }

  function costFor(row, products) {
    return products[row.barcode]
      || products[`mc:${row.modelCode}`]
      || products[`sc:${row.stockCode}`]
      || products[`mc:${normalize(row.modelCode)}`]
      || products[`sc:${normalize(row.stockCode)}`]
      || null;
  }

  function calculate(row, cost) {
    if (!cost || row.salePrice <= 0) return null;
    const commissionRate = Number(cost.commission_rate || 0);
    const commission = row.salePrice * commissionRate / 100;
    const vatRate = Number(cost.vat_rate || 0);
    const withholding = withholdingTaxEnabled ? (row.salePrice / (1 + vatRate / 100)) * .01 : 0;
    const netProfit = row.salePrice - row.sellerDiscount - commission - serviceFeeFixed - withholding - Number(cost.total_cost || 0);
    const margin = Number(cost.cogs || 0) > 0 ? (netProfit / Number(cost.cogs)) * 100 : null;
    const maximumAdCost = Math.max(0, netProfit);

    return {
      hasCost: Number(cost.cogs || 0) > 0,
      netProfit,
      margin,
      commissionRate,
      maximumAdCost,
      breakEvenRoas: maximumAdCost > 0 ? row.salePrice / maximumAdCost : null,
    };
  }

  function render(parsed, calculation) {
    const host = document.createElement('div');
    host.dataset.zolmSellerProfit = '1';
    host.style.cssText = 'margin-top:8px;min-width:210px;font-family:Inter,system-ui,sans-serif;';
    const shadow = host.attachShadow({ mode: 'open' });
    const box = document.createElement('div');
    box.style.cssText = 'border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;padding:8px;color:#0f172a;font-size:11px;line-height:1.35;';
    if (calculation?.hasCost && calculation.margin !== null) {
      if (calculation.margin < marginLow) box.style.cssText += 'border-color:#fecdd3;background:#fff1f2;';
      else if (calculation.margin >= marginHigh) box.style.cssText += 'border-color:#bbf7d0;background:#f0fdf4;';
      else box.style.cssText += 'border-color:#fed7aa;background:#fff7ed;';
    }
    const head = document.createElement('div');
    head.style.cssText = 'display:flex;justify-content:space-between;gap:8px;font-weight:800;';
    const title = document.createElement('span');
    title.textContent = pageKind() === 'ads' ? 'ZOLM reklam ekonomisi' : 'ZOLM tahmini kâr';
    const badge = document.createElement('span');
    badge.textContent = !calculation ? 'Eşleşme yok' : (!calculation.hasCost ? 'Maliyet eksik' : 'Tahmini');
    badge.style.color = !calculation || !calculation.hasCost ? '#c2410c' : '#047857';
    head.append(title, badge);
    box.append(head);

    const detail = document.createElement('div');
    detail.style.cssText = 'margin-top:5px;color:#475569;';
    if (!calculation) {
      detail.textContent = 'Barkod/model kodu ZOLM ürünleriyle eşleşmedi.';
    } else if (!calculation.hasCost) {
      detail.textContent = 'Ürün bulundu; güvenilir kâr için alış maliyetini tamamlayın.';
    } else if (pageKind() === 'ads') {
      detail.textContent = `Maks. reklam ${money(calculation.maximumAdCost)} / sipariş · Başa baş ROAS ${calculation.breakEvenRoas ? calculation.breakEvenRoas.toFixed(2) + 'x' : '-'}`;
    } else {
      detail.textContent = `${money(calculation.netProfit)} net · %${calculation.margin.toFixed(1)} maliyet marjı`;
    }
    box.append(detail);

    if (calculation && parsed.sellerDiscount > 0) {
      const note = document.createElement('div');
      note.style.cssText = 'margin-top:4px;color:#9a3412;font-size:10px;';
      note.textContent = `${money(parsed.sellerDiscount)} satıcı indirimi görünür tutardan ayrıca düşüldü.`;
      box.append(note);
    }
    const trust = document.createElement('div');
    trust.style.cssText = 'margin-top:5px;border-top:1px solid #e2e8f0;padding-top:4px;color:#64748b;font-size:9px;';
    trust.textContent = 'Sipariş/finans snapshot’ı oluşana kadar sonuç tahmindir.';
    box.append(trust);
    shadow.append(box);
    (parsed.row.querySelector('td:last-child, [role="gridcell"]:last-child') || parsed.row).append(host);
  }

  async function scan() {
    if (scanRunning) {
      queued = true;
      return;
    }
    scanRunning = true;
    try {
      const rows = findRows().map(parseRow).filter((row) => row.barcode || row.modelCode || row.stockCode);
      if (rows.length === 0) return;
      const products = await lookup(rows);
      rows.forEach((row) => render(row, calculate(row, costFor(row, products))));
    } catch (error) {
      console.warn('[ZOLM Seller Profit Overlay]', error);
    } finally {
      scanRunning = false;
      if (queued) {
        queued = false;
        schedule();
      }
    }
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(scan, 700);
  }

  chrome.storage.sync.get({ marginLow: 5, marginHigh: 20, serviceFeeFixed: 9.33, withholdingTaxEnabled: false }).then((settings) => {
    marginLow = Number(settings.marginLow || 5);
    marginHigh = Number(settings.marginHigh || 20);
    serviceFeeFixed = Math.max(0, Number(settings.serviceFeeFixed || 0));
    withholdingTaxEnabled = Boolean(settings.withholdingTaxEnabled);
    schedule();
  });
  chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'sync') return;
    if (changes.marginLow) marginLow = Number(changes.marginLow.newValue || 5);
    if (changes.marginHigh) marginHigh = Number(changes.marginHigh.newValue || 20);
    if (changes.serviceFeeFixed) serviceFeeFixed = Math.max(0, Number(changes.serviceFeeFixed.newValue || 0));
    if (changes.withholdingTaxEnabled) withholdingTaxEnabled = Boolean(changes.withholdingTaxEnabled.newValue);
    document.querySelectorAll('[data-zolm-seller-profit]').forEach((node) => node.remove());
    schedule();
  });
  new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
  setInterval(schedule, 5000);
})();
