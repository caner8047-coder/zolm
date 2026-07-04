(function () {
  const PAGE_SOURCE = 'zolm-booster-page';
  const EXTENSION_SOURCE = 'zolm-booster-extension';
  const COMPANION_PATH = '/marketplace-trendyol-booster/companion';
  const READY_RETRY_MS = [0, 250, 1000, 2500, 5000, 10000, 15000];
  const QUERY_CONFIGS = {
    STOCK_QUERY: {
      resultType: 'STOCK_QUERY_RESULT',
      backgroundType: 'ZOLM_BOOSTER_STOCK_PAYLOAD_FROM_URL',
      companionAction: 'stock_check',
    },
    PRODUCT_ANALYSIS_QUERY: {
      resultType: 'PRODUCT_ANALYSIS_QUERY_RESULT',
      backgroundType: 'ZOLM_BOOSTER_PRODUCT_PAYLOAD_FROM_URL',
      companionAction: 'product_analysis',
    },
    BESTSELLER_QUERY: {
      resultType: 'BESTSELLER_QUERY_RESULT',
      backgroundType: 'ZOLM_BOOSTER_BESTSELLER_FROM_URL',
    },
    BESTSELLER_TRACK: {
      resultType: 'BESTSELLER_TRACK_RESULT',
      backgroundType: 'ZOLM_BOOSTER_TRACK_PAYLOAD_FROM_URL',
      companionAction: 'track',
    },
    KEYWORD_TRACKING_QUERY: {
      resultType: 'KEYWORD_TRACKING_RESULT',
      backgroundType: 'ZOLM_BOOSTER_KEYWORD_TRACKING_FROM_URL',
    },
    SUPPLIER_RESEARCH_QUERY: {
      resultType: 'SUPPLIER_RESEARCH_RESULT',
      backgroundType: 'ZOLM_BOOSTER_SUPPLIER_RESEARCH_PAYLOAD_FROM_URL',
      companionAction: 'market_research',
    },
    STORE_SCAN_QUERY: {
      resultType: 'STORE_SCAN_RESULT',
      backgroundType: 'ZOLM_BOOSTER_STORE_SCAN_FROM_URL',
    },
    REVIEW_SCAN_QUERY: {
      resultType: 'REVIEW_SCAN_RESULT',
      backgroundType: 'ZOLM_BOOSTER_REVIEW_SCAN_START',
    },
    REVIEW_STORE_PREVIEW_QUERY: {
      resultType: 'REVIEW_STORE_PREVIEW_RESULT',
      backgroundType: 'ZOLM_BOOSTER_REVIEW_STORE_PREVIEW',
    },
  };
  let readyAnnounced = false;

  if (window.__ZOLM_BOOSTER_BRIDGE_LOADED__) {
    window.postMessage({
      source: EXTENSION_SOURCE,
      type: 'READY',
      version: chrome.runtime.getManifest().version,
    }, window.location.origin);
    return;
  }

  window.__ZOLM_BOOSTER_BRIDGE_LOADED__ = true;

  console.log('[ZOLM Extension Bridge] Content script injected on page:', window.location.href);

  READY_RETRY_MS.forEach((delay) => window.setTimeout(announceReady, delay));
  window.addEventListener('focus', announceReady);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      announceReady();
    }
  });

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type !== 'ZOLM_BOOSTER_PAGE_SESSION_CHECK') {
      return false;
    }

    companionSession()
      .then((response) => sendResponse(response))
      .catch((error) => sendResponse({
        ok: false,
        message: error instanceof Error ? error.message : 'ZOLM panel oturumu kontrol edilemedi.',
      }));

    return true;
  });

  window.addEventListener('message', (event) => {
    if (event.source !== window || event.origin !== window.location.origin || event.data?.source !== PAGE_SOURCE) {
      return;
    }

    console.log('[ZOLM Extension Bridge] Received window message from page:', event.data);

    if (event.data.type === 'PING') {
      announceReady();
      return;
    }

    if (event.data.type === 'SESSION_CHECK') {
      handleSessionCheck(String(event.data.request_id || ''));
      return;
    }

    const config = QUERY_CONFIGS[event.data.type];
    if (!config) {
      return;
    }

    handleQuery(event.data, config);
  });

  async function handleSessionCheck(requestId) {
    try {
      const response = await companionSession();
      postResult(requestId, response, 'SESSION_CHECK_RESULT');
    } catch (error) {
      postResult(requestId, {
        ok: false,
        message: error instanceof Error ? error.message : 'ZOLM panel oturumu kontrol edilemedi.',
      }, 'SESSION_CHECK_RESULT');
    }
  }

  async function handleQuery(data, config) {
    const requestId = String(data.request_id || '');

    try {
      console.log('[ZOLM Extension Bridge] Forwarding message to background worker:', config.backgroundType);
      const backgroundResponse = await sendRuntimeMessage({
        type: config.backgroundType,
        source_url: String(data.source_url || ''),
        keyword: String(data.keyword || ''),
        keywords: Array.isArray(data.keywords) ? data.keywords : [],
        min_price: data.min_price ?? null,
        max_price: data.max_price ?? null,
        options: data.options || {},
      });

      if (!backgroundResponse?.ok) {
        postResult(requestId, backgroundResponse || {
          ok: false,
          message: 'Chrome Booster köprüsünden yanıt alınamadı.',
        }, config.resultType);
        return;
      }

      if (!config.companionAction) {
        postResult(requestId, backgroundResponse, config.resultType);
        return;
      }

      const payload = backgroundResponse.payload;
      if (!payload || typeof payload !== 'object') {
        throw new Error('Chrome Companion canlı veriyi okudu ancak ZOLM payload olusturulamadı.');
      }

      const companionResponse = await companionPost(config.companionAction, payload);
      postResult(requestId, mergeCompanionResponse(data.type, backgroundResponse, companionResponse), config.resultType);
    } catch (error) {
      console.error('[ZOLM Extension Bridge] Query failed:', error);
      postResult(requestId, {
        ok: false,
        message: error instanceof Error ? error.message : 'Chrome Booster köprüsü çalıştırılamadı.',
      }, config.resultType);
    }
  }

  function announceReady() {
    console.log('[ZOLM Extension Bridge] Announcing ready status to background worker...');
    try {
      sendRuntimeMessage({ type: 'ZOLM_BOOSTER_EXTENSION_PING' })
        .then((response) => {
          console.log('[ZOLM Extension Bridge] announceReady response from background:', response);
          if (response?.ok) {
            readyAnnounced = true;
            console.log('[ZOLM Extension Bridge] Sending READY message back to ZOLM page window.');
            window.postMessage({
              source: EXTENSION_SOURCE,
              type: 'READY',
              version: chrome.runtime.getManifest().version,
            }, window.location.origin);

            return;
          }

          if (!readyAnnounced) {
            postBridgeError(response?.message || 'Chrome Companion hazır sinyali alınamadı.');
          }
        })
        .catch((error) => {
          console.error('[ZOLM Extension Bridge] announceReady failed:', error);
          postBridgeError(error instanceof Error ? error.message : 'Chrome Companion arka plan servisi yanıt vermedi.');
        });
    } catch (error) {
      console.error('[ZOLM Extension Bridge] announceReady threw:', error);
      postBridgeError(error instanceof Error ? error.message : 'Chrome Companion köprüsü başlatılamadı.');
    }
  }

  function sendRuntimeMessage(message) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage(message, (response) => {
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message || 'Chrome Companion arka plan servisi çalışmıyor.'));
          return;
        }

        resolve(response);
      });
    });
  }

  async function companionSession() {
    return await companionGet('session');
  }

  async function companionGet(action) {
    const response = await fetch(`${COMPANION_PATH}/${actionPath(action)}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    return await readCompanionJson(response);
  }

  async function companionPost(action, payload) {
    const response = await fetch(`${COMPANION_PATH}/${actionPath(action)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    });

    return await readCompanionJson(response);
  }

  async function readCompanionJson(response) {
    const text = await response.text();
    let json = {};

    try {
      json = text ? JSON.parse(text) : {};
    } catch (error) {
      if (response.status === 401 || response.redirected || /<form|<!doctype html|<html/i.test(text)) {
        throw new Error('ZOLM panel oturumu doğrulanamadı. ZOLM sayfasını yenileyip tekrar deneyin.');
      }

      throw new Error(`ZOLM JSON yanıtı alınamadı (${response.status}).`);
    }

    if (response.status === 401) {
      throw new Error(json.message || 'ZOLM panel oturumu doğrulanamadı. ZOLM sayfasını yenileyip tekrar deneyin.');
    }

    if (response.status === 419) {
      throw new Error(json.message || 'ZOLM CSRF doğrulaması geçmedi. ZOLM sayfasını yenileyip tekrar deneyin.');
    }

    if (!response.ok || json.ok === false) {
      throw new Error(json.message || validationMessage(json) || 'ZOLM Booster isteği başarısız oldu.');
    }

    return json;
  }

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function validationMessage(json) {
    const errors = json?.errors || {};
    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : '';
  }

  function actionPath(action) {
    return {
      product_analysis: 'product-analysis',
      stock_check: 'stock-check',
      store_scan: 'store-scan',
      market_research: 'market-research',
      pending_jobs: 'pending-jobs',
      review_scan_start: 'review-scan/start',
      review_scan_ingest: 'review-scan/ingest',
      review_scan_status: 'review-scan/status',
      review_scan_verify: 'review-scan/verify',
    }[action] || action;
  }

  function mergeCompanionResponse(queryType, backgroundResponse, companionResponse) {
    const response = {
      ...companionResponse,
      payload: backgroundResponse.payload,
      source: 'browser_bridge',
    };

    if (queryType === 'SUPPLIER_RESEARCH_QUERY') {
      response.google_result_count = Array.isArray(backgroundResponse.payload?.offers)
        ? backgroundResponse.payload.offers.length
        : (backgroundResponse.google_result_count || 0);
    }

    return response;
  }

  function postBridgeError(message) {
    window.postMessage({
      source: EXTENSION_SOURCE,
      type: 'BRIDGE_ERROR',
      message,
    }, window.location.origin);
  }

  function postResult(requestId, response, type = 'STOCK_QUERY_RESULT') {
    window.postMessage({
      source: EXTENSION_SOURCE,
      type,
      request_id: requestId,
      response,
    }, window.location.origin);
  }
})();
