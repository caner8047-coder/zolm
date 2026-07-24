/**
 * ZOLM Booster — Trendyol Sipariş Kârlılığı
 *
 * Sipariş satırlarını ZOLM finans snapshot'larıyla eşleştirir. Snapshot henüz
 * oluşmadıysa görünür fatura tutarı ve ürün kartı maliyetleriyle anlık tahmin yapar.
 */
(function () {
  'use strict';

  const SCRIPT_ID = 'zolm-orders-panel';
  const VERSION = chrome.runtime.getManifest().version;
  const DEBOUNCE_MS = 600;
  const SCAN_INTERVAL_MS = 12000;
  const MAX_BATCH_SIZE = 100;

  if (window[SCRIPT_ID] === VERSION) return;
  window[SCRIPT_ID] = VERSION;

  function isOrdersPage() {
    return /\/orders\/shipment-packages\//i.test(location.pathname);
  }

  if (!isOrdersPage()) return;

  let marginLow = 5;
  let marginHigh = 20;
  let serviceFeeFixed = 9.33;
  let withholdingTaxEnabled = true;
  let scanInProgress = false;
  let scanQueued = false;
  let debounceTimer = null;

  function clean(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function parseNumber(value) {
    let normalized = String(value || '')
      .replace(/\s|₺|TL/gi, '')
      .trim();

    if (!normalized) return 0;

    const lastComma = normalized.lastIndexOf(',');
    const lastDot = normalized.lastIndexOf('.');

    if (lastComma > lastDot) {
      normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (lastDot > lastComma && lastComma >= 0) {
      normalized = normalized.replace(/,/g, '');
    } else if (lastComma >= 0) {
      normalized = normalized.replace(',', '.');
    } else if (lastDot >= 0) {
      const groups = normalized.split('.');
      if (groups.length > 1 && groups.slice(1).every((group) => group.length === 3)) {
        normalized = groups.join('');
      }
    }

    const parsed = Number.parseFloat(normalized.replace(/[^0-9.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number(value || 0));
  }

  function formatPercent(value) {
    return Number(value || 0).toFixed(1).replace('.', ',');
  }

  async function loadSettings() {
    const stored = await chrome.storage.sync.get({
      marginLow: 5,
      marginHigh: 20,
      serviceFeeFixed: 9.33,
      withholdingTaxEnabled: true,
    });

    marginLow = Number.parseFloat(stored.marginLow) || 5;
    marginHigh = Number.parseFloat(stored.marginHigh) || 20;
    serviceFeeFixed = Math.max(0, Number.parseFloat(stored.serviceFeeFixed) || 0);
    withholdingTaxEnabled = Boolean(stored.withholdingTaxEnabled);
  }

  function rowCells(row) {
    return Array.from(row.querySelectorAll('td, [role="gridcell"]'))
      .filter((cell) => cell.closest('tr, [role="row"]') === row);
  }

  function findOrderRows() {
    const selectors = [
      '[data-testid*="order"] tbody tr',
      'table tbody tr',
      '[role="row"]',
    ];

    for (const selector of selectors) {
      const rows = Array.from(document.querySelectorAll(selector));
      const orders = rows.filter((row) => {
        const text = clean(row.innerText || row.textContent || '');
        return /#\s*\d{6,}/.test(text) && /Barkod\s*:/i.test(text);
      });

      if (orders.length > 0) return orders;
    }

    return [];
  }

  function uniqueMatches(text, pattern) {
    return [...text.matchAll(pattern)]
      .map((match) => clean(match.slice(1).find((value) => value != null && value !== '') || ''))
      .filter((value, index, values) => value && values.indexOf(value) === index);
  }

  function moneyAfterLabel(text, label) {
    const match = text.match(new RegExp(label + '\\s*:?\\s*₺?\\s*([\\d.,]+)', 'i'));
    return parseNumber(match?.[1] || 0);
  }

  function findInvoiceCell(cells) {
    return cells.find((cell) => {
      const text = clean(cell.innerText || cell.textContent || '');
      return /Satış\s+Tutarı|Faturalanacak\s+Tutar/i.test(text);
    }) || null;
  }

  function findUnitPrice(cells, productCell, invoiceCell) {
    const productIndex = cells.indexOf(productCell);
    const invoiceIndex = cells.indexOf(invoiceCell);
    const candidates = cells.slice(Math.max(0, productIndex + 1), invoiceIndex > 0 ? invoiceIndex : undefined);

    for (const cell of candidates) {
      const text = clean(cell.innerText || cell.textContent || '');
      if (/Kargo|Fatura|Satış\s+Tutarı/i.test(text)) continue;
      const values = uniqueMatches(text, /₺\s*([\d.,]+)|([\d.,]+)\s*₺/gi)
        .map(parseNumber)
        .filter((value) => value > 0);
      if (values.length > 0) return values[values.length - 1];
    }

    return 0;
  }

  function parseOrderRow(row) {
    const cells = rowCells(row);
    const text = clean(row.innerText || row.textContent || '');
    const orderMatch = text.match(/#\s*(\d{6,})/);
    const productCell = cells.find((cell) => /Barkod\s*:/i.test(clean(cell.innerText || cell.textContent || ''))) || null;
    const productText = clean(productCell?.innerText || productCell?.textContent || '');
    const invoiceCell = findInvoiceCell(cells);
    const invoiceText = clean(invoiceCell?.innerText || invoiceCell?.textContent || '');
    const barcodes = uniqueMatches(productText, /Barkod\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/gi);
    const modelCodes = uniqueMatches(productText, /(?:Stok|Model)\s+Kod[u:]?\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/gi);
    const salesAmount = moneyAfterLabel(invoiceText, 'Satış\\s+Tutarı');
    const billableAmount = moneyAfterLabel(invoiceText, 'Faturalanacak\\s+Tutar');
    const revenue = billableAmount > 0 ? billableAmount : salesAmount;
    const unitPrice = findUnitPrice(cells, productCell, invoiceCell);
    const inferredQuantity = barcodes.length === 1 && unitPrice > 0 && salesAmount > 0
      ? Math.max(1, Math.min(100, Math.round(salesAmount / unitPrice)))
      : 1;

    const lineShare = revenue / Math.max(1, barcodes.length);
    const items = barcodes.map((barcode, index) => ({
      barcode,
      model_code: modelCodes[index] || modelCodes[0] || '',
      quantity: barcodes.length === 1 ? inferredQuantity : 1,
      line_amount: Math.round(lineShare * 100) / 100,
    }));

    return {
      element: row,
      invoiceCell,
      orderNumber: orderMatch?.[1] || '',
      revenue: Math.round(revenue * 100) / 100,
      items,
    };
  }

  function cardTone(result) {
    const profit = Number(result?.profit || 0);
    const margin = Number(result?.margin_percent || 0);
    if (profit < 0 || margin < marginLow) return 'danger';
    if (margin < marginHigh) return 'warning';
    return 'success';
  }

  function resultLabel(result) {
    if (result?.state === 'confirmed') return 'Kesinleşmiş Kâr';
    if (result?.source === 'snapshot') return 'ZOLM Tahmini';
    return 'Anlık Tahmin';
  }

  function cardStyles() {
    return `
      :host { all:initial; display:block; color:#0f172a; font-family:Arial,sans-serif; }
      * { box-sizing:border-box; }
      .card { position:relative; width:100%; margin-top:7px; padding:7px 8px; border:1px solid; border-radius:6px; font-size:11px; line-height:1.25; cursor:help; }
      .card.success { color:#166534; border-color:#86efac; background:#f0fdf4; }
      .card.warning { color:#92400e; border-color:#fcd34d; background:#fffbeb; }
      .card.danger { color:#991b1b; border-color:#fca5a5; background:#fef2f2; }
      .card.neutral { color:#475569; border-color:#cbd5e1; background:#f8fafc; cursor:default; }
      .head, .metric, .tooltip-row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
      .head { margin-bottom:4px; font-size:9px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
      .metric { font-weight:700; }
      .profit { font-size:13px; }
      .margin { padding:1px 4px; border-radius:4px; background:rgba(255,255,255,.72); }
      .note { margin-top:4px; padding-top:4px; border-top:1px dashed currentColor; font-size:9px; opacity:.8; }
      .tooltip { visibility:hidden; opacity:0; position:absolute; z-index:2147483647; right:0; bottom:calc(100% + 7px); width:230px; padding:9px 10px; border-radius:7px; color:#f8fafc; background:#0f172a; box-shadow:0 8px 24px rgba(15,23,42,.28); transition:opacity .15s ease; }
      .card:hover .tooltip { visibility:visible; opacity:1; }
      .tooltip-title { margin-bottom:5px; padding-bottom:4px; border-bottom:1px solid rgba(255,255,255,.15); font-weight:700; }
      .tooltip-row { margin-top:3px; }
      .tooltip-row.total { margin-top:6px; padding-top:5px; border-top:1px dashed rgba(255,255,255,.2); color:#38bdf8; font-weight:700; }
    `;
  }

  function renderResult(item, result) {
    if (!item.invoiceCell) return;

    const existing = item.element.querySelector(`.zolm-order-profit-host[data-order-number="${CSS.escape(item.orderNumber)}"]`);
    if (existing) existing.remove();
    item.element.querySelectorAll('.zolm-order-profit-host').forEach((host) => host.remove());

    const host = document.createElement('div');
    host.className = 'zolm-order-profit-host';
    host.dataset.orderNumber = item.orderNumber;
    host.style.cssText = 'position:relative;display:block;width:100%;min-width:0;margin-top:7px;box-sizing:border-box;z-index:10;';
    const shadow = host.attachShadow({ mode: 'open' });

    if (!result || result.state === 'missing_product') {
      const identifiers = result?.missing_identifiers?.join(', ');
      shadow.innerHTML = `<style>${cardStyles()}</style><div class="card neutral"><strong>ZOLM eşleşme yok</strong>${identifiers ? `<div class="note">${identifiers}</div>` : ''}</div>`;
    } else if (result.state === 'missing_cost') {
      shadow.innerHTML = `<style>${cardStyles()}</style><div class="card neutral"><strong>Maliyet tanımsız</strong><div class="note">Ürün kartındaki maliyetleri tamamlayın.</div></div>`;
    } else {
      const tone = cardTone(result);
      const isLiveEstimate = result.source === 'live_estimate';
      const note = isLiveEstimate
        ? 'Finans ve gerçek kargo kesintileri senkron sonrası kesinleşir.'
        : result.calculated_at ? `ZOLM snapshot: ${new Date(result.calculated_at).toLocaleString('tr-TR')}` : '';

      shadow.innerHTML = `
        <style>${cardStyles()}</style>
        <div class="card ${tone}">
          <div class="head"><span>${resultLabel(result)}</span><span>#${item.orderNumber.slice(-5)}</span></div>
          <div class="metric"><span class="profit">${formatMoney(result.profit)}</span><span class="margin">%${formatPercent(result.margin_percent)}</span></div>
          ${note ? `<div class="note">${note}</div>` : ''}
          <div class="tooltip">
            <div class="tooltip-title">Sipariş Kârlılık Detayı</div>
            <div class="tooltip-row"><span>Gelir:</span><span>${formatMoney(result.gross_revenue)}</span></div>
            <div class="tooltip-row"><span>Komisyon:</span><span>-${formatMoney(result.commission_total)}</span></div>
            ${Number(result.withholding_total || 0) > 0 ? `<div class="tooltip-row"><span>%1 stopaj:</span><span>-${formatMoney(result.withholding_total)}</span></div>` : ''}
            ${Number(result.marketplace_cargo_total || 0) > 0 ? `<div class="tooltip-row"><span>Pazaryeri kargo:</span><span>-${formatMoney(result.marketplace_cargo_total)}</span></div>` : ''}
            ${Number(result.service_fee_total || 0) > 0 ? `<div class="tooltip-row"><span>Hizmet bedeli:</span><span>-${formatMoney(result.service_fee_total)}</span></div>` : ''}
            <div class="tooltip-row"><span>Ürün maliyeti:</span><span>-${formatMoney(result.cogs_cost)}</span></div>
            ${Number(result.packaging_cost || 0) > 0 ? `<div class="tooltip-row"><span>Ambalaj:</span><span>-${formatMoney(result.packaging_cost)}</span></div>` : ''}
            ${Number(result.own_cargo_cost || 0) > 0 ? `<div class="tooltip-row"><span>Tanımlı kargo:</span><span>-${formatMoney(result.own_cargo_cost)}</span></div>` : ''}
            <div class="tooltip-row total"><span>Net kâr:</span><span>${formatMoney(result.profit)}</span></div>
          </div>
        </div>
      `;
    }

    const container = item.invoiceCell.firstElementChild?.tagName === 'DIV'
      ? item.invoiceCell.firstElementChild
      : item.invoiceCell;
    container.appendChild(host);
  }

  function lookupOrders(orders) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({
        type: 'ZOLM_ORDER_PROFIT_LOOKUP',
        payload: {
          orders,
          service_fee_fixed: serviceFeeFixed,
          withholding_tax_enabled: withholdingTaxEnabled,
        },
      }, (response) => {
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message));
          return;
        }
        resolve(response);
      });
    });
  }

  async function performScan() {
    if (!isOrdersPage()) return;

    const parsedRows = findOrderRows()
      .map(parseOrderRow)
      .filter((item) => item.orderNumber && item.revenue > 0 && item.items.length > 0)
      .slice(0, MAX_BATCH_SIZE);

    if (parsedRows.length === 0) return;

    const response = await lookupOrders(parsedRows.map((item) => ({
      order_number: item.orderNumber,
      revenue: item.revenue,
      items: item.items,
    })));

    if (!response?.ok || !response.orders) {
      throw new Error(response?.message || 'Sipariş kârlılığı alınamadı.');
    }

    for (const item of parsedRows) {
      const live = parseOrderRow(item.element);
      if (live.orderNumber !== item.orderNumber) {
        scanQueued = true;
        continue;
      }
      renderResult(item, response.orders[item.orderNumber]);
    }
  }

  async function scan() {
    if (scanInProgress) {
      scanQueued = true;
      return;
    }

    scanInProgress = true;
    try {
      await performScan();
    } catch (error) {
      console.error('[ZOLM Sipariş Kârı] Tarama hatası:', error);
    } finally {
      scanInProgress = false;
      if (scanQueued) {
        scanQueued = false;
        debouncedScan();
      }
    }
  }

  function debouncedScan() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(scan, DEBOUNCE_MS);
  }

  const observer = new MutationObserver((mutations) => {
    const onlyOwnChanges = mutations.every((mutation) => {
      if (mutation.type !== 'childList') return false;
      const nodes = [...mutation.addedNodes, ...mutation.removedNodes];
      return nodes.length > 0 && nodes.every((node) =>
        node.nodeType === Node.ELEMENT_NODE
          && node.classList?.contains('zolm-order-profit-host'));
    });

    if (!onlyOwnChanges) debouncedScan();
  });

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type === 'ZOLM_SETTINGS_CHANGED') {
      loadSettings().then(debouncedScan);
      return false;
    }

    if (message?.type === 'ZOLM_BOOSTER_PAGE_STATUS') {
      sendResponse({
        ok: isOrdersPage(),
        context: 'orders',
        summary: isOrdersPage() ? 'Trendyol sipariş kârlılığı aktif' : 'Trendyol siparişleri',
        payload: {
          page_type: 'orders',
          order_count: findOrderRows().length,
          url: location.href,
        },
      });
      return false;
    }

    return false;
  });

  loadSettings().then(() => {
    observer.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true,
      characterData: true,
    });
    debouncedScan();
    setInterval(debouncedScan, SCAN_INTERVAL_MS);
  });
})();
