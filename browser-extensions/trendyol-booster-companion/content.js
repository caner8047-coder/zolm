(function () {
  const PANEL_ID = 'zolm-trendyol-booster-panel';
  const CONTENT_VERSION = chrome.runtime.getManifest().version;

  if (window[PANEL_ID] === CONTENT_VERSION) {
    return;
  }

  window[PANEL_ID] = CONTENT_VERSION;
  document.getElementById(PANEL_ID)?.remove();

  // Çok Satanlar köprüsü tüm Trendyol liste sayfalarında hazır kalır.
  chrome.runtime.onMessage.addListener(function bestsellerHandler(message, sender, sendResponse) {
    if (message?.type !== 'ZOLM_BOOSTER_BESTSELLER_PAGE_STATUS') {
      return false;
    }

    const products = extractListingProducts();

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

  if (context === 'listing') {
    const listingPanel = createListingPanel();
    document.documentElement.appendChild(listingPanel.host);
    refreshListingPanel(listingPanel);

    listingPanel.refreshButton.addEventListener('click', () => refreshListingPanel(listingPanel));
    listingPanel.openButton.addEventListener('click', () => openListingInZolm(listingPanel));
    listingPanel.opportunityButton.addEventListener('click', () => scanListingOpportunities(listingPanel));
    listingPanel.queueButton.addEventListener('click', () => startListingDecisionQueue(listingPanel));
    listingPanel.queueRetryButton.addEventListener('click', () => retryListingDecisionQueue(listingPanel));
    listingPanel.queueClearButton.addEventListener('click', () => clearListingDecisionQueue(listingPanel));
    listingPanel.compareButton.addEventListener('click', () => openListingComparison(listingPanel));
    listingPanel.trackButton.addEventListener('click', () => trackListingSelection(listingPanel));

    chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
      if (message?.type !== 'ZOLM_BOOSTER_PAGE_STATUS') {
        return false;
      }

      const summary = listingSummary();
      sendResponse({
        ok: summary.products.length > 0,
        context: 'listing',
        payload: summary,
        summary: {
          ready: summary.products.length > 0,
          pricedItems: summary.pricedProducts.length,
          message: summary.products.length > 0
            ? `${summary.products.length} görünür ürün ve ${summary.pricedProducts.length} fiyat sinyali gözlendi. Derin analiz için Çok Satanlar çalışma alanını açın.`
            : 'Liste kartları henüz okunamadı. Sayfayı aşağı kaydırıp Yenile deneyin.',
        },
      });

      return false;
    });

    return;
  }

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
  panel.mediaButton.addEventListener('click', () => toggleProductMedia(panel));
  panel.mediaDownloadButton.addEventListener('click', () => downloadSelectedProductMedia(panel));
  panel.mediaCopyButton.addEventListener('click', () => copySelectedProductMedia(panel));
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

function createListingPanel() {
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
        width: 340px;
        max-height: min(680px, calc(100vh - 32px));
        overflow: auto;
        border: 1px solid #dbe3ef;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
        color: #0f172a;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      }
      .head, .foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 12px; }
      .head { border-bottom: 1px solid #e2e8f0; }
      .foot { border-top: 1px solid #e2e8f0; color: #64748b; font-size: 11px; }
      .brand { font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #475569; }
      .pill { border: 1px solid #bfdbfe; border-radius: 6px; background: #eff6ff; padding: 3px 6px; color: #1d4ed8; font-size: 10px; font-weight: 800; }
      .body { padding: 12px; }
      .eyebrow { color: #64748b; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
      .title { margin: 4px 0 0; font-size: 15px; font-weight: 800; line-height: 1.35; }
      .copy { margin: 5px 0 0; color: #64748b; font-size: 11px; line-height: 1.45; }
      .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 11px; }
      .metric { min-width: 0; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 8px; }
      .label { color: #64748b; font-size: 10px; }
      .value { margin-top: 3px; overflow: hidden; color: #0f172a; font-size: 13px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
      .section-title { margin-top: 12px; color: #475569; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
      .products { display: grid; gap: 6px; margin-top: 7px; }
      .product { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 8px; align-items: start; min-width: 0; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 8px; }
      .product:has(input:checked) { border-color: #94a3b8; background: #f8fafc; }
      .product input { width: 16px; height: 16px; margin: 1px 0 0; accent-color: #0f172a; }
      .product-main { min-width: 0; }
      .product-name { overflow: hidden; color: #0f172a; font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
      .product-meta { margin-top: 3px; overflow: hidden; color: #64748b; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
      .product-action { min-height: 28px; margin-top: 6px; border-color: #cbd5e1; padding: 0 8px; color: #0f172a; font-size: 10px; }
      .product-decision { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 5px; border-top: 1px solid #e2e8f0; padding-top: 7px; }
      .product-decision.hidden { display: none; }
      .decision-cell { min-width: 0; border-radius: 6px; background: #f8fafc; padding: 6px; }
      .decision-cell span { display: block; overflow: hidden; color: #64748b; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
      .decision-cell strong { display: block; margin-top: 2px; overflow: hidden; color: #0f172a; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
      .decision-cell strong.ok { color: #047857; }
      .decision-cell strong.warn { color: #c2410c; }
      .decision-cell strong.err { color: #be123c; }
      .decision-note { grid-column: 1 / -1; color: #64748b; font-size: 9px; line-height: 1.35; }
      .evidence { margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 8px; color: #475569; font-size: 11px; line-height: 1.45; }
      .opportunities { display: none; margin-top: 10px; overflow: hidden; border: 1px solid #bbf7d0; border-radius: 8px; background: #f0fdf4; }
      .opportunities.show { display: block; }
      .opportunity-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-bottom: 1px solid #bbf7d0; padding: 7px 8px; color: #047857; font-size: 10px; font-weight: 800; text-transform: uppercase; }
      .opportunity-list { display: grid; gap: 1px; background: #d1fae5; }
      .opportunity-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; background: #fff; padding: 7px 8px; }
      .opportunity-name { overflow: hidden; color: #0f172a; font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
      .opportunity-reason { margin-top: 2px; overflow: hidden; color: #64748b; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
      .opportunity-score { color: #047857; font-size: 12px; font-weight: 900; }
      .opportunity-confidence { color: #64748b; font-size: 9px; text-align: right; }
      .queue { display: none; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 8px; }
      .queue.show { display: block; }
      .queue-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: #475569; font-size: 10px; font-weight: 800; text-transform: uppercase; }
      .queue-track { height: 6px; margin-top: 7px; overflow: hidden; border-radius: 999px; background: #e2e8f0; }
      .queue-bar { height: 100%; width: 0; border-radius: inherit; background: #0f172a; transition: width .25s ease; }
      .queue-copy { margin-top: 6px; color: #64748b; font-size: 10px; line-height: 1.4; }
      .queue-retry { display: none; min-height: 30px; margin-top: 7px; padding: 0 8px; font-size: 10px; }
      .queue-retry.show { display: inline-block; }
      .queue-buttons { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
      .queue-buttons button { min-height: 30px; padding: 0 8px; font-size: 10px; }
      .actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 11px; }
      .actions .wide { grid-column: 1 / -1; }
      button { min-height: 40px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; padding: 0 11px; color: #334155; cursor: pointer; font: inherit; font-size: 11px; font-weight: 800; }
      button.primary { border-color: #0f172a; background: #0f172a; color: #fff; }
      button:disabled { cursor: not-allowed; opacity: .55; }
      .refresh { min-height: 28px; border: 0; padding: 0; color: #64748b; }
      .status.ok { color: #047857; }
      .status.warn { color: #c2410c; }
      .status.err { color: #be123c; }
      @media (max-width: 480px) {
        :host { left: 8px; right: 8px; bottom: 8px; }
        .box { width: auto; max-height: calc(100vh - 16px); }
      }
    </style>
    <div class="box">
      <div class="head">
        <div class="brand">ZOLM Discovery</div>
        <div class="pill">Gözlenen veri</div>
      </div>
      <div class="body">
        <div class="eyebrow">Trendyol liste araştırması</div>
        <h2 class="title js-query">Arama sonuçları</h2>
        <p class="copy js-copy">Görünür ürün kartları okunuyor...</p>
        <div class="grid">
          <div class="metric"><div class="label">Görünür ürün</div><div class="value js-count">-</div></div>
          <div class="metric"><div class="label">Fiyat aralığı</div><div class="value js-range">-</div></div>
          <div class="metric"><div class="label">Ortalama puan</div><div class="value js-rating">-</div></div>
          <div class="metric"><div class="label">Marka çeşitliliği</div><div class="value js-brands">-</div></div>
        </div>
        <div class="section-title">Öne çıkan görünür kartlar</div>
        <div class="products js-products"></div>
        <div class="opportunities js-opportunities"><div class="opportunity-head"><span>Fırsat sıralaması</span><span class="js-opportunity-count"></span></div><div class="opportunity-list js-opportunity-list"></div></div>
        <div class="queue js-queue"><div class="queue-head"><span>Toplu karar kuyruğu</span><span class="js-queue-count">0/0</span></div><div class="queue-track"><div class="queue-bar js-queue-bar"></div></div><div class="queue-copy js-queue-copy">Kuyruk bekleniyor.</div><div class="queue-buttons"><button class="queue-retry js-queue-retry" type="button">Başarısızları yeniden dene</button><button class="js-queue-clear" type="button">Kuyruğu temizle</button></div></div>
        <div class="evidence">Bu özet yalnızca açık sayfadaki kartlardan gözlenir. Satış adedi veya stok tahmini değildir; tahmin ve finans kararı ZOLM panelinde ayrıca hesaplanır.</div>
        <div class="actions">
          <button class="primary wide js-opportunity" type="button">Fırsatları tara</button>
          <button class="wide js-queue-start" type="button">İlk 10 ürünü karar kuyruğuna al</button>
          <button class="js-compare" type="button">2 ürünü karşılaştır</button>
          <button class="js-track" type="button">2 ürünü takibe al</button>
          <button class="primary wide js-open" type="button">Listeyi raporla</button>
        </div>
      </div>
      <div class="foot"><button class="refresh js-refresh" type="button">Yenile</button><span class="status js-status">Hazır</span></div>
    </div>
  `;

  return {
    host,
    query: shadow.querySelector('.js-query'),
    copy: shadow.querySelector('.js-copy'),
    count: shadow.querySelector('.js-count'),
    range: shadow.querySelector('.js-range'),
    rating: shadow.querySelector('.js-rating'),
    brands: shadow.querySelector('.js-brands'),
    products: shadow.querySelector('.js-products'),
    status: shadow.querySelector('.js-status'),
    opportunities: shadow.querySelector('.js-opportunities'),
    opportunityList: shadow.querySelector('.js-opportunity-list'),
    opportunityCount: shadow.querySelector('.js-opportunity-count'),
    opportunityButton: shadow.querySelector('.js-opportunity'),
    queue: shadow.querySelector('.js-queue'),
    queueBar: shadow.querySelector('.js-queue-bar'),
    queueCount: shadow.querySelector('.js-queue-count'),
    queueCopy: shadow.querySelector('.js-queue-copy'),
    queueButton: shadow.querySelector('.js-queue-start'),
    queueRetryButton: shadow.querySelector('.js-queue-retry'),
    queueClearButton: shadow.querySelector('.js-queue-clear'),
    openButton: shadow.querySelector('.js-open'),
    compareButton: shadow.querySelector('.js-compare'),
    trackButton: shadow.querySelector('.js-track'),
    refreshButton: shadow.querySelector('.js-refresh'),
    selectedIds: new Set(),
    opportunityResults: [],
    queuePollTimer: null,
  };
}

function refreshListingPanel(panel) {
  const summary = listingSummary();
  panel.query.textContent = summary.keyword || 'Trendyol liste sonuçları';
  panel.copy.textContent = summary.products.length > 0
    ? `${summary.products.length} karttan karar öncesi hızlı pazar görünümü oluşturuldu.`
    : 'Ürün kartları henüz okunamadı. Sayfayı biraz aşağı kaydırıp Yenile deneyin.';
  panel.count.textContent = String(summary.products.length);
  panel.range.textContent = summary.pricedProducts.length > 0
    ? `${formatMoney(Math.min(...summary.pricedProducts))} – ${formatMoney(Math.max(...summary.pricedProducts))}`
    : 'Yayınlanmıyor';
  panel.rating.textContent = summary.ratings.length > 0
    ? `${(summary.ratings.reduce((sum, value) => sum + value, 0) / summary.ratings.length).toFixed(1)} / 5`
    : 'Yayınlanmıyor';
  panel.brands.textContent = `${summary.brands.length} marka`;
  panel.openButton.disabled = summary.products.length === 0;
  panel.status.textContent = summary.products.length > 0 ? 'Hazır' : 'Veri bekleniyor';
  panel.status.className = summary.products.length > 0 ? 'status js-status ok' : 'status js-status err';
  const visibleProducts = summary.products.slice(0, 6);
  const visibleIds = new Set(visibleProducts.map((product) => String(product.trendyol_product_id)));
  panel.selectedIds = new Set(Array.from(panel.selectedIds).filter((productId) => visibleIds.has(productId)));

  for (const product of visibleProducts) {
    if (panel.selectedIds.size >= Math.min(2, visibleProducts.length)) break;
    panel.selectedIds.add(String(product.trendyol_product_id));
  }

  panel.products.replaceChildren(...visibleProducts.map((product) => {
    const row = document.createElement('div');
    row.className = 'product';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = panel.selectedIds.has(String(product.trendyol_product_id));
    checkbox.setAttribute('aria-label', `${product.title || 'Ürün'} karşılaştırma seçimi`);
    const main = document.createElement('div');
    main.className = 'product-main';
    const title = document.createElement('div');
    title.className = 'product-name';
    title.textContent = product.title || 'Ürün';
    const meta = document.createElement('div');
    meta.className = 'product-meta';
    const details = [
      product.brand,
      Number(product.sale_price || 0) > 0 ? formatMoney(product.sale_price) : null,
      Number(product.rating || 0) > 0 ? `${Number(product.rating).toFixed(1)} puan` : null,
      Number(product.review_count || 0) > 0 ? `${formatNumber(product.review_count)} yorum` : null,
    ].filter(Boolean);
    meta.textContent = details.join(' · ') || `Ürün ID ${product.trendyol_product_id}`;
    const decisionButton = document.createElement('button');
    decisionButton.type = 'button';
    decisionButton.className = 'product-action';
    decisionButton.textContent = 'Karar merkezine al';
    const decisionBox = document.createElement('div');
    decisionBox.className = 'product-decision hidden';
    decisionButton.addEventListener('click', () => openListingDecision(panel, product, decisionButton, decisionBox));
    checkbox.addEventListener('change', () => {
      const productId = String(product.trendyol_product_id);
      if (checkbox.checked && panel.selectedIds.size >= 4) {
        checkbox.checked = false;
        panel.status.textContent = 'En fazla 4 ürün karşılaştırılabilir.';
        panel.status.className = 'status js-status err';
        return;
      }

      if (checkbox.checked) panel.selectedIds.add(productId);
      else panel.selectedIds.delete(productId);
      updateListingSelection(panel, visibleProducts);
    });
    main.append(title, meta, decisionButton);
    row.append(checkbox, main, decisionBox);
    return row;
  }));
  updateListingSelection(panel, visibleProducts);
}

function openListingDecision(panel, product, button, decisionBox) {
  const sourceUrl = String(product?.source_url || '');
  if (!sourceUrl) {
    panel.status.textContent = 'Ürün bağlantısı bulunamadı.';
    panel.status.className = 'status js-status err';
    return;
  }

  button.disabled = true;
  panel.status.textContent = 'Canlı ürün verisi doğrulanıyor...';
  panel.status.className = 'status js-status';

  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_DECIDE_LISTING_PRODUCT', source_url: sourceUrl }, (response) => {
    button.disabled = false;

    if (chrome.runtime.lastError || !response?.ok) {
      panel.status.textContent = response?.message || chrome.runtime.lastError?.message || 'Karar merkezi açılamadı.';
      panel.status.className = 'status js-status err';
      return;
    }

    renderListingDecisionSummary(decisionBox, response.summary || {});
    button.textContent = 'Karar merkezini yeniden aç';
    panel.status.textContent = 'Canlı analiz kaydedildi · Karar merkezi açıldı';
    panel.status.className = 'status js-status ok';
  });
}

function renderListingDecisionSummary(container, summary) {
  const decision = summary?.decision || {};
  const current = summary?.current || {};
  const evidence = summary?.evidence || {};
  const tone = decision.status === 'go'
    ? 'ok'
    : (['loss', 'risk'].includes(decision.status) ? 'err' : 'warn');
  const dailySales = Number(current.estimated_daily_sales);
  const confidence = Number(current.confidence_score ?? evidence.confidence_score);
  const margin = Number(decision.profit_margin_percent);

  const cells = [
    ['Karar', decision.label || 'Veri topla', tone],
    ['Satış tahmini', Number.isFinite(dailySales) && dailySales > 0 ? `~${dailySales.toFixed(1)} / gün` : 'Henüz hazır değil', ''],
    ['Finans', decision.finance_ready && Number.isFinite(margin) ? `%${margin.toFixed(1)} marj` : 'Maliyet gerekli', decision.finance_ready ? tone : 'warn'],
  ].map(([label, value, valueTone]) => {
    const cell = document.createElement('div');
    cell.className = 'decision-cell';
    const labelNode = document.createElement('span');
    labelNode.textContent = label;
    const valueNode = document.createElement('strong');
    valueNode.className = valueTone;
    valueNode.textContent = value;
    cell.append(labelNode, valueNode);
    return cell;
  });

  const note = document.createElement('div');
  note.className = 'decision-note';
  note.textContent = `${Number.isFinite(confidence) ? `%${Math.max(0, Math.min(100, confidence))} güven · ` : ''}${evidence.sales_label || 'Tahmin, kesin sipariş verisi değildir.'}`;
  container.replaceChildren(...cells, note);
  container.classList.remove('hidden');
}

function updateListingSelection(panel, products) {
  const selectedCount = products.filter((product) => panel.selectedIds.has(String(product.trendyol_product_id))).length;
  panel.compareButton.disabled = selectedCount < 2;
  panel.compareButton.textContent = `${selectedCount} ürünü karşılaştır`;
  panel.trackButton.disabled = selectedCount < 1;
  panel.trackButton.textContent = `${selectedCount} ürünü takibe al`;

  if (selectedCount >= 2) {
    panel.status.textContent = `${selectedCount} ürün seçildi`;
    panel.status.className = 'status js-status ok';
  }
}

function scanListingOpportunities(panel) {
  const summary = listingSummary();
  if (summary.products.length < 2) {
    panel.status.textContent = 'Fırsat taraması için en az 2 görünür ürün gerekir.';
    panel.status.className = 'status js-status err';
    return;
  }

  panel.opportunityButton.disabled = true;
  panel.status.textContent = `${Math.min(40, summary.products.length)} ürün fırsat sinyalleriyle taranıyor...`;
  panel.status.className = 'status js-status';
  chrome.runtime.sendMessage({
    type: 'ZOLM_BOOSTER_SCAN_LISTING_OPPORTUNITIES',
    payload: {
      query: summary.keyword,
      matched_label: summary.keyword,
      source_url: summary.source_url,
      items: summary.products.slice(0, 40),
    },
  }, (response) => {
    panel.opportunityButton.disabled = false;
    if (chrome.runtime.lastError || !response?.ok) {
      panel.status.textContent = response?.message || chrome.runtime.lastError?.message || 'Fırsat taraması tamamlanamadı.';
      panel.status.className = 'status js-status err';
      return;
    }

    renderListingOpportunities(panel, response.scan || {});
    panel.opportunityButton.textContent = 'Fırsatları yeniden tara';
    panel.status.textContent = response.message || 'Fırsat sıralaması hazırlandı.';
    panel.status.className = 'status js-status ok';
  });
}

function renderListingOpportunities(panel, scan) {
  panel.opportunityResults = Array.isArray(scan.results) ? scan.results.slice(0, 40) : [];
  const results = panel.opportunityResults.slice(0, 5);
  panel.opportunityCount.textContent = `${scan.scanned_count || results.length} tarandı`;
  panel.opportunityList.replaceChildren(...results.map((result) => {
    const row = document.createElement('div');
    row.className = 'opportunity-row';
    const copy = document.createElement('div');
    copy.style.minWidth = '0';
    const name = document.createElement('div');
    name.className = 'opportunity-name';
    name.textContent = result.title || 'Ürün';
    const reason = document.createElement('div');
    reason.className = 'opportunity-reason';
    reason.textContent = Array.isArray(result.reasons) && result.reasons.length > 0
      ? result.reasons[0]
      : 'Detay doğrulaması önerilir';
    copy.append(name, reason);
    const scoreBox = document.createElement('div');
    const score = document.createElement('div');
    score.className = 'opportunity-score';
    score.textContent = `${Number(result.opportunity_score || 0)}/100`;
    const confidence = document.createElement('div');
    confidence.className = 'opportunity-confidence';
    confidence.textContent = `%${Number(result.confidence_score || 0)} güven`;
    scoreBox.append(score, confidence);
    row.append(copy, scoreBox);
    return row;
  }));
  panel.opportunities.classList.add('show');
  panel.queueButton.textContent = `${Math.min(40, panel.opportunityResults.length || listingSummary().products.length)} ürünü karar kuyruğuna al`;
}

function startListingDecisionQueue(panel) {
  const fallback = listingSummary().products;
  const source = panel.opportunityResults.length > 0 ? panel.opportunityResults : fallback;
  const urls = source.map((item) => item.source_url).filter(Boolean).slice(0, 40);
  if (urls.length === 0) {
    panel.status.textContent = 'Karar kuyruğuna alınacak ürün bulunamadı.';
    panel.status.className = 'status js-status err';
    return;
  }

  panel.queueButton.disabled = true;
  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_START_DECISION_QUEUE', urls }, (response) => {
    panel.queueButton.disabled = false;
    if (chrome.runtime.lastError || !response?.ok) {
      panel.status.textContent = response?.message || chrome.runtime.lastError?.message || 'Karar kuyruğu başlatılamadı.';
      panel.status.className = 'status js-status err';
      return;
    }
    renderDecisionQueue(panel, response.queue);
    pollDecisionQueue(panel);
  });
}

function pollDecisionQueue(panel) {
  clearTimeout(panel.queuePollTimer);
  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_DECISION_QUEUE_STATUS' }, (response) => {
    if (!chrome.runtime.lastError && response?.ok && response.queue) {
      renderDecisionQueue(panel, response.queue);
      if (response.queue.status !== 'completed') {
        panel.queuePollTimer = setTimeout(() => pollDecisionQueue(panel), 1200);
      }
    }
  });
}

function retryListingDecisionQueue(panel) {
  panel.queueRetryButton.disabled = true;
  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_RETRY_DECISION_QUEUE' }, (response) => {
    panel.queueRetryButton.disabled = false;
    if (chrome.runtime.lastError || !response?.ok) return;
    renderDecisionQueue(panel, response.queue);
    pollDecisionQueue(panel);
  });
}

function clearListingDecisionQueue(panel) {
  clearTimeout(panel.queuePollTimer);
  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_CLEAR_DECISION_QUEUE' }, (response) => {
    if (chrome.runtime.lastError || !response?.ok) return;
    panel.queue.classList.remove('show');
    panel.queueBar.style.width = '0%';
    panel.status.textContent = 'Karar kuyruğu temizlendi.';
    panel.status.className = 'status js-status';
  });
}

function renderDecisionQueue(panel, queue) {
  if (!queue) return;
  const completed = Number(queue.completed || 0);
  const failed = Number(queue.failed || 0);
  const total = Number(queue.total || 0);
  panel.queue.classList.add('show');
  panel.queueBar.style.width = `${Math.max(0, Math.min(100, Number(queue.progress_percent || 0)))}%`;
  panel.queueCount.textContent = `${completed + failed}/${total}`;
  panel.queueCopy.textContent = queue.status === 'completed'
    ? `${completed} ürün analiz edildi${failed ? ` · ${failed} ürün iki denemede okunamadı` : ' · tümü başarılı'}`
    : `${completed} tamamlandı · ${Number(queue.processing || 0)} işleniyor · ${Number(queue.pending || 0)} bekliyor`;
  panel.queueRetryButton.classList.toggle('show', queue.status === 'completed' && failed > 0);
  panel.status.textContent = queue.status === 'completed' ? 'Toplu karar kuyruğu tamamlandı.' : 'Toplu karar kuyruğu çalışıyor...';
  panel.status.className = failed > 0 && queue.status === 'completed' ? 'status js-status warn' : 'status js-status ok';
}

function openListingInZolm(panel) {
  const summary = listingSummary();
  panel.openButton.disabled = true;
  panel.status.textContent = 'Pazar ölçümü kaydediliyor...';
  panel.status.className = 'status js-status';

  chrome.runtime.sendMessage({
    type: 'ZOLM_BOOSTER_CAPTURE_LISTING',
    payload: {
      query: summary.keyword,
      matched_label: summary.keyword,
      source_url: summary.source_url,
      items: summary.products,
    },
  }, (response) => {
    panel.openButton.disabled = false;

    if (chrome.runtime.lastError || !response?.ok) {
      panel.status.textContent = response?.message || chrome.runtime.lastError?.message || 'ZOLM açılamadı.';
      panel.status.className = 'status js-status err';
      return;
    }

    panel.openButton.textContent = 'Yeni ölçüm kaydet';
    panel.status.textContent = `${response.item_count || summary.products.length} ürün kaydedildi · Rapor açıldı`;
    panel.status.className = 'status js-status ok';
  });
}

function openListingComparison(panel) {
  const summary = listingSummary();
  const urls = summary.products
    .filter((product) => panel.selectedIds.has(String(product.trendyol_product_id)))
    .map((product) => product.source_url)
    .filter(Boolean)
    .slice(0, 4);

  if (urls.length < 2) {
    panel.status.textContent = 'Karşılaştırma için en az 2 ürün seçin.';
    panel.status.className = 'status js-status err';
    return;
  }

  panel.compareButton.disabled = true;
  panel.status.textContent = 'Karşılaştırma seti hazırlanıyor...';
  panel.status.className = 'status js-status';

  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_COMPARE_LISTING', urls }, (response) => {
    panel.compareButton.disabled = false;

    if (chrome.runtime.lastError || !response?.ok) {
      panel.status.textContent = response?.message || chrome.runtime.lastError?.message || 'Karşılaştırma açılamadı.';
      panel.status.className = 'status js-status err';
      return;
    }

    panel.status.textContent = `${urls.length} ürün ZOLM karşılaştırmasına taşındı`;
    panel.status.className = 'status js-status ok';
  });
}

function trackListingSelection(panel) {
  const summary = listingSummary();
  const urls = summary.products
    .filter((product) => panel.selectedIds.has(String(product.trendyol_product_id)))
    .map((product) => product.source_url)
    .filter(Boolean)
    .slice(0, 4);

  if (urls.length === 0) {
    panel.status.textContent = 'Takip için en az 1 ürün seçin.';
    panel.status.className = 'status js-status err';
    return;
  }

  panel.trackButton.disabled = true;
  panel.status.textContent = `${urls.length} ürün doğrulanıp takibe alınıyor...`;
  panel.status.className = 'status js-status';

  chrome.runtime.sendMessage({ type: 'ZOLM_BOOSTER_TRACK_LISTING_SELECTION', urls }, (response) => {
    panel.trackButton.disabled = false;

    if (chrome.runtime.lastError || !response?.ok) {
      panel.status.textContent = response?.message || chrome.runtime.lastError?.message || 'Toplu takip tamamlanamadı.';
      panel.status.className = 'status js-status err';
      return;
    }

    panel.trackButton.textContent = 'Takibi güncelle';
    panel.status.textContent = response.message || `${response.tracked_count || urls.length} ürün takibe alındı`;
    panel.status.className = response.failed_count > 0 ? 'status js-status warn' : 'status js-status ok';
  });
}

function listingSummary() {
  const products = extractListingProducts();
  const pricedProducts = products.map((product) => Number(product.sale_price || 0)).filter((value) => value > 0);
  const ratings = products.map((product) => Number(product.rating || 0)).filter((value) => value > 0 && value <= 5);
  const brands = Array.from(new Set(products.map((product) => clean(product.brand)).filter(Boolean)));

  const keyword = listingKeyword();

  return {
    keyword: keyword.length >= 2 ? keyword : 'Trendyol liste',
    source_url: location.href,
    products,
    pricedProducts,
    ratings,
    brands,
  };
}

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
        max-height: min(720px, calc(100vh - 32px));
        border: 1px solid #dbe3ef;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
        color: #0f172a;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        overflow: auto;
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
      .actions .wide { grid-column: 1 / -1; }
      .media-center { display: none; margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 8px; }
      .media-center.show { display: block; }
      .media-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: #475569; font-size: 10px; font-weight: 800; text-transform: uppercase; }
      .media-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin-top: 8px; }
      .media-item { position: relative; min-width: 0; overflow: hidden; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; aspect-ratio: 1; }
      .media-item img { width: 100%; height: 100%; object-fit: cover; }
      .media-item input { position: absolute; top: 4px; left: 4px; width: 15px; height: 15px; margin: 0; accent-color: #0f172a; }
      .media-video { display: grid; width: 100%; height: 100%; place-items: center; color: #475569; font-size: 9px; font-weight: 800; }
      .media-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 8px; }
      .media-actions button { min-height: 34px; font-size: 10px; }
      .media-note { margin-top: 7px; color: #64748b; font-size: 9px; line-height: 1.4; }
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
        <div class="media-center js-media-center">
          <div class="media-head"><span>Ürün medya merkezi</span><span class="js-media-count">0 öğe</span></div>
          <div class="media-grid js-media-grid"></div>
          <div class="media-actions">
            <button class="primary js-media-download" type="button">Seçileni indir</button>
            <button class="js-media-copy" type="button">Bağlantıları kopyala</button>
          </div>
          <div class="media-note">Görseller yerel cihazınıza indirilir. Video bağlantıları boyut nedeniyle yeni sekmede açılır; ZOLM’a yüklenmez.</div>
        </div>
        <div class="actions">
          <button class="js-preview">Ön izle</button>
          <button class="primary js-track">Takibe al</button>
          <button class="js-stock">Stok sorgula</button>
          <button class="primary js-store">Mağaza tara</button>
          <button class="wide js-media" type="button">Medya merkezi</button>
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
    mediaButton: shadow.querySelector('.js-media'),
    mediaCenter: shadow.querySelector('.js-media-center'),
    mediaGrid: shadow.querySelector('.js-media-grid'),
    mediaCount: shadow.querySelector('.js-media-count'),
    mediaDownloadButton: shadow.querySelector('.js-media-download'),
    mediaCopyButton: shadow.querySelector('.js-media-copy'),
    refreshButton: shadow.querySelector('.js-refresh'),
    tracking: shadow.querySelector('.js-tracking'),
    estimatedSales: shadow.querySelector('.js-estimated-sales'),
    riskConfidence: shadow.querySelector('.js-risk-confidence'),
    trackedStock: shadow.querySelector('.js-tracked-stock'),
    favoriteDelta: shadow.querySelector('.js-favorite-delta'),
    lastScan: shadow.querySelector('.js-last-scan'),
    mediaItems: [],
    selectedMediaIndexes: new Set(),
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
  panel.mediaButton.style.display = productMode ? '' : 'none';
  panel.storeButton.textContent = productMode ? 'Satıcıyı tara' : 'Mağaza tara';
  panel.previewButton.disabled = productMode && !page.trendyol_product_id;
  panel.trackButton.disabled = productMode && !page.trendyol_product_id;
  panel.stockButton.disabled = productMode && !page.trendyol_product_id;
  panel.storeButton.disabled = productMode ? !page.trendyol_product_id : !summary.ready;
  renderProductMedia(panel, productMode ? data.media : []);
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
  panel.mediaButton.disabled = disabled || panel.mediaItems.length === 0;
}

function renderProductMedia(panel, items) {
  panel.mediaItems = Array.isArray(items) ? items.slice(0, 24) : [];
  panel.selectedMediaIndexes = new Set(panel.mediaItems
    .map((item, index) => item.type === 'image' ? index : null)
    .filter((index) => index !== null)
    .slice(0, 12));
  panel.mediaCount.textContent = `${panel.mediaItems.length} öğe`;
  panel.mediaButton.disabled = panel.mediaItems.length === 0;
  panel.mediaButton.textContent = panel.mediaItems.length > 0 ? `Medya merkezi · ${panel.mediaItems.length}` : 'Medya bulunamadı';
  panel.mediaCenter.className = 'media-center js-media-center';

  panel.mediaGrid.replaceChildren(...panel.mediaItems.map((item, index) => {
    const label = document.createElement('label');
    label.className = 'media-item';
    label.title = item.type === 'video' ? 'Video bağlantısı' : `Görsel ${index + 1}`;
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = panel.selectedMediaIndexes.has(index);
    checkbox.addEventListener('change', () => {
      if (checkbox.checked) panel.selectedMediaIndexes.add(index);
      else panel.selectedMediaIndexes.delete(index);
    });

    if (item.type === 'video') {
      const video = document.createElement('span');
      video.className = 'media-video';
      video.textContent = 'VİDEO';
      label.append(checkbox, video);
    } else {
      const image = document.createElement('img');
      image.src = item.url;
      image.alt = `Ürün görseli ${index + 1}`;
      image.loading = 'lazy';
      label.append(checkbox, image);
    }

    return label;
  }));
}

function toggleProductMedia(panel) {
  if (panel.mediaItems.length === 0) return;
  panel.mediaCenter.classList.toggle('show');
}

async function downloadSelectedProductMedia(panel) {
  const selected = panel.mediaItems
    .map((item, index) => ({ ...item, index }))
    .filter((item) => panel.selectedMediaIndexes.has(item.index));
  const images = selected.filter((item) => item.type === 'image').slice(0, 12);
  const videos = selected.filter((item) => item.type === 'video').slice(0, 4);

  if (images.length === 0 && videos.length === 0) {
    panel.status.textContent = 'İndirmek veya açmak için medya seçin.';
    panel.status.className = 'js-status err';
    return;
  }

  panel.mediaDownloadButton.disabled = true;
  panel.status.textContent = `${images.length} görsel hazırlanıyor...`;
  panel.status.className = 'js-status';
  let downloaded = 0;

  for (const item of images) {
    try {
      const response = await sendRuntimeMessageFromPage({
        type: 'ZOLM_BOOSTER_DOWNLOAD_MEDIA',
        media_url: item.url,
        filename: `${extractProductId(location.href) || 'urun'}-${item.index + 1}`,
      });
      const link = document.createElement('a');
      link.href = response.data_url;
      link.download = response.filename;
      link.style.display = 'none';
      document.documentElement.appendChild(link);
      link.click();
      link.remove();
      downloaded++;
    } catch (error) {
      console.warn('ZOLM media download error:', error);
    }
  }

  for (const item of videos) {
    window.open(item.url, '_blank', 'noopener,noreferrer');
  }

  panel.mediaDownloadButton.disabled = false;
  panel.status.textContent = `${downloaded} görsel indirildi${videos.length ? ` · ${videos.length} video açıldı` : ''}`;
  panel.status.className = downloaded === images.length ? 'js-status ok' : 'js-status err';
}

async function copySelectedProductMedia(panel) {
  const urls = panel.mediaItems
    .filter((item, index) => panel.selectedMediaIndexes.has(index))
    .map((item) => item.url);
  if (urls.length === 0) return;

  try {
    await navigator.clipboard.writeText(urls.join('\n'));
    panel.status.textContent = `${urls.length} medya bağlantısı kopyalandı.`;
    panel.status.className = 'js-status ok';
  } catch (error) {
    panel.status.textContent = 'Bağlantılar kopyalanamadı.';
    panel.status.className = 'js-status err';
  }
}

function sendRuntimeMessageFromPage(message) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(message, (response) => {
      if (chrome.runtime.lastError || !response?.ok) {
        reject(new Error(response?.message || chrome.runtime.lastError?.message || 'Eklenti işlemi tamamlanamadı.'));
        return;
      }
      resolve(response);
    });
  });
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

  const url = new URL(location.href);
  if (
    url.pathname === '/sr'
    || url.pathname.startsWith('/sr/')
    || url.pathname.startsWith('/cok-satanlar')
    || url.pathname.startsWith('/butik/liste')
    || /-x-c\d+/i.test(url.pathname)
    || url.searchParams.has('q')
    || Boolean(readSearchState())
    || document.querySelector('.p-card-wrppr a[href*="-p-"], [data-testid="product-card"] a[href*="-p-"]')
  ) {
    return 'listing';
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
    media: extractProductMedia(envoyProduct),
  };
}

function extractProductMedia(envoyProduct) {
  const media = [];
  const add = (value, type = 'image') => {
    let url = clean(value || '');
    if (url.startsWith('//')) url = `https:${url}`;
    if (!/^https:\/\//i.test(url)) return;
    url = url.slice(0, 1600);
    if (!media.some((item) => item.url === url)) media.push({ url, type });
  };

  for (const image of Array.isArray(envoyProduct?.images) ? envoyProduct.images : []) {
    add(typeof image === 'string' ? image : (image?.url || image?.src), 'image');
  }
  add(meta('og:image'), 'image');
  document.querySelectorAll('img[src*="dsmcdn.com"], img[src*="trendyol.com"]').forEach((image) => add(image.currentSrc || image.src, 'image'));
  add(meta('og:video'), 'video');
  document.querySelectorAll('video[src], video source[src]').forEach((video) => add(video.currentSrc || video.src, 'video'));

  return media.slice(0, 24);
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

function extractListingProducts(maxProducts = 72) {
  const products = new Map();
  const anchors = document.querySelectorAll('a[href*="-p-"]');

  for (const anchor of anchors) {
    if (products.size >= maxProducts) break;

    const href = String(anchor.href || anchor.getAttribute('href') || '');
    const match = href.match(/-p-(\d+)/i);
    if (!match || products.has(match[1])) continue;

    const card = anchor.closest('.p-card-wrppr, [data-testid="product-card"], .prdct-cntnr-wrppr, li, article') || anchor;
    const image = card.querySelector('img');
    const brandNode = card.querySelector('.product-brand, .prdct-desc-cntnr-ttl-w .prdct-desc-cntnr-ttl, [class*="brandName"], [class*="brand-name"]');
    const nameNode = card.querySelector('.product-name, .prdct-desc-cntnr-name, [data-testid="product-card-name"], [class*="productName"], [class*="product-name"], h2, h3');
    const priceNode = card.querySelector('.sale-price, .price-value, .single-price, .price-section, .prc-box-dscntd, .prc-box-sllng, [class*="discountedPrice"], [class*="sellingPrice"]');
    const ratingNode = card.querySelector('.average-rating, .rating-score, .rtngs, [class*="rating-score"], [class*="averageRating"]');
    const reviewNode = card.querySelector('.review-rating, .ratingCount, .rating-count, .rtngs-cntnr, [class*="reviewCount"]');
    const brand = clean(brandNode?.textContent || '');
    const productName = clean(nameNode?.textContent || image?.alt || anchor.getAttribute('title') || anchor.getAttribute('aria-label') || 'Ürün');
    const title = brand && !productName.toLocaleLowerCase('tr-TR').startsWith(brand.toLocaleLowerCase('tr-TR'))
      ? `${brand} ${productName}`
      : productName;
    const imageUrl = clean(image?.src || image?.getAttribute('data-src') || '');
    const reviewMatch = clean(reviewNode?.textContent || '').match(/([\d.,]+)/);
    const rating = Number.parseFloat(clean(ratingNode?.textContent || '').replace(',', '.'));

    products.set(match[1], {
      trendyol_product_id: match[1],
      source_url: href.startsWith('http') ? href : new URL(href, location.origin).href,
      image_url: /^https?:\/\//i.test(imageUrl) ? imageUrl.slice(0, 1000) : null,
      title: title.slice(0, 240),
      brand: brand.slice(0, 120),
      sale_price: parseListingMoney(priceNode?.textContent || ''),
      rating: Number.isFinite(rating) && rating > 0 && rating <= 5 ? rating : null,
      review_count: reviewMatch ? Number.parseInt(reviewMatch[1].replace(/\./g, '').replace(',', ''), 10) : null,
      favorite_count: null,
      campaign_badges: [],
      seller_name: '',
      category_name: '',
    });
  }

  if (products.size > 0) {
    return Array.from(products.values());
  }

  const state = readSearchState();
  const stateProducts = state?.searchStateManager?.searchProducts?.productList
    || state?.productList
    || state?.products
    || [];

  for (const product of Array.isArray(stateProducts) ? stateProducts.slice(0, maxProducts) : []) {
    const productId = String(product?.id || product?.productId || product?.contentId || '');
    if (!productId || products.has(productId)) continue;

    const productUrl = clean(product?.url || '');
    const imagePath = clean(product?.images?.[0] || product?.imageUrl || '');
    const imageUrl = imagePath.startsWith('http') ? imagePath : (imagePath ? `https://cdn.dsmcdn.com${imagePath}` : '');
    products.set(productId, {
      trendyol_product_id: productId,
      source_url: productUrl ? new URL(productUrl, location.origin).href : '',
      image_url: /^https?:\/\//i.test(imageUrl) ? imageUrl.slice(0, 1000) : null,
      title: clean(product?.name || product?.title || 'Ürün').slice(0, 240),
      brand: clean(product?.brand?.name || product?.brand || '').slice(0, 120),
      sale_price: numericValue(product?.price?.sellingPrice?.value ?? product?.price?.sellingPrice ?? product?.price?.discountedPrice ?? product?.sellingPrice) || 0,
      rating: numericValue(product?.ratingScore?.averageRating),
      review_count: nonNegativeInteger(product?.ratingScore?.totalCount),
      favorite_count: nonNegativeInteger(product?.favoriteCount),
      campaign_badges: [],
      seller_name: clean(product?.merchant?.name || product?.merchantName || '').slice(0, 255),
      category_name: clean(product?.categoryName || '').slice(0, 180),
    });
  }

  return Array.from(products.values());
}

function listingKeyword() {
  const url = new URL(location.href);
  const query = clean(url.searchParams.get('q') || url.searchParams.get('qt') || '');

  if (query) return query;

  const heading = firstText(['h1', '[data-testid="search-title"]', '.dscrptn', '.category-title']);
  return clean(heading || document.title.replace(/\s*[-|]\s*Trendyol.*$/i, '')).slice(0, 120);
}

function parseListingMoney(value) {
  const text = clean(value).replace(/[^\d.,]/g, '');
  if (!text) return 0;

  const lastComma = text.lastIndexOf(',');
  const lastDot = text.lastIndexOf('.');
  let normalized = text;

  if (lastComma > lastDot) {
    normalized = text.replace(/\./g, '').replace(',', '.');
  } else if (lastDot > lastComma && /\.\d{1,2}$/.test(text)) {
    normalized = text.replace(/,/g, '');
  } else {
    normalized = text.replace(/[.,]/g, '');
  }

  const amount = Number.parseFloat(normalized);
  return Number.isFinite(amount) ? amount : 0;
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
