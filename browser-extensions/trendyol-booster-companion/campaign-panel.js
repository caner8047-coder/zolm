/**
 * ZOLM Booster — Trendyol Kampanya Karlılık Kartları
 *
 * Kampanyaya ürün ekleme ve eklenen ürünler sayfalarında mevcut fiyat ile
 * kampanya fiyatını ZOLM maliyetleri üzerinden karşılaştırır.
 */
(function () {
  'use strict';

  const SCRIPT_ID = 'zolm-campaign-panel';
  const VERSION = chrome.runtime.getManifest().version;
  const DEBOUNCE_MS = 500;
  const SCAN_INTERVAL_MS = 4000;
  const MAX_BATCH_SIZE = 80;

  if (window[SCRIPT_ID] === VERSION) return;
  window[SCRIPT_ID] = VERSION;

  function isCampaignPage() {
    return /\/promotions\/campaigns\/details\/[^/]+\/(?:add-new-products|campaign-products)/i.test(location.pathname);
  }

  if (!isCampaignPage()) return;

  const costCache = new Map();
  let marginLow = 5.0;
  let marginHigh = 20.0;
  let serviceFeeFixed = 9.33;
  let withholdingTaxEnabled = false;
  let scanInProgress = false;
  let scanQueued = false;
  let debounceTimer = null;

  function clean(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function normalizeIdentifier(value) {
    return String(value || '')
      .replace(/[İı]/g, 'I')
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim()
      .toUpperCase();
  }

  function modelCacheKey(modelCode) {
    const normalized = normalizeIdentifier(modelCode);
    return normalized ? 'mc:' + normalized : '';
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

    const parsed = parseFloat(normalized.replace(/[^0-9.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value || 0);
  }

  function formatPercent(value) {
    return Number(value || 0).toFixed(1).replace('.', ',');
  }

  function formatRate(value) {
    return new Intl.NumberFormat('tr-TR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(Number(value || 0));
  }

  async function loadSettings() {
    const stored = await chrome.storage.sync.get({
      marginLow: 5.0,
      marginHigh: 20.0,
      serviceFeeFixed: 9.33,
      withholdingTaxEnabled: false,
    });

    marginLow = Number.parseFloat(stored.marginLow) || 5.0;
    marginHigh = Number.parseFloat(stored.marginHigh) || 20.0;
    serviceFeeFixed = Math.max(0, Number.parseFloat(stored.serviceFeeFixed) || 0);
    withholdingTaxEnabled = Boolean(stored.withholdingTaxEnabled);
  }

  function findProductRows() {
    const selectors = [
      '[data-testid="available-products-table"] tbody tr',
      '[data-testid="campaign-table"] tbody tr',
      'table tbody tr',
      '[role="row"]',
    ];

    for (const selector of selectors) {
      const rows = Array.from(document.querySelectorAll(selector));
      const products = rows.filter((row) => {
        const text = clean(row.innerText || row.textContent || '');
        return /Barkod\s*:/i.test(text) && /Model\s+Kod[u:]?\s*:/i.test(text);
      });

      if (products.length > 0) return products;
    }

    return [];
  }

  function rowCells(row) {
    return Array.from(row.querySelectorAll('td, [role="gridcell"]'))
      .filter((cell) => cell.closest('tr, [role="row"]') === row);
  }

  function parseProductRow(row) {
    const text = clean(row.innerText || row.textContent || '');
    const barcodeMatch = text.match(/Barkod\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/i);
    const modelMatch = text.match(/Model\s+Kod[u:]?\s*:\s*([A-ZÇĞİÖŞÜ0-9_.-]+)/i);

    return {
      element: row,
      barcode: barcodeMatch ? barcodeMatch[1].trim() : '',
      modelCode: modelMatch ? modelMatch[1].trim() : '',
    };
  }

  function parseCampaignRules() {
    const text = clean(document.body?.innerText || '');
    const detailMatch = text.match(/([\d.,]+)\s*TL\s*['’]?\s*(?:ye|ya)\s+([\d.,]+)\s*TL\s+İndirim/i);
    const titleMatch = text.match(/([\d.,]+)\s*TL\s+Üzeri\s+([\d.,]+)\s*TL\s+İndirim/i);
    const discountMatch = detailMatch || titleMatch;
    const coverageMatch = text.match(/%\s*([\d.,]+)\s*Trendyol\s+Karşılamalı/i);

    if (!discountMatch) {
      return {
        valid: false,
        thresholdAmount: 0,
        discountAmount: 0,
        trendyolCoveragePercent: 0,
        sellerSharePercent: 0,
      };
    }

    const trendyolCoveragePercent = Math.min(100, Math.max(0, parseNumber(coverageMatch?.[1] || 0)));

    return {
      valid: true,
      thresholdAmount: parseNumber(discountMatch[1]),
      discountAmount: parseNumber(discountMatch[2]),
      trendyolCoveragePercent,
      sellerSharePercent: 100 - trendyolCoveragePercent,
    };
  }

  function findCommission(cells) {
    for (const cell of cells) {
      const text = clean(cell.innerText || cell.textContent || '');
      const matches = Array.from(text.matchAll(/%\s*([\d.,]+)/g));
      if (matches.length === 0) continue;

      const value = parseNumber(matches[0][1]);
      if (value > 0 && value <= 100) return value;
    }

    return 0;
  }

  function findCurrentPriceCell(cells) {
    return cells.find((cell) => /Müşterinin\s+Gördüğü\s+Fiyat/i.test(clean(cell.innerText || cell.textContent || '')))
      || null;
  }

  function parseCurrentPrice(cell) {
    if (!cell) return 0;
    const text = clean(cell.innerText || cell.textContent || '');
    const match = text.match(/Müşterinin\s+Gördüğü\s+Fiyat\s*([\d.,]+)/i);
    return parseNumber(match?.[1] || 0);
  }

  function findCampaignPriceCell(cells) {
    return cells.find((cell) => {
      const text = clean(cell.innerText || cell.textContent || '');
      return /Maksimum\s+Tutar|Fiyat\s+Güncellendi|Uygula/i.test(text);
    }) || null;
  }

  function parseCampaignPrice(cell) {
    if (!cell) return { enteredPrice: 0, maximumPrice: 0 };

    const input = cell.querySelector('input, bl-input, [role="textbox"], [role="spinbutton"]');
    const enteredPrice = parseNumber(input?.value || input?.getAttribute('value') || '');
    const text = clean(cell.innerText || cell.textContent || '');
    const maxMatch = text.match(/Maksimum\s+Tutar\s*:\s*([\d.,]+)/i);
    const maximumPrice = parseNumber(maxMatch?.[1] || 0);

    return {
      enteredPrice,
      maximumPrice,
    };
  }

  function campaignBasePrice(currentPrice, priceOptions) {
    return Math.min(...[
      currentPrice,
      priceOptions.maximumPrice,
      priceOptions.enteredPrice,
    ].filter((price) => Number.isFinite(price) && price > 0));
  }

  function calculateSellerDiscount(price, rules) {
    if (!rules.valid || price <= 0 || rules.thresholdAmount <= 0 || rules.discountAmount <= 0) {
      return 0;
    }

    const shareBase = Math.min(price, rules.thresholdAmount) / rules.thresholdAmount;
    return Math.round(rules.discountAmount * (rules.sellerSharePercent / 100) * shareBase * 100) / 100;
  }

  function calculateScenario(price, commissionRate, costData, sellerDiscount = 0) {
    if (!costData || price <= 0) return null;

    const cogs = parseNumber(costData.cogs);
    const totalCost = parseNumber(costData.total_cost);
    const effectiveCommission = commissionRate > 0
      ? commissionRate
      : parseNumber(costData.commission_rate);
    const commissionAmount = price * (effectiveCommission / 100);
    const vatRate = parseNumber(costData.vat_rate);
    const withholdingGrossBase = Math.max(0, price - sellerDiscount);
    const withholdingBase = vatRate > 0
      ? withholdingGrossBase / (1 + (vatRate / 100))
      : withholdingGrossBase;
    const withholdingTax = withholdingTaxEnabled ? withholdingBase * 0.01 : 0;
    const serviceFee = Math.max(0, serviceFeeFixed);
    const roundedCommissionAmount = Math.round(commissionAmount * 100) / 100;
    const roundedWithholdingTax = Math.round(withholdingTax * 100) / 100;
    const netProfit = price - roundedCommissionAmount - serviceFee - roundedWithholdingTax - sellerDiscount - totalCost;
    const profitMargin = cogs > 0 ? (netProfit / cogs) * 100 : 0;

    return {
      hasCost: cogs > 0,
      price,
      commissionRate: effectiveCommission,
      commissionAmount: roundedCommissionAmount,
      serviceFee: Math.round(serviceFee * 100) / 100,
      sellerDiscount: Math.round(sellerDiscount * 100) / 100,
      vatRate,
      withholdingBase: Math.round(withholdingBase * 100) / 100,
      withholdingTax: roundedWithholdingTax,
      totalCost: Math.round(totalCost * 100) / 100,
      cogs,
      cargoCost: parseNumber(costData.cargo_cost),
      packagingCost: parseNumber(costData.packaging_cost),
      netProfit: Math.round(netProfit * 100) / 100,
      profitMargin: Math.round(profitMargin * 10) / 10,
    };
  }

  function statusClass(scenario) {
    if (!scenario || !scenario.hasCost) return 'no-cost';
    if (scenario.profitMargin < marginLow) return 'loss';
    if (scenario.profitMargin >= marginHigh) return 'profit';
    return 'breakeven';
  }

  function badgeStyles() {
    return `
      :host { display:block; width:100%; box-sizing:border-box; }
      .badge { display:flex; flex-direction:column; gap:4px; padding:6px 8px; border:1px solid; border-radius:6px; box-sizing:border-box; font:11px/1.3 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; text-align:left; }
      .badge.profit { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
      .badge.loss { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
      .badge.breakeven { background:#fffbeb; border-color:#fde68a; color:#92400e; }
      .badge.no-cost,.badge.no-match { background:#f8fafc; border-color:#cbd5e1; color:#64748b; }
      .main { display:flex; align-items:center; justify-content:space-between; gap:6px; font-weight:700; }
      .scenario { font-size:9px; text-transform:uppercase; letter-spacing:.03em; opacity:.75; }
      .margin { padding:1px 4px; border-radius:4px; background:rgba(255,255,255,.65); white-space:nowrap; }
      .profit-value { white-space:nowrap; }
      .detail { display:flex; justify-content:space-between; gap:6px; padding-top:3px; border-top:1px dashed rgba(15,23,42,.12); font-size:9.5px; }
      .tooltip-trigger { position:relative; cursor:help; border-bottom:1px dashed currentColor; }
      .tooltip { visibility:hidden; opacity:0; position:absolute; z-index:99999; bottom:130%; left:50%; transform:translateX(-50%); width:190px; padding:8px 10px; border-radius:6px; background:#0f172a; color:#f8fafc; box-shadow:0 4px 14px rgba(0,0,0,.2); transition:opacity .15s ease; }
      .tooltip-trigger:hover .tooltip { visibility:visible; opacity:1; }
      .tooltip-title { margin-bottom:5px; padding-bottom:3px; border-bottom:1px solid rgba(255,255,255,.15); font-weight:700; }
      .tooltip-row { display:flex; justify-content:space-between; gap:8px; margin-top:2px; }
      .tooltip-row.total { margin-top:5px; padding-top:4px; border-top:1px dashed rgba(255,255,255,.15); color:#38bdf8; font-weight:700; }
    `;
  }

  function renderBadge(cell, scenario, costData, label, rules) {
    if (!cell) return;

    const productKey = String(costData?.mp_product_id || costData?.barcode || costData?.model_code || 'no-match');
    const renderKey = [
      productKey,
      label,
      scenario?.price || 0,
      scenario?.commissionRate || 0,
      scenario?.serviceFee || 0,
      scenario?.sellerDiscount || 0,
      scenario?.totalCost || 0,
      scenario?.netProfit || 0,
      scenario?.customerPrice || 0,
      scenario?.maximumPrice || 0,
      scenario?.enteredPrice || 0,
    ].join('|');
    const existing = cell.querySelector(`.zolm-campaign-profit-host[data-scenario="${label}"]`);
    if (existing?.dataset.renderKey === renderKey) return;
    if (existing) existing.remove();

    const host = document.createElement('div');
    host.className = 'zolm-campaign-profit-host';
    host.dataset.productKey = productKey;
    host.dataset.scenario = label;
    host.dataset.renderKey = renderKey;
    host.style.cssText = 'position:relative;display:block;width:100%;margin-top:7px;box-sizing:border-box;z-index:10;';
    const shadow = host.attachShadow({ mode: 'open' });

    if (!costData) {
      shadow.innerHTML = `<style>${badgeStyles()}</style><div class="badge no-match">ZOLM eşleşme yok</div>`;
    } else if (!scenario?.hasCost) {
      shadow.innerHTML = `<style>${badgeStyles()}</style><div class="badge no-cost">Maliyet tanımsız</div>`;
    } else {
      const state = statusClass(scenario);
      const discountRow = scenario.sellerDiscount > 0
        ? `<div class="tooltip-row"><span>Satıcı indirimi:</span><span>-${formatMoney(scenario.sellerDiscount)}</span></div>`
        : '';
      const coverageRow = label !== 'Mevcut' && rules?.valid
        ? `<div class="tooltip-row"><span>Trendyol katkısı:</span><span>%${formatPercent(rules.trendyolCoveragePercent)}</span></div>`
        : '';
      const priceBasisRows = label === 'Kampanya'
        ? `
          <div class="tooltip-row"><span>Müşteri fiyatı:</span><span>${formatMoney(scenario.customerPrice)}</span></div>
          <div class="tooltip-row"><span>Maksimum tutar:</span><span>${formatMoney(scenario.maximumPrice)}</span></div>
          ${scenario.enteredPrice > 0 ? `<div class="tooltip-row"><span>Girilen fiyat:</span><span>${formatMoney(scenario.enteredPrice)}</span></div>` : ''}
        `
        : '';

      shadow.innerHTML = `
        <style>${badgeStyles()}</style>
        <div class="badge ${state}">
          <div class="scenario">${label} fiyat</div>
          <div class="main">
            <span class="margin">${scenario.profitMargin > 0 ? '+' : ''}${formatPercent(scenario.profitMargin)}</span>
            <span class="profit-value">${formatMoney(scenario.netProfit)}</span>
          </div>
          <div class="detail">
            <span class="tooltip-trigger">Maliyet: ${formatMoney(scenario.totalCost)}
              <span class="tooltip">
                <span class="tooltip-title">Hesaplama Detayları</span>
                <span class="tooltip-row"><span>Senaryo fiyatı:</span><span>${formatMoney(scenario.price)}</span></span>
                ${priceBasisRows}
                <span class="tooltip-row"><span>Komisyon (%${formatRate(scenario.commissionRate)}):</span><span>-${formatMoney(scenario.commissionAmount)}</span></span>
                <span class="tooltip-row"><span>Platform hizmet bedeli:</span><span>-${formatMoney(scenario.serviceFee)}</span></span>
                ${discountRow}
                ${coverageRow}
                ${withholdingTaxEnabled ? `<span class="tooltip-row"><span>Stopaj matrahı (KDV hariç):</span><span>${formatMoney(scenario.withholdingBase)}</span></span><span class="tooltip-row"><span>%1 stopaj:</span><span>-${formatMoney(scenario.withholdingTax)}</span></span>` : ''}
                <span class="tooltip-row"><span>Ürün:</span><span>${formatMoney(scenario.cogs)}</span></span>
                <span class="tooltip-row"><span>Kargo:</span><span>${formatMoney(scenario.cargoCost)}</span></span>
                <span class="tooltip-row"><span>Ambalaj:</span><span>${formatMoney(scenario.packagingCost)}</span></span>
                <span class="tooltip-row total"><span>Net kâr:</span><span>${formatMoney(scenario.netProfit)}</span></span>
              </span>
            </span>
          </div>
        </div>
      `;
    }

    const container = cell.firstElementChild?.tagName === 'DIV' ? cell.firstElementChild : cell;
    container.appendChild(host);
  }

  function removeForeignBadges(row, costData) {
    const expected = String(costData?.mp_product_id || costData?.barcode || costData?.model_code || 'no-match');
    row.querySelectorAll('.zolm-campaign-profit-host').forEach((host) => {
      if (host.dataset.productKey !== expected) host.remove();
    });
  }

  function removeScenarioBadge(cell, label) {
    cell?.querySelector(`.zolm-campaign-profit-host[data-scenario="${label}"]`)?.remove();
  }

  function lookupCosts(barcodes, modelCodes) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({
        type: 'ZOLM_PRICING_COST_LOOKUP',
        barcodes,
        model_codes: modelCodes,
      }, (response) => {
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message));
          return;
        }
        resolve(response);
      });
    });
  }

  function resolveCostData(item) {
    if (item.barcode && costCache.has(item.barcode)) {
      return costCache.get(item.barcode) || undefined;
    }

    const modelKey = modelCacheKey(item.modelCode);
    return modelKey ? (costCache.get(modelKey) || undefined) : undefined;
  }

  async function fetchMissingCosts(items) {
    const barcodes = [];
    const modelCodes = [];

    for (const item of items) {
      if (item.barcode && !costCache.has(item.barcode)) barcodes.push(item.barcode);
      const key = modelCacheKey(item.modelCode);
      if (item.modelCode && key && !costCache.has(key)) modelCodes.push(item.modelCode);
    }

    const uniqueBarcodes = [...new Set(barcodes)].slice(0, MAX_BATCH_SIZE);
    const uniqueModelCodes = [...new Set(modelCodes)].slice(0, MAX_BATCH_SIZE);
    if (uniqueBarcodes.length === 0 && uniqueModelCodes.length === 0) return;

    const response = await lookupCosts(uniqueBarcodes, uniqueModelCodes);
    if (!response?.ok || !response.products) {
      throw new Error(response?.message || 'ZOLM maliyet sorgusu başarısız.');
    }

    for (const [key, value] of Object.entries(response.products)) {
      if (key.startsWith('mc:')) {
        costCache.set(modelCacheKey(key.slice(3)), value);
      } else {
        costCache.set(key, value);
      }
    }

    for (const barcode of uniqueBarcodes) {
      if (!costCache.has(barcode)) costCache.set(barcode, null);
    }
    for (const modelCode of uniqueModelCodes) {
      const key = modelCacheKey(modelCode);
      if (key && !costCache.has(key)) costCache.set(key, null);
    }
  }

  async function performScan() {
    if (!isCampaignPage()) return;

    const parsedRows = findProductRows().map(parseProductRow)
      .filter((item) => item.barcode || item.modelCode);
    if (parsedRows.length === 0) return;

    await fetchMissingCosts(parsedRows);
    const rules = parseCampaignRules();

    for (const item of parsedRows) {
      const live = parseProductRow(item.element);
      if (live.barcode !== item.barcode || live.modelCode !== item.modelCode) {
        scanQueued = true;
        continue;
      }

      const costData = resolveCostData(item);
      removeForeignBadges(item.element, costData);
      const cells = rowCells(item.element);
      const commissionRate = findCommission(cells);
      const currentCell = findCurrentPriceCell(cells);
      const currentPrice = parseCurrentPrice(currentCell);
      const campaignCell = findCampaignPriceCell(cells);
      const campaignPrice = parseCampaignPrice(campaignCell);
      const basePrice = campaignBasePrice(currentPrice, campaignPrice);

      if (currentCell && Number.isFinite(basePrice) && basePrice > 0) {
        const sellerDiscount = rules.valid ? calculateSellerDiscount(basePrice, rules) : 0;
        const scenario = costData
          ? calculateScenario(basePrice, commissionRate, costData, sellerDiscount)
          : null;
        if (scenario) {
          scenario.customerPrice = currentPrice;
          scenario.maximumPrice = campaignPrice.maximumPrice;
          scenario.enteredPrice = campaignPrice.enteredPrice;
        }
        removeScenarioBadge(currentCell, 'Mevcut');
        renderBadge(
          currentCell,
          scenario,
          costData,
          'Kampanya',
          rules,
        );
      }

      removeScenarioBadge(campaignCell, 'Kampanya');
      removeScenarioBadge(campaignCell, 'Maksimum');
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
      console.error('[ZOLM Kampanya] Tarama hatası:', error);
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
          && node.classList?.contains('zolm-campaign-profit-host'));
    });

    if (!onlyOwnChanges) debouncedScan();
  });

  document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.closest('tr, [role="row"]')) {
      debouncedScan();
    }
  }, true);
  document.addEventListener('change', debouncedScan, true);

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type === 'ZOLM_SETTINGS_CHANGED') {
      loadSettings().then(debouncedScan);
      return false;
    }

    if (message?.type === 'ZOLM_BOOSTER_PAGE_STATUS') {
      sendResponse({
        ok: isCampaignPage(),
        context: 'campaign',
        summary: isCampaignPage()
          ? 'Trendyol kampanya karlılık sayfası'
          : 'Trendyol kampanyaları',
        payload: {
          page_type: 'campaign',
          product_count: findProductRows().length,
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
