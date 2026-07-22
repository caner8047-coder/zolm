const DEFAULT_BASE_URL = 'https://m.zolm.com.tr';
const COMPANION_PATH = '/marketplace-trendyol-booster/companion';
const ZOLM_REQUEST_TIMEOUT_MS = 12000;
const DECISION_QUEUE_STORAGE_KEY = 'zolmDecisionQueue';
const DECISION_QUEUE_ALARM = 'zolm-booster-decision-queue';
const STORE_DETAIL_ENRICH_LIMIT = 24;
const STORE_DETAIL_ENRICH_WORKERS = 3;
const ZOLM_BRIDGE_TAB_PATTERNS = [
  'https://m.zolm.com.tr/*',
  'http://localhost/*',
  'https://localhost/*',
  'http://127.0.0.1/*',
  'https://127.0.0.1/*',
  'http://localhost:*/*',
  'https://localhost:*/*',
  'http://127.0.0.1:*/*',
  'https://127.0.0.1:*/*',
];
const MARKETPLACE_TARGETS = [
  ['trendyol', 'Trendyol', ['trendyol.com']],
  ['hepsiburada', 'Hepsiburada', ['hepsiburada.com']],
  ['n11', 'n11', ['n11.com']],
  ['amazon_tr', 'Amazon Türkiye', ['amazon.com.tr']],
  ['pazarama', 'Pazarama', ['pazarama.com']],
  ['pttavm', 'PttAVM / EpttAVM', ['pttavm.com', 'epttavm.com']],
  ['boyner', 'Boyner Pazaryeri', ['boyner.com.tr']],
  ['teknosa', 'Teknosa Pazaryeri', ['teknosa.com']],
  ['mediamarkt', 'MediaMarkt Pazaryeri', ['mediamarkt.com.tr']],
  ['modanisa', 'Modanisa', ['modanisa.com']],
  ['koctas', 'Koçtaş Pazaryeri', ['koctas.com.tr']],
  ['ciceksepeti', 'ÇiçekSepeti', ['ciceksepeti.com']],
  ['akakce', 'Akakçe', ['akakce.com']],
  ['cimri', 'Cimri', ['cimri.com']],
];

chrome.runtime.onInstalled.addListener(() => {
  wakeZolmTabs();
});

chrome.runtime.onStartup.addListener(() => {
  wakeZolmTabs();
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  const senderUrl = sender.tab ? sender.tab.url : (sender.url || 'unknown');
  console.log('[Background Worker] Received message:', message?.type, 'from:', senderUrl);
  
  handleMessage(message)
    .then((response) => {
      console.log('[Background Worker] Sending response for:', message?.type, response);
      sendResponse(response);
    })
    .catch((error) => {
      console.error('[Background Worker] Error handling message:', message?.type, error);
      sendResponse({
        ok: false,
        message: error instanceof Error ? error.message : 'ZOLM companion isteği başarısız oldu.',
      });
    });

  return true;
});

async function handleMessage(message) {
  if (!message || typeof message.type !== 'string') {
    throw new Error('Geçersiz companion mesajı.');
  }

  if (message.type === 'ZOLM_BOOSTER_SESSION') {
    return await companionSession();
  }

  if (message.type === 'ZOLM_BOOSTER_EXTENSION_PING') {
    return { ok: true, mode: 'extension_ready', version: chrome.runtime.getManifest().version };
  }

  if (message.type === 'ZOLM_BOOSTER_DOWNLOAD_MEDIA') {
    return await downloadProductMedia(message.media_url || '', message.filename || 'trendyol-urun');
  }

  if (message.type === 'ZOLM_BOOSTER_WAKE_ZOLM_TABS') {
    const count = await wakeZolmTabs();
    return { ok: true, mode: 'zolm_tabs_woken', count };
  }

  if (message.type === 'ZOLM_BOOSTER_OPEN_DASHBOARD') {
    return await openBoosterDashboard(message.module || 'analysis', message.keyword || '');
  }

  if (message.type === 'ZOLM_BOOSTER_CAPTURE_LISTING') {
    const capture = await companionPost('bestseller_capture', message.payload || {});
    const dashboard = await openBoosterDashboard(
      'bestseller',
      message.payload?.query || '',
      {
        reportId: capture.report_id,
        reportMode: 'reports',
      },
    );

    return {
      ...capture,
      dashboard_url: dashboard.url,
    };
  }

  if (message.type === 'ZOLM_BOOSTER_SCAN_LISTING_OPPORTUNITIES') {
    const payload = message.payload || {};
    const items = Array.isArray(payload.items) ? payload.items.slice(0, 40) : [];
    if (items.length < 2) {
      throw new Error('Fırsat taraması için en az iki görünür ürün gerekir.');
    }

    const scanPayload = { ...payload, items };
    const [capture, opportunity] = await Promise.all([
      companionPost('bestseller_capture', scanPayload),
      companionPost('opportunity_scan', { items }),
    ]);

    return {
      ok: true,
      mode: 'listing_opportunity_scan',
      report_id: capture.report_id,
      run_id: capture.run_id,
      scan: opportunity.scan || {},
      message: opportunity.message || `${items.length} ürün fırsat sinyalleriyle sıralandı.`,
    };
  }

  if (message.type === 'ZOLM_BOOSTER_START_DECISION_QUEUE') {
    return await startDecisionQueue(message.urls || []);
  }

  if (message.type === 'ZOLM_BOOSTER_DECISION_QUEUE_STATUS') {
    return await decisionQueueStatus();
  }

  if (message.type === 'ZOLM_BOOSTER_RETRY_DECISION_QUEUE') {
    return await retryDecisionQueue();
  }

  if (message.type === 'ZOLM_BOOSTER_CLEAR_DECISION_QUEUE') {
    await chrome.alarms.clear(DECISION_QUEUE_ALARM);
    await chrome.storage.local.remove(DECISION_QUEUE_STORAGE_KEY);
    return { ok: true, mode: 'decision_queue_cleared', message: 'Karar kuyruğu temizlendi.' };
  }

  if (message.type === 'ZOLM_BOOSTER_COMPARE_LISTING') {
    const urls = normalizeListingProductUrls(message.urls);

    if (urls.length < 2) {
      throw new Error('Karşılaştırma için en az iki Trendyol ürünü seçin.');
    }

    return await openBoosterDashboard('comparison', '', { comparisonUrls: urls });
  }

  if (message.type === 'ZOLM_BOOSTER_DECIDE_LISTING_PRODUCT') {
    const [sourceUrl] = normalizeListingProductUrls([message.source_url], 1);

    if (!sourceUrl) {
      throw new Error('Karar merkezi için geçerli bir Trendyol ürünü seçin.');
    }

    const analysis = await productAnalysisFromUrl(sourceUrl);
    const trackedProductId = Number(analysis?.analysis?.tracked_product_id || 0);

    if (!Number.isInteger(trackedProductId) || trackedProductId <= 0) {
      throw new Error('Canlı analiz kaydedildi ancak ZOLM ürün kaydı doğrulanamadı.');
    }

    const dashboard = await openBoosterDashboard('sell_decision', '', { decisionTrackedProductId: trackedProductId });

    return {
      ok: true,
      mode: 'listing_sell_decision',
      tracked_product_id: trackedProductId,
      summary: {
        decision: analysis?.analysis?.decision || {},
        current: analysis?.analysis?.current || {},
        evidence: analysis?.analysis?.evidence || {},
      },
      message: 'Canlı ürün analizi kaydedildi; Sat veya Satma karar merkezi açıldı.',
      dashboard_url: dashboard.url,
    };
  }

  if (message.type === 'ZOLM_BOOSTER_TRACK_LISTING_SELECTION') {
    const urls = normalizeListingProductUrls(message.urls);

    if (urls.length === 0) {
      throw new Error('Takip için en az bir Trendyol ürünü seçin.');
    }

    const tracked = [];
    const failures = [];

    for (const url of urls) {
      try {
        tracked.push(await trackProductFromUrl(url));
      } catch (error) {
        failures.push({
          source_url: url,
          message: error instanceof Error ? error.message : 'Ürün takibe alınamadı.',
        });
      }
    }

    if (tracked.length === 0) {
      throw new Error(failures[0]?.message || 'Seçilen ürünler Booster Radar takibine alınamadı.');
    }

    const dashboard = await openBoosterDashboard('tracking');

    return {
      ok: true,
      mode: 'listing_bulk_track',
      tracked_count: tracked.length,
      failed_count: failures.length,
      tracked_product_ids: tracked.map((result) => result.tracked_product_id).filter(Boolean),
      failures,
      message: failures.length > 0
        ? `${tracked.length} ürün takibe alındı; ${failures.length} ürün okunamadı.`
        : `${tracked.length} ürün Booster Radar takibine alındı.`,
      dashboard_url: dashboard.url,
    };
  }

  if (message.type === 'ZOLM_BOOSTER_STOCK_FROM_URL') {
    return await stockCheckFromUrl(message.source_url || '');
  }

  if (message.type === 'ZOLM_BOOSTER_PRODUCT_ANALYSIS_FROM_URL') {
    return await productAnalysisFromUrl(message.source_url || '');
  }

  if (message.type === 'ZOLM_BOOSTER_PRODUCT_PAYLOAD_FROM_URL') {
    const payload = await productPayloadFromUrl(message.source_url || '', true);
    return { ok: true, payload, source: 'browser_bridge' };
  }

  if (message.type === 'ZOLM_BOOSTER_STOCK_PAYLOAD_FROM_URL') {
    const payload = await stockPayloadFromUrl(message.source_url || '');
    return { ok: true, payload, source: 'browser_bridge' };
  }

  if (message.type === 'ZOLM_BOOSTER_TRACK_FROM_URL') {
    return await trackProductFromUrl(message.source_url || '');
  }

  if (message.type === 'ZOLM_BOOSTER_TRACK_PAYLOAD_FROM_URL') {
    const payload = await productPayloadFromUrl(message.source_url || '', true);
    return { ok: true, payload, source: 'browser_bridge' };
  }

  if (message.type === 'ZOLM_BOOSTER_BESTSELLER_FROM_URL') {
    return await bestsellerFromUrl(
      message.source_url || '',
      message.keyword || '',
      message.min_price,
      message.max_price
    );
  }

  if (message.type === 'ZOLM_BOOSTER_KEYWORD_TRACKING_FROM_URL') {
    return await keywordTrackingFromUrl(
      message.source_url || '',
      message.keywords || []
    );
  }

  if (message.type === 'ZOLM_BOOSTER_KEYWORD_LOOKUP_FROM_URL') {
    return await keywordLookupFromUrl(
      message.source_url || '',
      message.keyword || ''
    );
  }

  if (message.type === 'ZOLM_BOOSTER_SUPPLIER_RESEARCH_FROM_URL') {
    return await supplierResearchFromUrl(message.source_url || '');
  }

  if (message.type === 'ZOLM_BOOSTER_SUPPLIER_RESEARCH_PAYLOAD_FROM_URL') {
    const payload = await supplierResearchPayloadFromUrl(message.source_url || '');
    return {
      ok: true,
      payload,
      source: 'browser_bridge',
      google_result_count: Array.isArray(payload.offers) ? payload.offers.length : 0,
    };
  }

  if (message.type === 'ZOLM_BOOSTER_STORE_SCAN_FROM_URL') {
    return await storeFullScanFromUrl(message.source_url || '');
  }

  if (message.type === 'ZOLM_BOOSTER_PREVIEW') {
    return await companionPost('preview', message.payload || {});
  }

  if (message.type === 'ZOLM_BOOSTER_TRACK') {
    return await companionPost('track', message.payload || {});
  }

  if (message.type === 'ZOLM_BOOSTER_TRACKING_STATUS') {
    return await companionStatus(message.product_id || '');
  }

  if (message.type === 'ZOLM_BOOSTER_STOCK_CHECK') {
    return await companionPost('stock_check', message.payload || {});
  }

  if (message.type === 'ZOLM_BOOSTER_STORE_SCAN') {
    return await companionPost('store_scan', message.payload || {});
  }

  if (message.type === 'ZOLM_BOOSTER_REVIEW_SCAN_START') {
    return await reviewScanFromUrl(message.source_url || '', message.options || {});
  }

  if (message.type === 'ZOLM_BOOSTER_REVIEW_STORE_PREVIEW') {
    return await reviewStorePreviewFromUrl(message.source_url || '', message.options || {});
  }

  if (message.type === 'ZOLM_BOOSTER_REVIEW_SCAN_VERIFY') {
    return await companionPost('review_scan_verify', message.payload || {});
  }

  if (message.type === 'ZOLM_PRICING_COST_LOOKUP') {
    return await pricingCostLookup(
      message.barcodes || [],
      message.model_codes || [],
      message.stock_codes || [],
    );
  }

  if (message.type === 'ZOLM_UPDATE_PRODUCT_COST') {
    return await updateProductCost(message.payload || {});
  }

  if (message.type === 'ZOLM_ORDER_PROFIT_LOOKUP') {
    return await orderProfitLookup(message.payload || {});
  }

  throw new Error('Bilinmeyen companion mesajı.');
}

async function stockCheckFromUrl(sourceUrl) {
  const payload = await stockPayloadFromUrl(sourceUrl);
  const response = await companionPost('stock_check', payload);

  return {
    ...response,
    payload,
    source: 'browser_bridge',
  };
}

async function stockPayloadFromUrl(sourceUrl) {
  const url = normalizeTrendyolUrl(sourceUrl);
  let createdTabId = null;

  try {
    const tab = await chrome.tabs.create({ url, active: false });
    createdTabId = tab.id;

    if (!createdTabId) {
      throw new Error('Trendyol tarama sekmesi açılamadı.');
    }

    await waitForTabComplete(createdTabId, 30000);
    const pageStatus = await readPageStatus(createdTabId);

    if (!pageStatus?.ok || pageStatus.context !== 'product' || !pageStatus.payload) {
      throw new Error('Trendyol ürün sayfasından stok verisi okunamadı. Sayfayı normal sekmede açıp tekrar deneyin.');
    }

    return pageStatus.payload;
  } finally {
    if (createdTabId) {
      await chrome.tabs.remove(createdTabId).catch(() => undefined);
    }
  }
}

async function productAnalysisFromUrl(sourceUrl) {
  const payload = await productPayloadFromUrl(sourceUrl, true);
  const response = await companionPost('product_analysis', payload);

  return {
    ...response,
    payload,
    source: 'browser_bridge',
  };
}

async function startDecisionQueue(values) {
  const urls = normalizeListingProductUrls(values, 40);
  if (urls.length === 0) {
    throw new Error('Karar kuyruğu için en az bir Trendyol ürünü gerekir.');
  }

  const queue = {
    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    status: 'queued',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    total: urls.length,
    items: urls.map((sourceUrl, index) => ({
      index,
      source_url: sourceUrl,
      status: 'pending',
      attempts: 0,
      tracked_product_id: null,
      title: '',
      message: '',
    })),
  };
  await chrome.storage.local.set({ [DECISION_QUEUE_STORAGE_KEY]: queue });
  await chrome.alarms.clear(DECISION_QUEUE_ALARM);
  chrome.alarms.create(DECISION_QUEUE_ALARM, { when: Date.now() + 250 });

  return queueResponse(queue, 'Karar kuyruğu başlatıldı.');
}

async function decisionQueueStatus() {
  const stored = await chrome.storage.local.get({ [DECISION_QUEUE_STORAGE_KEY]: null });
  const queue = stored[DECISION_QUEUE_STORAGE_KEY];

  return queue ? queueResponse(queue) : {
    ok: true,
    mode: 'decision_queue',
    queue: null,
    message: 'Aktif karar kuyruğu yok.',
  };
}

async function retryDecisionQueue() {
  const stored = await chrome.storage.local.get({ [DECISION_QUEUE_STORAGE_KEY]: null });
  const queue = stored[DECISION_QUEUE_STORAGE_KEY];
  if (!queue || !Array.isArray(queue.items)) {
    throw new Error('Yeniden denenecek karar kuyruğu bulunamadı.');
  }

  queue.items = queue.items.map((item) => item.status === 'failed'
    ? { ...item, status: 'pending', attempts: 0, message: '' }
    : item);
  queue.status = 'queued';
  queue.updated_at = new Date().toISOString();
  await chrome.storage.local.set({ [DECISION_QUEUE_STORAGE_KEY]: queue });
  chrome.alarms.create(DECISION_QUEUE_ALARM, { when: Date.now() + 250 });

  return queueResponse(queue, 'Başarısız ürünler yeniden kuyruğa alındı.');
}

function queueResponse(queue, message = '') {
  const items = Array.isArray(queue?.items) ? queue.items : [];
  const completed = items.filter((item) => item.status === 'completed').length;
  const failed = items.filter((item) => item.status === 'failed').length;
  const processing = items.filter((item) => item.status === 'processing').length;

  return {
    ok: true,
    mode: 'decision_queue',
    queue: {
      ...queue,
      completed,
      failed,
      processing,
      pending: Math.max(0, items.length - completed - failed - processing),
      progress_percent: items.length > 0 ? Math.round(((completed + failed) / items.length) * 100) : 0,
    },
    message,
  };
}

let decisionQueueRunning = false;

async function processDecisionQueue() {
  if (decisionQueueRunning) return;
  decisionQueueRunning = true;

  try {
    const stored = await chrome.storage.local.get({ [DECISION_QUEUE_STORAGE_KEY]: null });
    const queue = stored[DECISION_QUEUE_STORAGE_KEY];
    if (!queue || !Array.isArray(queue.items)) return;
    const batch = queue.items.filter((item) => item.status === 'pending').slice(0, 2);
    if (batch.length === 0) {
      queue.status = queue.items.some((item) => item.status === 'processing') ? 'running' : 'completed';
      queue.updated_at = new Date().toISOString();
      await chrome.storage.local.set({ [DECISION_QUEUE_STORAGE_KEY]: queue });
      return;
    }

    queue.status = 'running';
    for (const item of batch) {
      item.status = 'processing';
      item.attempts = Number(item.attempts || 0) + 1;
    }
    queue.updated_at = new Date().toISOString();
    await chrome.storage.local.set({ [DECISION_QUEUE_STORAGE_KEY]: queue });

    const results = await Promise.allSettled(batch.map((item) => productAnalysisFromUrl(item.source_url)));
    results.forEach((result, index) => {
      const item = batch[index];
      if (result.status === 'fulfilled' && result.value?.analysis?.tracked_product_id) {
        item.status = 'completed';
        item.tracked_product_id = Number(result.value.analysis.tracked_product_id);
        item.title = String(result.value.analysis.title || 'Ürün').slice(0, 180);
        item.summary = {
          decision: result.value.analysis.decision || {},
          current: result.value.analysis.current || {},
          evidence: result.value.analysis.evidence || {},
        };
        item.message = 'Canlı analiz kaydedildi.';
      } else {
        item.status = Number(item.attempts || 0) < 2 ? 'pending' : 'failed';
        item.message = result.status === 'rejected'
          ? String(result.reason?.message || 'Ürün okunamadı.').slice(0, 240)
          : 'Ürün analizi doğrulanamadı.';
      }
    });

    const hasPending = queue.items.some((item) => item.status === 'pending');
    queue.status = hasPending ? 'running' : 'completed';
    queue.updated_at = new Date().toISOString();
    await chrome.storage.local.set({ [DECISION_QUEUE_STORAGE_KEY]: queue });
    if (hasPending) chrome.alarms.create(DECISION_QUEUE_ALARM, { when: Date.now() + 1000 });
  } finally {
    decisionQueueRunning = false;
  }
}

async function trackProductFromUrl(sourceUrl) {
  const payload = await productPayloadFromUrl(sourceUrl, true);
  const response = await companionPost('track', payload);

  return {
    ...response,
    payload,
    source: 'browser_bridge',
  };
}

async function productPayloadFromUrl(sourceUrl, fullAnalysis = false) {
  const url = normalizeTrendyolUrl(sourceUrl);
  let createdTabId = null;

  try {
    const tab = await chrome.tabs.create({ url, active: false });
    createdTabId = tab.id;

    if (!createdTabId) {
      throw new Error('Trendyol ürün detay sekmesi açılamadı.');
    }

    await waitForTabComplete(createdTabId, 30000);
    const pageStatus = await readPageStatus(
      createdTabId,
      fullAnalysis ? 'ZOLM_BOOSTER_PRODUCT_ANALYSIS_PAGE_STATUS' : 'ZOLM_BOOSTER_PAGE_STATUS',
    );

    if (!pageStatus?.ok || pageStatus.context !== 'product' || !pageStatus.payload) {
      throw new Error(pageStatus?.message || 'Trendyol ürün detayı tarayıcıdan okunamadı.');
    }

    return pageStatus.payload;
  } finally {
    if (createdTabId) {
      await chrome.tabs.remove(createdTabId).catch(() => undefined);
    }
  }
}

// ─── Rakip Mağaza Tam Tarama ───────────────────────────────────────
async function storeFullScanFromUrl(storeUrl) {
  const url = String(storeUrl || '').trim();
  if (!url) throw new Error('Mağaza URL\'si boş.');

  // Mağaza ID'sini çıkar — tüm Trendyol mağaza URL formatları
  // Örnekler:
  //   /magaza/marken-m-113012
  //   /magaza/profil/zem-home-m-121057
  //   /sr?mid=113012
  //   ?merchantId=113012
  const storeIdMatch = url.match(/-m-(\d+)/i) || url.match(/[?&]mid=(\d+)/i) || url.match(/merchantId=(\d+)/i);
  const storeId = storeIdMatch ? storeIdMatch[1] : '';

  const scanUrls = [];
  const pushScanUrl = (candidate) => {
    if (candidate && !scanUrls.includes(candidate)) scanUrls.push(candidate);
  };
  pushScanUrl(url);
  if (storeId) {
    pushScanUrl(`https://www.trendyol.com/sr?mid=${storeId}&os=1`);
    pushScanUrl(`https://www.trendyol.com/sr/?mid=${storeId}&os=1`);
  }

  let lastError = null;
  for (const scanUrl of scanUrls) {
    let createdTabId = null;
    try {
    const tab = await chrome.tabs.create({ url: scanUrl, active: false });
    createdTabId = tab.id;
    if (!createdTabId) throw new Error('Mağaza sekmesi açılamadı.');

    await waitForTabComplete(createdTabId, 30000);
    // Ürün kartlarının yüklenmesini bekle
    await waitForStoreProducts(createdTabId, 20000);

    // Sayfayı aşağı kaydırarak daha fazla ürün yükle (infinite scroll)
    await scrollForMoreProducts(createdTabId, 3);

    // Tüm ürün kartlarını çek
    const [result] = await chrome.scripting.executeScript({
      target: { tabId: createdTabId },
      world: 'MAIN',
      func: () => {
        const items = [];

        // Mağaza meta bilgileri
        const storeNameEl = document.querySelector('.seller-store-name, .merchant-name, h1');
        const storeName = storeNameEl ? storeNameEl.textContent.trim() : '';
        const totalProductsEl = document.querySelector('.product-count, .dscrptn, .total-product-count');
        const totalProductsText = totalProductsEl ? totalProductsEl.textContent : '';
        const totalMatch = (totalProductsText || document.body.innerText).match(/([\d.,]+)\+?\s*Ürün/i);
        const totalProducts = totalMatch ? parseInt(totalMatch[1].replace(/\./g, ''), 10) : 0;
        const storeRatingEl = document.querySelector('.seller-score, .store-rating');
        const storeRating = storeRatingEl ? parseFloat(storeRatingEl.textContent.replace(',', '.')) : null;

        // Ürün kartlarını çek
        const currentCards = Array.from(document.querySelectorAll('main a.product-card[href*="-p-"]'));
        const legacyCards = Array.from(document.querySelectorAll('.p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr .p-card-chldrn-cntnr, .product-wrapper, li.p-card, div[class*="productCard"]'));
        const genericCardMap = new Map();
        Array.from(document.querySelectorAll('main a[href*="-p-"], a[href*="-p-"]')).forEach(anchor => {
          const href = anchor.getAttribute('href') || '';
          const pidMatch = href.match(/-p-(\d+)/i);
          if (!pidMatch || genericCardMap.has(pidMatch[1])) return;
          const card = anchor.closest('.p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr .p-card-chldrn-cntnr, .product-wrapper, li.p-card, article, [class*="product"], [class*="Product"], [class*="card"], [class*="Card"]') || anchor;
          genericCardMap.set(pidMatch[1], card);
        });
        const genericCards = Array.from(genericCardMap.values());
        const cards = currentCards.length > 0 ? currentCards : (legacyCards.length > 0 ? legacyCards : genericCards);
        const seenProductIds = new Set();
        const parseMoney = (value) => {
          const text = String(value || '').trim();
          const match = text.match(/\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?|\d+(?:,\d{1,2})?/);
          if (!match) return 0;
          const amount = Number.parseFloat(match[0].replace(/\./g, '').replace(',', '.'));
          return Number.isFinite(amount) ? amount : 0;
        };
        const cleanText = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const normalizeCampaignBadge = (value) => {
          const text = cleanText(value)
            .replace(/KUPONLUÜRÜN/gi, 'Kuponlu Ürün')
            .replace(/Sepette(?=\d)/gi, 'Sepette ')
            .replace(/([a-zığüşöç])([A-ZİĞÜŞÖÇ])/g, '$1 $2');
          if (!text || text.length < 4) return '';
          if (/Trendyol'da Satış Yap|Hakkımızda|Yardım\s*&\s*Destek|Ürün,\s*kategori|Giriş Yap|Sepete Ekle|Alışveriş Kredisi|Temel Kavr/i.test(text)) return '';
          const known = [
            /\d+[\d.]*\s*TL'?ye\s+%\d+\s*İndirim/i,
            /\d+[\d.]*\s*TL'?ye\s+(?:%\d+\s*)?\d*[\d.]*\s*TL?\s*İndirim/i,
            /\d+[\d.]*\s*TL\s+ve\s+Üzeri\s+Kargo\s+Bedava/i,
            /\d+[\d.]*\s*TL\s+Kupon/i,
            /En\s+Çok\s+(?:Satan|Satılan|Ziyaret\s+Edilen)\s+#?\d+\.?\s*Ürün/i,
            /Trendyol\s+Plus'?a\s+Özel\s+Fiyat/i,
            /Son\s+\d+\s+Günün\s+En\s+Düşük\s+Fiyatı/i,
            /Flaş\s+Ürün/i,
            /Avantajlı\s+Ürün/i,
            /Kargo\s+Bedava/i,
            /Kupon\s+Fırsatı/i,
            /Kuponlu\s+Ürün/i,
            /Birlikte\s+Al\s+Kazan/i,
            /Çok\s+Al\s+Az\s+Öde/i,
            /Fenomen\s+Seçimi/i,
            /Yetkili\s+Satıcı/i,
          ];
          const match = known.map((pattern) => text.match(pattern)).find(Boolean);
          return match ? cleanText(match[0]) : '';
        };
        cards.forEach((card, index) => {
          try {
            const linkEl = card.matches?.('a[href*="-p-"]') ? card : card.querySelector('a[href*="-p-"]');
            const href = linkEl ? linkEl.getAttribute('href') || '' : '';
            const pidMatch = href.match(/-p-(\d+)/i);
            if (!pidMatch) return;

            const productId = pidMatch[1];
            if (seenProductIds.has(productId)) return;
            seenProductIds.add(productId);

            // Görsel
            const imgEl = card.querySelector('img.p-card-img, img[loading], img');
            const imageUrl = imgEl ? (imgEl.getAttribute('src') || imgEl.getAttribute('data-src') || '') : '';
            const imageAlt = cleanText(imgEl?.getAttribute('alt') || '');

            // Başlık
            const titleParts = [];
            const headingEl = card.querySelector('h2.title, h2, h3');
            const brandEl = card.querySelector('.product-brand, .prdct-desc-cntnr-ttl-w .prdct-desc-cntnr-ttl, span.prdct-desc-cntnr-name, [class*="brandName"], [class*="brand-name"]');
            const nameEl = card.querySelector('.product-name, .prdct-desc-cntnr-name, span[data-testid="product-card-name"], [class*="productName"], [class*="product-name"]');
            if (brandEl) titleParts.push(cleanText(brandEl.textContent));
            if (nameEl) titleParts.push(cleanText(nameEl.textContent));
            const title = titleParts.join(' ').trim()
              || cleanText(headingEl?.textContent)
              || cleanText(linkEl?.getAttribute('title'))
              || cleanText(linkEl?.getAttribute('aria-label'))
              || imageAlt
              || cleanText(linkEl ? linkEl.textContent : '')
              || 'Ürün';

            // Marka
            const brand = brandEl ? cleanText(brandEl.textContent) : '';

            // Fiyat
            const priceEl = card.querySelector('.sale-price, .price-value, .single-price, .price-section, .prc-box-dscntd, .prc-box-sllng, [class*="discountedPrice"], [class*="sellingPrice"]');
            const salePrice = priceEl ? parseMoney(priceEl.textContent) : parseMoney(card.textContent);
            const originalPriceEl = card.querySelector('.strikethrough-price, .prc-box-orgnl, .prc-box-old, [class*="originalPrice"]');
            const originalPrice = originalPriceEl ? parseMoney(originalPriceEl.textContent) : null;
            const discountRate = originalPrice && originalPrice > salePrice
              ? Math.round((1 - salePrice / originalPrice) * 100)
              : null;

            // Puan ve Yorum
            const ratingEl = card.querySelector('.average-rating, .rating-score, .rtngs, .ratings');
            const rating = ratingEl ? parseFloat(ratingEl.textContent.replace(',', '.')) : null;
            const reviewEl = card.querySelector('.review-rating, .ratingCount, .rating-count, .rtngs-cntnr, [class*="reviewCount"]');
            let reviewCount = null;
            if (reviewEl) {
              const reviewMatch = reviewEl.textContent.match(/\(([\d.,]+)\)/) || reviewEl.textContent.match(/([\d.,]+)/);
              reviewCount = reviewMatch ? parseInt(reviewMatch[1].replace(/\./g, ''), 10) : null;
            }

            // Favori
            const favEl = card.querySelector('.fvrt-count, .favorite-count, [class*="favoriteCount"]');
            let favoriteCount = null;
            if (favEl) {
              const favMatch = favEl.textContent.match(/([\d.,]+)/);
              favoriteCount = favMatch ? parseInt(favMatch[1].replace(/\./g, ''), 10) : null;
            }
            if (favoriteCount === null) {
              const favMatch = card.textContent.match(/([\d.,]+)\s*kişi\s+favoriledi/i);
              favoriteCount = favMatch ? parseInt(favMatch[1].replace(/\./g, ''), 10) : null;
            }

            // Kampanya badge'leri
            const campaignBadges = [];
            card.querySelectorAll('.campaign-badge, .rush-badge, .promoted-badge, .campaign-label, .shipping-badge, .free-cargo-badge, .installment-badge, .flash-product, .coupon-badge, [class*="campaign-badge"], [class*="coupon-badge"], [class*="shipping-badge"], [class*="free-cargo"], [class*="rush-delivery"]').forEach(badge => {
              const text = normalizeCampaignBadge(badge.textContent);
              if (text && !campaignBadges.includes(text)) campaignBadges.push(text);
            });

            // Kargo bedava badge
            if (card.querySelector('.free-cargo-stamp, .free-shipping, [class*="free-cargo"], [class*="kargo-bedava"], [class*="freeCargo"]')) {
              if (!campaignBadges.includes('Kargo Bedava')) campaignBadges.push('Kargo Bedava');
            }

            // 1. satıcı kontrolü
            const sellerBadge = card.querySelector('.seller-badge, .official-store, .merchant-badge, [class*="officialStore"], [class*="merchant"]');
            const isFirstSeller = !!sellerBadge;

            // Satıcı adı (varsa)
            const sellerNameEl = card.querySelector('.merchant-name, .seller-name, [class*="merchantName"]');
            const sellerName = sellerNameEl ? sellerNameEl.textContent.trim() : '';

            items.push({
              trendyol_product_id: productId,
              source_url: href.startsWith('http') ? href : `https://www.trendyol.com${href}`,
              image_url: imageUrl.startsWith('http') ? imageUrl : (imageUrl ? `https://cdn.dsmcdn.com${imageUrl}` : ''),
              title: title.substring(0, 240),
              brand: brand.substring(0, 120),
              sale_price: salePrice || 0,
              original_price: originalPrice,
              discount_rate: discountRate,
              rating: rating,
              review_count: reviewCount,
              favorite_count: favoriteCount,
              campaign_badges: campaignBadges.slice(0, 8),
              is_first_seller: isFirstSeller,
              seller_name: sellerName.substring(0, 255),
              category_name: '',
            });
          } catch (e) {
            // Tek bir kart hata verse devam et
          }
        });

        // Eğer DOM'dan ürün çıkaramazsak window state'ten dene
        if (items.length === 0) {
          try {
            const stateProducts =
              window.__SEARCH_APP_INITIAL_STATE__?.searchStateManager?.searchProducts?.productList ||
              window.__SEARCH_APP_INITIAL_STATE__?.productList ||
              [];
            stateProducts.slice(0, 72).forEach(p => {
              const pid = String(p.id || p.productId || p.contentId || '');
              if (!pid) return;
              const priceVal = p.price?.sellingPrice || p.price?.discountedPrice || p.sellingPrice || 0;
              const originalVal = p.price?.originalPrice || null;
              const url = p.url ? (p.url.startsWith('http') ? p.url : 'https://www.trendyol.com' + p.url) : '';
              items.push({
                trendyol_product_id: pid,
                source_url: url,
                image_url: p.images?.[0] ? 'https://cdn.dsmcdn.com' + p.images[0] : '',
                title: String(p.name || p.title || 'Ürün').substring(0, 240),
                brand: String(p.brand?.name || p.brand || '').substring(0, 120),
                sale_price: parseFloat(priceVal) || 0,
                original_price: originalVal ? parseFloat(originalVal) : null,
                discount_rate: p.price?.discountRatio || null,
                rating: p.ratingScore?.averageRating || null,
                review_count: p.ratingScore?.totalCount || null,
                favorite_count: p.favoriteCount || null,
                campaign_badges: [],
                is_first_seller: false,
                seller_name: String(p.merchant?.name || p.merchantName || '').substring(0, 255),
                category_name: String(p.categoryName || '').substring(0, 180),
              });
            });
          } catch(e) {}
        }

        return {
          store_name: storeName,
          total_products: totalProducts || items.length,
          store_rating: storeRating,
          items,
        };
      },
    });

    const data = result?.result || { store_name: '', total_products: 0, store_rating: null, items: [] };

    if (!Array.isArray(data.items) || data.items.length === 0) {
      throw new Error('Trendyol mağaza ürün kartları yüklenemedi; boş tarama kaydedilmedi.');
    }

    const enrichedItems = await enrichStoreScanItems(data.items, STORE_DETAIL_ENRICH_LIMIT);
    const enrichedCount = enrichedItems.filter((item) => item.enrichment_status === 'enriched').length;

    return {
      ok: true,
      message: `${data.store_name || 'Rakip mağaza'} tarandı: ${data.items.length} ürün yakalandı; ${enrichedCount} ürün stok/favori için derin okundu.`,
      store_id: storeId,
      store_name: data.store_name || '',
      store_url: url,
      store_rating: data.store_rating,
      total_products: data.total_products,
      items: enrichedItems,
      enrichment: {
        mode: 'product_detail',
        limit: STORE_DETAIL_ENRICH_LIMIT,
        enriched_count: enrichedCount,
        total_count: data.items.length,
      },
      source: 'browser_bridge',
    };
    } catch (error) {
      lastError = error;
    } finally {
      if (createdTabId) {
        await chrome.tabs.remove(createdTabId).catch(() => undefined);
      }
    }
  }

  throw lastError || new Error('Trendyol mağaza ürün kartları yüklenemedi; boş tarama kaydedilmedi.');
}

async function waitForStoreProducts(tabId, timeoutMs = 20000) {
  const interval = 500;
  const maxAttempts = Math.ceil(timeoutMs / interval);
  for (let i = 0; i < maxAttempts; i++) {
    const [check] = await chrome.scripting.executeScript({
      target: { tabId },
      world: 'MAIN',
      func: () => {
        const cards = document.querySelectorAll('main a.product-card[href*="-p-"], main a[href*="-p-"], a[href*="-p-"], .p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr .p-card-chldrn-cntnr, .product-wrapper, li.p-card, div[class*="productCard"]');
        const noResult = document.querySelector('.no-result, .no-srch-rslt, .empty-result, [class*="empty-result"], [class*="no-result"]');
        // Check for app state data as fallback
        const hasStateData = !!(window.__SEARCH_APP_INITIAL_STATE__?.searchStateManager?.searchProducts?.productList?.length);
        if (cards.length > 0 || hasStateData) return 'loaded';
        if (noResult) return 'empty';
        return 'loading';
      },
    });
    const state = check?.result;
    if (state === 'loaded' || state === 'empty') return state;
    await delay(interval);
  }
  return 'timeout';
}

async function scrollForMoreProducts(tabId, maxScrolls = 3) {
  for (let i = 0; i < maxScrolls; i++) {
    const [before] = await chrome.scripting.executeScript({
      target: { tabId },
      func: () => document.querySelectorAll('main a.product-card[href*="-p-"], main a[href*="-p-"], a[href*="-p-"], .p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr .p-card-chldrn-cntnr').length,
    });
    const countBefore = before?.result || 0;

    await chrome.scripting.executeScript({
      target: { tabId },
      func: () => window.scrollTo(0, document.body.scrollHeight),
    });

    await delay(1500);

    const [after] = await chrome.scripting.executeScript({
      target: { tabId },
      func: () => document.querySelectorAll('main a.product-card[href*="-p-"], main a[href*="-p-"], a[href*="-p-"], .p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr .p-card-chldrn-cntnr').length,
    });
    const countAfter = after?.result || 0;

    // Yeni ürün yüklenmediyse dur
    if (countAfter <= countBefore) break;
  }
}
// ─── Trendyol Yorum Senkronizasyonu (Faz 2) ────────────────────────────
/**
 * Mağaza yorum taramasını başlatır.
 * 1. ZOLM'dan sync run oluştur
 * 2. Aktif Trendyol tab'ında mağaza ürün listesini topla
 * 3. Her ürün için yorumları pagination ile çek (rate-limited)
 * 4. Batch olarak ZOLM'a ingest et
 */
async function reviewScanFromUrl(sourceUrl, options = {}) {
  const syncType = options.sync_type || 'delta';
  const maxProducts = options.max_products || 500;
  const storeUrl = String(options.store_url || '').trim();
  const merchantId = String(options.merchant_id || '').trim();
  const storeName = String(options.store_name || '').trim();

  if (!storeUrl || !/^\d{2,20}$/.test(merchantId)) {
    throw new Error('Yorum taraması için doğrulanmış Trendyol mağazası ve merchant ID gereklidir.');
  }

  // Panelden gelen sync run varsa onu kullan; popup taramasında yeni run oluştur.
  const startResponse = await companionPost('review_scan_start', {
    sync_run_id: options.sync_run_id || null,
    sync_type: syncType,
    review_source_id: options.review_source_id || null,
  });
  if (!startResponse?.ok) {
    throw new Error('ZOLM sync run oluşturulamadı: ' + (startResponse?.message || 'Bilinmeyen hata'));
  }

  const syncRunId = startResponse.sync_run_id;
  // Tam tarama geçmiş senkronizasyon eşiğini kullanmaz; bütün yorumları baştan okur.
  const lastSyncedAt = syncType === 'full' ? null : startResponse.last_synced_at;
  let reviewTabId = null;

  try {
    const storeContext = await openReviewStoreContext(storeUrl, merchantId, maxProducts);
    reviewTabId = storeContext.tab_id;
    const productList = storeContext.products;

    let totalNew = 0, totalUpdated = 0, totalSpam = 0;
    let processedProducts = 0;
    let failedProducts = 0;
    let ignoredSellerReviews = 0;
    const failureExamples = [];
    const totalProducts = productList.length;

    for (const product of productList) {
      await randomDelay();
      let progressReported = false;

      try {
        const reviewData = await sendTrendyolTabMessage(reviewTabId, {
          type: 'ZOLM_BOOSTER_REVIEW_FETCH',
          product_id: product.trendyol_product_id,
          since: lastSyncedAt,
          max_pages: 50,
        });

        if (!reviewData?.ok) {
          throw new Error(reviewData?.message || 'Yorumlar okunamadı.');
        }

        const rawReviews = Array.isArray(reviewData?.reviews) ? reviewData.reviews : [];
        const reviews = rawReviews.filter((review) => {
          const structurallyValid = review?.trendyol_review_id
            && review?.trendyol_product_id
            && review?.comment
            && Number(review?.rating) >= 1
            && Number(review?.rating) <= 5;
          if (!structurallyValid) return false;

          const matches = reviewMatchesSelectedStore(review, merchantId, storeName);
          if (!matches) ignoredSellerReviews++;
          return matches;
        });

        if (reviews.length > 0) {
          for (const review of reviews) {
            review.product_title = product.product_title;
            review.product_image_url = product.product_image_url;
            review.trendyol_product_barcode = product.trendyol_product_barcode;
          }
          const chunks = [];
          for (let offset = 0; offset < reviews.length; offset += REVIEW_SCAN_RATE.BATCH_SIZE) {
            chunks.push(reviews.slice(offset, offset + REVIEW_SCAN_RATE.BATCH_SIZE));
          }

          for (let index = 0; index < chunks.length; index++) {
            const ingestResult = await companionPost('review_scan_ingest', {
              sync_run_id: syncRunId,
              reviews: chunks[index],
              total_products: totalProducts,
              processed_products: index === chunks.length - 1 ? processedProducts + 1 : processedProducts,
            });
            totalNew += ingestResult?.new || 0;
            totalUpdated += ingestResult?.updated || 0;
            totalSpam += ingestResult?.spam || 0;
            if (index === chunks.length - 1) progressReported = true;
          }
        }
      } catch (error) {
        failedProducts++;
        const failureMessage = error instanceof Error ? error.message : 'Bilinmeyen yorum okuma hatası.';
        if (failureExamples.length < 3) {
          failureExamples.push(`${product.trendyol_product_id}: ${failureMessage}`);
        }
        console.warn(`[Review Scan] Ürün ${product.trendyol_product_id} yorum çekme hatası:`, failureMessage);
      }

      processedProducts++;
      if (!progressReported) {
        await companionPost('review_scan_ingest', {
          sync_run_id: syncRunId,
          reviews: [],
          total_products: totalProducts,
          processed_products: processedProducts,
        });
      }
    }

    if (failedProducts === totalProducts) {
      throw new Error(
        `${totalProducts} ürünün hiçbirinde Trendyol yorum servisine ulaşılamadı. `
        + `İlk hata: ${failureExamples[0] || 'Yanıt alınamadı.'}`
      );
    }

    await companionPost('review_scan_ingest', {
      sync_run_id: syncRunId,
      reviews: [],
      total_products: totalProducts,
      processed_products: processedProducts,
      completed: true,
    });

    return {
      ok: true,
      sync_run_id: syncRunId,
      message: `${storeName} için ${totalProducts} ürün tarandı: ${totalNew} yeni, ${totalUpdated} güncellenen, ${ignoredSellerReviews} farklı satıcı yorumu dışlandı.`,
      stats: {
        total_products: totalProducts,
        processed_products: processedProducts,
        new_reviews: totalNew,
        updated_reviews: totalUpdated,
        spam_detected: totalSpam,
        failed_products: failedProducts,
        ignored_seller_reviews: ignoredSellerReviews,
      },
    };
  } finally {
    if (reviewTabId) {
      await chrome.tabs.remove(reviewTabId).catch(() => undefined);
    }
  }
}

async function reviewStorePreviewFromUrl(sourceUrl, options = {}) {
  const storeUrl = String(options.store_url || sourceUrl || '').trim();
  const merchantId = String(options.merchant_id || '').trim();
  const context = await openReviewStoreContext(storeUrl, merchantId, 500);

  try {
    return {
      ok: true,
      store_id: context.store_id,
      store_name: context.store_name || String(options.store_name || ''),
      store_url: context.store_url,
      product_count: context.products.length,
      sample_products: context.products.slice(0, 6),
    };
  } finally {
    await chrome.tabs.remove(context.tab_id).catch(() => undefined);
  }
}

async function openReviewStoreContext(storeUrl, merchantId, maxProducts) {
  if (!/^\d{2,20}$/.test(String(merchantId || ''))) {
    throw new Error('Geçerli bir Trendyol merchant ID girin.');
  }

  const targetUrl = `https://www.trendyol.com/sr?mid=${encodeURIComponent(merchantId)}&os=1`;
  const tab = await chrome.tabs.create({ url: targetUrl, active: false });
  if (!tab?.id) throw new Error('Trendyol mağaza doğrulama sekmesi açılamadı.');

  try {
    await waitForTabComplete(tab.id, 30000);
    await waitForStoreProducts(tab.id, 20000);
    await scrollForMoreProducts(tab.id, 20);
    const response = await sendTrendyolTabMessage(tab.id, {
      type: 'ZOLM_BOOSTER_REVIEW_PRODUCT_LIST',
      max_products: maxProducts,
    });
    const resolvedStoreId = String(response?.store?.store_id || merchantId);
    const products = Array.isArray(response?.products) ? response.products : [];

    if (resolvedStoreId !== String(merchantId)) {
      throw new Error(`Açılan mağaza ${resolvedStoreId}, seçilen merchant ID ise ${merchantId}.`);
    }
    if (products.length === 0) {
      throw new Error('Seçilen Trendyol mağazasında ürün bulunamadı.');
    }

    return {
      tab_id: tab.id,
      store_id: resolvedStoreId,
      store_name: String(response?.store?.store_name || ''),
      store_url: storeUrl || targetUrl,
      products,
    };
  } catch (error) {
    await chrome.tabs.remove(tab.id).catch(() => undefined);
    throw error;
  }
}

function reviewMatchesSelectedStore(review, merchantId, storeName) {
  const reviewMerchantId = String(review?.seller_id || '').replace(/\D+/g, '');
  if (reviewMerchantId) {
    return reviewMerchantId === String(merchantId);
  }

  const normalizeName = (value) => String(value || '')
    .toLocaleLowerCase('tr-TR')
    .replace(/[^a-z0-9çğıöşü]+/gi, ' ')
    .trim();
  const reviewSeller = normalizeName(review?.seller_name);
  const selectedSeller = normalizeName(storeName);

  return Boolean(reviewSeller && selectedSeller && (reviewSeller === selectedSeller || reviewSeller.includes(selectedSeller) || selectedSeller.includes(reviewSeller)));
}

async function sendTrendyolTabMessage(tabId, message) {
  try {
    return await chrome.tabs.sendMessage(tabId, message);
  } catch (firstError) {
    try {
      await chrome.scripting.executeScript({
        target: { tabId },
        func: () => {
          window['zolm-trendyol-booster-panel'] = null;
          document.getElementById('zolm-trendyol-booster-panel')?.remove();
        },
      });
      await chrome.scripting.executeScript({
        target: { tabId },
        files: ['content.js'],
      });
      await delay(150);

      return await chrome.tabs.sendMessage(tabId, message);
    } catch (retryError) {
      const detail = retryError instanceof Error
        ? retryError.message
        : (firstError instanceof Error ? firstError.message : 'Bilinmeyen bağlantı hatası.');

      throw new Error(`Trendyol sekmesiyle bağlantı kurulamadı. Sekmeyi yenileyip tekrar deneyin. Ayrıntı: ${detail}`);
    }
  }
}



async function enrichStoreScanItems(items, limit = STORE_DETAIL_ENRICH_LIMIT) {
  const normalizedItems = Array.isArray(items) ? items.filter(Boolean) : [];
  const results = normalizedItems.map((item) => ({ ...item, enrichment_status: 'list_card' }));
  const candidates = normalizedItems
    .map((item, index) => ({ item, index }))
    .filter(({ item }) => String(item?.source_url || '').includes('-p-'))
    .slice(0, Math.max(0, limit));

  if (candidates.length === 0) {
    return results;
  }

  let cursor = 0;
  const workerCount = Math.min(STORE_DETAIL_ENRICH_WORKERS, candidates.length);

  const worker = async () => {
    while (cursor < candidates.length) {
      const current = candidates[cursor];
      cursor += 1;

      try {
        const payload = await productPayloadFromUrl(current.item.source_url, false);
        const page = payload?.page || {};
        const expectedProductId = String(current.item.trendyol_product_id || productIdFromUrl(current.item.source_url) || '').trim();
        const actualProductId = String(
          page.trendyol_product_id ||
          payload?.trendyol_product_id ||
          productIdFromUrl(payload?.source_url || page.source_url || '') ||
          ''
        ).trim();

        if (!expectedProductId || !actualProductId || expectedProductId !== actualProductId) {
          results[current.index] = {
            ...current.item,
            enrichment_status: 'mismatch',
            enrichment_source: 'product_detail',
            enrichment_error: actualProductId
              ? `Ürün detayı farklı ürün döndürdü (${actualProductId}); liste kartı korundu.`
              : 'Ürün detayı kimliği doğrulanamadı; liste kartı korundu.',
            enrichment_checked_at: new Date().toISOString(),
          };
          continue;
        }

        const metrics = payload?.metrics || {};
        const sellers = Array.isArray(page.sellers) ? page.sellers : [];
        const firstSeller = sellers[0] || {};
        const promotions = sanitizeCampaignBadges(page.promotions);
        const stockQuantity = integerOrNull(page.total_stock ?? metrics.stock_quantity ?? current.item.total_stock ?? current.item.stock_quantity);
        const favoriteCount = integerOrNull(metrics.favorite_count ?? page.favorite_count ?? current.item.favorite_count);
        const reviewCount = integerOrNull(metrics.review_count ?? metrics.evaluation_count ?? page.review_count ?? current.item.review_count);
        const rating = decimalOrNull(metrics.average_rating ?? page.average_rating ?? current.item.rating);
        const livePrice = moneyOrNull(page.sale_price ?? current.item.sale_price);

        results[current.index] = {
          ...current.item,
          title: String(page.title || current.item.title || '').slice(0, 500),
          brand: String(page.brand || current.item.brand || '').slice(0, 120),
          category_name: String(page.category_name || current.item.category_name || '').slice(0, 180),
          image_url: String(page.image_url || current.item.image_url || '').slice(0, 1000),
          sale_price: livePrice !== null && livePrice > 0 ? livePrice : current.item.sale_price,
          rating: rating ?? current.item.rating ?? null,
          review_count: reviewCount ?? current.item.review_count ?? null,
          favorite_count: favoriteCount ?? current.item.favorite_count ?? null,
          favorite_precision: metrics.favorite_precision || page.favorite_precision || current.item.favorite_precision || '',
          total_stock: stockQuantity,
          stock_quantity: stockQuantity,
          stock_status: String(page.stock_status || current.item.stock_status || (stockQuantity !== null ? 'in_stock' : 'unknown')).slice(0, 80),
          seller_id: String(firstSeller.seller_id || page.seller_id || current.item.seller_id || '').slice(0, 80),
          seller_name: String(firstSeller.seller_name || page.seller_name || current.item.seller_name || '').slice(0, 180),
          campaign_badges: sanitizeCampaignBadges([...(current.item.campaign_badges || []), ...promotions]).slice(0, 8),
          sellers,
          enrichment_status: 'enriched',
          enrichment_source: 'product_detail',
          enrichment_checked_at: new Date().toISOString(),
        };
      } catch (error) {
        results[current.index] = {
          ...current.item,
          enrichment_status: 'partial',
          enrichment_source: 'product_detail',
          enrichment_error: error instanceof Error ? error.message : 'Ürün detayı okunamadı.',
          enrichment_checked_at: new Date().toISOString(),
        };
      }
    }
  };

  await Promise.all(Array.from({ length: workerCount }, () => worker()));

  return results;
}

function productIdFromUrl(value) {
  const match = String(value || '').match(/-p-(\d+)/i);
  return match ? match[1] : '';
}

function sanitizeCampaignBadges(values) {
  return uniqueStrings(Array.isArray(values) ? values.map(normalizeCampaignBadge).filter(Boolean) : []).slice(0, 8);
}

function normalizeCampaignBadge(value) {
  const text = String(value || '')
    .replace(/\s+/g, ' ')
    .replace(/KUPONLUÜRÜN/gi, 'Kuponlu Ürün')
    .replace(/Sepette(?=\d)/gi, 'Sepette ')
    .replace(/([a-zığüşöç])([A-ZİĞÜŞÖÇ])/g, '$1 $2')
    .trim();

  if (!text || text.length < 4) return '';
  if (/Trendyol'da Satış Yap|Hakkımızda|Yardım\s*&\s*Destek|Ürün,\s*kategori|Giriş Yap|Sepete Ekle|Alışveriş Kredisi|Temel Kavr/i.test(text)) return '';
  if (/^Sepette\s*[\d.,]+\s*TL(?:\s*[\d.,]+\s*TL)?$/i.test(text)) return '';
  const patterns = [
    /\d+[\d.]*\s*TL'?ye\s+%\d+\s*İndirim/i,
    /\d+[\d.]*\s*TL'?ye\s+(?:%\d+\s*)?\d*[\d.]*\s*TL?\s*İndirim/i,
    /\d+[\d.]*\s*TL\s+ve\s+Üzeri\s+Kargo\s+Bedava/i,
    /\d+[\d.]*\s*TL\s+Kupon/i,
    /En\s+Çok\s+(?:Satan|Satılan|Ziyaret\s+Edilen)\s+#?\d+\.?\s*Ürün/i,
    /Trendyol\s+Plus'?a\s+Özel\s+Fiyat/i,
    /Son\s+\d+\s+Günün\s+En\s+Düşük\s+Fiyatı/i,
    /Flaş\s+Ürün/i,
    /Avantajlı\s+Ürün/i,
    /Kargo\s+Bedava/i,
    /Kupon\s+Fırsatı/i,
    /Kuponlu\s+Ürün/i,
    /Birlikte\s+Al\s+Kazan/i,
    /Çok\s+Al\s+Az\s+Öde/i,
    /Fenomen\s+Seçimi/i,
    /Yetkili\s+Satıcı/i,
  ];
  const match = patterns.map((pattern) => text.match(pattern)).find(Boolean);

  return match ? match[0].replace(/\s+/g, ' ').trim() : '';
}

function uniqueStrings(values) {
  const seen = new Set();
  return (Array.isArray(values) ? values : [])
    .map((value) => String(value || '').replace(/\s+/g, ' ').trim())
    .filter((value) => {
      if (!value || seen.has(value)) return false;
      seen.add(value);
      return true;
    });
}

function integerOrNull(value) {
  if (value === null || value === undefined || value === '') return null;
  const number = Number.parseInt(String(value).replace(/\D+/g, ''), 10);
  return Number.isFinite(number) ? Math.max(0, number) : null;
}

function decimalOrNull(value) {
  if (value === null || value === undefined || value === '') return null;
  const number = Number.parseFloat(String(value).replace(',', '.'));
  return Number.isFinite(number) ? number : null;
}

function moneyOrNull(value) {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  const text = String(value).replace(/[^\d,.\-]/g, '');
  if (!text) return null;
  const lastComma = text.lastIndexOf(',');
  const lastDot = text.lastIndexOf('.');
  const normalized = lastComma !== -1 && lastDot !== -1
    ? (lastComma > lastDot ? text.replace(/\./g, '').replace(',', '.') : text.replace(/,/g, ''))
    : text.replace(',', '.');
  const number = Number.parseFloat(normalized);
  return Number.isFinite(number) ? number : null;
}

async function supplierResearchFromUrl(sourceUrl) {
  const payload = await supplierResearchPayloadFromUrl(sourceUrl);
  const response = await companionPost('market_research', payload);

  return {
    ...response,
    source: 'browser_bridge',
    google_result_count: Array.isArray(payload.offers) ? payload.offers.length : 0,
  };
}

async function supplierResearchPayloadFromUrl(sourceUrl) {
  const payload = await productPayloadFromUrl(sourceUrl, true);
  const page = { ...(payload.page || {}) };
  const metrics = payload.metrics || {};
  page.favorite_count = metrics.favorite_count ?? page.favorite_count ?? null;
  page.review_count = metrics.review_count ?? page.review_count ?? null;
  page.average_rating = metrics.average_rating ?? page.average_rating ?? null;

  const title = String(page.title || '').trim();
  const brand = String(page.brand || '').trim();
  if (!title) {
    throw new Error('Trendyol ürün adı okunamadı. Ürün sayfasını yenileyip tekrar deneyin.');
  }

  const search = await googleMarketplaceResearch(title, brand);
  return {
    source_url: String(payload.source_url || sourceUrl),
    page,
    offers: search.offers,
    search_query: search.query,
    search_url: search.search_url,
    searched_platforms: search.searched_platforms,
  };
}

async function googleMarketplaceResearch(title, brand) {
  const productName = title.toLocaleLowerCase('tr-TR').includes(brand.toLocaleLowerCase('tr-TR'))
    ? title.replace(/\s+/g, ' ')
    : `${brand} ${title}`.trim().replace(/\s+/g, ' ');
  const shoppingRows = await googleShoppingResultsForQuery(productName).catch(() => []);
  const siteRows = await googleTargetedMarketplaceResults(productName, brand).catch(() => []);
  const rows = [...shoppingRows, ...siteRows];
  const seen = new Set();
  const offers = rows
    .map((row) => ({ ...row, match_score: scoreShoppingMatch(productName, brand, String(row.title || '')) }))
    .filter((row) => row.match_score >= 85)
    .filter((row) => {
      const key = `${String(row.source_url || '').replace(/[?#].*$/, '')}|${String(row.seller_name || '')}|${String(row.title || '')}`;
      if (!row.source_url || seen.has(key)) return false;
      seen.add(key);
      return true;
    })
    .map((row, index) => ({ ...row, rank: index + 1 }));

  return {
    query: productName,
    search_url: `https://www.google.com/search?hl=tr&gl=tr&udm=28&q=${encodeURIComponent(productName)}`,
    offers: offers.slice(0, 40),
    searched_platforms: MARKETPLACE_TARGETS.map((target) => target[0]),
  };
}

async function googleTargetedMarketplaceResults(productName, brand) {
  const modelCodes = shoppingModelCodes(productName);
  const identityQuery = modelCodes.length > 0
    ? `${brand ? `"${brand}" ` : ''}${modelCodes.map((code) => `"${code}"`).join(' ')}`
    : `"${productName}"`;
  const targets = MARKETPLACE_TARGETS.filter((target) => target[0] !== 'trendyol');
  const rows = [];

  for (const group of chunk(targets, 4).slice(0, 4)) {
    const domainQuery = group
      .flatMap((target) => target[2])
      .map((domain) => `site:${domain}`)
      .join(' OR ');
    const query = `${identityQuery} (${domainQuery})`;
    const groupRows = await googleWebResultsForQuery(query).catch(() => []);
    rows.push(...groupRows);
  }

  return dedupeRows(rows).slice(0, 50);
}

async function googleWebResultsForQuery(query) {
  let tabId = null;
  try {
    const url = `https://www.google.com/search?hl=tr&gl=tr&q=${encodeURIComponent(query)}`;
    const tab = await chrome.tabs.create({ url, active: false });
    tabId = tab.id;
    if (!tabId) throw new Error('Google hedef pazaryeri sekmesi açılamadı.');

    await waitForTabComplete(tabId, 30000);
    await delay(1500);
    const execution = await chrome.scripting.executeScript({
      target: { tabId },
      func: (targets) => {
        const clean = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const money = (text) => {
          const match = clean(text).match(/(?:₺\s*([\d.]+,\d{2})|([\d.]+,\d{2})\s*(?:TL|₺))/u);
          if (!match) return 0;
          const value = Number.parseFloat(String(match[1] || match[2]).replace(/\./g, '').replace(',', '.'));
          return Number.isFinite(value) ? value : 0;
        };
        const unwrapGoogleUrl = (value) => {
          try {
            const parsed = new URL(value, location.origin);
            if (parsed.hostname.endsWith('google.com') && parsed.pathname === '/url') {
              return parsed.searchParams.get('url') || parsed.searchParams.get('q') || parsed.href;
            }
            return parsed.href;
          } catch (error) {
            return '';
          }
        };
        const detect = (urlValue) => {
          let host = '';
          try { host = new URL(urlValue).hostname.replace(/^www\./, '').toLowerCase(); } catch (error) { host = ''; }
          const found = targets.find((target) => target[2].some((domain) => host === domain || host.endsWith(`.${domain}`)));
          return found ? { key: found[0], label: found[1] } : null;
        };
        const rows = [];
        const seen = new Set();
        const links = Array.from(document.querySelectorAll('a[href]'));

        links.forEach((anchor) => {
          const href = unwrapGoogleUrl(anchor.href || anchor.getAttribute('href') || '');
          if (!href) return;
          const detected = detect(href);
          if (!detected) return;

          const titleNode = anchor.querySelector('h3') || anchor.closest('a')?.querySelector('h3');
          const title = clean(titleNode?.textContent || anchor.textContent || '');
          if (!title || title.length < 6) return;

          const result = anchor.closest('.g, [data-sokoban-container], [data-hveid], div') || anchor.parentElement;
          const text = clean(result?.innerText || result?.textContent || title);
          const key = `${detected.key}|${href.replace(/[?#].*$/, '')}|${title}`.toLocaleLowerCase('tr-TR');
          if (seen.has(key)) return;
          seen.add(key);

          const availability = /stokta|in stock/i.test(text)
            ? 'in_stock'
            : (/tükendi|stokta yok|out of stock/i.test(text) ? 'out_of_stock' : 'unknown');

          rows.push({
            platform: detected.key,
            platform_label: detected.label,
            seller_name: detected.label,
            title: title.slice(0, 500),
            source_url: href.slice(0, 1000),
            sale_price: money(text),
            availability,
            source_type: 'google_site_search',
            snippet: text.slice(0, 1000),
          });
        });

        return rows.slice(0, 30);
      },
      args: [MARKETPLACE_TARGETS],
    });

    return Array.isArray(execution?.[0]?.result) ? execution[0].result : [];
  } finally {
    if (tabId) await chrome.tabs.remove(tabId).catch(() => undefined);
  }
}

function chunk(values, size) {
  const groups = [];
  for (let index = 0; index < values.length; index += size) {
    groups.push(values.slice(index, index + size));
  }

  return groups;
}

function dedupeRows(rows) {
  const seen = new Set();

  return rows.filter((row) => {
    const key = `${String(row.platform || '')}|${String(row.source_url || '').replace(/[?#].*$/, '')}|${String(row.title || '')}`.toLocaleLowerCase('tr-TR');
    if (!row.source_url || !row.title || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function canonicalShoppingProductName(title, brand) {
  let normalizedTitle = normalizeShoppingIdentity(title);
  const normalizedBrand = normalizeShoppingIdentity(brand);

  if (normalizedBrand && !new RegExp(`(?:^| )${normalizedBrand.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?: |$)`).test(normalizedTitle)) {
    normalizedTitle = `${normalizedBrand} ${normalizedTitle}`.trim();
  }

  return normalizedTitle;
}

function normalizeShoppingIdentity(value) {
  return String(value || '')
    .toLocaleLowerCase('tr-TR')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/ı/g, 'i')
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function scoreShoppingMatch(sourceTitle, brand, candidateTitle) {
  const sourceCanonical = canonicalShoppingProductName(sourceTitle, brand);
  const candidateCanonical = canonicalShoppingProductName(candidateTitle, brand);
  if (!sourceCanonical || !candidateCanonical) return 0;
  if (sourceCanonical === candidateCanonical) return 100;

  const source = shoppingProductSignature(sourceTitle, brand);
  const candidate = shoppingProductSignature(candidateTitle, brand);
  if (!source.tokens.length || !candidate.tokens.length) return 0;

  const matchedModels = source.model_codes.filter((code) => candidate.model_codes.includes(code));
  const hasModel = source.model_codes.length > 0;
  if (hasModel && matchedModels.length === 0) return 0;

  if (hasModel && candidate.model_codes.length > 0) {
    const conflictingModels = candidate.model_codes.filter((code) => !source.model_codes.includes(code));
    if (conflictingModels.length > 0 && matchedModels.length < source.model_codes.length) return 0;
  }

  let brandScore = 0;
  if (source.brand) {
    brandScore = candidate.has_brand ? 15 : (matchedModels.length > 0 ? 8 : 0);
    if (brandScore === 0 && !hasModel) return 0;
  }

  const modelScore = hasModel ? Math.round(30 + (10 * matchedModels.length / Math.max(1, source.model_codes.length))) : 0;
  const unitRatio = overlapShoppingRatio(source.unit_features, candidate.unit_features);
  if (!hasModel && source.unit_features.length > 0 && unitRatio < 0.45) return 0;
  const unitScore = Math.round(20 * unitRatio);

  const tokenRatio = overlapShoppingRatio(source.tokens, candidate.tokens);
  if (!hasModel && tokenRatio < 0.72) return 0;
  const tokenScore = Math.round(25 * tokenRatio);

  let score = brandScore + modelScore + unitScore + tokenScore;
  if (hasModel && matchedModels.length > 0 && tokenRatio >= 0.35) score = Math.max(score, 88);
  if (!hasModel && brandScore > 0 && tokenRatio >= 0.9 && (!source.unit_features.length || unitRatio >= 0.6)) score = Math.max(score, 92);

  return Math.max(0, Math.min(99, score));
}

function shoppingProductSignature(title, brand) {
  const canonical = canonicalShoppingProductName(title, brand);
  const normalizedBrand = normalizeShoppingIdentity(brand);
  const normalizedTitle = normalizeShoppingIdentity(title);
  const tokens = Array.from(new Set(canonical.split(' ')
    .filter((token) => token && token !== normalizedBrand && !isWeakShoppingToken(token))));

  return {
    brand: normalizedBrand,
    has_brand: !normalizedBrand || new RegExp(`(?:^| )${normalizedBrand.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?: |$)`).test(normalizedTitle),
    tokens,
    model_codes: shoppingModelCodes(title),
    unit_features: shoppingUnitFeatures(title),
  };
}

function shoppingModelCodes(title) {
  const ascii = String(title || '')
    .toLocaleUpperCase('tr-TR')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/İ/g, 'I')
    .replace(/Ş/g, 'S')
    .replace(/Ğ/g, 'G')
    .replace(/Ü/g, 'U')
    .replace(/Ö/g, 'O')
    .replace(/Ç/g, 'C');
  const matches = ascii.match(/\b[A-Z]{1,8}\s?-?\s?\d{2,}[A-Z0-9]*(?:-[A-Z0-9]{1,8})*\b/g) || [];
  return Array.from(new Set(matches
    .map((code) => code.replace(/[^A-Z0-9]+/g, ''))
    .filter((code) => code && !/^(CM|MM|KG|GR|LT|ML|W|V)\d+$/.test(code))));
}

function shoppingUnitFeatures(title) {
  const normalized = normalizeShoppingIdentity(title);
  const features = [];
  const patterns = [
    [/\b(\d{1,3})\s*(?:inc|inch)\b/g, 'inc'],
    [/\b(\d{1,4})\s*(?:w|watt)\b/g, 'w'],
    [/\b(\d{1,4})\s*(?:cm|santim|santimetre)\b/g, 'cm'],
    [/\b(\d{1,3})\s*(?:kg|kilo)\b/g, 'kg'],
    [/\b(\d{1,2})\s*(?:pervane|pervaneli|kanat)\b/g, 'pervane'],
    [/\b(\d{1,2})\s*(?:kademe|kademeli|hiz|hizli)\b/g, 'kademe'],
  ];

  patterns.forEach(([pattern, label]) => {
    for (const match of normalized.matchAll(pattern)) {
      features.push(`${label}:${Number.parseInt(match[1], 10)}`);
    }
  });

  return Array.from(new Set(features));
}

function overlapShoppingRatio(source, candidate) {
  if (!source.length) return 1;
  return source.filter((value) => candidate.includes(value)).length / Math.max(1, source.length);
}

function isWeakShoppingToken(token) {
  return /^\d+$/.test(token) || [
    'urun', 'urunu', 'urunler', 'model', 'modelleri', 'fiyat', 'fiyati', 'fiyatlari',
    'en', 'ucuz', 'kampanya', 'kampanyali', 'indirim', 'indirimli', 'satici', 'magaza',
    'tl', 'try', 'adet', 'set', 'icin', 'ile', 've', 'veya', 'cm', 'mm', 'kg', 'gr',
    'w', 'watt', 'inc', 'inch',
  ].includes(token);
}

async function googleShoppingResultsForQuery(query) {
  let tabId = null;
  try {
    const url = `https://www.google.com/search?hl=tr&gl=tr&udm=28&q=${encodeURIComponent(query)}`;
    const tab = await chrome.tabs.create({ url, active: false });
    tabId = tab.id;
    if (!tabId) throw new Error('Google Alışveriş sekmesi açılamadı.');

    await waitForTabComplete(tabId, 30000);
    await delay(1800);
    await chrome.scripting.executeScript({
      target: { tabId },
      func: () => window.scrollBy(0, Math.max(window.innerHeight, 700)),
    }).catch(() => undefined);
    await delay(700);
    const execution = await chrome.scripting.executeScript({
      target: { tabId },
      func: () => {
        const clean = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const money = (text) => {
          const match = clean(text).match(/(?:₺\s*([\d.]+,\d{2})|([\d.]+,\d{2})\s*(?:TL|₺))/u);
          if (!match) return 0;
          const value = Number.parseFloat(String(match[1] || match[2]).replace(/\./g, '').replace(',', '.'));
          return Number.isFinite(value) ? value : 0;
        };
        const unwrapGoogleUrl = (value) => {
          try {
            const parsed = new URL(value, location.origin);
            if (parsed.hostname.endsWith('google.com') && parsed.pathname === '/url') {
              return parsed.searchParams.get('url') || parsed.searchParams.get('q') || parsed.href;
            }
            return parsed.href;
          } catch (error) {
            return '';
          }
        };
        const platform = (url, seller, cardText) => {
          let host = '';
          try { host = new URL(url).hostname.replace(/^www\./, '').toLowerCase(); } catch (error) { host = ''; }
          const map = [
            ['trendyol', 'Trendyol', ['trendyol.com'], ['trendyol']],
            ['hepsiburada', 'Hepsiburada', ['hepsiburada.com'], ['hepsiburada']],
            ['n11', 'n11', ['n11.com'], ['n11']],
            ['amazon_tr', 'Amazon Türkiye', ['amazon.com.tr'], ['amazon.com.tr', 'amazon türkiye']],
            ['pazarama', 'Pazarama', ['pazarama.com'], ['pazarama']],
            ['pttavm', 'PttAVM / EpttAVM', ['pttavm.com', 'epttavm.com'], ['pttavm', 'epttavm']],
            ['boyner', 'Boyner Pazaryeri', ['boyner.com.tr'], ['boyner']],
            ['teknosa', 'Teknosa Pazaryeri', ['teknosa.com'], ['teknosa']],
            ['mediamarkt', 'MediaMarkt Pazaryeri', ['mediamarkt.com.tr'], ['mediamarkt']],
            ['modanisa', 'Modanisa', ['modanisa.com'], ['modanisa']],
            ['koctas', 'Koçtaş Pazaryeri', ['koctas.com.tr'], ['koçtaş', 'koctas']],
            ['ciceksepeti', 'ÇiçekSepeti', ['ciceksepeti.com'], ['çiçeksepeti', 'ciceksepeti']],
            ['akakce', 'Akakçe', ['akakce.com'], ['akakçe', 'akakce']],
            ['cimri', 'Cimri', ['cimri.com'], ['cimri']],
          ];
          const haystack = `${seller} ${cardText}`.toLocaleLowerCase('tr-TR');
          const found = map.find((entry) => (
            entry[2].some((domain) => host === domain || host.endsWith(`.${domain}`))
            || entry[3].some((label) => haystack.includes(label))
          ));
          return found
            ? { key: found[0], label: found[1] }
            : { key: 'other', label: seller || (host && !host.endsWith('google.com') ? host : 'Diğer mağaza') };
        };
        const sellerFromCard = (card, text) => {
          const node = card.querySelector('.aULzUe, .IuHnof, .E5ocAb, [data-merchant-name], [class*="merchant"], [class*="Merchant"]');
          if (clean(node?.textContent)) return clean(node.textContent).slice(0, 180);
          const known = ['Hepsiburada', 'Trendyol', 'Koçtaş', 'ÇiçekSepeti', 'Amazon.com.tr', 'Pazarama', 'n11', 'PttAVM', 'EpttAVM', 'Boyner', 'Teknosa', 'MediaMarkt', 'Modanisa', 'Akakçe', 'Cimri'];
          return known.find((name) => text.toLocaleLowerCase('tr-TR').includes(name.toLocaleLowerCase('tr-TR'))) || '';
        };
        const rows = [];
        const seen = new Set();
        const surfaces = new Set(Array.from(document.querySelectorAll('[data-docid], .sh-dgr__grid-result, .sh-dlr__list-result, .pla-unit')));
        const titleNodes = Array.from(document.querySelectorAll('.tAxDx, .gkQHve, .sh-np__product-title, h3, [role="heading"]'));

        titleNodes.forEach((titleNode) => {
          const surface = titleNode.closest('[data-docid], .sh-dgr__grid-result, .sh-dlr__list-result, .pla-unit')
            || titleNode.parentElement?.parentElement?.parentElement;
          if (surface) surfaces.add(surface);
        });

        surfaces.forEach((surface) => {
          const text = clean(surface?.innerText || '');
          const titleNode = surface.querySelector('.tAxDx, .gkQHve, .sh-np__product-title, h3, [role="heading"]');
          const title = clean(titleNode?.textContent || '');
          const salePrice = money(text);
          if (!title || salePrice <= 0 || !surface.querySelector('img')) return;

          const links = Array.from(surface.querySelectorAll('a[href]'));
          const preferredLink = links.find((anchor) => {
            const href = unwrapGoogleUrl(anchor.href || '');
            try { return href && !new URL(href).hostname.endsWith('google.com'); } catch (error) { return false; }
          }) || links.find((anchor) => anchor.contains(titleNode)) || links[0];
          const href = unwrapGoogleUrl(preferredLink?.href || '') || location.href;
          const seller = sellerFromCard(surface, text);
          const detected = platform(href, seller, text);
          const key = `${title}|${seller}|${salePrice}`.toLocaleLowerCase('tr-TR');
          if (seen.has(key)) return;
          seen.add(key);
          const availability = /stokta|in stock/i.test(text)
            ? 'in_stock'
            : (/tükendi|stokta yok|out of stock/i.test(text) ? 'out_of_stock' : 'unknown');

          rows.push({
            platform: detected.key,
            platform_label: detected.label,
            seller_name: seller,
            title: title.slice(0, 500),
            source_url: href.slice(0, 1000),
            sale_price: salePrice,
            availability,
            source_type: 'google_shopping',
            image_url: clean(surface.querySelector('img')?.src || '').slice(0, 1000),
            snippet: text.slice(0, 1000),
          });
        });

        return rows.slice(0, 60);
      },
    });

    return Array.isArray(execution?.[0]?.result) ? execution[0].result : [];
  } finally {
    if (tabId) await chrome.tabs.remove(tabId).catch(() => undefined);
  }
}

async function bestsellerFromUrl(sourceUrl, requestedKeyword = '', minPrice = null, maxPrice = null) {
  let url;
  try {
    url = new URL(String(sourceUrl || '').trim());
  } catch (error) {
    throw new Error('Geçerli bir arama linki girin.');
  }

  let createdTabId = null;

  try {
    const keyword = String(requestedKeyword || url.searchParams.get('q') || '').trim();
    const dictionaryMatch = await resolveBestsellerDictionaryMatch(keyword);
    const staticCategory = bestsellerDirectCategory(keyword);

    // Trendyol, arka planda sessiz açılan sekmeleri zaman zaman eksik hydrate ediyor.
    const tab = await chrome.tabs.create({ url: 'about:blank', active: true });
    createdTabId = tab.id;

    if (!createdTabId) {
      throw new Error('Trendyol arama sekmesi açılamadı.');
    }

    let navigationResult = staticCategory
      ? { ok: true, categoryId: staticCategory.categoryId, label: staticCategory.label, source: 'static_category' }
      : await resolveBestsellerCategoryFromSearch(createdTabId, keyword, dictionaryMatch);

    const sourceCandidates = buildBestsellerSourceCandidates(keyword, navigationResult, dictionaryMatch);
    let activeSource = null;
    let lastSourceError = null;

    for (const sourceCandidate of sourceCandidates) {
      try {
        await navigateTab(createdTabId, sourceCandidate.url, 30000);
        await waitForBestsellerCards(createdTabId, sourceCandidate.timeout || 20000);
        activeSource = sourceCandidate;
        break;
      } catch (error) {
        lastSourceError = error;
      }
    }

    if (!activeSource && !navigationResult.ok) {
      const pillResult = await navigateToVisibleBestsellerCategory(createdTabId, keyword, dictionaryMatch);
      if (!pillResult.ok) {
        const hint = dictionaryMatch?.matched_term || dictionaryMatch?.product_group || keyword;
        throw new Error(`"${hint}" için Trendyol kategori bağlantısı bulunamadı. Kelimeyi Trendyol kategori adıyla biraz daha net yazın.`);
      }

      navigationResult = pillResult;
      await delay(1200);
      await waitForBestsellerCards(createdTabId, 15000);
      activeSource = {
        type: 'visible_bestseller',
        url: (await chrome.tabs.get(createdTabId))?.url || '',
      };
    }

    if (!activeSource) {
      throw lastSourceError || new Error('Trendyol Çok Satanlar ürün kartları zamanında yüklenemedi.');
    }

    const cardProducts = await extractBestsellerCards(createdTabId, minPrice, maxPrice);

    if (cardProducts.length === 0) {
      throw new Error('Trendyol Çok Satanlar sayfası açıldı ancak filtrelerle eşleşen ürün bulunamadı. Fiyat aralığını veya kategoriyi değiştirin.');
    }

    const currentTab = await chrome.tabs.get(createdTabId);
    const pageLabel = await readBestsellerPageLabel(createdTabId);
    const matchedLabel = pageLabel
      || navigationResult.label
      || navigationResult.subLabel
      || navigationResult.mainLabel
      || dictionaryMatch?.matched_term
      || keyword;
    const products = await enrichBestsellerProducts(cardProducts);
    const enrichedCount = products.filter((product) => product.enrichment_status === 'enriched').length;
    const fallbackNote = activeSource.type === 'search'
      ? ' Trendyol Çok Satanlar özel kartı bu kategori için yüklenmedi; en çok satan sıralı kategori sayfası kullanıldı.'
      : '';

    return {
      ok: true,
      message: `${matchedLabel ? `${matchedLabel} için ` : ''}${products.length} çok satan ürün getirildi; ${enrichedCount} ürün satıcı, stok ve kampanya verisiyle zenginleştirildi.${fallbackNote}`,
      products,
      matched_category: navigationResult.mainLabel || dictionaryMatch?.category || '',
      matched_sub_category: navigationResult.subLabel || dictionaryMatch?.sub_category || '',
      source_url: currentTab.url || activeSource.url,
      source: 'browser_bridge',
    };
  } finally {
    if (createdTabId) {
      await chrome.tabs.remove(createdTabId).catch(() => undefined);
    }
  }
}

function buildBestsellerUrl(keyword, navigationResult = {}) {
  const bestsellerUrl = new URL('https://www.trendyol.com/cok-satanlar');
  bestsellerUrl.searchParams.set('type', 'bestSeller');

  if (navigationResult?.categoryId) {
    bestsellerUrl.searchParams.set('categoryId', String(navigationResult.categoryId));
    return bestsellerUrl;
  }

  const genderId = bestsellerGenderId(keyword);
  if (genderId) {
    bestsellerUrl.searchParams.set('webGenderId', genderId);
  }

  return bestsellerUrl;
}

function buildBestsellerSearchUrl(term, baseUrl = '') {
  let searchUrl;

  try {
    searchUrl = baseUrl ? new URL(baseUrl) : new URL('https://www.trendyol.com/sr');
  } catch (error) {
    searchUrl = new URL('https://www.trendyol.com/sr');
  }

  const keyword = String(term || searchUrl.searchParams.get('q') || searchUrl.searchParams.get('st') || '').trim();
  searchUrl.pathname = searchUrl.pathname || '/sr';

  if (keyword) {
    searchUrl.searchParams.set('q', keyword);
    searchUrl.searchParams.set('qt', keyword);
    searchUrl.searchParams.set('st', keyword);
  }

  searchUrl.searchParams.set('sst', 'BEST_SELLER');
  searchUrl.searchParams.set('os', '1');

  return searchUrl;
}

function buildBestsellerSourceCandidates(keyword, navigationResult = {}, dictionaryMatch = null) {
  const candidates = [];
  const pushCandidate = (candidate) => {
    if (!candidate?.url || candidates.some((item) => item.url === candidate.url)) {
      return;
    }

    candidates.push(candidate);
  };

  const bestsellerUrl = buildBestsellerUrl(keyword, navigationResult);
  if (navigationResult?.categoryId || !navigationResult?.searchUrl) {
    pushCandidate({ type: 'bestseller', url: bestsellerUrl.href, timeout: 20000 });
  }

  if (navigationResult?.searchUrl) {
    pushCandidate({
      type: 'search',
      url: buildBestsellerSearchUrl(keyword, navigationResult.searchUrl).href,
      timeout: 22000,
    });
  }

  const fallbackTerms = [
    keyword,
    dictionaryMatch?.matched_term,
    dictionaryMatch?.product_group,
    dictionaryMatch?.sub_category,
  ]
    .map((term) => String(term || '').trim())
    .filter((term) => term.length >= 2);

  for (const term of [...new Set(fallbackTerms)]) {
    pushCandidate({ type: 'search', url: buildBestsellerSearchUrl(term).href, timeout: 22000 });
  }

  return candidates;
}

async function navigateTab(tabId, url, timeoutMs = 30000) {
  await chrome.tabs.update(tabId, { url });
  await waitForTabComplete(tabId, timeoutMs);
}

async function keywordTrackingFromUrl(sourceUrl, keywords) {
  const url = normalizeTrendyolUrl(sourceUrl);
  const match = url.match(/-p-(\d+)/i);
  const productId = match ? match[1] : null;

  if (!productId) {
    throw new Error('Geçerli bir Trendyol ürün linki veya IDsi bulunamadı.');
  }

  // Önce ürün detayını al
  const productPayload = await productPayloadFromUrl(sourceUrl, false);
  const keywordResults = [];

  for (const kw of keywords) {
    if (!kw || String(kw).trim().length === 0) continue;
    const cleanKw = String(kw).trim();

    try {
      const searchUrl = `https://www.trendyol.com/sr?q=${encodeURIComponent(cleanKw)}`;
      const response = await fetch(searchUrl, {
        headers: {
          'accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
          'accept-language': 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
          'cache-control': 'max-age=0',
          'sec-fetch-dest': 'document',
          'sec-fetch-mode': 'navigate',
          'sec-fetch-site': 'none',
          'upgrade-insecure-requests': '1'
        }
      });

      if (!response.ok) {
        throw new Error(`Arama sayfası okunamadı (HTTP ${response.status})`);
      }

      const html = await response.text();
      
      const totalResultsMatch = html.match(/"totalCount":(\d+)/i) || html.match(/([\d.,]+)\s*sonuç/i) || html.match(/([\d.,]+)\s*ürün/i);
      const totalResults = totalResultsMatch ? parseInt(totalResultsMatch[1].replace(/\./g, ''), 10) : 0;

      const regex = /-p-(\d+)/gi;
      let htmlMatch;
      let foundRank = null;
      const seenIds = new Set();
      
      while ((htmlMatch = regex.exec(html)) !== null) {
        const foundId = htmlMatch[1];
        if (!seenIds.has(foundId)) {
          seenIds.add(foundId);

          if (foundId === String(productId)) {
            foundRank = seenIds.size;
            break;
          }
        }
        
        if (seenIds.size >= 120) break;
      }

      keywordResults.push({
        keyword: cleanKw,
        rank: foundRank,
        result_count: totalResults,
        checked_count: seenIds.size,
        scan_limit: 120,
        status: foundRank !== null ? 'found' : 'not_found',
      });
    } catch (err) {
      keywordResults.push({
        keyword: cleanKw,
        rank: null,
        result_count: 0,
        status: 'error',
        note: err.message,
      });
    }
  }

  return {
    ok: true,
    message: 'Kelime takibi başarıyla tamamlandı.',
    product: productPayload,
    keywords: keywordResults,
  };
}

async function keywordLookupFromUrl(sourceUrl, requestedKeyword = '') {
  let url;

  try {
    url = new URL(normalizeTrendyolUrl(sourceUrl));
  } catch (error) {
    throw new Error('Geçerli bir Trendyol arama linki oluşturulamadı.');
  }

  const keyword = String(requestedKeyword || url.searchParams.get('q') || '').replace(/\s+/g, ' ').trim();
  if (keyword.length < 2) {
    throw new Error('Anahtar kelime en az 2 karakter olmalı.');
  }

  url.pathname = '/sr';
  url.search = '';
  url.searchParams.set('q', keyword);

  let createdTabId = null;

  try {
    const tab = await chrome.tabs.create({ url: url.href, active: false });
    createdTabId = tab.id;

    if (!createdTabId) {
      throw new Error('Trendyol arama sekmesi açılamadı.');
    }

    await waitForTabComplete(createdTabId, 30000);
    await waitForBestsellerCards(createdTabId, 20000);

    const [execution] = await chrome.scripting.executeScript({
      target: { tabId: createdTabId },
      args: [keyword],
      func: (searchKeyword) => {
        const clean = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const productLinks = Array.from(document.querySelectorAll([
          'main a[data-testid="product-card-link"]',
          'main a.product-card-link',
          'main a.product-card[href*="-p-"]',
          '.p-card-wrppr a[href*="-p-"]',
          '.prdct-cntnr-wrppr a[href*="-p-"]',
          'main a[href*="-p-"]',
        ].join(', ')));
        const seen = new Set();
        const topProducts = [];

        for (const link of productLinks) {
          const href = String(link.getAttribute('href') || '');
          const productId = href.match(/-p-(\d+)/i)?.[1] || '';
          if (!productId || seen.has(productId)) continue;

          const root = link.closest(
            '[data-testid*="product-card"], .product-card, .p-card-wrppr, .p-card-chldrn-cntnr, article, li',
          ) || link;
          const heading = root.querySelector('h2, [role="heading"], .product-name, .prdct-desc-cntnr-name, [class*="product-name"]');
          const image = root.querySelector('img[alt]');
          const sourceUrl = new URL(href, window.location.origin);
          const title = clean(
            heading?.textContent
            || link.getAttribute('title')
            || image?.getAttribute('alt')
            || '',
          ).slice(0, 500);
          const brandSlug = decodeURIComponent(sourceUrl.pathname.split('/').filter(Boolean)[0] || '');
          const brand = brandSlug
            .split('-')
            .filter(Boolean)
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ')
            .slice(0, 120);

          if (!title) continue;

          seen.add(productId);
          topProducts.push({
            trendyol_product_id: productId,
            source_url: sourceUrl.href,
            title,
            brand,
            rank: topProducts.length + 1,
          });

          if (topProducts.length >= 40) break;
        }

        const mainText = clean(document.querySelector('main')?.innerText || '');
        const resultCountText = mainText.match(/([\d.]+)\+?\s*Ürün\b/i)?.[1] || '';
        const stateText = Array.from(document.scripts)
          .map((script) => String(script.textContent || ''))
          .find((text) => /"totalCount"\s*:\s*\d+/i.test(text)) || '';
        const stateCountText = stateText.match(/"totalCount"\s*:\s*(\d+)/i)?.[1] || '';
        const parsedCount = Number.parseInt(String(resultCountText || stateCountText).replace(/\./g, ''), 10);

        return {
          keyword: clean(searchKeyword).slice(0, 180),
          source_url: window.location.href,
          product_ids: topProducts.map((product) => product.trendyol_product_id),
          result_count: Number.isFinite(parsedCount) ? parsedCount : topProducts.length,
          checked_result_count: topProducts.length,
          scan_limit: 40,
          top_products: topProducts,
        };
      },
    });

    const data = execution?.result || null;
    if (!data || !Array.isArray(data.top_products) || data.top_products.length === 0) {
      throw new Error('Trendyol arama sayfası açıldı ancak ürün kartları okunamadı.');
    }

    return {
      ok: true,
      message: `${data.top_products.length} ürün başlığı tarayıcıdan okundu.`,
      data,
      source: 'browser_bridge',
    };
  } finally {
    if (createdTabId) {
      await chrome.tabs.remove(createdTabId).catch(() => undefined);
    }
  }
}

function bestsellerDirectCategory(keyword) {
  const normalized = normalizeBestsellerText(keyword);
  const categoryMap = [
    {
      label: 'Puflar',
      categoryId: '104493',
      patterns: [
        /\bpuf\b/,
        /\bpuflar\b/,
        /\bpuf koltuk\b/,
        /\bpuf bench\b/,
        /\bbench puf\b/,
        /\bpuf minder\b/,
        /\bpuf tabure\b/,
      ],
    },
    {
      label: 'Berjerler',
      categoryId: '104495',
      patterns: [
        /\bberjer\b/,
        /\bberjerler\b/,
        /\btekli koltuk\b/,
        /\bdinlenme koltugu\b/,
      ],
    },
    {
      label: 'Kanepeler',
      categoryId: '104491',
      patterns: [
        /\bkanepe\b/,
        /\bkanepeler\b/,
        /\bcekyat\b/,
        /\bcek yat\b/,
        /\bsofa\b/,
        /\bchester\b/,
      ],
    },
  ];

  return categoryMap.find((category) => category.patterns.some((pattern) => pattern.test(normalized))) || null;
}

let bestsellerDictionaryPromise = null;

async function loadBestsellerCategoryDictionary() {
  if (!bestsellerDictionaryPromise) {
    bestsellerDictionaryPromise = fetch(chrome.runtime.getURL('trendyol-category-dictionary.json'))
      .then((response) => (response.ok ? response.json() : { entries: [] }))
      .catch(() => ({ entries: [] }));
  }

  const dictionary = await bestsellerDictionaryPromise;
  return Array.isArray(dictionary?.entries) ? dictionary.entries : [];
}

async function resolveBestsellerDictionaryMatch(keyword) {
  const query = normalizeBestsellerText(keyword);
  if (query.length < 2) {
    return null;
  }

  const entries = await loadBestsellerCategoryDictionary();
  let best = null;

  for (const entry of entries) {
    const terms = Array.isArray(entry.normalized_terms) ? entry.normalized_terms : [];
    for (let index = 0; index < terms.length; index += 1) {
      const term = String(terms[index] || '');
      if (!term) continue;

      const score = bestsellerDictionaryScore(query, term);
      if (score <= 0) continue;

      if (!best || score > best.score) {
        best = {
          score,
          matched_term: String((entry.terms || [])[index] || term),
          category: String(entry.category || ''),
          sub_category: String(entry.sub_category || ''),
          product_group: String(entry.product_group || ''),
        };
      }
    }
  }

  return best && best.score >= 40 ? best : null;
}

function bestsellerDictionaryScore(query, term) {
  if (query === term) {
    return 1000 + term.length;
  }

  if (query.length >= 3 && term.includes(query)) {
    return 820 + query.length;
  }

  if (term.length >= 3 && query.includes(term)) {
    return 760 + term.length;
  }

  const queryTokens = query.split(' ').filter(Boolean);
  const termTokens = term.split(' ').filter(Boolean);
  if (queryTokens.length === 0 || termTokens.length === 0) {
    return 0;
  }

  const overlap = queryTokens.filter((token) => termTokens.includes(token)).length;
  if (overlap === 0) {
    return 0;
  }

  return 35 + (overlap / queryTokens.length) * 140 + Math.min(25, term.length / 2);
}

async function resolveBestsellerCategoryFromSearch(tabId, keyword, dictionaryMatch = null) {
  const terms = [
    keyword,
    dictionaryMatch?.matched_term,
    dictionaryMatch?.product_group,
    dictionaryMatch?.sub_category,
  ]
    .map((term) => String(term || '').trim())
    .filter((term) => term.length >= 2);
  const uniqueTerms = [...new Set(terms)];

  for (const term of uniqueTerms) {
    const searchUrl = new URL('https://www.trendyol.com/sr');
    searchUrl.searchParams.set('q', term);
    searchUrl.searchParams.set('sst', 'BEST_SELLER');

    try {
      await navigateTab(tabId, searchUrl.href, 30000);
      const candidate = await waitForSearchCategoryCandidate(tabId, keyword, dictionaryMatch, 14000);
      if (candidate?.categoryId) {
        return {
          ok: true,
          categoryId: candidate.categoryId,
          label: candidate.label || dictionaryMatch?.matched_term || term,
          mainLabel: dictionaryMatch?.category || '',
          subLabel: dictionaryMatch?.sub_category || '',
          searchUrl: candidate.href || searchUrl.href,
          source: 'trendyol_search_category',
        };
      }

      const clickedCandidate = await clickSearchCategoryFilter(tabId, keyword, dictionaryMatch);
      if (clickedCandidate?.categoryId || clickedCandidate?.href) {
        return {
          ok: true,
          categoryId: clickedCandidate.categoryId || '',
          label: clickedCandidate.label || dictionaryMatch?.matched_term || term,
          mainLabel: dictionaryMatch?.category || '',
          subLabel: dictionaryMatch?.sub_category || '',
          searchUrl: clickedCandidate.href || searchUrl.href,
          source: 'trendyol_search_filter',
        };
      }
    } catch (error) {
      // Bir arama terimi sonuç vermezse sonraki sözlük terimini deniyoruz.
    }
  }

  const fallbackTerm = uniqueTerms[0] || keyword;

  return {
    ok: false,
    label: dictionaryMatch?.matched_term || fallbackTerm,
    mainLabel: dictionaryMatch?.category || '',
    subLabel: dictionaryMatch?.sub_category || '',
    searchUrl: fallbackTerm ? buildBestsellerSearchUrl(fallbackTerm).href : '',
  };
}

async function waitForSearchCategoryCandidate(tabId, keyword, dictionaryMatch, timeoutMs) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    const candidate = await readSearchCategoryCandidate(tabId, keyword, dictionaryMatch);
    if (candidate?.categoryId) {
      return candidate;
    }

    await delay(500);
  }

  return null;
}

async function clickSearchCategoryFilter(tabId, keyword, dictionaryMatch = null) {
  try {
    const [clickResult] = await chrome.scripting.executeScript({
      target: { tabId },
      args: [keyword, dictionaryMatch],
      func: (search, match) => {
        const normalize = (value) => String(value || '')
          .toLocaleLowerCase('tr-TR')
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/ı/g, 'i')
          .replace(/ğ/g, 'g')
          .replace(/ü/g, 'u')
          .replace(/ş/g, 's')
          .replace(/ö/g, 'o')
          .replace(/ç/g, 'c')
          .replace(/[^a-z0-9]+/g, ' ')
          .trim();
        const compact = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const targetTerms = [
          search,
          match?.matched_term,
          match?.product_group,
          match?.sub_category,
          match?.category,
        ]
          .map(normalize)
          .filter((term) => term.length >= 2);
        const scoreLabel = (label) => {
          const normalized = normalize(label);
          if (!normalized) return 0;
          let score = 0;

          for (const target of targetTerms) {
            if (normalized === target) score = Math.max(score, 1000 + target.length);
            if (target.length >= 3 && normalized.includes(target)) score = Math.max(score, 860 + target.length);
            if (normalized.length >= 3 && target.includes(normalized)) score = Math.max(score, 790 + normalized.length);
            const targetTokens = target.split(' ').filter(Boolean);
            const labelTokens = normalized.split(' ').filter(Boolean);
            const overlap = targetTokens.filter((token) => labelTokens.includes(token)).length;
            if (overlap > 0) score = Math.max(score, 40 + (overlap / targetTokens.length) * 130 + Math.min(30, normalized.length / 2));
          }

          return score;
        };
        const categoryRoot = Array.from(document.querySelectorAll('aside, nav, [class*="filter"], [class*="fltr"], [class*="category"], body'))
          .find((node) => normalize(node.textContent).includes('kategori')) || document.body;
        const nodes = Array.from(categoryRoot.querySelectorAll('a, label, button, li, div'))
          .map((node) => {
            const text = compact(node.textContent);
            const href = node.href || node.getAttribute?.('href') || '';
            const score = scoreLabel(text || href);
            const controlPriority = node.matches('label.checkbox-root, label, a, button')
              ? 100
              : node.matches('.checkbox-list-item, li')
                ? 50
                : 0;
            return { node, text, href, score, controlPriority };
          })
          .filter((item) => item.score > 0 && item.text.length <= 90)
          .sort((left, right) => (
            (right.score + right.controlPriority) - (left.score + left.controlPriority)
          ));
        const target = nodes[0];

        if (!target?.node) {
          return { clicked: false };
        }

        const clickable = target.node.closest('a, label, button, li') || target.node;
        clickable.click();

        return { clicked: true, label: target.text, score: target.score };
      },
    });

    if (!clickResult?.result?.clicked) {
      return null;
    }

    const startedAt = Date.now();
    while (Date.now() - startedAt < 12000) {
      const candidate = await readSearchCategoryCandidate(tabId, keyword, dictionaryMatch);
      if (candidate?.categoryId) {
        return {
          ...candidate,
          label: candidate.label || clickResult.result.label || '',
        };
      }

      await delay(500);
    }

    const currentTab = await chrome.tabs.get(tabId).catch(() => null);

    return {
      categoryId: '',
      label: clickResult.result.label || '',
      href: currentTab?.url || '',
    };
  } catch (error) {
    return null;
  }
}

async function readSearchCategoryCandidate(tabId, keyword, dictionaryMatch = null) {
  try {
    const [result] = await chrome.scripting.executeScript({
      target: { tabId },
      args: [keyword, dictionaryMatch],
      func: (search, match) => {
        const normalize = (value) => String(value || '')
          .toLocaleLowerCase('tr-TR')
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/ı/g, 'i')
          .replace(/ğ/g, 'g')
          .replace(/ü/g, 'u')
          .replace(/ş/g, 's')
          .replace(/ö/g, 'o')
          .replace(/ç/g, 'c')
          .replace(/[^a-z0-9]+/g, ' ')
          .trim();
        const compact = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const categoryIdFromUrl = (value) => {
          const rawUrl = String(value || '');
          const pathCategoryId = rawUrl.match(/-x-c(\d+)/i)?.[1] || '';
          if (pathCategoryId) return pathCategoryId;

          try {
            const parsedUrl = new URL(rawUrl, location.origin);
            for (const key of ['categoryId', 'wc', 'lc', 'category', 'catId']) {
              const valueFromQuery = parsedUrl.searchParams.get(key);
              const queryCategoryId = String(valueFromQuery || '').match(/\d+/)?.[0] || '';
              if (queryCategoryId) return queryCategoryId;
            }
          } catch (error) {
            return rawUrl.match(/[?&](?:categoryId|wc|lc|category|catId)=(\d+)/i)?.[1] || '';
          }

          return '';
        };
        const labelFromHref = (href) => decodeURIComponent(String(href || '').split('?')[0].split('/').filter(Boolean).pop() || '')
          .replace(/-x-c\d+$/i, '')
          .replace(/-/g, ' ');
        const targetTerms = [
          search,
          match?.matched_term,
          match?.product_group,
          match?.sub_category,
          match?.category,
        ]
          .map(normalize)
          .filter((term) => term.length >= 2);
        const scoreLabel = (label, href) => {
          const haystack = `${normalize(label)} ${normalize(labelFromHref(href))}`.trim();
          if (!haystack) return 0;
          let score = 0;
          for (const target of targetTerms) {
            if (haystack === target) score = Math.max(score, 1000 + target.length);
            if (target.length >= 3 && haystack.includes(target)) score = Math.max(score, 850 + target.length);
            if (haystack.length >= 3 && target.includes(haystack)) score = Math.max(score, 780 + haystack.length);
            const targetTokens = target.split(' ').filter(Boolean);
            const labelTokens = haystack.split(' ').filter(Boolean);
            const overlap = targetTokens.filter((token) => labelTokens.includes(token)).length;
            if (overlap > 0) score = Math.max(score, 40 + (overlap / targetTokens.length) * 120 + Math.min(30, haystack.length / 2));
          }

          return score;
        };

        const currentCategoryId = categoryIdFromUrl(location.href);
        if (currentCategoryId) {
          const heading = compact(document.querySelector('h1, h2, .prdct-cntnr-ttl')?.textContent);
          return {
            categoryId: currentCategoryId,
            label: heading || labelFromHref(location.href),
            href: location.href,
            score: 1200,
          };
        }

        const linkCandidates = Array.from(document.querySelectorAll(
          'a[href*="-x-c"], a[href*="categoryId="], a[href*="wc="], a[href*="lc="], a[href*="catId="]',
        ))
          .map((link) => {
            const href = link.href || link.getAttribute('href') || '';
            const categoryId = categoryIdFromUrl(href);
            const label = compact(link.textContent) || compact(link.getAttribute('title')) || labelFromHref(href);
            return {
              categoryId,
              label,
              href,
              score: categoryId ? scoreLabel(label, href) : 0,
            };
          })
          .filter((candidate) => candidate.categoryId && candidate.score > 0);
        const checkboxCandidates = Array.from(document.querySelectorAll(
          'input[type="checkbox"][name*="LeafCategory"][value], input[type="checkbox"][name*="WebCategory"][value]',
        ))
          .map((input) => {
            const categoryId = String(input.value || '').match(/\d+/)?.[0] || '';
            const label = compact(
              input.getAttribute('aria-label')
              || input.closest('label')?.textContent
              || '',
            );
            const filteredUrl = new URL(location.href);
            if (categoryId) {
              filteredUrl.searchParams.set('wc', categoryId);
              filteredUrl.searchParams.set('sst', 'BEST_SELLER');
              filteredUrl.searchParams.set('os', '1');
            }

            return {
              categoryId,
              label,
              href: filteredUrl.href,
              score: categoryId ? scoreLabel(label, filteredUrl.href) + 80 : 0,
            };
          })
          .filter((candidate) => candidate.categoryId && candidate.score > 0);

        const candidates = [...linkCandidates, ...checkboxCandidates]
          .sort((left, right) => right.score - left.score);

        return candidates[0] || null;
      },
    });

    return result?.result || null;
  } catch (error) {
    return null;
  }
}

function bestsellerGenderId(keyword) {
  const normalized = normalizeBestsellerText(keyword);

  if (/\b(erkek|bay)\b/.test(normalized)) {
    return '2';
  }

  if (/\b(cocuk|bebek|kiz cocuk|erkek cocuk)\b/.test(normalized)) {
    return '3';
  }

  if (/\b(kadin|bayan)\b/.test(normalized)) {
    return '1';
  }

  return '';
}

function normalizeBestsellerText(value) {
  return String(value || '')
    .toLocaleLowerCase('tr-TR')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/ı/g, 'i')
    .replace(/ğ/g, 'g')
    .replace(/ü/g, 'u')
    .replace(/ş/g, 's')
    .replace(/ö/g, 'o')
    .replace(/ç/g, 'c')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

async function navigateToCategory(tabId, keyword) {
  const [result] = await chrome.scripting.executeScript({
    target: { tabId },
    args: [keyword],
    func: async (search) => {
      const normalize = (value) => String(value || '')
        .toLocaleLowerCase('tr-TR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ı/g, 'i')
        .replace(/ğ/g, 'g')
        .replace(/ü/g, 'u')
        .replace(/ş/g, 's')
        .replace(/ö/g, 'o')
        .replace(/ç/g, 'c')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
        
      const query = normalize(search);
      
      const TRENDYOL_CATEGORY_MAP = {
        // --- EV & YAŞAM / MOBİLYA ---
        "sehpa": { main: "Ev", sub: "Sehpa" },
        "orta sehpa": { main: "Ev", sub: "Sehpa" },
        "zigon sehpa": { main: "Ev", sub: "Sehpa" },
        "koltuk": { main: "Ev", sub: "Koltuk" },
        "tekli koltuk": { main: "Ev", sub: "Koltuk" },
        "berjer": { main: "Ev", sub: "Koltuk" },
        "tv koltuk": { main: "Ev", sub: "Koltuk" },
        "baba koltuk": { main: "Ev", sub: "Koltuk" },
        "kanepe": { main: "Ev", sub: "Koltuk" },
        "cek yat": { main: "Ev", sub: "Koltuk" },
        "kose takimi": { main: "Ev", sub: "Koltuk" },
        "sark kosesi": { main: "Ev", sub: "Koltuk" },
        "puf": { main: "Ev", sub: "Puf" },
        "bench": { main: "Ev", sub: "Puf" },
        "hali": { main: "Ev", sub: "Hali" },
        "yolluk": { main: "Ev", sub: "Hali" },
        "kilim": { main: "Ev", sub: "Hali" },
        "perde": { main: "Ev", sub: "Perde" },
        "fon perde": { main: "Ev", sub: "Perde" },
        "stor perde": { main: "Ev", sub: "Perde" },
        "cibinlik": { main: "Ev", sub: "Perde" },
        "masa": { main: "Ev", sub: "Masa" },
        "calisma masasi": { main: "Ev", sub: "Masa" },
        "yemek masasi": { main: "Ev", sub: "Masa" },
        "oyuncu masasi": { main: "Ev", sub: "Masa" },
        "sandalye": { main: "Ev", sub: "Sandalye" },
        "ofis sandalyesi": { main: "Ev", sub: "Sandalye" },
        "dolap": { main: "Ev", sub: "Dolap" },
        "gardirop": { main: "Ev", sub: "Dolap" },
        "cok amacli dolaplar": { main: "Ev", sub: "Dolap" },
        "ayakkabilik": { main: "Ev", sub: "Dolap" },
        "kitaplik": { main: "Ev", sub: "Kitaplik" },
        "tv unitesi": { main: "Ev", sub: "Tv Unite" },
        "yatak": { main: "Ev", sub: "Yatak" },
        "baza": { main: "Ev", sub: "Yatak" },
        "aydinlatma": { main: "Ev", sub: "Aydinlatma" },
        "avize": { main: "Ev", sub: "Aydinlatma" },
        "lambader": { main: "Ev", sub: "Aydinlatma" },
        "abajur": { main: "Ev", sub: "Aydinlatma" },
        "aplik": { main: "Ev", sub: "Aydinlatma" },
        "ampul": { main: "Ev", sub: "Aydinlatma" },
        "mutfak": { main: "Ev", sub: "Mutfak" },
        "tabak": { main: "Ev", sub: "Mutfak" },
        "tencere": { main: "Ev", sub: "Mutfak" },
        "tava": { main: "Ev", sub: "Mutfak" },
        "bardak": { main: "Ev", sub: "Mutfak" },
        "caydanlik": { main: "Ev", sub: "Mutfak" },
        "fincan": { main: "Ev", sub: "Mutfak" },
        "catal kasik": { main: "Ev", sub: "Mutfak" },
        "bicak": { main: "Ev", sub: "Mutfak" },
        "tahta": { main: "Ev", sub: "Mutfak" },
        "pisirme": { main: "Ev", sub: "Mutfak" },
        "hazirlama": { main: "Ev", sub: "Mutfak" },
        "yastik": { main: "Ev", sub: "Yastik" },
        "yorgan": { main: "Ev", sub: "Yorgan" },
        "nevresim": { main: "Ev", sub: "Nevresim" },
        "yatak ortusu": { main: "Ev", sub: "Yatak Ortusu" },
        "battaniye": { main: "Ev", sub: "Battaniye" },
        "havlu": { main: "Ev", sub: "Havlu" },
        "bornoz": { main: "Ev", sub: "Havlu" },
        "banyo tekstili": { main: "Ev", sub: "Havlu" },
        "carsaf": { main: "Ev", sub: "Carsaf" },
        "tablo": { main: "Ev", sub: "Tablo" },
        "cerceve": { main: "Ev", sub: "Tablo" },
        "heykel": { main: "Ev", sub: "Tablo" },
        "mum": { main: "Ev", sub: "Mum" },
        "mumluk": { main: "Ev", sub: "Mum" },
        "vazo": { main: "Ev", sub: "Dekorasyon" },
        "saksi": { main: "Ev", sub: "Dekorasyon" },
        "oda kokusu": { main: "Ev", sub: "Oda Koku" },
        "banyo aksesuarlari": { main: "Ev", sub: "Banyo" },
        "yilbasi agaci": { main: "Ev", sub: "Dekorasyon" },
        "yilbasi": { main: "Ev", sub: "Dekorasyon" },
        "bebek odasi": { main: "Ev", sub: "Dekorasyon" },

        // --- SAAT & AKSESUAR ---
        "saat": { main: "Saat", sub: "Saat" },
        "kol saati": { main: "Saat", sub: "Saat" },
        "akilli saat": { main: "Elektronik", sub: "Giyilebilir" },
        "taki": { main: "Saat", sub: "Taki" },
        "mucevher": { main: "Saat", sub: "Taki" },
        "taki ve mucevher": { main: "Saat", sub: "Taki" },
        "kolye": { main: "Saat", sub: "Taki" },
        "yuzuk": { main: "Saat", sub: "Taki" },
        "kupe": { main: "Saat", sub: "Taki" },
        "bileklik": { main: "Saat", sub: "Taki" },
        "halhal": { main: "Saat", sub: "Taki" },
        "gozluk": { main: "Saat", sub: "Gozluk" },
        "gunes gozlugu": { main: "Saat", sub: "Gozluk" },
        "sapka": { main: "Saat", sub: "Sapka" },
        "bere": { main: "Saat", sub: "Sapka" },
        "sal": { main: "Saat", sub: "Sal" },
        "atki": { main: "Saat", sub: "Sal" },
        "eldiven": { main: "Saat", sub: "Eldiven" },
        "kemer": { main: "Saat", sub: "Kemer" },
        "cuzdan": { main: "Saat", sub: "Cuzdan" },
        "toka": { main: "Saat", sub: "Toka" },

        // --- GİYİM (Kadın / Erkek) ---
        "giyim": { main: "Kadin", sub: "Giyim" },
        "elbise": { main: "Kadin", sub: "Elbise" },
        "abiye": { main: "Kadin", sub: "Elbise" },
        "tisort": { main: "Kadin", sub: "Tisort" },
        "t shirt": { main: "Kadin", sub: "Tisort" },
        "polo": { main: "Kadin", sub: "Tisort" },
        "pantolon": { main: "Kadin", sub: "Pantolon" },
        "jeans": { main: "Kadin", sub: "Pantolon" },
        "kot pantolon": { main: "Kadin", sub: "Pantolon" },
        "etek": { main: "Kadin", sub: "Etek" },
        "kazak": { main: "Kadin", sub: "Kazak" },
        "hirka": { main: "Kadin", sub: "Hirka" },
        "sweatshirt": { main: "Kadin", sub: "Sweatshirt" },
        "mont": { main: "Kadin", sub: "Mont" },
        "kaban": { main: "Kadin", sub: "Kaban" },
        "trenckot": { main: "Kadin", sub: "Mont" },
        "ceket": { main: "Kadin", sub: "Ceket" },
        "kot ceket": { main: "Kadin", sub: "Ceket" },
        "yagmurluk": { main: "Kadin", sub: "Mont" },
        "tayt": { main: "Kadin", sub: "Tayt" },
        "sort": { main: "Kadin", sub: "Sort" },
        "body": { main: "Kadin", sub: "Body" },
        "bustiyer": { main: "Kadin", sub: "Bustiyer" },
        "bluz": { main: "Kadin", sub: "Bluz" },
        "gomlek": { main: "Kadin", sub: "Gomlek" },
        "pijama": { main: "Kadin", sub: "Pijama" },
        "gecelik": { main: "Kadin", sub: "Gecelik" },
        "ic camasiri": { main: "Kadin", sub: "Ic Giyim" },
        "sutyen": { main: "Kadin", sub: "Sutyen" },
        "kulot": { main: "Kadin", sub: "Kulot" },
        "corap": { main: "Kadin", sub: "Corap" },
        "tesettur": { main: "Kadin", sub: "Tesettur" },
        "esarp": { main: "Kadin", sub: "Tesettur" },
        "takim elbise": { main: "Erkek", sub: "Takim Elbise" },
        "is kiyafetleri": { main: "Erkek", sub: "Giyim" },

        // --- AYAKKABI & ÇANTA ---
        "ayakkabi": { main: "Ayakkabi", sub: "Ayakkabi" },
        "sneaker": { main: "Ayakkabi", sub: "Ayakkabi" },
        "spor ayakkabi": { main: "Ayakkabi", sub: "Ayakkabi" },
        "gunluk ayakkabi": { main: "Ayakkabi", sub: "Ayakkabi" },
        "bot": { main: "Ayakkabi", sub: "Bot" },
        "cizme": { main: "Ayakkabi", sub: "Cizme" },
        "terlik": { main: "Ayakkabi", sub: "Terlik" },
        "ev terligi": { main: "Ayakkabi", sub: "Terlik" },
        "sandalet": { main: "Ayakkabi", sub: "Sandalet" },
        "topuklu ayakkabi": { main: "Ayakkabi", sub: "Topuklu" },
        "babet": { main: "Ayakkabi", sub: "Babet" },
        "loafer": { main: "Ayakkabi", sub: "Ayakkabi" },
        "canta": { main: "Ayakkabi", sub: "Canta" },
        "omuz cantasi": { main: "Ayakkabi", sub: "Canta" },
        "sirt cantasi": { main: "Ayakkabi", sub: "Canta" },
        "capraz canta": { main: "Ayakkabi", sub: "Canta" },
        "valiz": { main: "Ayakkabi", sub: "Valiz" },
        "bavul": { main: "Ayakkabi", sub: "Valiz" },

        // --- KOZMETİK ---
        "kozmetik": { main: "Kozmetik", sub: "Kozmetik" },
        "parfum": { main: "Kozmetik", sub: "Parfum" },
        "deodorant": { main: "Kozmetik", sub: "Deodorant" },
        "makyaj": { main: "Kozmetik", sub: "Makyaj" },
        "ruj": { main: "Kozmetik", sub: "Makyaj" },
        "fondoten": { main: "Kozmetik", sub: "Makyaj" },
        "rimel": { main: "Kozmetik", sub: "Makyaj" },
        "maskara": { main: "Kozmetik", sub: "Makyaj" },
        "far": { main: "Kozmetik", sub: "Makyaj" },
        "goz kalemi": { main: "Kozmetik", sub: "Makyaj" },
        "oje": { main: "Kozmetik", sub: "Makyaj" },
        "allik": { main: "Kozmetik", sub: "Makyaj" },
        "sampuan": { main: "Kozmetik", sub: "Sac Bakim" },
        "sac kremi": { main: "Kozmetik", sub: "Sac Bakim" },
        "sac boyasi": { main: "Kozmetik", sub: "Sac Bakim" },
        "sac spreyi": { main: "Kozmetik", sub: "Sac Bakim" },
        "cilt bakimi": { main: "Kozmetik", sub: "Cilt Bakim" },
        "nemlendirici": { main: "Kozmetik", sub: "Cilt Bakim" },
        "yuz kremi": { main: "Kozmetik", sub: "Cilt Bakim" },
        "serum": { main: "Kozmetik", sub: "Cilt Bakim" },
        "tonik": { main: "Kozmetik", sub: "Cilt Bakim" },
        "gunes kremi": { main: "Kozmetik", sub: "Gunes Kremi" },
        "epilasyon": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "agda": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "epilator": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "ipl lazer epilasyon": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "tiras": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "tiras bicagi": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "tiras makinesi": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "agiz bakimi": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "agiz bakim": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "dis macunu": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "ped": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "kadin hijyen": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "gunluk ped": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "hijyenik ped": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "banyo": { main: "Kozmetik", sub: "Kisisel Bakim" },
        "dus urunleri": { main: "Kozmetik", sub: "Kisisel Bakim" },

        // --- ELEKTRONİK ---
        "elektronik": { main: "Elektronik", sub: "Elektronik" },
        "telefon": { main: "Elektronik", sub: "Telefon" },
        "cep telefonu": { main: "Elektronik", sub: "Telefon" },
        "telefon bataryasi": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "telefon ekrani": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "telefon kamerasi": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "telefon kilifi": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "sarj adaptoru": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "arac sarj": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "sarj aleti": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "powerbank": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "selfie cubugu": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "ekran koruyucu": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "kamera lens koruyucu": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "arac ici telefon tutucu": { main: "Elektronik", sub: "Telefon Aksesuar" },
        "bilgisayar": { main: "Elektronik", sub: "Bilgisayar" },
        "laptop": { main: "Elektronik", sub: "Bilgisayar" },
        "notebook standi": { main: "Elektronik", sub: "Bilgisayar" },
        "tablet standi": { main: "Elektronik", sub: "Bilgisayar" },
        "tablet": { main: "Elektronik", sub: "Tablet" },
        "veri depolama": { main: "Elektronik", sub: "Bilgisayar" },
        "harddisk": { main: "Elektronik", sub: "Bilgisayar" },
        "usb": { main: "Elektronik", sub: "Bilgisayar" },
        "notebook cantasi": { main: "Elektronik", sub: "Bilgisayar" },
        "kulaklik": { main: "Elektronik", sub: "Kulaklik" },
        "bluetooth kulaklik": { main: "Elektronik", sub: "Kulaklik" },
        "hoparlor": { main: "Elektronik", sub: "Ses" },
        "ses kablolari": { main: "Elektronik", sub: "Ses" },
        "kamera": { main: "Elektronik", sub: "Kamera" },
        "drone": { main: "Elektronik", sub: "Oyuncak" },
        "oyun konsolu": { main: "Elektronik", sub: "Konsol" },
        "mouse": { main: "Elektronik", sub: "Cevre Birimleri" },
        "klavye": { main: "Elektronik", sub: "Cevre Birimleri" },
        "monitor": { main: "Elektronik", sub: "Cevre Birimleri" },
        "monitor aparati": { main: "Elektronik", sub: "Cevre Birimleri" },
        "oyuncu koltugu": { main: "Elektronik", sub: "Cevre Birimleri" },
        "ses sistemi": { main: "Elektronik", sub: "Ses" },
        "televizyon": { main: "Elektronik", sub: "Televizyon" },
        "tv": { main: "Elektronik", sub: "Televizyon" },
        "uydu alici": { main: "Elektronik", sub: "Televizyon" },
        "tv ekran koruyucu": { main: "Elektronik", sub: "Televizyon" },
        "klima": { main: "Elektronik", sub: "Beyaz Esya" },
        "buzdolabi": { main: "Elektronik", sub: "Beyaz Esya" },
        "camasir makinesi": { main: "Elektronik", sub: "Beyaz Esya" },
        "bulasik makinesi": { main: "Elektronik", sub: "Beyaz Esya" },
        "utu": { main: "Elektronik", sub: "Kucuk Ev Aletleri" },
        "supurge": { main: "Elektronik", sub: "Kucuk Ev Aletleri" },
        "robot supurge": { main: "Elektronik", sub: "Kucuk Ev Aletleri" },
        "kahve makinesi": { main: "Elektronik", sub: "Kucuk Ev Aletleri" },
        "cay makinesi": { main: "Elektronik", sub: "Kucuk Ev Aletleri" },
        "sac kurutma": { main: "Elektronik", sub: "Kisisel Bakim" },

        // --- ANNE & ÇOCUK ---
        "oyuncak": { main: "Anne", sub: "Oyuncak" },
        "pelus": { main: "Anne", sub: "Oyuncak" },
        "egitici oyuncak": { main: "Anne", sub: "Oyuncak" },
        "cocuk cizim tableti": { main: "Anne", sub: "Oyuncak" },
        "rc arac": { main: "Anne", sub: "Oyuncak" },
        "sisme havuz": { main: "Anne", sub: "Oyuncak" },
        "scooter": { main: "Anne", sub: "Oyuncak" },
        "pedalli araclar": { main: "Anne", sub: "Oyuncak" },
        "oyuncak silah": { main: "Anne", sub: "Oyuncak" },
        "su tabancasi": { main: "Anne", sub: "Oyuncak" },
        "lego": { main: "Anne", sub: "Oyuncak" },
        "yapi oyuncaklari": { main: "Anne", sub: "Oyuncak" },
        "kutu oyunlari": { main: "Anne", sub: "Oyuncak" },
        "figur oyuncaklar": { main: "Anne", sub: "Oyuncak" },
        "ahsap oyuncaklar": { main: "Anne", sub: "Oyuncak" },
        "akulu araclar": { main: "Anne", sub: "Oyuncak" },
        "bebek aktivite": { main: "Anne", sub: "Oyuncak" },
        "cocuk salincak": { main: "Anne", sub: "Oyuncak" },
        "bebek hediyelik": { main: "Anne", sub: "Oyuncak" },
        "bebek salincagi": { main: "Anne", sub: "Oyuncak" },
        "cocuk puzzle": { main: "Anne", sub: "Oyuncak" },
        "bebek bezi": { main: "Anne", sub: "Bebek Bezi" },
        "islak mendil": { main: "Anne", sub: "Bebek Bakim" },
        "bebek gunes kremi": { main: "Anne", sub: "Bebek Bakim" },
        "bebek kremi": { main: "Anne", sub: "Bebek Bakim" },
        "bebek sampuani": { main: "Anne", sub: "Bebek Bakim" },
        "bebek bakim seti": { main: "Anne", sub: "Bebek Bakim" },
        "bebek bakim cantasi": { main: "Anne", sub: "Bebek Bakim" },
        "bebek arabasi": { main: "Anne", sub: "Bebek Arabasi" },
        "puset": { main: "Anne", sub: "Bebek Arabasi" },
        "portbebe": { main: "Anne", sub: "Bebek Arabasi" },
        "kanguru": { main: "Anne", sub: "Bebek Arabasi" },
        "3 tekerlekli": { main: "Anne", sub: "Bebek Arabasi" },
        "ana kucagi": { main: "Anne", sub: "Bebek Arabasi" },
        "baston puset": { main: "Anne", sub: "Bebek Arabasi" },
        "oto koltugu": { main: "Anne", sub: "Oto Koltugu" },
        "arabada guvenlik": { main: "Anne", sub: "Oto Koltugu" },
        "emzik": { main: "Anne", sub: "Bebek Beslenme" },
        "biberon": { main: "Anne", sub: "Bebek Beslenme" },
        "mama sandalyesi": { main: "Anne", sub: "Bebek Beslenme" },
        "gogus pompasi": { main: "Anne", sub: "Bebek Beslenme" },
        "alistirma bardagi": { main: "Anne", sub: "Bebek Beslenme" },
        "beslenme aksesuari": { main: "Anne", sub: "Bebek Beslenme" },
        "biberon isitici": { main: "Anne", sub: "Bebek Beslenme" },
        "bebek telsizi": { main: "Anne", sub: "Bebek Guvenlik" },
        "bebek giyim": { main: "Anne", sub: "Bebek Giyim" },
        "cocuk giyim": { main: "Anne", sub: "Cocuk Giyim" },
        "bebek kuveti": { main: "Anne", sub: "Bebek Banyo" },

        // --- SÜPERMARKET ---
        "supermarket": { main: "Supermarket", sub: "Gida" },
        "kahve": { main: "Supermarket", sub: "Icecek" },
        "cay": { main: "Supermarket", sub: "Icecek" },
        "su": { main: "Supermarket", sub: "Icecek" },
        "enerji icecegi": { main: "Supermarket", sub: "Icecek" },
        "bardak poset cay": { main: "Supermarket", sub: "Icecek" },
        "deterjan": { main: "Supermarket", sub: "Temizlik" },
        "camasir deterjani": { main: "Supermarket", sub: "Temizlik" },
        "bulasik deterjani": { main: "Supermarket", sub: "Temizlik" },
        "bulasik tableti": { main: "Supermarket", sub: "Temizlik" },
        "yumusatici": { main: "Supermarket", sub: "Temizlik" },
        "tuvalet kagidi": { main: "Supermarket", sub: "Kagit" },
        "kagit havlu": { main: "Supermarket", sub: "Kagit" },
        "kedi mamasi": { main: "Supermarket", sub: "Evcil Hayvan" },
        "kopek mamasi": { main: "Supermarket", sub: "Evcil Hayvan" },
        "kedi kumu": { main: "Supermarket", sub: "Evcil Hayvan" },
        "kedi vitamini": { main: "Supermarket", sub: "Evcil Hayvan" },
        "atistirmalik": { main: "Supermarket", sub: "Gida" },
        "cikolata": { main: "Supermarket", sub: "Gida" },
        "yag": { main: "Supermarket", sub: "Gida" },
        "zeytinyagi": { main: "Supermarket", sub: "Gida" },
        "peynir": { main: "Supermarket", sub: "Gida" },
        "bal": { main: "Supermarket", sub: "Gida" },
        "noodle": { main: "Supermarket", sub: "Gida" },
        "helva": { main: "Supermarket", sub: "Gida" },
        "hurma": { main: "Supermarket", sub: "Gida" },
        "polen": { main: "Supermarket", sub: "Gida" },
        "propolis": { main: "Supermarket", sub: "Gida" },
        "tahin": { main: "Supermarket", sub: "Gida" },
        "biskuvi": { main: "Supermarket", sub: "Gida" },
        "kraker": { main: "Supermarket", sub: "Gida" },
        "tatli sosu": { main: "Supermarket", sub: "Gida" },
        "ton baligi": { main: "Supermarket", sub: "Gida" },
        "medikal maske": { main: "Supermarket", sub: "Saglik" },
        "cinsel saglik": { main: "Supermarket", sub: "Saglik" },
        "prezervatif": { main: "Supermarket", sub: "Saglik" },

        // --- SPOR & OUTDOOR ---
        "bisiklet": { main: "Spor", sub: "Bisiklet" },
        "bisiklet aksesuarlari": { main: "Spor", sub: "Bisiklet" },
        "bisiklet kilidi": { main: "Spor", sub: "Bisiklet" },
        "kamp": { main: "Spor", sub: "Kamp" },
        "cadir": { main: "Spor", sub: "Kamp" },
        "kamp sandalyesi": { main: "Spor", sub: "Kamp" },
        "uyku tulumu": { main: "Spor", sub: "Kamp" },
        "termos": { main: "Spor", sub: "Kamp" },
        "pilates": { main: "Spor", sub: "Fitness" },
        "yoga": { main: "Spor", sub: "Fitness" },
        "pilates mati": { main: "Spor", sub: "Fitness" },
        "yoga mati": { main: "Spor", sub: "Fitness" },
        "dambil": { main: "Spor", sub: "Fitness" },
        "spor aleti": { main: "Spor", sub: "Fitness" },
        "kosu bandi": { main: "Spor", sub: "Fitness" },
        "kettlebell": { main: "Spor", sub: "Fitness" },
        "bant": { main: "Spor", sub: "Fitness" },
        "protein tozu": { main: "Spor", sub: "Sporcu" },
        "deniz malzemeleri": { main: "Spor", sub: "Outdoor" },
        "plaj": { main: "Spor", sub: "Outdoor" },

        // --- KİTAP & KIRTASİYE & BAHÇE & OTO ---
        "kitap": { main: "Kitap", sub: "Kitap" },
        "roman": { main: "Kitap", sub: "Kitap" },
        "ajanda": { main: "Kirtasiye", sub: "Defter" },
        "defter": { main: "Kirtasiye", sub: "Defter" },
        "kalem": { main: "Kirtasiye", sub: "Kalem" },
        "a4 kagidi": { main: "Kirtasiye", sub: "Kagit" },
        "fotokopi kagidi": { main: "Kirtasiye", sub: "Kagit" },
        "ofis": { main: "Kirtasiye", sub: "Ofis" },
        "yazi tahtasi": { main: "Kirtasiye", sub: "Ofis" },
        "etiket": { main: "Kirtasiye", sub: "Ofis" },
        "matkap": { main: "Bahce", sub: "Elektrikli" },
        "hirdavat": { main: "Bahce", sub: "Hirdavat" },
        "ev kapilari": { main: "Bahce", sub: "Hirdavat" },
        "para kasalari": { main: "Bahce", sub: "Hirdavat" },
        "boya": { main: "Bahce", sub: "Boya" },
        "salincak": { main: "Bahce", sub: "Bahce" },
        "hamak": { main: "Bahce", sub: "Bahce" },
        "havuz urunleri": { main: "Bahce", sub: "Havuz" },
        "bahce aydinlatmasi": { main: "Bahce", sub: "Aydinlatma" },
        "oto lastik": { main: "Otomobil", sub: "Lastik" },
        "oto ampul": { main: "Otomobil", sub: "Yedek Parca" },
        "oto far": { main: "Otomobil", sub: "Yedek Parca" },
        "oto koltuk kilifi": { main: "Otomobil", sub: "Aksesuar" },
        "paspas": { main: "Otomobil", sub: "Aksesuar" },
        "motosiklet": { main: "Otomobil", sub: "Motosiklet" },
        "kask": { main: "Otomobil", sub: "Motosiklet Aksesuar" },
      };

      let dictMatch = TRENDYOL_CATEGORY_MAP[query];
      
      if (!dictMatch) {
         const fuzzyKey = Object.keys(TRENDYOL_CATEGORY_MAP).find(k => query.includes(k));
         if (!fuzzyKey) {
            return { ok: false };
         }
         dictMatch = TRENDYOL_CATEGORY_MAP[fuzzyKey];
      }

      const mainCategoryName = dictMatch.main;
      const subCategoryName = dictMatch.sub;
      const mainCategoryTargetTokens = normalize(mainCategoryName).split(' ').filter(Boolean);

      // 1. Ana Kategoriyi Bul ve Tıkla
      const mainButtons = Array.from(document.querySelectorAll('main button.category-pill.has-children'));
      const mainTarget = mainButtons.find(button => {
         const btnTokens = normalize(button.textContent).split(' ').filter(Boolean);
         return mainCategoryTargetTokens.some(token => btnTokens.includes(token));
      });

      if (!mainTarget) {
         return { ok: false };
      }
      
      mainTarget.click();

      // Dropdown / Alt kategorilerin yüklenmesini bekle (1.2 saniye)
      await new Promise((resolve) => setTimeout(resolve, 1200));

      // 2. Alt Kategoriyi Bul ve Tıkla
      const subCategoryTargetTokens = normalize(subCategoryName).split(' ').filter(Boolean);
      const subButtons = Array.from(document.querySelectorAll('main button.category-pill:not(.has-children)'));
      
      const subTarget = subButtons.find(button => {
         const btnTokens = normalize(button.textContent).split(' ').filter(Boolean);
         return subCategoryTargetTokens.some(token => btnTokens.includes(token));
      });

      if (!subTarget) {
         // Alt kategori butonu yoksa Ana kategoride kal
         return { ok: true, mainLabel: String(mainTarget.textContent || '').trim(), subLabel: '' };
      }

      subTarget.click();
      return { ok: true, mainLabel: String(mainTarget.textContent || '').trim(), subLabel: String(subTarget.textContent || '').trim() };
    },
  });

  return result?.result || { ok: false };
}

async function navigateToVisibleBestsellerCategory(tabId, keyword, dictionaryMatch = null) {
  const [result] = await chrome.scripting.executeScript({
    target: { tabId },
    args: [keyword, dictionaryMatch],
    func: async (search, match) => {
      const normalize = (value) => String(value || '')
        .toLocaleLowerCase('tr-TR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ı/g, 'i')
        .replace(/ğ/g, 'g')
        .replace(/ü/g, 'u')
        .replace(/ş/g, 's')
        .replace(/ö/g, 'o')
        .replace(/ç/g, 'c')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
      const targetTerms = [
        search,
        match?.matched_term,
        match?.product_group,
        match?.sub_category,
        match?.category,
      ]
        .map(normalize)
        .filter((term) => term.length >= 2);
      const scoreLabel = (label) => {
        const normalized = normalize(label);
        if (!normalized) return 0;
        let score = 0;
        for (const target of targetTerms) {
          if (normalized === target) score = Math.max(score, 1000 + target.length);
          if (target.length >= 3 && normalized.includes(target)) score = Math.max(score, 840 + target.length);
          if (normalized.length >= 3 && target.includes(normalized)) score = Math.max(score, 760 + normalized.length);
          const targetTokens = target.split(' ').filter(Boolean);
          const labelTokens = normalized.split(' ').filter(Boolean);
          const overlap = targetTokens.filter((token) => labelTokens.includes(token)).length;
          if (overlap > 0) score = Math.max(score, 35 + (overlap / targetTokens.length) * 130);
        }

        return score;
      };

      const mainButtons = Array.from(document.querySelectorAll('main button.category-pill.has-children'));
      const mainTarget = mainButtons
        .map((button) => ({ button, label: String(button.textContent || '').trim(), score: scoreLabel(button.textContent) }))
        .filter((item) => item.score > 0)
        .sort((left, right) => right.score - left.score)[0];

      if (!mainTarget?.button) {
        return { ok: false };
      }

      mainTarget.button.click();
      await new Promise((resolve) => setTimeout(resolve, 1200));

      const subButtons = Array.from(document.querySelectorAll('main button.category-pill:not(.has-children)'));
      const subTarget = subButtons
        .map((button) => ({ button, label: String(button.textContent || '').trim(), score: scoreLabel(button.textContent) }))
        .filter((item) => item.score > 0)
        .sort((left, right) => right.score - left.score)[0];

      if (subTarget?.button && subTarget.score >= mainTarget.score * 0.55) {
        subTarget.button.click();
        return { ok: true, mainLabel: mainTarget.label, subLabel: subTarget.label, source: 'visible_pills' };
      }

      return { ok: true, mainLabel: mainTarget.label, subLabel: '', source: 'visible_pills' };
    },
  });

  return result?.result || { ok: false };
}

async function waitForBestsellerCards(tabId, timeoutMs) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    try {
      const [result] = await chrome.scripting.executeScript({
        target: { tabId },
        func: () => document.querySelectorAll([
          'main a[data-testid="product-card-link"]',
          'main a.product-card-link',
          'main a.product-card[href*="-p-"]',
          '.p-card-wrppr a[href*="-p-"]',
          '.prdct-cntnr-wrppr a[href*="-p-"]',
        ].join(', ')).length,
      });

      if (Number(result?.result || 0) > 0) {
        return;
      }
    } catch (error) {
      // SPA henüz yeni document'e geçiyor olabilir.
    }

    await delay(400);
  }

  throw new Error('Trendyol ürün kartları zamanında yüklenemedi.');
}

async function readBestsellerPageLabel(tabId) {
  try {
    const [result] = await chrome.scripting.executeScript({
      target: { tabId },
      func: () => {
        const text = String(document.querySelector('main h1, main h2, h1')?.textContent || '')
          .replace(/\s+/g, ' ')
          .trim();

        return text.replace(/\s+Kategorisinde$/i, '').trim();
      },
    });

    return String(result?.result || '').trim();
  } catch (error) {
    return '';
  }
}

async function extractBestsellerCards(tabId, minPrice, maxPrice) {
  const [result] = await chrome.scripting.executeScript({
    target: { tabId },
    args: [minPrice, maxPrice],
    func: (minimum, maximum) => {
      const compactNumber = (value) => {
        const match = String(value || '').match(/([\d.,]+)\s*(B|K|M)?\+?/i);
        if (!match) return null;
        let number = Number.parseFloat(match[1].replace(/\./g, '').replace(',', '.'));
        const suffix = String(match[2] || '').toUpperCase();
        if (suffix === 'B' || suffix === 'K') number *= 1000;
        if (suffix === 'M') number *= 1000000;
        return Number.isFinite(number) ? Math.round(number) : null;
      };
      const priceNumber = (value) => {
        const match = String(value || '').match(/[\d.,]+/);
        if (!match) return null;
        const number = Number.parseFloat(match[0].replace(/\./g, '').replace(',', '.'));
        return Number.isFinite(number) ? number : null;
      };
      const cards = Array.from(document.querySelectorAll([
        'main a[data-testid="product-card-link"]',
        'main a.product-card-link',
        'main a.product-card[href*="-p-"]',
        '.p-card-wrppr a[href*="-p-"]',
        '.prdct-cntnr-wrppr a[href*="-p-"]',
        'main a[href*="-p-"]',
      ].join(', '))).filter((card) => (
        !card.matches('.seller-store-product-card')
        && !card.closest('.seller-store-product-card, [class*="seller-store-product"]')
      ));
      const seen = new Set();
      const items = [];

      for (const card of cards) {
        const href = String(card.getAttribute('href') || '');
        const productId = href.match(/-p-(\d+)/i)?.[1] || '';
        if (!productId || seen.has(productId)) continue;

        const cardRoot = card.closest(
          '[data-testid*="product-card"], .product-card, .p-card-wrppr, .p-card-chldrn-cntnr, article, li',
        ) || card.parentElement || card;
        const priceSelectors = [
          '.current-price__current',
          '.prc-box-dscntd',
          '.price-section',
          '.single-price',
          '.prc-box-sllng',
          '.discounted-price',
          '[data-testid*="price"]',
        ];
        let priceNode = null;
        for (const selector of priceSelectors) {
          priceNode = cardRoot.querySelector?.(selector);
          if (priceNode) break;
        }
        const currencyPrice = String(cardRoot.innerText || '').match(
          /(?:^|\s)(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?)\s*(?:₺|TL)(?:\s|$)/i,
        )?.[1] || '';
        const price = priceNumber(priceNode?.textContent || currencyPrice);
        if (Number.isFinite(Number(minimum)) && Number(minimum) > 0 && (price === null || price < Number(minimum))) continue;
        if (Number.isFinite(Number(maximum)) && Number(maximum) > 0 && (price === null || price > Number(maximum))) continue;

        const text = String(cardRoot.innerText || card.innerText || '').replace(/\s+/g, ' ').trim();
        const soldText = text.match(/3\s*günde\s*([\d.,]+\s*(?:B|K|M)?\+?)\s*ürün\s*satıldı/i)?.[0] || '';
        const favoriteText = text.match(/([\d.,]+\s*(?:B|K|M)?\+?)\s*kişi\s*favoriledi/i)?.[0] || '';
        const basketText = text.match(/([\d.,]+\s*(?:B|K|M)?\+?)\s*kişinin\s*sepetinde/i)?.[0] || '';
        const viewText = text.match(/24\s*saatte\s*([\d.,]+\s*(?:B|K|M)?\+?)\s*kişi\s*inceledi/i)?.[0] || '';
        const sales3d = compactNumber(soldText);
        const productImage = Array.from(cardRoot.querySelectorAll(
          '.product-image-preview img, .product-image-wrapper img, img.p-card-img, img',
        ))
          .find((image) => (
            (image.src || image.dataset?.src)
            && !/rank|stamp|badge|favorite|rocket|basket|heart|view|icon/i.test(image.src || image.dataset?.src)
            && (image.naturalWidth === 0 || image.naturalWidth >= 80)
          ));
        const ratingCountText = cardRoot.querySelector(
          '.p-total-rating-count, .ratingCount, [class*="rating-count"], .review-rating',
        )?.textContent || '';
        const ratingText = cardRoot.querySelector(
          '.rating-score, [class*="rating-score"], .average-rating, .ratings',
        )?.textContent || '';
        const ratingCountValue = String(ratingCountText).match(/\(([\d.]+)\)/)?.[1]
          || String(ratingCountText);
        const ratingCount = Number.parseInt(ratingCountValue.replace(/\D/g, ''), 10);
        const rating = Number.parseFloat(String(ratingText).match(/\d(?:[.,]\d)?/)?.[0]?.replace(',', '.') || '');
        const brand = String(cardRoot.querySelector(
          '.product-brand-name, .product-brand, .prdct-desc-cntnr-ttl, [class*="brand-name"]',
        )?.textContent || '').replace(/\s+/g, ' ').trim();
        const productName = String(cardRoot.querySelector(
          '.product-name, .prdct-desc-cntnr-name, [class*="product-name"]',
        )?.textContent || '').replace(/\s+/g, ' ').trim();
        const cardTitle = String(card.getAttribute('title') || '').replace(/\s+/g, ' ').trim();
        const title = cardTitle || [brand, productName].filter(Boolean).join(' ').trim() || text.slice(0, 180);
        const rankFromText = text.match(/En\s+Çok\s+Satan\s*(\d+)\.?\s*Ürün/i)?.[1] || '';

        seen.add(productId);
        items.push({
          id: productId,
          trendyol_product_id: productId,
          source_url: new URL(href, window.location.origin).href,
          url: href,
          rank: Number.parseInt(
            cardRoot.querySelector('.ranks-badge-number')?.textContent || rankFromText || items.length + 1,
            10,
          ),
          name: title,
          title,
          brand,
          image_url: productImage?.src || productImage?.dataset?.src || '',
          price,
          rating: Number.isFinite(rating) ? rating : null,
          rating_count: Number.isFinite(ratingCount) ? ratingCount : 0,
          sold_text: soldText,
          estimated_sales_3d: sales3d,
          estimated_revenue_3d: sales3d !== null && price !== null ? Math.round(sales3d * price * 100) / 100 : null,
          favorite_text: favoriteText,
          favorite_count: compactNumber(favoriteText),
          basket_count: compactNumber(basketText),
          view_count_24h: compactNumber(viewText),
        });

        if (items.length >= 20) break;
      }

      return items;
    },
  });

  return Array.isArray(result?.result) ? result.result : [];
}

async function enrichBestsellerProducts(products) {
  const results = new Array(products.length);
  let cursor = 0;
  const workerCount = Math.min(3, products.length);

  const worker = async () => {
    while (cursor < products.length) {
      const index = cursor;
      cursor += 1;
      const product = products[index];

      try {
        const payload = await productPayloadFromUrl(product.source_url, false);
        const page = payload?.page || {};
        const metrics = payload?.metrics || {};
        const sellers = Array.isArray(page.sellers) ? page.sellers : [];
        const seller = sellers[0] || {};
        const campaigns = Array.isArray(page.promotions) ? page.promotions.filter(Boolean) : [];
        const livePrice = Number(page.sale_price || product.price || 0);
        const sales3d = Number.isFinite(Number(product.estimated_sales_3d)) ? Number(product.estimated_sales_3d) : null;

        results[index] = {
          ...product,
          price: livePrice > 0 ? livePrice : product.price,
          estimated_revenue_3d: sales3d !== null && livePrice > 0
            ? Math.round(sales3d * livePrice * 100) / 100
            : product.estimated_revenue_3d,
          seller_name: String(seller.seller_name || ''),
          seller_id: String(seller.seller_id || page.seller_id || ''),
          seller_score: seller.seller_score ?? page.seller_score ?? metrics.seller_score ?? null,
          sellers,
          stock_quantity: page.total_stock ?? null,
          total_stock: page.total_stock ?? null,
          stock_status: page.stock_status || 'unknown',
          campaign_count: Number.isFinite(Number(page.campaign_count)) ? Number(page.campaign_count) : campaigns.length,
          campaigns,
          promotions: campaigns,
          listing_id: page.listing_id || '',
          barcode: page.barcode || '',
          enrichment_status: 'enriched',
          captured_at: new Date().toISOString(),
        };
      } catch (error) {
        results[index] = {
          ...product,
          enrichment_status: 'partial',
          enrichment_error: error instanceof Error ? error.message : 'Ürün detayı okunamadı.',
          captured_at: new Date().toISOString(),
        };
      }
    }
  };

  await Promise.all(Array.from({ length: workerCount }, () => worker()));

  return results.filter(Boolean);
}


function normalizeTrendyolUrl(value) {
  let url;

  try {
    url = new URL(String(value || '').trim());
  } catch (error) {
    throw new Error('Geçerli bir Trendyol ürün linki girin.');
  }

  const host = url.hostname.toLowerCase();
  const allowed = host === 'trendyol.com' || host.endsWith('.trendyol.com') || host === 'ty.gl' || host.endsWith('.ty.gl');

  if (!allowed || !['http:', 'https:'].includes(url.protocol)) {
    throw new Error('Geçerli bir Trendyol ürün linki girin.');
  }

  return url.href;
}

async function waitForTabComplete(tabId, timeoutMs) {
  const current = await chrome.tabs.get(tabId);

  if (current.status === 'complete') {
    return;
  }

  await new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      chrome.tabs.onUpdated.removeListener(listener);
      reject(new Error('Trendyol ürün sayfası zamanında yüklenemedi.'));
    }, timeoutMs);
    const listener = (updatedTabId, changeInfo) => {
      if (updatedTabId !== tabId || changeInfo.status !== 'complete') {
        return;
      }

      clearTimeout(timer);
      chrome.tabs.onUpdated.removeListener(listener);
      resolve();
    };

    chrome.tabs.onUpdated.addListener(listener);
  });
}

async function waitForSearchCards(tabId, timeoutMs) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    try {
      const [result] = await chrome.scripting.executeScript({
        target: { tabId },
        func: () => {
          if (document.querySelectorAll('.p-card-wrppr, .prdct-cntnr-wrppr').length > 0) return 'found';
          if (document.querySelector('.no-rslt-icon') || document.body.innerText.includes('sonuç bulunamadı')) return 'empty';
          return 'loading';
        },
      });

      if (result?.result === 'found' || result?.result === 'empty') {
        return result.result;
      }
    } catch (error) {
      // navigation can cause errors during checking
    }
    await delay(400);
  }

  throw new Error('Arama sonuçları sayfada yüklenemedi.');
}

async function readPageStatus(tabId, messageType = 'ZOLM_BOOSTER_PAGE_STATUS') {
  let lastError = null;

  for (let attempt = 0; attempt < 20; attempt += 1) {
    try {
      const response = await chrome.tabs.sendMessage(tabId, { type: messageType });

      if (response?.ok) {
        return response;
      }
    } catch (error) {
      lastError = error;
    }

    await delay(350);
  }

  throw lastError || new Error('Trendyol sayfa okuyucusu hazır olmadı.');
}

function delay(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function wakeZolmTabs() {
  try {
    const tabs = await chrome.tabs.query({ url: ZOLM_BRIDGE_TAB_PATTERNS });
    await Promise.all(tabs.map((tab) => {
      if (!tab.id) return Promise.resolve();

      return chrome.scripting.executeScript({
        target: { tabId: tab.id },
        files: ['zolm-bridge.js'],
      }).catch(() => undefined);
    }));

    return tabs.length;
  } catch (error) {
    console.warn('[Background Worker] ZOLM sekmeleri uyandırılamadı:', error);
    return 0;
  }
}

async function companionSession() {
  const baseUrl = await getBaseUrl();
  const response = await fetchWithTimeout(`${baseUrl}${COMPANION_PATH}/session`, {
    method: 'GET',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  }, ZOLM_REQUEST_TIMEOUT_MS);

  const json = await readJson(response);

  if (response.status === 401) {
    throw new Error('ZOLM oturumu doğrulanamadı. Yapılandırılan ZOLM adresinde giriş yapıp popup içinden Oturumu test et düğmesine basın.');
  }

  if (!response.ok || !json.ok) {
    throw new Error(json.message || 'ZOLM oturumu bulunamadı. Önce ZOLM paneline giriş yapın.');
  }

  return json;
}
async function companionPost(action, payload) {
  const baseUrl = await getBaseUrl();
  const session = await companionSession();
  const endpoint = companionEndpoint(baseUrl, session.endpoints?.[action], action);

  if (!endpoint) {
    throw new Error('ZOLM companion endpoint bulunamadı.');
  }

  const response = await fetchWithTimeout(endpoint, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': session.csrf_token,
    },
    body: JSON.stringify(payload),
  }, ZOLM_REQUEST_TIMEOUT_MS);

  const json = await readJson(response);

  if (response.status === 401 || response.status === 419) {
    throw new Error('ZOLM oturumu veya CSRF doğrulaması geçmedi. ZOLM panelini yenileyip popup içinden Oturumu test et düğmesine basın.');
  }

  if (!response.ok || !json.ok) {
    throw new Error(json.message || validationMessage(json) || 'ZOLM Booster isteği başarısız oldu.');
  }

  return json;
}

async function companionStatus(productId) {
  const normalizedId = String(productId || '').replace(/\D/g, '');
  if (!normalizedId) {
    return { ok: true, tracked: false, product: null };
  }

  const baseUrl = await getBaseUrl();
  const session = await companionSession();
  const endpoint = companionEndpoint(baseUrl, session.endpoints?.status, 'status');
  const url = new URL(endpoint);
  url.searchParams.set('product_id', normalizedId);
  const response = await fetchWithTimeout(url.href, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  }, ZOLM_REQUEST_TIMEOUT_MS);
  const json = await readJson(response);

  if (response.status === 401) {
    throw new Error('ZOLM oturumu eklenti tarafından doğrulanamadı. ZOLM panelinde oturum açık olmalı.');
  }

  if (!response.ok || !json.ok) {
    throw new Error(json.message || 'Takip durumu okunamadı.');
  }

  return json;
}

function companionEndpoint(baseUrl, endpoint, action) {
  if (!endpoint) {
    return `${baseUrl}${COMPANION_PATH}/${actionPath(action)}`;
  }

  try {
    const parsed = new URL(endpoint);
    return `${baseUrl}${parsed.pathname}`;
  } catch (error) {
    return endpoint.startsWith('/') ? `${baseUrl}${endpoint}` : `${baseUrl}${COMPANION_PATH}/${actionPath(action)}`;
  }
}

function actionPath(action) {
  return {
    product_analysis: 'product-analysis',
    stock_check: 'stock-check',
    store_scan: 'store-scan',
    review_scan_start: 'review-scan/start',
    review_scan_ingest: 'review-scan/ingest',
    review_scan_status: 'review-scan/status',
    review_scan_verify: 'review-scan/verify',
    bestseller_capture: 'bestseller-capture',
  }[action] || action;
}

// ===== Rate Limiting & Backoff (Faz 2) =====

const REVIEW_SCAN_RATE = {
  MIN_DELAY_MS: 2000,
  MAX_DELAY_MS: 5000,
  MAX_RETRIES: 3,
  BACKOFF_BASE_MS: 5000,
  BATCH_SIZE: 50,
};

function randomDelay(minMs = REVIEW_SCAN_RATE.MIN_DELAY_MS, maxMs = REVIEW_SCAN_RATE.MAX_DELAY_MS) {
  const delay = minMs + Math.random() * (maxMs - minMs);
  return new Promise((resolve) => setTimeout(resolve, delay));
}

async function fetchWithRetry(url, options = {}, maxRetries = REVIEW_SCAN_RATE.MAX_RETRIES) {
  let lastError = null;
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    try {
      const response = await fetch(url, options);
      if (response.status === 429 || response.status === 503) {
        if (attempt < maxRetries) {
          const backoff = REVIEW_SCAN_RATE.BACKOFF_BASE_MS * Math.pow(2, attempt);
          console.warn(`[Review Scan] Rate limit (${response.status}), backoff ${backoff}ms (attempt ${attempt + 1}/${maxRetries})`);
          await new Promise((resolve) => setTimeout(resolve, backoff));
          continue;
        }
      }
      return response;
    } catch (error) {
      lastError = error;
      if (attempt < maxRetries) {
        const backoff = REVIEW_SCAN_RATE.BACKOFF_BASE_MS * Math.pow(2, attempt);
        await new Promise((resolve) => setTimeout(resolve, backoff));
        continue;
      }
      throw error;
    }
  }
  throw lastError || new Error('Max retries exceeded');
}

async function readJson(response) {
  const text = await response.text();

  try {
    return text ? JSON.parse(text) : {};
  } catch (error) {
    if (response.status === 401 || response.redirected || /<form|<!doctype html|<html/i.test(text)) {
      throw new Error('ZOLM oturumu doğrulanamadı. Yapılandırılan ZOLM adresinde giriş yapıp popup içinden Oturumu test et düğmesine basın.');
    }

    throw new Error(`ZOLM JSON yanıtı alınamadı (${response.status}).`);
  }
}

async function fetchWithTimeout(url, options = {}, timeoutMs = ZOLM_REQUEST_TIMEOUT_MS) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal,
    });
  } catch (error) {
    if (error?.name === 'AbortError') {
      throw new Error('ZOLM isteği zaman aşımına uğradı. ZOLM adresini ve oturumu kontrol edin.');
    }

    throw error;
  } finally {
    clearTimeout(timer);
  }
}

function validationMessage(json) {
  const errors = json?.errors || {};
  const first = Object.values(errors)[0];

  return Array.isArray(first) ? first[0] : '';
}

async function getBaseUrl() {
  const stored = await chrome.storage.sync.get({ zolmBaseUrl: DEFAULT_BASE_URL });
  let baseUrl = String(stored.zolmBaseUrl || DEFAULT_BASE_URL).trim().replace(/\/+$/, '');
  baseUrl = baseUrl.replace(/:+$/, '');

  return baseUrl || DEFAULT_BASE_URL;
}

function normalizeListingProductUrls(values, maxProducts = 4) {
  if (!Array.isArray(values)) return [];

  return Array.from(new Set(values.map((value) => {
    try {
      const productUrl = new URL(String(value || ''));
      if (!/^https:\/\/([^/]+\.)?trendyol\.com\//i.test(productUrl.href) || !/-p-\d+/i.test(productUrl.pathname)) {
        return '';
      }

      productUrl.search = '';
      productUrl.hash = '';
      return productUrl.href;
    } catch (error) {
      return '';
    }
  }).filter(Boolean))).slice(0, maxProducts);
}

async function downloadProductMedia(value, requestedFilename) {
  let url;
  try {
    url = new URL(String(value || ''));
  } catch (error) {
    throw new Error('Geçerli bir Trendyol medya bağlantısı gerekir.');
  }

  const hostname = url.hostname.toLowerCase();
  const allowedHost = hostname === 'trendyol.com'
    || hostname.endsWith('.trendyol.com')
    || hostname === 'dsmcdn.com'
    || hostname.endsWith('.dsmcdn.com');
  if (url.protocol !== 'https:' || !allowedHost) {
    throw new Error('Yalnız Trendyol ürün medyası indirilebilir.');
  }

  const response = await fetch(url.href, { credentials: 'omit', redirect: 'follow' });
  if (!response.ok) {
    throw new Error(`Ürün görseli indirilemedi (${response.status}).`);
  }

  const contentType = String(response.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
  const extensions = {
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
    'image/avif': 'avif',
    'image/gif': 'gif',
  };
  const extension = extensions[contentType];
  if (!extension) {
    throw new Error('Bu medya türü güvenli görsel indirme listesinde değil.');
  }

  const declaredSize = Number(response.headers.get('content-length') || 0);
  if (declaredSize > 15 * 1024 * 1024) {
    throw new Error('Görsel 15 MB indirme sınırını aşıyor.');
  }

  const buffer = await response.arrayBuffer();
  if (buffer.byteLength === 0 || buffer.byteLength > 15 * 1024 * 1024) {
    throw new Error('Görsel boş veya 15 MB sınırından büyük.');
  }

  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let offset = 0; offset < bytes.length; offset += 0x8000) {
    binary += String.fromCharCode(...bytes.subarray(offset, offset + 0x8000));
  }
  const safeName = String(requestedFilename || 'trendyol-urun')
    .normalize('NFKD')
    .replace(/[^a-zA-Z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80) || 'trendyol-urun';

  return {
    ok: true,
    mode: 'media_download',
    filename: `${safeName}.${extension}`,
    data_url: `data:${contentType};base64,${btoa(binary)}`,
    byte_size: buffer.byteLength,
  };
}

async function openBoosterDashboard(module = 'analysis', keyword = '', options = {}) {
  const allowedModules = new Set([
    'analysis',
    'tracking',
    'bestseller',
    'comparison',
    'sell_decision',
    'profit_loss',
  ]);
  const safeModule = allowedModules.has(String(module)) ? String(module) : 'analysis';
  const baseUrl = await getBaseUrl();
  const url = new URL('/marketplace-trendyol-booster', `${baseUrl}/`);
  url.searchParams.set('booster', safeModule);

  if (safeModule === 'bestseller' && String(keyword || '').trim()) {
    url.searchParams.set('bestseller_q', String(keyword).trim().slice(0, 120));
  }
  if (safeModule === 'bestseller' && options.reportMode === 'reports') {
    url.searchParams.set('bestseller_mode', 'reports');
  }
  if (safeModule === 'bestseller' && Number.isInteger(Number(options.reportId)) && Number(options.reportId) > 0) {
    url.searchParams.set('bestseller_report', String(Number(options.reportId)));
  }
  if (safeModule === 'comparison' && Array.isArray(options.comparisonUrls)) {
    options.comparisonUrls.slice(0, 4).forEach((productUrl, index) => {
      url.searchParams.set(`compare[${index}]`, String(productUrl));
    });
    if (options.comparisonUrls.length >= 2) {
      url.searchParams.set('compare_now', '1');
    }
  }
  if (safeModule === 'sell_decision' && Number.isInteger(Number(options.decisionTrackedProductId)) && Number(options.decisionTrackedProductId) > 0) {
    url.searchParams.set('decision_product', String(Number(options.decisionTrackedProductId)));
  }

  const tab = await chrome.tabs.create({ url: url.href });

  if (!tab?.id) {
    throw new Error('ZOLM Booster sekmesi açılamadı. Eklenti ayarlarındaki ZOLM adresini kontrol edin.');
  }

  return {
    ok: true,
    mode: 'dashboard_opened',
    module: safeModule,
    url: url.href,
  };
}

// ─── BACKGROUND AUTOMATION (Cron / Alarms) ─────────────────────────
chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create('zolm-booster-auto-scan', { periodInMinutes: 15 });
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'zolm-booster-auto-scan') {
    await runPendingJobs();
  } else if (alarm.name === DECISION_QUEUE_ALARM) {
    await processDecisionQueue();
  }
});

let isRunningJobs = false;

async function runPendingJobs() {
  if (isRunningJobs) return;
  isRunningJobs = true;

  try {
    const session = await companionSession().catch(() => null);
    if (!session || !session.authenticated || !session.endpoints?.pending_jobs) {
      isRunningJobs = false;
      return;
    }

    const baseUrl = await getBaseUrl();
    const endpoint = companionEndpoint(baseUrl, session.endpoints.pending_jobs, 'pending-jobs');
    const response = await fetch(endpoint, {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) throw new Error('ZOLM pending jobs endpoint hatası');
    const json = await response.json();
    
    if (json.jobs) {
      if (json.jobs.keywords && json.jobs.keywords.length > 0) {
        for (const kw of json.jobs.keywords) {
          if (kw.search_url) {
            await keywordTrackingFromUrl(kw.search_url, [kw.keyword]).catch(e => console.warn('Keyword scan failed:', e));
            await new Promise(r => setTimeout(r, 2000)); // bekleme
          }
        }
      }

      if (json.jobs.stores && json.jobs.stores.length > 0) {
        for (const store of json.jobs.stores) {
          if (store.store_url) {
            await storeFullScanFromUrl(store.store_url).catch(e => console.warn('Store scan failed:', e));
            await new Promise(r => setTimeout(r, 5000)); // bekleme
          }
        }
      }
    }
  } catch (error) {
    console.warn('Booster Auto Scan error:', error);
  } finally {
    isRunningJobs = false;
  }
}

// ─── Trendyol Seller Panel: Maliyet Sorgulama ────────────────
async function pricingCostLookup(barcodes, modelCodes, stockCodes) {
  const baseUrl = await getBaseUrl();
  const session = await companionSession();
  const endpoint = companionEndpoint(baseUrl, session.endpoints?.pricing_cost_lookup, 'pricing-cost-lookup');

  if (!endpoint) {
    throw new Error('ZOLM pricing-cost-lookup endpoint bulunamadı.');
  }

  const url = new URL(endpoint);

  // Query string olarak barkod ve model kodlarını ekle
  if (Array.isArray(barcodes)) {
    barcodes.filter(Boolean).forEach(b => url.searchParams.append('barcodes[]', b));
  }
  if (Array.isArray(modelCodes)) {
    modelCodes.filter(Boolean).forEach(m => url.searchParams.append('model_codes[]', m));
  }
  if (Array.isArray(stockCodes)) {
    stockCodes.filter(Boolean).forEach(s => url.searchParams.append('stock_codes[]', s));
  }

  const response = await fetchWithTimeout(url.href, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  }, ZOLM_REQUEST_TIMEOUT_MS);

  const json = await readJson(response);

  if (response.status === 401) {
    throw new Error('ZOLM oturumu eklenti tarafından doğrulanamadı. ZOLM panelinde oturum açık olmalı.');
  }

  if (!response.ok || !json.ok) {
    throw new Error(json.message || 'Maliyet sorgulama başarısız.');
  }

  return json;
}

// ─── Trendyol Seller Panel: Maliyet Güncelleme ────────────────
async function updateProductCost(payload) {
  const baseUrl = await getBaseUrl();
  const session = await companionSession();
  const endpoint = companionEndpoint(baseUrl, session.endpoints?.update_product_cost, 'update-product-cost');

  if (!endpoint) {
    throw new Error('ZOLM update-product-cost endpoint bulunamadı.');
  }

  const response = await fetchWithTimeout(endpoint, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': session.csrf_token,
    },
    body: JSON.stringify(payload),
  }, ZOLM_REQUEST_TIMEOUT_MS);

  const json = await readJson(response);

  if (response.status === 401 || response.status === 419) {
    throw new Error('ZOLM oturumu veya CSRF doğrulaması geçmedi.');
  }

  if (!response.ok || !json.ok) {
    throw new Error(json.message || 'Maliyet güncelleme başarısız.');
  }

  return json;
}

async function orderProfitLookup(payload) {
  const baseUrl = await getBaseUrl();
  const session = await companionSession();
  const endpoint = companionEndpoint(baseUrl, session.endpoints?.order_profit_lookup, 'order-profit-lookup');

  if (!endpoint) {
    throw new Error('ZOLM order-profit-lookup endpoint bulunamadı.');
  }

  const response = await fetchWithTimeout(endpoint, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': session.csrf_token,
    },
    body: JSON.stringify(payload),
  }, ZOLM_REQUEST_TIMEOUT_MS);

  const json = await readJson(response);

  if (response.status === 401 || response.status === 419) {
    throw new Error('ZOLM oturumu veya CSRF doğrulaması geçmedi.');
  }

  if (!response.ok || !json.ok) {
    throw new Error(json.message || 'Sipariş kârlılığı sorgulanamadı.');
  }

  return json;
}
