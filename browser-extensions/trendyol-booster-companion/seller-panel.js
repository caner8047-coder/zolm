/**
 * ZOLM Booster — Trendyol Seller Panel Karlılık Kartı
 *
 * partner.trendyol.com/pricing/home sayfasında çalışır.
 * Her ürün satırına ZOLM'daki maliyet verileriyle karlılık badge'i ekler.
 */
(function () {
  'use strict';

  const SCRIPT_ID = 'zolm-seller-panel';
  const VERSION = chrome.runtime.getManifest().version;
  const DEBOUNCE_MS = 800;
  const SCAN_INTERVAL_MS = 3000;
  const MAX_BATCH_SIZE = 80;

  // Tekrar yükleme koruması
  if (window[SCRIPT_ID] === VERSION) return;
  window[SCRIPT_ID] = VERSION;

  console.log('[ZOLM Seller Panel] Content script loaded v' + VERSION);

  // ─── Sayfa Algılama ─────────────────────────────────────────
  function isPricingPage() {
    return /\/pricing\//i.test(location.pathname);
  }

  if (!isPricingPage()) {
    console.log('[ZOLM Seller Panel] Bu sayfa fiyatlandırma sayfası değil, atlanıyor.');
    return;
  }

  console.log('[ZOLM Seller Panel] Fiyatlandırma sayfası algılandı, tarama başlıyor...');

  // ─── Maliyet Cache ──────────────────────────────────────────
  const costCache = new Map();
  let scanTimer = null;
  let scanInProgress = false;
  let scanQueued = false;

  // ─── Ayarlar ve Eşik Değerleri ──────────────────────────────
  let marginLow = 5.0;
  let marginHigh = 20.0;
  let serviceFeeFixed = 9.33;
  let withholdingTaxEnabled = false;

  async function loadSettings() {
    try {
      const stored = await chrome.storage.sync.get({
        marginLow: 5.0,
        marginHigh: 20.0,
        serviceFeeFixed: 9.33,
        withholdingTaxEnabled: false
      });
      marginLow = parseFloat(stored.marginLow) ?? 5.0;
      marginHigh = parseFloat(stored.marginHigh) ?? 20.0;
      serviceFeeFixed = Math.max(0, parseFloat(stored.serviceFeeFixed) || 0);
      withholdingTaxEnabled = Boolean(stored.withholdingTaxEnabled);
      console.log('[ZOLM Seller Panel] Ayarlar yüklendi: zarar=' + marginLow + '%, kar=' + marginHigh + '%, hizmet=' + serviceFeeFixed + ' TL, stopaj=' + withholdingTaxEnabled);
    } catch (e) {
      console.warn('[ZOLM Seller Panel] Ayarlar yüklenemedi:', e);
    }
  }

  // İlk yüklemede ayarları al
  loadSettings().then(() => {
    debouncedScan();
  });


  // ─── DOM Parse: Ürün Satırlarını Bul ────────────────────────
  function findProductRows() {
    // Fiyatlandırma sayfasında her ürün bir satırda gösteriliyor.
    // Olası selector'lar — SPA'nın yapısına göre düzeltilebilir.
    const selectors = [
      // Yeni Trendyol fiyatlandırma sayfası tasarımı
      'table tbody tr',
      '[class*="product-row"]',
      '[class*="ProductRow"]',
      '[class*="product-card"]',
      '[class*="ProductCard"]',
      '[class*="pricing-row"]',
      '[class*="PricingRow"]',
      // Genel fallback
      '[data-testid*="product"]',
    ];

    for (const selector of selectors) {
      const rows = Array.from(document.querySelectorAll(selector));
      // En az 2 satır olan seçiciyi kullan (başlık satırını hariç tutmak için)
      const meaningful = rows.filter(row => {
        const text = (row.innerText || row.textContent || '').trim();
        // Standart sayfalarda barkod doğrudan görünür. Flash sayfasında barkod
        // açılır menüde kaldığı için model kodu etiketi ürün satırını doğrular.
        const hasBarcode = /\d{8,}/.test(text);
        const hasModelCode = /Model\s+Kod[u:]?\s*:/i.test(text);
        return text.length > 20 && (hasBarcode || hasModelCode);
      });
      if (meaningful.length > 0) return meaningful;
    }

    // Hiçbir seçici tutmadıysa, ana container'daki tüm satırları tara
    return [];
  }

  // ─── DOM Parse: Satırdan Veri Çıkarma ───────────────────────
  function parseProductRow(row) {
    const text = clean(row.innerText || row.textContent || '');

    // Barkod — genelde 8-14 haneli sayı
    const barcodeMatch = text.match(/\b(\d{8,14})\b/);
    const barcode = barcodeMatch ? barcodeMatch[1] : '';

    // Model kodu — genelde alfanümerik (ör: ZEMLINES, ZOLMPUF123)
    // Trendyol'da "Model Kodu:" etiketinden sonra gelir
    const modelCodeMatch = text.match(/Model\s+Kod[u:]?\s*:?\s*([A-ZÇĞİÖŞÜa-zçğıöşü0-9_.-]+)/i)
      || text.match(/Model:\s*([A-ZÇĞİÖŞÜa-zçğıöşü0-9_.-]+)/i);
    const modelCode = modelCodeMatch ? modelCodeMatch[1].trim() : '';

    return {
      element: row,
      barcode,
      modelCode,
      text: text.slice(0, 200),
    };
  }

  function extractPrices(text) {
    // Trendyol para formatı: ₺1.399,90 veya 1.399,90 TL veya 1399,90
    const matches = [
      ...text.matchAll(/(?:₺\s*)?(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?)\s*(?:TL|₺)?/g),
    ];

    return matches
      .map(m => {
        const raw = m[1].replace(/\./g, '').replace(',', '.');
        return parseFloat(raw);
      })
      .filter(v => v > 0 && v < 10000000)
      .sort((a, b) => b - a); // En büyük fiyattan başla
  }

  function isPricingOptionCell(cell) {
    const text = clean(cell.innerText || cell.textContent || '');
    const hasPrice = text.includes('₺')
      || text.includes('TL')
      || /\d+(?:[,.]\d+)?\s*(?:₺|TL)/i.test(text)
      || extractPrices(text).length > 0;
    const hasCommission = text.includes('%');
    const hasOfferedPriceRange = /ve\s+(?:altı|üstü)/i.test(text);

    // Komisyon indirimi olan hücrelerde yüzde bulunur. İndirimsiz hücrelerde
    // yalnızca teklif edilen fiyat aralığı gösterilir; seçim durumu "Seç",
    // "Geçerli aralık" veya Trendyol'un başka bir etiketi olabilir.
    return hasPrice && (hasCommission || hasOfferedPriceRange);
  }

  function parseCellPricing(cell) {
    const text = clean(cell.innerText || cell.textContent || '');

    // 1. Komisyon oranı: tüm % eşleşmelerini bulup sonuncusunu kullanır (hedef oranı)
    const percentMatches = Array.from(text.matchAll(/%\s*(\d{1,2}(?:[,.]\d{1,2})?)/g));
    let commissionRate = 0;
    if (percentMatches.length > 0) {
      commissionRate = parseFloat(percentMatches[percentMatches.length - 1][1].replace(',', '.'));
    }

    // 2. Fiyat: Müşterinin Gördüğü Fiyat / Komisyona Esas Fiyat varsa onu, yoksa ilk fiyatı çeker
    let price = 0;
    const escPriceMatch = text.match(/(?:Müşterinin Gördüğü Fiyat|Komisyona Esas Fiyat)[^\d]*([\d.,]+)\s*(?:₺|TL)?/i);
    if (escPriceMatch) {
      price = parseFloat(escPriceMatch[1].replace(/\./g, '').replace(',', '.'));
    } else {
      const prices = extractPrices(text);
      if (prices.length > 0) {
        price = prices[0];
      }
    }

    return { price, commissionRate };
  }

  // ─── ZOLM'dan Maliyet Sorgulama ────────────────────────────
  async function lookupCosts(barcodes, modelCodes) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage(
        {
          type: 'ZOLM_PRICING_COST_LOOKUP',
          barcodes: barcodes,
          model_codes: modelCodes,
        },
        (response) => {
          if (chrome.runtime.lastError) {
            reject(new Error(chrome.runtime.lastError.message));
            return;
          }
          resolve(response);
        },
      );
    });
  }

  // ─── Karlılık Hesaplama ─────────────────────────────────────
  function calculateProfit(salePrice, commissionRate, costData) {
    if (!costData) return null;

    const cogs = parseFloat(costData.cogs) || 0;
    const totalCost = costData.total_cost || 0;
    const effectiveCommission = commissionRate > 0 ? commissionRate : (costData.commission_rate || 0);
    const effectiveSalePrice = salePrice > 0 ? salePrice : (costData.sale_price || 0);

    const commissionAmount = effectiveSalePrice * (effectiveCommission / 100);

    // %1 e-ticaret stopajı: komisyon matrahı azaltmaz; satış fiyatının
    // KDV hariç tutarı üzerinden hesaplanır.
    const vatRate = parseFloat(costData.vat_rate) || 0;
    const withholdingBase = vatRate > 0
      ? effectiveSalePrice / (1 + (vatRate / 100))
      : effectiveSalePrice;
    const withholdingTax = withholdingTaxEnabled ? (withholdingBase * 0.01) : 0;

    const serviceFee = Math.max(0, serviceFeeFixed);
    const roundedCommissionAmount = Math.round(commissionAmount * 100) / 100;
    const roundedWithholdingTax = Math.round(withholdingTax * 100) / 100;
    const netProfit = effectiveSalePrice - roundedCommissionAmount - serviceFee - roundedWithholdingTax - totalCost;
    const profitMargin = cogs > 0 ? (netProfit / cogs) * 100 : 0;

    return {
      hasCost: cogs > 0,
      totalCost: Math.round(totalCost * 100) / 100,
      commissionRate: effectiveCommission,
      commissionAmount: roundedCommissionAmount,
      serviceFee: Math.round(serviceFee * 100) / 100,
      netProfit: Math.round(netProfit * 100) / 100,
      profitMargin: Math.round(profitMargin * 10) / 10,
      salePrice: effectiveSalePrice,
      cogs: cogs,
      cargoCost: parseFloat(costData.cargo_cost) || 0,
      packagingCost: parseFloat(costData.packaging_cost) || 0,
      extraFixed: parseFloat(costData.extra_cost_fixed) || 0,
      extraPercent: parseFloat(costData.extra_cost_percentage) || 0,
      vatRate: vatRate,
      withholdingBase: Math.round(withholdingBase * 100) / 100,
      withholdingTax: roundedWithholdingTax,
    };
  }

  // ─── Hücre Bazlı Badge Oluşturma (Shadow DOM) ────────────────
  function createCellBadge(cell, profitData, costData) {
    const productKey = String(
      costData?.mp_product_id || costData?.barcode || costData?.model_code || 'no-match',
    );
    const renderKey = [
      productKey,
      profitData?.salePrice || 0,
      profitData?.commissionRate || 0,
      profitData?.serviceFee || 0,
      profitData?.totalCost || 0,
      profitData?.netProfit || 0,
      profitData?.profitMargin || 0,
    ].join('|');

    // Trendyol tablo satırlarını yeniden kullanabildiği için mevcut kutunun
    // gerçekten aynı ürün ve fiyat senaryosuna ait olduğunu doğrula.
    const existingBadge = cell.querySelector('.zolm-profit-badge-host');
    if (existingBadge?.dataset.renderKey === renderKey) return;
    if (existingBadge) existingBadge.remove();

    // Hücrenin altındaki ilk div kutu (card) konteyneridir.
    // Eğer div yoksa cell'in kendisini kullanırız.
    const card = (cell.firstElementChild && cell.firstElementChild.tagName === 'DIV') 
      ? cell.firstElementChild 
      : cell;

    const host = document.createElement('div');
    host.className = 'zolm-profit-badge-host';
    host.dataset.productKey = productKey;
    host.dataset.renderKey = renderKey;
    host.style.cssText = 'position: relative; display: block; width: 100%; margin-top: 8px; box-sizing: border-box; z-index: 10;';

    const shadow = host.attachShadow({ mode: 'open' });

    if (!costData) {
      // Eşleşme yok
      shadow.innerHTML = `
        <style>${cellBadgeStyles()}</style>
        <div class="zolm-cell-badge no-match" title="ZOLM'da eşleşme bulunamadı">
          <span class="label">ZOLM eşleşme yok</span>
        </div>
      `;
      card.appendChild(host);
      return;
    }

    if (!profitData || !profitData.hasCost) {
      // Maliyet tanımlı değil (Gri renk)
      shadow.innerHTML = `
        <style>${cellBadgeStyles()}</style>
        <div class="zolm-cell-badge no-cost" title="ZOLM'da maliyet tanımlı değil">
          <div class="cost-view-wrapper" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <span class="label">Maliyet tanımsız</span>
            <span class="edit-cost-btn" style="cursor:pointer;" title="Maliyet Ekle">✏️</span>
          </div>
          <div class="cost-edit-wrapper" style="display: none; align-items:center; gap:4px; width:100%;">
            <input type="number" class="cogs-input" value="0" step="0.01">
            <button class="btn-save">✓</button>
            <button class="btn-cancel">✗</button>
          </div>
        </div>
      `;
      card.appendChild(host);
      attachEditListeners(shadow, costData);
      return;
    }

    const isProfitable = profitData.netProfit > 0;
    const isLoss = profitData.netProfit < 0;

    // Marj değerine ve ayarlara göre durum sınıfı belirleme
    let statusClass = 'breakeven';
    if (profitData.profitMargin < marginLow) {
      statusClass = 'loss'; // Kırmızı
    } else if (profitData.profitMargin >= marginHigh) {
      statusClass = 'profit'; // Yeşil
    }

    shadow.innerHTML = `
      <style>${cellBadgeStyles()}</style>
      <div class="zolm-cell-badge ${statusClass}">
        <div class="main-metrics">
          <span class="margin-badge ${statusClass}">${profitData.profitMargin > 0 ? '+' : ''}${formatPercent(profitData.profitMargin)}</span>
          <span class="net-profit">${formatMoney(profitData.netProfit)}</span>
        </div>
        <div class="detail-metrics">
          <div class="cost-view-wrapper" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <div class="cost-tooltip-trigger">
              Maliyet: ${formatMoney(profitData.totalCost)}
              <div class="cost-tooltip">
                <div class="tooltip-title">Hesaplama Detayları:</div>
                <div class="tooltip-row"><span>Ürün (COGS):</span> <span>${formatMoney(profitData.cogs)}</span></div>
                <div class="tooltip-row"><span>Kargo:</span> <span>${formatMoney(profitData.cargoCost)}</span></div>
                <div class="tooltip-row"><span>Ambalaj:</span> <span>${formatMoney(profitData.packagingCost)}</span></div>
                ${profitData.extraFixed > 0 ? `<div class="tooltip-row"><span>Ek Sabit:</span> <span>${formatMoney(profitData.extraFixed)}</span></div>` : ''}
                ${profitData.extraPercent > 0 ? `<div class="tooltip-row"><span>Ek Yüzde:</span> <span>%${profitData.extraPercent}</span></div>` : ''}
                <div class="tooltip-row" style="color:#f43f5e;"><span>Platform Hizmet Bedeli:</span> <span>-${formatMoney(profitData.serviceFee)}</span></div>
                ${withholdingTaxEnabled ? `<div class="tooltip-row"><span>Stopaj Matrahı (KDV Hariç):</span> <span>${formatMoney(profitData.withholdingBase)}</span></div><div class="tooltip-row" style="color:#f43f5e;"><span>%1 Stopaj:</span> <span>-${formatMoney(profitData.withholdingTax)}</span></div>` : ''}
                <div class="tooltip-divider"></div>
                <div class="tooltip-row total"><span>Toplam Maliyet:</span> <span>${formatMoney(profitData.totalCost)}</span></div>
              </div>
            </div>
            <span class="edit-cost-btn" style="cursor:pointer; margin-left: 4px;" title="Maliyeti Güncelle">✏️</span>
          </div>
          <div class="cost-edit-wrapper" style="display: none; align-items:center; gap:4px; width:100%;">
            <input type="number" class="cogs-input" value="${profitData.cogs}" step="0.01">
            <button class="btn-save">✓</button>
            <button class="btn-cancel">✗</button>
          </div>
        </div>
      </div>
    `;

    card.appendChild(host);
    attachEditListeners(shadow, costData);
  }

  function attachEditListeners(shadow, costData) {
    const editBtn = shadow.querySelector('.edit-cost-btn');
    const viewWrapper = shadow.querySelector('.cost-view-wrapper');
    const editWrapper = shadow.querySelector('.cost-edit-wrapper');
    const cogsInput = shadow.querySelector('.cogs-input');
    const saveBtn = shadow.querySelector('.btn-save');
    const cancelBtn = shadow.querySelector('.btn-cancel');

    if (editBtn && viewWrapper && editWrapper) {
      editBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        viewWrapper.style.display = 'none';
        editWrapper.style.display = 'flex';
        if (cogsInput) {
          cogsInput.focus();
          cogsInput.select();
        }
      });
    }

    if (cancelBtn && viewWrapper && editWrapper) {
      cancelBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        editWrapper.style.display = 'none';
        viewWrapper.style.display = 'flex';
      });
    }

    if (saveBtn && viewWrapper && editWrapper && cogsInput) {
      saveBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const newValue = parseFloat(cogsInput.value);
        if (isNaN(newValue) || newValue < 0) {
          alert('Lütfen geçerli bir maliyet değeri girin.');
          return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = '..';

        try {
          const response = await new Promise((resolve, reject) => {
            chrome.runtime.sendMessage({
              type: 'ZOLM_UPDATE_PRODUCT_COST',
              payload: {
                barcode: costData.barcode,
                model_code: costData.model_code,
                stock_code: costData.stock_code,
                mp_product_id: costData.mp_product_id,
                cogs: newValue
              }
            }, (res) => {
              if (chrome.runtime.lastError) {
                reject(new Error(chrome.runtime.lastError.message));
              } else {
                resolve(res);
              }
            });
          });

          if (response && response.ok) {
            const updated = response.product;
            
            // Cache güncelle
            const keys = [costData.barcode, modelCacheKey(costData.model_code), 'sc:' + costData.stock_code];
            for (const key of keys) {
              if (key) {
                costCache.set(key, {
                  ...costData,
                  cogs: updated.cogs,
                  total_cost: updated.total_cost,
                  has_cost: updated.cogs > 0
                });
              }
            }

            // Yeniden tara ve çiz
            await scanAndEnrich();
          } else {
            alert('Hata: ' + (response?.message || 'Maliyet güncellenemedi.'));
          }
        } catch (err) {
          alert('Hata: ' + err.message);
        } finally {
          saveBtn.disabled = false;
          saveBtn.textContent = '✓';
        }
      });

      cogsInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          saveBtn.click();
        } else if (e.key === 'Escape') {
          cancelBtn.click();
        }
      });
    }
  }

  function cellBadgeStyles() {
    return `
      :host {
        display: block;
        width: 100%;
        box-sizing: border-box;
      }
      .zolm-cell-badge {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 6px 8px;
        border-radius: 6px;
        border: 1px solid;
        font-family: Inter, -apple-system, system-ui, sans-serif;
        font-size: 11px;
        line-height: 1.3;
        text-align: left;
        box-sizing: border-box;
        transition: box-shadow 0.15s ease;
      }
      .zolm-cell-badge:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      }
      .zolm-cell-badge.profit {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #166534;
      }
      .zolm-cell-badge.loss {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
      }
      .zolm-cell-badge.breakeven {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
      }
      .zolm-cell-badge.no-match {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
        text-align: center;
      }
      .zolm-cell-badge.no-cost {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #475569;
      }
      .main-metrics {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
      }
      .margin-badge {
        padding: 1px 4px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
      }
      .margin-badge.profit {
        background: #dcfce7;
        color: #15803d;
      }
      .margin-badge.loss {
        background: #fee2e2;
        color: #b91c1c;
      }
      .margin-badge.breakeven {
        background: #fef3c7;
        color: #b45309;
      }
      .net-profit {
        font-size: 11px;
      }
      .detail-metrics {
        font-size: 9.5px;
        opacity: 0.9;
        border-top: 1px dashed rgba(0, 0, 0, 0.08);
        padding-top: 3px;
        margin-top: 1px;
        display: flex;
        justify-content: space-between;
        color: inherit;
        align-items: center;
      }

      /* Tooltip Styles */
      .cost-tooltip-trigger {
        position: relative;
        cursor: help;
        display: inline-block;
        border-bottom: 1px dashed currentColor;
      }
      .cost-tooltip {
        visibility: hidden;
        position: absolute;
        bottom: 130%;
        left: 50%;
        transform: translateX(-50%);
        background: #0f172a;
        color: #f1f5f9;
        padding: 8px 10px;
        border-radius: 6px;
        font-size: 10px;
        line-height: 1.45;
        box-shadow: 0 4px 12px rgba(0,0,0,0.18);
        z-index: 99999;
        width: 140px;
        box-sizing: border-box;
        opacity: 0;
        transition: opacity 0.15s ease, visibility 0.15s ease;
      }
      .cost-tooltip::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: #0f172a;
      }
      .cost-tooltip-trigger:hover .cost-tooltip {
        visibility: visible;
        opacity: 1;
      }
      .tooltip-title {
        font-weight: 700;
        margin-bottom: 4px;
        color: #fff;
        border-bottom: 1px solid rgba(255,255,255,0.15);
        padding-bottom: 2px;
      }
      .tooltip-row {
        display: flex;
        justify-content: space-between;
        gap: 4px;
      }
      .tooltip-divider {
        border-top: 1px dashed rgba(255,255,255,0.15);
        margin: 4px 0;
      }
      .tooltip-row.total {
        font-weight: 700;
        color: #38bdf8;
      }

      /* Edit form styles */
      .cost-edit-wrapper {
        display: flex;
        align-items: center;
        gap: 4px;
        width: 100%;
      }
      .cogs-input {
        width: 50px;
        font-size: 10px;
        padding: 1px 3px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        font-family: inherit;
        box-sizing: border-box;
      }
      .btn-save {
        background: #10b981;
        color: #fff;
        border: none;
        padding: 1px 4px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 700;
        font-size: 9px;
      }
      .btn-cancel {
        background: #ef4444;
        color: #fff;
        border: none;
        padding: 1px 4px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 700;
        font-size: 9px;
      }
      .edit-cost-btn {
        opacity: 0.5;
        transition: opacity 0.15s ease;
      }
      .edit-cost-btn:hover {
        opacity: 1;
      }
    `;
  }

  // ─── Format Helpers ─────────────────────────────────────────
  function formatMoney(value) {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  }

  function formatPercent(value) {
    return value.toFixed(1).replace('.', ',');
  }

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

  // ─── Ana Tarama Döngüsü ─────────────────────────────────────
  function resolveCostData(item) {
    // Barkod bulunduysa yalnızca kesin barkod eşleşmesini kullan. Aynı model
    // kodunu paylaşan varyantların maliyetinin başka ürüne taşmasını engeller.
    if (item.barcode) {
      return costCache.has(item.barcode)
        ? (costCache.get(item.barcode) || undefined)
        : undefined;
    }

    const modelKey = modelCacheKey(item.modelCode);
    return modelKey
      ? (costCache.get(modelKey) || undefined)
      : undefined;
  }

  function removeForeignRowBadges(row, costData) {
    const expectedProductKey = String(
      costData?.mp_product_id || costData?.barcode || costData?.model_code || 'no-match',
    );

    row.querySelectorAll('.zolm-profit-badge-host').forEach((host) => {
      if (host.dataset.productKey !== expectedProductKey) {
        host.remove();
      }
    });
  }

  async function performScanAndEnrich() {
    if (!isPricingPage()) return;

    const rows = findProductRows();
    if (rows.length === 0) {
      console.log('[ZOLM Seller Panel] Ürün satırı bulunamadı, tekrar denenecek...');
      return;
    }

    // Satırları parse et
    const parsed = rows.map(parseProductRow).filter(p => p.barcode || p.modelCode);

    if (parsed.length === 0) {
      console.log('[ZOLM Seller Panel] Parse edilen ürün yok.');
      return;
    }

    console.log('[ZOLM Seller Panel] ' + parsed.length + ' ürün satırı parse edildi, ZOLM sorgulanıyor...');

    // Cache'de olmayan barkodları topla
    const uncachedBarcodes = [];
    const uncachedModelCodes = [];

    for (const item of parsed) {
      if (item.barcode && !costCache.has(item.barcode)) {
        uncachedBarcodes.push(item.barcode);
      }
      if (item.modelCode && !costCache.has(modelCacheKey(item.modelCode))) {
        uncachedModelCodes.push(item.modelCode);
      }
    }

    // ZOLM'dan maliyet verisi çek (cache'de olmayanlar için)
    if (uncachedBarcodes.length > 0 || uncachedModelCodes.length > 0) {
      try {
        const response = await lookupCosts(
          uncachedBarcodes.slice(0, MAX_BATCH_SIZE),
          uncachedModelCodes.slice(0, MAX_BATCH_SIZE),
        );

        if (response?.ok && response.products) {
          // Cache'e ekle
          for (const [key, value] of Object.entries(response.products)) {
            if (key.startsWith('mc:')) {
              costCache.set(modelCacheKey(key.slice(3)), value);
            } else {
              costCache.set(key, value);
            }
          }

          // Sorgu yapılan ama eşleşmeyen barkodları da cache'e ekle (boş olarak)
          for (const barcode of uncachedBarcodes) {
            if (!costCache.has(barcode)) {
              costCache.set(barcode, null); // Eşleşme yok
            }
          }
          for (const mc of uncachedModelCodes) {
            const key = modelCacheKey(mc);
            if (key && !costCache.has(key)) {
              costCache.set(key, null);
            }
          }

          console.log('[ZOLM Seller Panel] ZOLM yanıtı: ' + response.matched + '/' + response.total_requested + ' eşleşme');
        } else {
          console.warn('[ZOLM Seller Panel] ZOLM yanıtı başarısız:', response?.message || 'Bilinmeyen hata');
        }
      } catch (error) {
        console.error('[ZOLM Seller Panel] ZOLM sorgusu başarısız:', error.message);
        return;
      }
    }

    // Her satırın tarife hücrelerini tek tek güncelle
    for (const item of parsed) {
      // API yanıtı beklenirken Trendyol aynı DOM satırını başka bir ürün için
      // kullanmış olabilir. Eski ürün verisini yeni satıra basma.
      const liveItem = parseProductRow(item.element);
      if (liveItem.barcode !== item.barcode || liveItem.modelCode !== item.modelCode) {
        scanQueued = true;
        continue;
      }

      const costData = resolveCostData(item);
      removeForeignRowBadges(item.element, costData);

      const cells = Array.from(item.element.querySelectorAll('td, th, [role="gridcell"]'));
      for (const cell of cells) {
        if (!isPricingOptionCell(cell)) continue;

        const { price, commissionRate } = parseCellPricing(cell);
        const profitData = costData
          ? calculateProfit(price, commissionRate, costData)
          : null;

        createCellBadge(cell, profitData, costData);
      }
    }
  }

  async function scanAndEnrich() {
    if (scanInProgress) {
      scanQueued = true;
      return;
    }

    scanInProgress = true;

    try {
      await performScanAndEnrich();
    } finally {
      scanInProgress = false;

      if (scanQueued) {
        scanQueued = false;
        debouncedScan();
      }
    }
  }

  // ─── MutationObserver + Debounce ────────────────────────────
  let debounceTimer = null;

  function debouncedScan() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      scanAndEnrich().catch(err => {
        console.error('[ZOLM Seller Panel] Tarama hatası:', err);
      });
    }, DEBOUNCE_MS);
  }

  // DOM değişikliklerini izle (SPA navigasyon, filtre, sıralama vb.)
  const observer = new MutationObserver((mutations) => {
    // Badge ekleyip kaldırmamızın kendisini tetiklememesi için kontrol.
    const isOwnMutation = mutations.every((mutation) => {
      if (mutation.type !== 'childList') return false;

      const changedNodes = [
        ...Array.from(mutation.addedNodes || []),
        ...Array.from(mutation.removedNodes || []),
      ];

      return changedNodes.length > 0 && changedNodes.every((node) =>
        node.nodeType === Node.ELEMENT_NODE
          && node.classList?.contains('zolm-profit-badge-host'),
      );
    });
    if (isOwnMutation) return;

    debouncedScan();
  });

  // İlk taramayı başlat
  setTimeout(() => {
    observer.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true,
      characterData: true,
    });
    debouncedScan();
  }, 1500); // SPA'nın yüklenmesini bekle

  // Periyodik tarama (SPA navigasyonlarını yakalamak için)
  scanTimer = setInterval(() => {
    if (isPricingPage()) {
      // Render anahtarı değişmeyen kutular tekrar oluşturulmaz.
      debouncedScan();
    }
  }, SCAN_INTERVAL_MS);

  // URL değişikliklerini izle (SPA navigasyon)
  let lastUrl = location.href;
  const urlObserver = new MutationObserver(() => {
    if (location.href !== lastUrl) {
      lastUrl = location.href;
      if (isPricingPage()) {
        debouncedScan();
      }
    }
  });
  urlObserver.observe(document.body || document.documentElement, {
    childList: true,
    subtree: true,
  });

  // Popup'tan gelen mesajları dinle
  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type === 'ZOLM_BOOSTER_PAGE_STATUS') {
      sendResponse({
        ok: isPricingPage(),
        context: 'seller_panel',
        payload: {
          page_type: 'pricing',
          url: location.href,
          product_count: findProductRows().length,
        },
        summary: isPricingPage()
          ? 'Trendyol Seller Panel fiyatlandırma sayfası'
          : 'Trendyol Seller Panel',
      });
      return false;
    }
    return false;
  });
})();
