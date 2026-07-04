(function () {
  const PANEL_ID = 'zolm-trendyol-booster-panel';
  const CONTENT_VERSION = chrome.runtime.getManifest().version;

  if (window[PANEL_ID] === CONTENT_VERSION) {
    return;
  }

  window[PANEL_ID] = CONTENT_VERSION;
  document.getElementById(PANEL_ID)?.remove();

  // Bestseller listener unconditionally registered — search result pages
  // (/sr?q=...) have context 'unknown' and hit the early return below,
  // so the listener must be hoisted above it.
  chrome.runtime.onMessage.addListener(function bestsellerHandler(message, sender, sendResponse) {
    if (message?.type !== 'ZOLM_BOOSTER_BESTSELLER_PAGE_STATUS') {
      return false;
    }

    const state = readSearchState();
    const products = Array.isArray(state?.products) ? state.products : [];

    sendResponse({
      ok: products.length > 0,
      products,
      message: products.length === 0 ? 'Trendyol arama sayfasında ürün verisi bulunamadı.' : '',
    });

    return false;
  });

  chrome.runtime.onMessage.addListener(function reviewSyncHandler(message, sender, sendResponse) {
    if (message?.type === 'ZOLM_BOOSTER_REVIEW_PRODUCT_LIST') {
      const store = extractStoreData().store;
      sendResponse({
        ok: true,
        store,
        products: extractStoreProductListForReviews(message.max_products || 500),
      });

      return false;
    }

    if (message?.type !== 'ZOLM_BOOSTER_REVIEW_FETCH') {
      return false;
    }

    fetchReviewsForSync(message.product_id, {
      since: message.since || null,
      maxPages: message.max_pages || 50,
    })
      .then((result) => sendResponse({ ok: true, ...result }))
      .catch((error) => sendResponse({
        ok: false,
        message: error instanceof Error ? error.message : 'Yorumlar okunamadı.',
        reviews: [],
        summary: {},
      }));

    return true;
  });

  const context = pageContext();

  if (context === 'unknown') {
    return;
  }

  const panel = createPanel();
  document.documentElement.appendChild(panel.host);
  refreshPanel(panel);

  panel.previewButton.addEventListener('click', () => sendToZolm(panel, 'ZOLM_BOOSTER_PREVIEW'));
  panel.trackButton.addEventListener('click', () => sendToZolm(panel, 'ZOLM_BOOSTER_TRACK'));
  panel.stockButton.addEventListener('click', () => sendToZolm(panel, 'ZOLM_BOOSTER_STOCK_CHECK'));
  panel.storeButton.addEventListener('click', () => sendToZolm(panel, 'ZOLM_BOOSTER_STORE_SCAN'));
  panel.refreshButton.addEventListener('click', () => refreshPanel(panel));

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type === 'ZOLM_BOOSTER_PRODUCT_ANALYSIS_PAGE_STATUS') {
      collectProductAnalysis()
        .then((payload) => sendResponse({
          ok: Boolean(payload?.page?.trendyol_product_id),
          context: 'product',
          payload,
        }))
        .catch((error) => sendResponse({
          ok: false,
          context: 'product',
          message: error instanceof Error ? error.message : 'Ürün analizi okunamadı.',
        }));

      return true;
    }

    if (message?.type !== 'ZOLM_BOOSTER_PAGE_STATUS') {
      return false;
    }

    const currentContext = pageContext();
    const payload = currentContext === 'unknown' ? null : extractPayload(currentContext);
    sendResponse({
      ok: currentContext !== 'unknown',
      context: currentContext,
      payload,
      summary: payload ? payloadSummary(payload, currentContext) : null,
    });

    return false;
  });
}
)();

function refreshPanel(panel) {
  const context = pageContext();

  if (context === 'unknown') {
    panel.status.textContent = 'Bu sayfa ürün veya mağaza sayfası değil.';
    panel.status.className = 'js-status err';
    return;
  }

  updatePanel(panel, extractPayload(context), context);
}

function createPanel() {
  const host = document.createElement('div');
  host.id = 'zolm-trendyol-booster-panel';
  host.style.position = 'fixed';
  host.style.right = '16px';
  host.style.bottom = '16px';
  host.style.zIndex = '2147483647';

  const shadow = host.attachShadow({ mode: 'open' });
  shadow.innerHTML = `
    <style>
      :host { all: initial; }
      .box {
        width: 320px;
        border: 1px solid #dbe3ef;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
        color: #0f172a;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        overflow: hidden;
      }
      .head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border-bottom: 1px solid #e2e8f0;
        padding: 12px;
      }
      .brand { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #475569; }
      .pill { border: 1px solid #bbf7d0; border-radius: 6px; background: #f0fdf4; padding: 3px 6px; font-size: 11px; color: #047857; }
      .pill.warn { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }
      .body { padding: 12px; }
      .title { margin: 0; font-size: 14px; font-weight: 700; line-height: 1.35; }
      .meta { margin-top: 5px; font-size: 12px; color: #64748b; }
      .hint { margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 8px; font-size: 12px; line-height: 1.45; color: #475569; }
      .hint.warn { border-color: #fed7aa; background: #fff7ed; color: #9a3412; }
      .tracking { display: none; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
      .tracking.show { display: block; }
      .tracking-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
      .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; }
      .metric { border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 8px; }
      .label { font-size: 11px; color: #64748b; }
      .value { margin-top: 3px; font-size: 14px; font-weight: 700; color: #0f172a; }
      .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
      button {
        min-height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #334155;
        cursor: pointer;
        font: inherit;
        font-size: 12px;
        font-weight: 700;
      }
      button.primary { border-color: #0f172a; background: #0f172a; color: #fff; }
      button:disabled { cursor: not-allowed; opacity: .6; }
      .foot { border-top: 1px solid #e2e8f0; padding: 9px 12px; font-size: 12px; color: #64748b; }
      .ok { color: #047857; }
      .err { color: #be123c; }
      .refresh { border: 0; min-height: 28px; padding: 0 4px; color: #64748b; }
    </style>
    <div class="box">
      <div class="head">
        <div>
          <div class="brand">ZOLM Booster</div>
          <div class="meta js-id"></div>
        </div>
        <div class="pill">Trendyol</div>
      </div>
      <div class="body">
        <p class="title js-title"></p>
        <div class="meta js-brand"></div>
        <div class="grid">
          <div class="metric">
            <div class="label">Sayfa fiyatı</div>
            <div class="value js-price"></div>
          </div>
          <div class="metric">
          <div class="label">Kategori</div>
          <div class="value js-category"></div>
          </div>
        </div>
        <div class="hint js-hint"></div>
        <div class="tracking js-tracking">
          <div class="tracking-title"><span>Booster Radar</span><span class="ok js-last-scan"></span></div>
          <div class="grid">
            <div class="metric"><div class="label">Tahmini satış</div><div class="value js-estimated-sales">-</div></div>
            <div class="metric"><div class="label">Risk / Güven</div><div class="value js-risk-confidence">-</div></div>
            <div class="metric"><div class="label">Stok / bitiş</div><div class="value js-tracked-stock">-</div></div>
            <div class="metric"><div class="label">Favori farkı</div><div class="value js-favorite-delta">-</div></div>
          </div>
        </div>
        <div class="actions">
          <button class="js-preview">Ön izle</button>
          <button class="primary js-track">Takibe al</button>
          <button class="js-stock">Stok sorgula</button>
          <button class="primary js-store">Mağaza tara</button>
        </div>
      </div>
      <div class="foot">
        <button class="refresh js-refresh" type="button">Yenile</button>
        <span class="js-status">Hazır</span>
      </div>
    </div>
  `;

  return {
    host,
    title: shadow.querySelector('.js-title'),
    brand: shadow.querySelector('.js-brand'),
    price: shadow.querySelector('.js-price'),
    category: shadow.querySelector('.js-category'),
    id: shadow.querySelector('.js-id'),
    status: shadow.querySelector('.js-status'),
    hint: shadow.querySelector('.js-hint'),
    contextPill: shadow.querySelector('.pill'),
    previewButton: shadow.querySelector('.js-preview'),
    trackButton: shadow.querySelector('.js-track'),
    stockButton: shadow.querySelector('.js-stock'),
    storeButton: shadow.querySelector('.js-store'),
    refreshButton: shadow.querySelector('.js-refresh'),
    tracking: shadow.querySelector('.js-tracking'),
    estimatedSales: shadow.querySelector('.js-estimated-sales'),
    riskConfidence: shadow.querySelector('.js-risk-confidence'),
    trackedStock: shadow.querySelector('.js-tracked-stock'),
    favoriteDelta: shadow.querySelector('.js-favorite-delta'),
    lastScan: shadow.querySelector('.js-last-scan'),
  };
}

function updatePanel(panel, data, context) {
  const productMode = context === 'product';
  const page = data.page || {};
  const store = data.store || {};
  const summary = payloadSummary(data, context);

  panel.title.textContent = productMode ? (page.title || 'Ürün başlığı bulunamadı') : (store.store_name || 'Mağaza okunamadı');
  panel.brand.textContent = productMode
    ? ([page.brand, page.category_name].filter(Boolean).join(' · ') || 'Marka/kategori okunamadı')
    : `${store.items?.length || 0} ürün kartı yakalandı`;
  panel.price.textContent = productMode
    ? (page.sale_price > 0 ? formatMoney(page.sale_price) : 'Okunamadı')
    : `${store.items?.length || 0} ürün`;
  panel.category.textContent = productMode ? (page.category_name || '-') : `${summary.pricedItems || 0} fiyatlı kart`;
  panel.id.textContent = productMode
    ? (page.trendyol_product_id ? `ID ${page.trendyol_product_id}` : 'ID yok')
    : (store.store_id ? `Mağaza ${store.store_id}` : 'Mağaza ID yok');
  panel.contextPill.textContent = productMode ? 'Ürün' : 'Mağaza';
  panel.contextPill.className = summary.ready ? 'pill' : 'pill warn';
  panel.hint.textContent = summary.message;
  panel.hint.className = summary.ready ? 'hint' : 'hint warn';
  panel.previewButton.style.display = productMode ? '' : 'none';
  panel.trackButton.style.display = productMode ? '' : 'none';
  panel.stockButton.style.display = productMode ? '' : 'none';
  panel.storeButton.style.display = '';
  panel.storeButton.textContent = productMode ? 'Satıcıyı tara' : 'Mağaza tara';
  panel.previewButton.disabled = productMode && !page.trendyol_product_id;
  panel.trackButton.disabled = productMode && !page.trendyol_product_id;
  panel.stockButton.disabled = productMode && !page.trendyol_product_id;
  panel.storeButton.disabled = productMode ? !page.trendyol_product_id : !summary.ready;
  panel.status.textContent = 'Hazır';
  panel.status.className = 'js-status';
  panel.tracking.className = 'tracking js-tracking';
  panel.trackButton.textContent = 'Takibe al';

  if (productMode && page.trendyol_product_id) {
    refreshTrackingStatus(panel, page.trendyol_product_id);
  }
}

async function sendToZolm(panel, type) {
  const context = pageContext();
  const payload = requestPayloadFor(type, extractPayload(context), context);
  setButtonsDisabled(panel, true);
  panel.status.textContent = 'ZOLM ile konuşuyor...';
  panel.status.className = 'js-status';

  chrome.runtime.sendMessage({ type, payload }, (response) => {
    setButtonsDisabled(panel, false);

    if (chrome.runtime.lastError) {
      panel.status.textContent = chrome.runtime.lastError.message;
      panel.status.className = 'js-status err';
      return;
    }

    if (!response?.ok) {
      panel.status.textContent = response?.message || 'İşlem başarısız.';
      panel.status.className = 'js-status err';
      return;
    }

    panel.status.textContent = responseSummary(response);
    panel.status.className = 'js-status ok';

    if (type === 'ZOLM_BOOSTER_TRACK' && payload?.page?.trendyol_product_id) {
      refreshTrackingStatus(panel, payload.page.trendyol_product_id);
    }
  });
}

function requestPayloadFor(type, payload, context) {
  if (type !== 'ZOLM_BOOSTER_STORE_SCAN' || context !== 'product') {
    return payload;
  }

  const page = payload.page || {};
  const seller = Array.isArray(payload.sellers) ? payload.sellers[0] || {} : {};
  const storeId = String(page.seller_id || seller.seller_id || new URL(location.href).searchParams.get('merchantId') || '');
  const sellerLegal = page.seller_legal || extractSellerLegalDetails();
  const storeName = clean(seller.seller_name || page.seller_name || sellerLegal.seller_name || page.brand || 'Rakip Mağaza');
  const storeUrl = storeId ? storeUrlForSeller(storeName, storeId) : (payload.source_url || location.href);
  const productItem = {
    trendyol_product_id: String(page.trendyol_product_id || extractProductId(location.href) || ''),
    source_url: payload.source_url || location.href,
    title: clean(page.title || document.title || 'Rakip ürün').slice(0, 500),
    brand: clean(page.brand || '').slice(0, 120),
    category_name: clean(page.category_name || '').slice(0, 180),
    image_url: clean(page.image_url || meta('og:image') || '').slice(0, 1000),
    sale_price: Number(page.sale_price || 0),
    total_stock: Number.isFinite(Number(page.total_stock)) ? Number(page.total_stock) : null,
    stock_status: clean(page.stock_status || '').slice(0, 80),
    seller_id: storeId,
    seller_name: storeName,
  };

  return {
    store_url: storeUrl,
    store: {
      store_id: storeId,
      store_name: storeName,
      total_products: productItem.trendyol_product_id || productItem.title ? 1 : 0,
      source_product_url: payload.source_url || location.href,
      resolved_from_product: true,
      seller: sellerLegal,
      seller_title: sellerLegal.title || '',
      address: sellerLegal.address || '',
      kep: sellerLegal.kep || '',
      tax_number: sellerLegal.tax_number || '',
      tax_office: sellerLegal.tax_office || '',
      phone: sellerLegal.phone || '',
      product_preview: productItem,
      items: productItem.trendyol_product_id || productItem.title ? [productItem] : [],
    },
  };
}

function refreshTrackingStatus(panel, productId) {
  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_TRACKING_STATUS', product_id: productId }, (response) => {
    if (chrome.runtime.lastError || !response?.ok || !response.tracked || !response.product) {
      return;
    }

    const data = response.product;
    panel.tracking.className = 'tracking js-tracking show';
    panel.trackButton.textContent = 'Takipte · Güncelle';
    panel.estimatedSales.textContent = typeof data.estimated_daily_sales === 'number' ? `~${formatNumber(data.estimated_daily_sales)} / gün` : 'Hesaplanıyor';
    panel.riskConfidence.textContent = `${data.risk_score ?? 0} / %${data.confidence_score ?? 0}`;
    if (data.stock_quantity === null || data.stock_quantity === undefined) {
      panel.trackedStock.textContent = 'Yayınlanmıyor';
    } else if (typeof data.estimated_days_of_stock === 'number') {
      panel.trackedStock.textContent = `${formatNumber(data.stock_quantity)} / ~${formatNumber(data.estimated_days_of_stock)} gün`;
    } else if ((data.stock_sample_count ?? 0) < 2) {
      panel.trackedStock.textContent = `${formatNumber(data.stock_quantity)} / veri birikiyor`;
    } else {
      panel.trackedStock.textContent = `${formatNumber(data.stock_quantity)} / düşüş yok`;
    }
    panel.favoriteDelta.textContent = data.favorite_delta === null || data.favorite_delta === undefined ? 'Kıyas yok' : `${data.favorite_delta > 0 ? '+' : ''}${formatNumber(data.favorite_delta)}`;
    panel.lastScan.textContent = data.last_checked_at ? new Date(data.last_checked_at).toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'Bekliyor';
    panel.hint.textContent = 'Bu ürün ZOLM Booster Radar tarafından otomatik izleniyor.';
    panel.hint.className = 'hint js-hint';
  });
}

function setButtonsDisabled(panel, disabled) {
  panel.previewButton.disabled = disabled;
  panel.trackButton.disabled = disabled;
  panel.stockButton.disabled = disabled;
  panel.storeButton.disabled = disabled;
}

function responseSummary(response) {
  if (response.mode === 'stock_check') {
    return `Stok kaydedildi · ${response.stock?.total_stock ?? 0} stok · ${response.stock?.estimated_sales ?? 0} tahmini satış`;
  }

  if (response.mode === 'store_scan') {
    return `${response.store?.store_name || 'Mağaza'} tarandı · ${response.store?.total_products ?? 0} ürün`;
  }

  const decision = response.decision?.label || 'Tamamlandı';
  const score = response.decision?.score ?? '-';
  const profit = response.metrics?.net_profit;

  return `${decision} · ${score}/100${typeof profit === 'number' ? ` · ${formatMoney(profit)}` : ''}`;
}

function payloadSummary(data, context) {
  if (context === 'product') {
    const page = data.page || {};
    const sellers = Array.isArray(data.sellers) ? data.sellers : [];
    const missing = [];

    if (!page.trendyol_product_id) missing.push('ürün ID');
    if (!page.title) missing.push('başlık');
    if (!page.sale_price) missing.push('fiyat');
    if (data.total_stock === null || data.total_stock === undefined) missing.push('stok');

    return {
      ready: missing.length === 0,
      pricedItems: page.sale_price > 0 ? 1 : 0,
      message: missing.length === 0
        ? `${sellers.length} satıcıda ${data.total_stock} stok yakalandı. Maliyet eşleşmesi ZOLM panelinden tamamlanabilir.`
        : `Eksik alan: ${missing.join(', ')}. Sayfayı yenileyip tekrar deneyin.`,
    };
  }

  if (context === 'store') {
    const items = Array.isArray(data.store?.items) ? data.store.items : [];
    const pricedItems = items.filter((item) => Number(item.sale_price || 0) > 0).length;

    return {
      ready: items.length > 0,
      pricedItems,
      message: items.length > 0
        ? `${items.length} ürün kartı, ${pricedItems} fiyat sinyali yakalandı. Mağaza taraması rakip ledger'a yazılacak.`
        : 'Bu mağaza sayfasında ürün kartı yakalanamadı. Sayfayı aşağı kaydırıp Yenile deneyin.',
    };
  }

  return {
    ready: false,
    pricedItems: 0,
    message: 'Bu sayfa Trendyol ürün veya mağaza sayfası olarak algılanmadı.',
  };
}

function pageContext() {
  if (extractProductId(location.href)) {
    return 'product';
  }

  if (extractStoreId(location.href)) {
    return 'store';
  }

  return 'unknown';
}

function extractPayload(context) {
  return context === 'store' ? extractStoreData() : extractProductData();
}

function extractProductData() {
  const envoyProduct = readEnvoyProduct();
  const winner = envoyProduct?.merchantListing?.winnerVariant || {};
  const merchant = envoyProduct?.merchantListing?.merchant || {};
  const title = clean(envoyProduct?.name) || firstText([
    '[data-testid="product-title"]',
    'h1.pr-new-br',
    'h1.product-title',
    '.product-detail h1',
    'h1',
  ]) || cleanTitle(meta('og:title') || document.title);
  const brand = clean(envoyProduct?.brand?.name) || firstText([
    '[data-testid="brand-name"]',
    '.product-brand-name-with-link',
    '.product-brand-name',
    'a[href*="/magaza/"]',
  ]) || titleFromSlug(location.pathname.split('/').filter(Boolean)[0] || '');
  const categoryName = clean(envoyProduct?.category?.name) || breadcrumbCategory();
  const salePrice = structuredPrice(winner?.price) || readPrice();
  const sellerLegal = extractSellerLegalDetails();
  const structuredSeller = sellerFromEnvoyProduct(envoyProduct, salePrice);
  const sellers = mergeSellers(
    structuredSeller ? [structuredSeller] : [],
    sellersFromEnvoyProduct(envoyProduct, salePrice),
    extractVisibleProductSellers(salePrice),
    extractSellerStocks(salePrice),
  );
  const structuredStock = nonNegativeInteger(winner?.quantity);
  const domStockSignal = sellers.some((seller) => Number(seller.stock || 0) > 0);
  const totalStock = structuredStock ?? (domStockSignal ? sellers.reduce((sum, seller) => sum + Number(seller.stock || 0), 0) : null);
  const barcode = clean(winner?.barcode || envoyProduct?.variants?.[0]?.barcode || '');
  const ratingScore = envoyProduct?.ratingScore || {};
  const sellerLevelValue = nonNegativeInteger(
    merchant?.sellerLevel?.level
      ?? merchant?.sellerLevel
      ?? merchant?.sellerTier?.level
      ?? merchant?.sellerTier,
  );
  const sellerLevel = sellerLevelValue >= 1 && sellerLevelValue <= 5 ? sellerLevelValue : null;
  const categoryRanking = Array.isArray(envoyProduct?.categoryTopRankings)
    ? envoyProduct.categoryTopRankings.find((rank) => Number.isFinite(Number(rank?.order)))
    : null;
  const structuredPromotions = Array.isArray(envoyProduct?.merchantListing?.promotions)
    ? envoyProduct.merchantListing.promotions.map((promotion) => clean(promotion?.name)).filter(Boolean)
    : [];
  const promotions = sanitizeCampaignBadges([...structuredPromotions, ...extractVisiblePromotions()]);
  const attributes = Array.isArray(envoyProduct?.attributes)
    ? envoyProduct.attributes.map((attribute) => ({
      name: clean(attribute?.key?.name),
      value: clean(attribute?.value?.name),
    })).filter((attribute) => attribute.name && attribute.value)
    : [];
  const structuredQuestionCount = nonNegativeInteger(envoyProduct?.questionCount ?? envoyProduct?.sellerQuestionCount);
  const domQuestionCount = readElementCount(
    ['[class*="questions-summary"]', 'a[href*="/saticiya-sor"]'],
    [/(\d[\d.,]*)\s*Soru\s*[-–—]?\s*Cevap/i, /Satıcı\s+Soruları\s*\(([\d.,]+)\)/i],
  ) ?? readVisibleCount([/(\d[\d.,]*)\s*Soru\s*[-–—]?\s*Cevap/i, /Satıcı\s+Soruları\s*\(([\d.,]+)\)/i]);
  const metrics = {
    evaluation_count: nonNegativeInteger(ratingScore?.totalCount),
    review_count: nonNegativeInteger(ratingScore?.commentCount),
    average_rating: numericValue(ratingScore?.averageRating),
    favorite_count: nonNegativeInteger(envoyProduct?.favoriteCount) ?? readVisibleCount([/([\d.,]+\s*(?:B|bin)?)\s+kişi\s+favoriledi/i]),
    favorite_precision: nonNegativeInteger(envoyProduct?.favoriteCount) !== null ? 'exact' : 'rounded',
    basket_count: readVisibleCount([
      /([\d.,]+\s*(?:B|bin)?)\s+kişi[^.]{0,80}sepete\s+ekledi/i,
      /([\d.,]+\s*(?:B|bin)?)\s+sepete\s+eklenme/i,
    ]),
    view_count_24h: readVisibleCount([
      /son\s+24\s+saat(?:te)?[^\d]{0,40}([\d.,]+\s*(?:B|bin)?)[^.]{0,80}görüntü/i,
      /([\d.,]+\s*(?:B|bin)?)\s+kişi[^.]{0,80}görüntüledi/i,
    ]),
    question_count: structuredQuestionCount ?? domQuestionCount,
    category_rank: nonNegativeInteger(categoryRanking?.order),
    seller_score: numericValue(merchant?.sellerScore?.value),
    seller_follower_count: nonNegativeInteger(merchant?.followerCount)
      ?? readVisibleCount([/([\d.,]+\s*(?:B|bin)?)\s+Takipçi/i]),
    campaign_count: promotions.length,
  };

  return {
    source_url: location.href,
    page: {
      trendyol_product_id: String(envoyProduct?.id || extractProductId(location.href) || ''),
      title,
      brand,
      category_name: categoryName,
      sale_price: salePrice,
      currency: 'TRY',
      image_url: clean(envoyProduct?.images?.[0]) || meta('og:image'),
      barcode,
      total_stock: totalStock,
      favorite_count: metrics.favorite_count,
      sellers,
      stock_source: structuredStock !== null ? 'envoy_shared_props' : 'visible_dom',
      availability: winner?.inStock === true ? 'InStock' : (winner?.inStock === false ? 'OutOfStock' : ''),
      stock_status: winner?.inStock === true ? 'in_stock' : (winner?.inStock === false ? 'out_of_stock' : 'unknown'),
      question_count: metrics.question_count,
      category_rank: metrics.category_rank,
      seller_score: metrics.seller_score,
      seller_id: String(merchant?.id || ''),
      seller_name: clean(merchant?.name || sellerLegal.seller_name || ''),
      seller_legal: sellerLegal,
      seller_title: sellerLegal.title || '',
      seller_address: sellerLegal.address || '',
      seller_kep: sellerLegal.kep || '',
      seller_tax_number: sellerLegal.tax_number || '',
      seller_tax_office: sellerLegal.tax_office || '',
      seller_phone: sellerLegal.phone || '',
      seller_level: sellerLevel,
      seller_follower_count: metrics.seller_follower_count,
      campaign_count: metrics.campaign_count,
      campaign_signature: promotions.length ? stableSignature(promotions) : null,
      promotions,
      listing_id: clean(winner?.listingId),
      item_number: String(winner?.itemNumber || ''),
      product_group_id: String(envoyProduct?.productGroupId || ''),
      product_code: clean(envoyProduct?.productCode),
      max_installment: nonNegativeInteger(envoyProduct?.maxInstallment),
      max_sale_limit: nonNegativeInteger(winner?.maxSaleLimit),
      rush_delivery_duration: nonNegativeInteger(winner?.rushDeliveryDuration),
      attributes,
      image_count: Array.isArray(envoyProduct?.images) ? envoyProduct.images.length : 0,
      data_sources: [
        'envoy_shared_props',
        'visible_dom',
        ...(structuredQuestionCount === null && domQuestionCount !== null ? ['question_summary_dom'] : []),
      ],
    },
    metrics,
    recent_reviews: [],
    barcode,
    total_stock: totalStock,
    sellers,
    watch_price: true,
  };
}

async function collectProductAnalysis() {
  await waitForProductAnalysisDom();
  const payload = extractProductData();
  const productId = payload?.page?.trendyol_product_id;

  if (!productId) {
    throw new Error('Trendyol ürün ID okunamadı. Sayfayı yenileyip tekrar deneyin.');
  }

  const [reviewResult, socialProofResult] = await Promise.all([
    fetchRecentReviews(productId).catch(() => ({ reviews: [], metrics: {} })),
    fetchSocialProof(productId).catch(() => ({ metrics: {} })),
  ]);
  const socialProofMetrics = { ...(socialProofResult.metrics || {}) };

  // Envoy urun verisindeki favori sayisi tam degerdir; social-proof servisi
  // favoriyi 42,7B gibi yuvarlanmis dondurebildigi icin tam degeri koruyoruz.
  if (payload.metrics?.favorite_count !== null && payload.metrics?.favorite_count !== undefined) {
    delete socialProofMetrics.favorite_count;
  }

  return {
    ...payload,
    metrics: {
      ...(payload.metrics || {}),
      ...Object.fromEntries(Object.entries(reviewResult.metrics || {}).filter(([, value]) => value !== null && value !== undefined)),
      ...Object.fromEntries(Object.entries(socialProofMetrics).filter(([, value]) => value !== null && value !== undefined)),
    },
    recent_reviews: reviewResult.reviews || [],
  };
}

async function waitForProductAnalysisDom(timeoutMs = 8000) {
  const questionPatterns = [
    /(\d[\d.,]*)\s*Soru\s*[-–—]?\s*Cevap/i,
    /Satıcı\s+Soruları\s*\(([\d.,]+)\)/i,
  ];
  const hasQuestionSummary = () => readElementCount(
    ['[class*="questions-summary"]', 'a[href*="/saticiya-sor"]'],
    questionPatterns,
  ) !== null;

  if (hasQuestionSummary()) {
    return;
  }

  await new Promise((resolve) => {
    let settled = false;
    let observer = null;
    const finish = () => {
      if (settled) return;
      settled = true;
      observer?.disconnect();
      clearTimeout(timer);
      resolve();
    };
    const timer = setTimeout(finish, timeoutMs);

    observer = new MutationObserver(() => {
      if (hasQuestionSummary()) finish();
    });
    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
      characterData: true,
    });
  });
}

async function fetchSocialProof(productId) {
  const endpoint = new URL('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/social-proof/');
  endpoint.searchParams.set('contentIds', String(productId));
  endpoint.searchParams.set('channelId', '1');

  const response = await fetch(endpoint.href, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`Trendyol sosyal kanit servisi ${response.status} yaniti verdi.`);
  }

  const json = await response.json();
  const productSocialProof = json?.[String(productId)] || {};
  const rows = Array.isArray(productSocialProof?.socialProofs) ? productSocialProof.socialProofs : [];
  const values = new Map(rows.map((row) => [String(row?.id || ''), parseCompactCount(row?.count)]));

  return {
    metrics: {
      basket_count: values.get('basket-count') ?? null,
      view_count_24h: values.get('page-view-count') ?? null,
      favorite_count: values.get('favorite-count') ?? null,
    },
  };
}

async function fetchRecentReviews(productId) {
  const endpoint = new URL('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/review-read/product-reviews/detailed');
  endpoint.searchParams.set('contentId', String(productId));
  endpoint.searchParams.set('page', '0');
  endpoint.searchParams.set('pageSize', '10');
  endpoint.searchParams.set('channelId', '1');
  endpoint.searchParams.set('order', 'DESC');
  endpoint.searchParams.set('orderBy', 'LastModifiedDate');

  const response = await fetch(endpoint.href, {
    method: 'GET',
    credentials: 'include',
    headers: {
      Accept: 'application/json, text/plain, */*',
      'Accept-Language': 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
      'x-agentname': 'web',
    },
  });

  if (!response.ok) {
    throw new Error(`Trendyol yorum servisi ${response.status} yanıtı verdi.`);
  }

  const json = await response.json();
  const summary = extractTrendyolReviewSummary(json);
  const reviews = extractTrendyolReviewArray(json);

  return {
    metrics: {
      evaluation_count: nonNegativeInteger(summary?.totalRatingCount),
      review_count: nonNegativeInteger(summary?.totalCommentCount),
      average_rating: numericValue(summary?.averageRating),
    },
    reviews: reviews.slice(0, 10).map((review) => ({
      review_id: String(review?.id || review?.reviewId || review?.commentId || ''),
      user_name: clean(review?.userFullName || review?.userName || review?.customerName || 'Anonim').slice(0, 180),
      rate: Math.max(0, Math.min(5, Number.parseInt(review?.rate ?? review?.rating ?? review?.score ?? review?.starCount ?? 0, 10))),
      comment: clean(review?.comment || review?.commentText || review?.text || review?.reviewText || '').slice(0, 2000),
      seller_name: clean(review?.seller?.name || review?.sellerName || '').slice(0, 180),
      reviewed_at: normalizeTrendyolReviewDate(review?.lastModifiedAt || review?.lastModifiedDate || review?.createdAt || review?.creationDate || review?.createdDate || review?.date),
    })).filter((review) => review.comment),
  };
}

// ===== Trendyol Yorum Senkronizasyonu (Faz 2) =====

/**
 * Pagination + delta sync ile tüm yorumları çeker.
 */
async function fetchReviewsForSync(productId, options = {}) {
  const since = options.since ? new Date(options.since) : null;
  const maxPages = options.maxPages || 50;
  const pageSize = 50;
  const allReviews = [];
  let summary = null;

  for (let page = 0; page < maxPages; page++) {
    const endpoint = new URL('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/review-read/product-reviews/detailed');
    endpoint.searchParams.set('contentId', String(productId));
    endpoint.searchParams.set('page', String(page));
    endpoint.searchParams.set('pageSize', String(pageSize));
    endpoint.searchParams.set('channelId', '1');
    endpoint.searchParams.set('order', 'DESC');
    endpoint.searchParams.set('orderBy', 'LastModifiedDate');

    const response = await fetch(endpoint.href, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json, text/plain, */*',
        'Accept-Language': 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'x-agentname': 'web',
      },
    });

    if (!response.ok) {
      throw new Error(`Trendyol yorum servisi ${response.status} yanıtı verdi.`);
    }

    const json = await response.json();
    summary = summary || extractTrendyolReviewSummary(json);
    const reviews = extractTrendyolReviewArray(json);

    if (reviews.length === 0) break;

    let reachedOld = false;
    for (const review of reviews) {
      const reviewedAt = review?.lastModifiedAt
        ? new Date(review.lastModifiedAt)
        : (review?.lastModifiedDate ? new Date(review.lastModifiedDate) : null);

      if (since && reviewedAt && reviewedAt < since) {
        reachedOld = true;
        break;
      }
      allReviews.push(normalizeReviewForSync(review, productId));
    }

    if (reachedOld || reviews.length < pageSize) break;
  }

  return { reviews: allReviews, summary: summary || {} };
}

function extractTrendyolReviewArray(payload) {
  const candidates = [
    payload?.result?.productReviews?.content,
    payload?.result?.productReviews?.reviews,
    payload?.result?.productReviews,
    payload?.result?.reviews,
    payload?.result?.comments,
    payload?.result?.data,
    payload?.reviews,
    payload?.comments,
    payload?.content,
    payload?.data,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

function extractTrendyolReviewSummary(payload) {
  return payload?.result?.summary
    || payload?.result?.productReviews?.summary
    || payload?.summary
    || {};
}

/**
 * Trendyol review verisini ZOLM ingest formatına normalize eder.
 */
function normalizeReviewForSync(review, productId) {
  return {
    trendyol_product_id: String(productId),
    trendyol_review_id: String(review?.id || review?.reviewId || review?.commentId || ''),
    reviewer_name: clean(review?.userFullName || review?.userName || review?.customerName || 'Anonim').slice(0, 180),
    reviewer_avatar_url: review?.userImage ? String(review.userImage).slice(0, 1000) : null,
    rating: Math.max(1, Math.min(5, Number.parseInt(review?.rate ?? review?.rating ?? review?.score ?? review?.starCount ?? 0, 10) || 1)),
    comment: clean(review?.comment || review?.commentText || review?.text || review?.reviewText || '').slice(0, 3000),
    review_media: extractReviewMedia(review),
    helpful_count: nonNegativeInteger(review?.likeCount ?? review?.helpfulCount ?? review?.likes),
    seller_name: clean(review?.seller?.name || review?.sellerName || '').slice(0, 180),
    seller_id: String(review?.seller?.id || review?.sellerId || review?.merchantId || '').slice(0, 80),
    reviewed_at: normalizeTrendyolReviewDate(review?.lastModifiedAt || review?.lastModifiedDate || review?.createdAt || review?.creationDate || review?.createdDate || review?.date),
  };
}

function normalizeTrendyolReviewDate(value) {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'number' && Number.isFinite(value)) {
    const timestamp = value > 9999999999 ? value : value * 1000;
    const date = new Date(timestamp);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
  }

  return String(value);
}

/**
 * Trendyol review fotoğraflarını çeker.
 */
function extractReviewMedia(review) {
  const photos = Array.isArray(review?.photos)
    ? review.photos
    : (Array.isArray(review?.reviewPhotos) ? review.reviewPhotos : []);
  const media = [];

  for (const photo of photos) {
    const url = photo?.url || photo?.imageUrl || photo?.path || '';
    if (url) {
      media.push({ url: String(url).slice(0, 1000), width: photo?.width || null, height: photo?.height || null, type: 'photo' });
    }
  }

  return media;
}

function extractStoreProductListForReviews(maxProducts = 500) {
  const products = new Map();
  const anchors = document.querySelectorAll('a[href*="-p-"]');

  for (const anchor of anchors) {
    if (products.size >= maxProducts) break;

    const href = String(anchor.href || '');
    const match = href.match(/-p-(\d+)/);
    if (!match || products.has(match[1])) continue;

    const card = anchor.closest('.p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr, li, article') || anchor;
    const image = card.querySelector('img');
    const titleNode = card.querySelector('.prdct-desc-cntnr-name, .product-name, [data-testid="product-name"], [title]');
    const title = clean(titleNode?.textContent || image?.alt || anchor.getAttribute('title') || '').slice(0, 500);
    const barcode = clean(card.getAttribute('data-barcode') || anchor.getAttribute('data-barcode') || '').slice(0, 120);

    products.set(match[1], {
      trendyol_product_id: match[1],
      product_title: title,
      product_image_url: image?.src ? String(image.src).slice(0, 1000) : '',
      trendyol_product_barcode: barcode || null,
    });
  }

  return Array.from(products.values());
}

function readEnvoyProduct() {
  const assignment = /window\[\s*["']__envoy__SHARED_PROPS["']\s*\]\s*=\s*/;

  for (const script of Array.from(document.scripts)) {
    const text = String(script.textContent || '').trim();
    const match = assignment.exec(text);

    if (!match) {
      continue;
    }

    try {
      const json = text.slice(match.index + match[0].length).replace(/;\s*$/, '');
      const state = JSON.parse(json);

      if (state?.product && typeof state.product === 'object') {
        return state.product;
      }
    } catch (error) {
      continue;
    }
  }

  return null;
}

function readSearchState() {
  const assignment = /window\.__SEARCH_APP_INITIAL_STATE__\s*=\s*/;

  for (const script of Array.from(document.scripts)) {
    const text = String(script.textContent || '').trim();

    if (text && assignment.test(text)) {
      try {
        const jsonText = text.replace(assignment, '').trim().replace(/;$/, '');
        return JSON.parse(jsonText);
      } catch (e) {
        return null;
      }
    }
  }

  return null;
}

function readBestsellerPageStatus() {
  const script = document.querySelector('#ZOLM_BOOSTER_BESTSELLER_PAGE_STATUS');
  if (script) {
    try {
      return JSON.parse(script.textContent || '{}');
    } catch (e) {
      return null;
    }
  }
  return null;
}

function sellerFromEnvoyProduct(product, defaultPrice) {
  const listing = product?.merchantListing;
  const merchant = listing?.merchant;
  const winner = listing?.winnerVariant;
  const stock = nonNegativeInteger(winner?.quantity);

  if (!listing || (!merchant?.name && !merchant?.id)) {
    return null;
  }

  return {
    seller_name: clean(merchant?.name) || 'Ana satıcı',
    seller_id: String(merchant?.id || ''),
    stock: stock ?? 0,
    sale_price: structuredPrice(winner?.price) || defaultPrice || 0,
    seller_score: numericValue(merchant?.sellerScore?.value),
    shipping_note: Number.isFinite(Number(winner?.rushDeliveryDuration))
      ? `${Math.max(0, Number.parseInt(winner.rushDeliveryDuration, 10))} saatte kargo`
      : '',
  };
}

function sellersFromEnvoyProduct(product, defaultPrice) {
  const sellers = [];
  const seen = new Set();

  const collections = [
    product?.merchantListing,
    ...(Array.isArray(product?.merchantListings) ? product.merchantListings : []),
    ...(Array.isArray(product?.otherMerchantListings) ? product.otherMerchantListings : []),
    ...(Array.isArray(product?.otherMerchants) ? product.otherMerchants : []),
    ...(Array.isArray(product?.sellers) ? product.sellers : []),
  ].filter(Boolean);

  for (const candidate of collections) {
    if (sellers.length >= 20) break;

    const listing = candidate?.merchantListing || candidate;
    const merchant = listing?.merchant;
    const winner = listing?.winnerVariant;

    if (merchant && winner && (merchant.id || merchant.name)) {
      const sellerId = String(merchant.id || '');
      const sellerName = clean(merchant.name) || 'Ana satıcı';
      const key = sellerId ? `id:${sellerId}` : `name:${sellerName.toLocaleLowerCase('tr-TR')}`;

      if (!seen.has(key)) {
        seen.add(key);
        sellers.push({
          seller_name: sellerName,
          seller_id: sellerId,
          stock: nonNegativeInteger(winner.quantity) ?? 0,
          sale_price: structuredPrice(winner.price) || defaultPrice || 0,
          seller_score: numericValue(merchant?.sellerScore?.value),
          shipping_note: Number.isFinite(Number(winner?.rushDeliveryDuration))
            ? `${Math.max(0, Number.parseInt(winner.rushDeliveryDuration, 10))} saatte kargo`
            : '',
        });
      }
    }
  }

  return sellers;
}

function structuredPrice(price) {
  const candidates = [
    price?.discountedPriceAfterNoLimitPromotions?.value,
    price?.discountedPrice?.value,
    price?.couponApplicablePrice?.value,
    price?.tyPlusCouponApplicablePrice?.value,
    price?.sellingPrice?.value,
    price?.originalPrice?.value,
  ];

  for (const candidate of candidates) {
    const value = numericValue(candidate);

    if (value !== null && value > 0) {
      return value;
    }
  }

  return 0;
}

function mergeSellers(...groups) {
  const sellers = groups.flat().filter(Boolean).map(normalizeSeller);
  const seen = new Set();

  return sellers.filter((seller) => {
    const key = String(seller.seller_id || seller.seller_name || '').trim().toLocaleLowerCase('tr-TR');

    if (!key || isBadSellerName(seller.seller_name) || (!seller.seller_id && seller.stock <= 0) || seen.has(key)) {
      return false;
    }

    seen.add(key);
    return true;
  }).slice(0, 20);
}

function uniqueStrings(values) {
  const seen = new Set();

  return values
    .map((value) => clean(value).slice(0, 180))
    .filter(Boolean)
    .filter((value) => {
      const key = value.toLocaleLowerCase('tr-TR');

      if (seen.has(key)) {
        return false;
      }

      seen.add(key);
      return true;
    })
    .slice(0, 20);
}

function sanitizeCampaignBadges(values) {
  return uniqueStrings((Array.isArray(values) ? values : [])
    .map((value) => normalizeCampaignBadge(value))
    .filter(Boolean))
    .slice(0, 8);
}

function normalizeCampaignBadge(value) {
  const text = clean(value)
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

  return match ? clean(match[0]) : '';
}

function extractVisiblePromotions() {
  const selectors = [
    '[data-testid*="campaign"]',
    '[data-testid*="coupon"]',
    '[data-testid*="promotion"]',
    '[class*="campaign"]',
    '[class*="Campaign"]',
    '[class*="coupon"]',
    '[class*="Coupon"]',
    '[class*="promotion"]',
    '[class*="Promotion"]',
    '[class*="basket-discount"]',
  ];
  const nodes = selectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)).slice(0, 20));
  const phrases = nodes
    .map((node) => clean(node.textContent || ''))
    .filter((text) => /(kupon|kampanya|indirim|sepette|avantaj|firsat|fırsat)/i.test(text))
    .flatMap((text) => text.split(/\s{2,}|(?<=\.)\s+/u))
    .map((text) => text.replace(/^(Tüm|Sepete Ekle|Detay|İncele)\s+/i, '').trim())
    .map((text) => text.replace(/\+\d+\s*kampanya\s*daha/gi, '').trim())
    .filter((text) => text.length >= 5 && text.length <= 180)
    .filter((text) => !/kampanya yok/i.test(text));

  return sanitizeCampaignBadges(phrases);
}

function normalizeSeller(seller) {
  return {
    seller_name: clean(seller?.seller_name).slice(0, 180),
    seller_id: clean(seller?.seller_id).slice(0, 80),
    stock: nonNegativeInteger(seller?.stock) ?? 0,
    sale_price: numericValue(seller?.sale_price) ?? 0,
    seller_score: numericValue(seller?.seller_score),
    shipping_note: clean(seller?.shipping_note).slice(0, 180),
  };
}

function nonNegativeInteger(value) {
  if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) {
    return null;
  }

  return Math.max(0, Number.parseInt(value, 10));
}

function numericValue(value) {
  if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) {
    return null;
  }

  return Number(value);
}

function readVisibleCount(patterns) {
  const text = clean(document.body?.innerText || '');

  for (const pattern of patterns) {
    const match = text.match(pattern);
    if (match) {
      return parseCompactCount(match[1]);
    }
  }

  return null;
}

function readElementCount(selectors, patterns) {
  for (const selector of selectors) {
    const nodes = Array.from(document.querySelectorAll(selector)).slice(0, 20);

    for (const node of nodes) {
      const text = clean(node.textContent || '');

      for (const pattern of patterns) {
        const match = text.match(pattern);
        const value = match ? parseCompactCount(match[1]) : null;

        if (value !== null) {
          return value;
        }
      }
    }
  }

  return null;
}

function parseCompactCount(value) {
  const text = clean(value).toLocaleLowerCase('tr-TR');
  const multiplier = /(?:b|bin)\b/i.test(text) ? 1000 : 1;
  const numeric = text
    .replace(/(?:b|bin)\b/gi, '')
    .replace(/\./g, '')
    .replace(',', '.')
    .replace(/[^\d.]/g, '');
  const number = Number.parseFloat(numeric);

  return Number.isFinite(number) ? Math.max(0, Math.round(number * multiplier)) : null;
}

function extractStoreData() {
  const storeId = extractStoreId(location.href);
  const storeName = firstText([
    '[data-testid="seller-name"]',
    '[class*="seller-name"]',
    '[class*="store-name"]',
    'h1',
  ]) || titleFromSlug(location.pathname.split('/').filter(Boolean).pop()?.replace(/-m-\d+.*/i, '') || '');

  return {
    store_url: location.href,
    store: {
      store_id: storeId,
      store_name: storeName || 'Rakip Mağaza',
      items: extractStoreItems(),
    },
  };
}

function extractStoreItems() {
  const seen = new Set();
  const items = [];
  const anchors = Array.from(document.querySelectorAll('a[href*="-p-"]')).slice(0, 300);

  for (const anchor of anchors) {
    const href = anchor.href || anchor.getAttribute('href') || '';
    const productId = extractProductId(href);

    if (!productId || seen.has(productId)) {
      continue;
    }

    seen.add(productId);
    const card = anchor.closest('[class*="card"], [class*="Card"], [class*="product"], [class*="Product"], li, article') || anchor;
    const title = storeProductTitle(anchor, card);
    const salePrice = parsePrice(card.textContent || '');

    items.push({
      trendyol_product_id: productId,
      source_url: href.startsWith('http') ? href : new URL(href, location.origin).href,
      title: title.slice(0, 240),
      brand: '',
      sale_price: salePrice,
    });

    if (items.length >= 72) {
      break;
    }
  }

  return items;
}

function extractSellerStocks(defaultPrice) {
  const candidates = [
    '[data-testid*="seller"]',
    '[class*="seller"]',
    '[class*="merchant"]',
    '[class*="Merchant"]',
    '[class*="boutique"]',
  ];
  const nodes = [];

  for (const selector of candidates) {
    for (const node of Array.from(document.querySelectorAll(selector)).slice(0, 20)) {
      if (!nodes.includes(node)) {
        nodes.push(node);
      }
    }
  }

  return nodes
    .map((node) => {
      const text = clean(node.textContent || '');
      const stock = parseStock(text);
      const sellerName = clean(
        node.querySelector('a[href*="/magaza/"]')?.textContent
        || node.querySelector('[class*="name"], [class*="Name"]')?.textContent
        || ''
      );

      return {
        seller_name: sellerName || parseSellerName(text),
        seller_id: extractStoreId(node.querySelector('a[href*="/magaza/"]')?.href || ''),
        stock,
        sale_price: parsePrice(text) || 0,
        seller_score: parseSellerScore(text),
        shipping_note: parseShippingNote(text),
      };
    })
    .filter((seller, index, list) => seller.seller_name && !isBadSellerName(seller.seller_name) && (seller.seller_id || seller.stock > 0) && list.findIndex((item) => item.seller_name === seller.seller_name) === index)
    .slice(0, 20);
}

function extractVisibleProductSellers(defaultPrice) {
  const selectors = [
    '[class*="other-merchant"]',
    '[class*="otherMerchant"]',
    '[class*="other-seller"]',
    '[class*="otherSeller"]',
    '[class*="merchant-box"]',
    '[class*="merchantBox"]',
    '[class*="seller-card"]',
    '[class*="sellerCard"]',
    '[class*="seller-container"]',
    '[class*="sellerContainer"]',
    '[class*="merchant"]',
    '[class*="Merchant"]',
    '[data-testid*="seller"]',
    '[data-testid*="merchant"]',
  ];
  const nodes = [];

  for (const selector of selectors) {
    for (const node of Array.from(document.querySelectorAll(selector)).slice(0, 60)) {
      if (!nodes.includes(node)) nodes.push(node);
    }
  }

  for (const node of Array.from(document.querySelectorAll('aside div, section div, article, li')).slice(0, 1200)) {
    const text = clean(node.innerText || node.textContent || '');
    if (!looksLikeSellerCardText(text)) continue;
    if (!nodes.includes(node)) nodes.push(node);
  }

  return nodes
    .filter((node) => {
      const text = clean(node.innerText || node.textContent || '');
      if (!looksLikeSellerCardText(text)) return false;

      // Büyük kapsayıcıları değil, mümkün olduğunca tek satıcı kartını al.
      return !Array.from(node.children || []).some((child) => {
        const childText = clean(child.innerText || child.textContent || '');
        return childText && childText !== text && looksLikeSellerCardText(childText) && childText.length < text.length * 0.9;
      });
    })
    .map((node) => {
      const text = clean(node.innerText || node.textContent || '');
      const sellerName = sellerNameFromVisibleCard(node, text);

      return {
        seller_name: sellerName,
        seller_id: extractStoreId(node.querySelector('a[href*="/magaza/"]')?.href || ''),
        stock: parseStock(text),
        sale_price: parsePrice(text) || 0,
        seller_score: parseSellerScore(text),
        shipping_note: parseShippingNote(text),
      };
    })
    .filter((seller) => seller.seller_name && !isBadSellerName(seller.seller_name) && (seller.seller_id || seller.stock > 0))
    .filter((seller, index, list) => list.findIndex((item) => item.seller_name.toLocaleLowerCase('tr-TR') === seller.seller_name.toLocaleLowerCase('tr-TR')) === index)
    .slice(0, 20);
}

function looksLikeSellerCardText(text) {
  const value = clean(text);
  if (value.length < 6 || value.length > 520) return false;
  if (/satıcı soruları|satici sorulari|mağazaya git|magazaya git|takip et kazan|ürünün kampanyaları|urunun kampanyalari/i.test(value) && !/\b\d(?:[,.]\d)?\b/.test(value)) return false;

  return /(kargo|teslim|sepette|tl|₺|puan|takipçi|takipci)/i.test(value)
    && Boolean(sellerNameFromVisibleText(value));
}

function sellerNameFromVisibleCard(node, text) {
  const linkName = clean(node.querySelector('a[href*="/magaza/"]')?.textContent || '');
  if (linkName && !isBadSellerName(linkName)) return linkName;

  const explicit = clean(node.querySelector('[class*="seller-name"], [class*="sellerName"], [class*="merchant-name"], [class*="merchantName"], [data-testid*="seller-name"], [data-testid*="merchant-name"]')?.textContent || '');
  if (explicit && !isBadSellerName(explicit)) return explicit;

  return sellerNameFromVisibleText(text);
}

function sellerNameFromVisibleText(text) {
  const lines = clean(text)
    .split(/(?=\b(?:Kargo|Sepette|Teslim|Puan|Satıcı|Satici|Mağaza|Magaza)\b)|\s{2,}/iu)
    .map((line) => clean(line))
    .filter(Boolean);
  const joined = clean(text);
  const scoreMatch = joined.match(/^([A-ZÇĞİÖŞÜ0-9][A-ZÇĞİÖŞÜ0-9 .&'’_-]{1,58}?)\s+\d{1,2}(?:[,.]\d)?(?:\s|$)/u);

  if (scoreMatch && !isBadSellerName(scoreMatch[1])) {
    return clean(scoreMatch[1]);
  }

  for (const line of lines) {
    const lineScoreMatch = line.match(/^(.{2,60}?)\s+\d{1,2}(?:[,.]\d)?$/u);
    const candidate = clean((lineScoreMatch ? lineScoreMatch[1] : line).replace(/\b(?:Puan|Kargo|Sepette|Teslim).*$/iu, ''));

    if (candidate && /[A-Za-zÇĞİÖŞÜçğıöşü]/u.test(candidate) && !isBadSellerName(candidate) && !/\b(?:TL|TRY|₺)\b/i.test(candidate)) {
      return candidate.slice(0, 180);
    }
  }

  return '';
}

function isBadSellerName(value) {
  const normalized = clean(value)
    .toLocaleLowerCase('tr-TR')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/ı/g, 'i')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

  return !normalized || normalized.length > 80 || [
    'urun', 'urunun diger saticilari', 'satici sorulari', 'satici', 'ana satici',
    'magazaya git', 'takip et', 'takip et kazan', 'kargo bedava', 'trendyol pazaryeri',
    'yarin', 'tahmini', 'kampanyali fiyat',
  ].includes(normalized) || /window|envoy|tanimlama bilgileri|reklam ortaklarimiz|trendyol da satis yap|trendyol plus|satici sorulari|sorulari \d|siparis verirsen|takipci|icerigimizi arkadaslarinizla|videolar ile canli sohbet/i.test(normalized);
}

function parseStock(text) {
  const patterns = [
    /(?:son|stok|kalan)\s*(\d{1,6})\s*(?:ürün|adet)?/i,
    /(\d{1,6})\s*(?:adet|stok)/i,
  ];

  for (const pattern of patterns) {
    const match = String(text || '').match(pattern);
    if (match) {
      return Number.parseInt(match[1], 10) || 0;
    }
  }

  return 0;
}

function parseSellerName(text) {
  const parts = String(text || '').split(/\s{2,}|Puan|Satıcı|Kargo|Sepete/i).map(clean).filter(Boolean);

  return parts[0] || '';
}

function storeProductTitle(anchor, card) {
  const selectors = [
    '[data-testid*="product-name"]',
    '[data-testid*="product-title"]',
    '.product-name',
    '[class*="product-name"]',
    '[class*="ProductName"]',
    '[class*="product-title"]',
    '[class*="ProductTitle"]',
    '[class*="name"]',
  ];
  const candidates = [
    anchor.getAttribute('title'),
    anchor.getAttribute('aria-label'),
  ];

  for (const selector of selectors) {
    candidates.push(anchor.querySelector(selector)?.textContent);
    candidates.push(card.querySelector?.(selector)?.textContent);
  }

  candidates.push(anchor.textContent, card.textContent);

  const title = candidates
    .map((candidate) => cleanStoreProductTitle(candidate))
    .filter((candidate) => candidate.length >= 5 && !/^(kargo|indirim|kupon|favori|sepete|birlikte al kazan)$/i.test(candidate))
    .sort((left, right) => left.length - right.length)[0];

  return title || 'Rakip ürün';
}

function cleanStoreProductTitle(value) {
  return clean(value || '')
    .replace(/\bBirlikte\s+Al\s+Kazan\b/giu, ' ')
    .replace(/\bKargo\s+Bedava\b/giu, ' ')
    .replace(/\bİndirim\s+Kodu\b/giu, ' ')
    .replace(/\b\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?\s*(?:TL|TRY|₺)\b/giu, ' ')
    .replace(/\b\d+(?:,\d)?\s*\(\d+\)/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 240);
}

function parseSellerScore(text) {
  const match = String(text || '').match(/(\d(?:[,.]\d)?)\s*(?:puan|\/10)/i)
    || String(text || '').match(/\b[A-ZÇĞİÖŞÜ0-9][A-ZÇĞİÖŞÜ0-9 .&'’_-]{1,58}?\s+(\d{1,2}(?:[,.]\d)?)\b/u);
  return match ? Number.parseFloat(match[1].replace(',', '.')) : null;
}

function parseShippingNote(text) {
  const match = String(text || '').match(/(kargo[^.]{0,80}|teslim[^.]{0,80})/i);
  return match ? clean(match[1]).slice(0, 180) : '';
}

function extractSellerLegalDetails() {
  const text = sellerLegalText();

  if (!text) {
    return emptySellerLegalDetails();
  }

  return {
    seller_name: labelValue(text, ['Satıcı', 'Satici']).slice(0, 180),
    title: labelValue(text, ['Satıcı Ünvanı', 'Satıcı Unvanı', 'Satici Unvani']).slice(0, 1000),
    address: labelValue(text, ['Adres', 'Açık Adres', 'Acik Adres']).slice(0, 1000),
    kep: labelValue(text, ['Kep Adresi', 'KEP Adresi', 'Kep', 'KEP']).slice(0, 255),
    tax_number: labelValue(text, ['Vergi Kimlik Numarası', 'Vergi Kimlik Numarasi', 'Vergi No', 'VKN']).replace(/\D+/g, '').slice(0, 120),
    tax_office: labelValue(text, ['Ticaret sicili', 'Ticaret Sicili', 'Vergi Dairesi', 'Vergi Dairesi Bilgisi']).slice(0, 255),
    phone: labelValue(text, ['Telefon', 'Telefon Numarası', 'Telefon Numarasi']).slice(0, 120),
  };
}

function emptySellerLegalDetails() {
  return {
    seller_name: '',
    title: '',
    address: '',
    kep: '',
    tax_number: '',
    tax_office: '',
    phone: '',
  };
}

function sellerLegalText() {
  const legalMatchers = [
    /Satıcı\s+Ünvanı/iu,
    /Satıcı\s+Unvanı/iu,
    /Vergi\s+Kimlik/iu,
    /Kep\s+Adresi/iu,
    /Ticaret\s+sicili/iu,
  ];
  const candidates = [];

  for (const node of Array.from(document.querySelectorAll('body *')).slice(0, 3500)) {
    const text = clean(node.textContent || '');

    if (text.length < 30 || text.length > 2500) {
      continue;
    }

    const score = legalMatchers.reduce((sum, matcher) => sum + (matcher.test(text) ? 1 : 0), 0);

    if (score >= 2) {
      candidates.push({ text, score });
    }
  }

  if (candidates.length > 0) {
    candidates.sort((left, right) => right.score - left.score || left.text.length - right.text.length);
    return candidates[0].text;
  }

  const bodyText = clean(document.body?.textContent || '');
  const bodyScore = legalMatchers.reduce((sum, matcher) => sum + (matcher.test(bodyText) ? 1 : 0), 0);

  return bodyScore >= 2 ? bodyText.slice(0, 12000) : '';
}

function labelValue(text, labels) {
  const stopLabels = [
    'Satıcı Ünvanı',
    'Satıcı Unvanı',
    'Satici Unvani',
    'Satıcı',
    'Satici',
    'Ticaret sicili',
    'Ticaret Sicili',
    'Vergi Kimlik Numarası',
    'Vergi Kimlik Numarasi',
    'Vergi No',
    'VKN',
    'Kep Adresi',
    'KEP Adresi',
    'İletişim',
    'Iletisim',
    'Telefon',
    'Adres',
    'Ücretsiz İade',
    'Hızlı Teslimat',
    'Trendyol Müşteri Desteği',
    'Bu ürün',
    'Ürünün Tüm Özellikleri',
    'Benzer Ürünler',
  ];
  const stopPattern = stopLabels.map(escapeRegExp).join('|');

  for (const label of labels) {
    const pattern = new RegExp(`(?:^|\\s)${escapeRegExp(label)}\\s*:\\s*(.*?)(?=\\s*(?:${stopPattern})(?:\\s*:)?|$)`, 'iu');
    const match = String(text || '').match(pattern);

    if (!match) {
      continue;
    }

    const value = clean(match[1] || '').replace(/^(?:tarafından|tarafindan)\s+/iu, '');

    if (value) {
      return value;
    }
  }

  return '';
}

function readPrice() {
  const metaPrice = parsePrice(meta('product:price:amount') || meta('twitter:data1'));

  if (metaPrice > 0) {
    return metaPrice;
  }

  const selectors = [
    '[data-testid="price-current-price"]',
    '.prc-dsc',
    '.prc-slg',
    '.price-information',
    '.product-price-container',
    '[class*="price"]',
    '[class*="Price"]',
  ];

  for (const selector of selectors) {
    const nodes = Array.from(document.querySelectorAll(selector)).slice(0, 20);

    for (const node of nodes) {
      const value = parsePrice(node.textContent || '');

      if (value > 0) {
        return value;
      }
    }
  }

  for (const script of Array.from(document.querySelectorAll('script[type="application/ld+json"]'))) {
    const value = priceFromJson(script.textContent || '');

    if (value > 0) {
      return value;
    }
  }

  return 0;
}

function priceFromJson(text) {
  try {
    const parsed = JSON.parse(text);
    const stack = Array.isArray(parsed) ? [...parsed] : [parsed];

    while (stack.length) {
      const item = stack.shift();

      if (!item || typeof item !== 'object') {
        continue;
      }

      if (item.offers) {
        const offers = Array.isArray(item.offers) ? item.offers : [item.offers];
        for (const offer of offers) {
          const value = parsePrice(String(offer?.price || offer?.lowPrice || ''));
          if (value > 0) {
            return value;
          }
        }
      }

      stack.push(...Object.values(item).filter((value) => value && typeof value === 'object'));
    }
  } catch (error) {
    return 0;
  }

  return 0;
}

function parsePrice(value) {
  const text = String(value || '')
    .replace(/\u00a0/g, ' ')
    .trim();
  const maxPlausiblePrice = 9999999.99;
  const currencyMatches = [
    ...text.matchAll(/(?:₺\s*)?(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)\s*(?:TL|TRY|₺)/giu),
    ...text.matchAll(/(?:TL|TRY|₺)\s*(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)/giu),
  ]
    .map((match) => normalizePriceToken(match[1]))
    .filter((price) => price > 0 && price <= maxPlausiblePrice);

  if (currencyMatches.length > 0) {
    return currencyMatches[currencyMatches.length - 1];
  }

  const compact = text.replace(/[^\d.,\s]/g, ' ').replace(/\s+/g, ' ').trim();
  const numericTokens = compact.match(/\d+(?:[.,]\d+)*/g) || [];

  if (numericTokens.length !== 1) {
    return 0;
  }

  const valueNumber = normalizePriceToken(numericTokens[0]);

  return valueNumber > 0 && valueNumber <= maxPlausiblePrice ? valueNumber : 0;
}

function normalizePriceToken(token) {
  let raw = String(token || '')
    .replace(/[^\d.,]/g, '')
    .trim();

  if (!raw) {
    return 0;
  }

  const lastComma = raw.lastIndexOf(',');
  const lastDot = raw.lastIndexOf('.');

  if (lastComma > -1 && lastDot > -1) {
    raw = lastComma > lastDot ? raw.replace(/\./g, '').replace(',', '.') : raw.replace(/,/g, '');
  } else if (lastComma > -1) {
    const commaDecimals = raw.length - lastComma - 1;
    raw = commaDecimals > 0 && commaDecimals <= 2 ? raw.replace(',', '.') : raw.replace(/,/g, '');
  } else if (lastDot > -1) {
    const dotDecimals = raw.length - lastDot - 1;
    raw = dotDecimals === 3 && raw.indexOf('.') === lastDot ? raw.replace(/\./g, '') : raw;
  }

  const valueNumber = Number.parseFloat(raw);

  return Number.isFinite(valueNumber) ? Math.round(valueNumber * 100) / 100 : 0;
}

function breadcrumbCategory() {
  const nodes = Array.from(document.querySelectorAll('nav a, [class*="breadcrumb"] a, [class*="Breadcrumb"] a'))
    .map((node) => clean(node.textContent))
    .filter((text) => text && !/trendyol/i.test(text));

  return nodes.length ? nodes[nodes.length - 1] : '';
}

function firstText(selectors) {
  for (const selector of selectors) {
    const node = document.querySelector(selector);
    const text = clean(node?.textContent || '');

    if (text) {
      return text;
    }
  }

  return '';
}

function meta(name) {
  const node = document.querySelector(`meta[property="${name}"], meta[name="${name}"]`);
  return clean(node?.getAttribute('content') || '');
}

function cleanTitle(value) {
  return clean(value).replace(/\s+[-|]\s+Trendyol\s*$/i, '');
}

function clean(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function stableSignature(values) {
  return values.map((value) => clean(value).toLocaleLowerCase('tr-TR')).sort().join('|').slice(0, 500);
}

function extractProductId(value) {
  const match = String(value || '').match(/-p-(\d+)/i);
  return match ? match[1] : '';
}

function extractStoreId(value) {
  const text = String(value || '');
  const pathMatch = text.match(/-m-(\d+)/i);
  if (pathMatch) return pathMatch[1];

  try {
    const url = new URL(text, location.origin);
    return url.searchParams.get('mid') || url.searchParams.get('merchantId') || '';
  } catch (error) {
    return '';
  }
}

function storeUrlForSeller(storeName, storeId) {
  const id = String(storeId || '').replace(/\D+/g, '');

  if (!id) {
    return '';
  }

  return `https://www.trendyol.com/magaza/${slugifyStoreName(storeName || 'satici')}-m-${id}`;
}

function slugifyStoreName(value) {
  const trMap = {
    ç: 'c',
    Ç: 'c',
    ğ: 'g',
    Ğ: 'g',
    ı: 'i',
    I: 'i',
    İ: 'i',
    ö: 'o',
    Ö: 'o',
    ş: 's',
    Ş: 's',
    ü: 'u',
    Ü: 'u',
  };
  const ascii = String(value || '')
    .replace(/[çÇğĞıIİöÖşŞüÜ]/g, (letter) => trMap[letter] || letter)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
  const slug = ascii
    .toLocaleLowerCase('en-US')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return slug || 'satici';
}

function escapeRegExp(value) {
  return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function titleFromSlug(value) {
  return String(value || '')
    .replace(/[-_]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\p{L}/gu, (letter) => letter.toLocaleUpperCase('tr-TR'));
}

function formatMoney(value) {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(value);
}

function formatNumber(value) {
  return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 }).format(Number(value || 0));
}
