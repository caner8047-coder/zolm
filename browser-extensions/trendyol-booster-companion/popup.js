const DEFAULT_BASE_URL = 'http://localhost';
const ZOLM_PANEL_TAB_PATTERNS = [
  'http://localhost/*',
  'https://localhost/*',
  'http://127.0.0.1/*',
  'https://127.0.0.1/*',
  'http://localhost:*/*',
  'https://localhost:*/*',
  'http://127.0.0.1:*/*',
  'https://127.0.0.1:*/*',
];
const baseUrlInput = document.getElementById('baseUrl');
const statusBox = document.getElementById('status');
const pageMeta = document.getElementById('pageMeta');
const pagePill = document.getElementById('pagePill');

const marginLowInput = document.getElementById('marginLow');
const marginHighInput = document.getElementById('marginHigh');
const serviceFeeFixedInput = document.getElementById('serviceFeeFixed');
const withholdingTaxEnabledInput = document.getElementById('withholdingTaxEnabled');

document.getElementById('save').addEventListener('click', saveSettings);
document.getElementById('test').addEventListener('click', testSession);

load();

async function load() {
  const stored = await chrome.storage.sync.get({
    zolmBaseUrl: DEFAULT_BASE_URL,
    marginLow: 5.0,
    marginHigh: 20.0,
    serviceFeeFixed: 9.33,
    withholdingTaxEnabled: false
  });
  baseUrlInput.value = stored.zolmBaseUrl || DEFAULT_BASE_URL;
  marginLowInput.value = stored.marginLow;
  marginHighInput.value = stored.marginHigh;
  serviceFeeFixedInput.value = stored.serviceFeeFixed;
  withholdingTaxEnabledInput.checked = Boolean(stored.withholdingTaxEnabled);
  refreshPageStatus();
}

async function saveSettings() {
  const value = normalizeBaseUrl(baseUrlInput.value);
  const lowVal = parseFloat(marginLowInput.value) || 5.0;
  const highVal = parseFloat(marginHighInput.value) || 20.0;
  const serviceFeeVal = Math.max(0, parseFloat(serviceFeeFixedInput.value) || 0);
  const taxEnabled = withholdingTaxEnabledInput.checked;

  await chrome.storage.sync.set({
    zolmBaseUrl: value,
    marginLow: lowVal,
    marginHigh: highVal,
    serviceFeeFixed: serviceFeeVal,
    withholdingTaxEnabled: taxEnabled
  });

  baseUrlInput.value = value;
  marginLowInput.value = lowVal;
  marginHighInput.value = highVal;
  serviceFeeFixedInput.value = serviceFeeVal;
  withholdingTaxEnabledInput.checked = taxEnabled;

  // Değişikliği bildirmek için aktif sayfalara mesaj gönderebiliriz
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (tab?.id) {
    chrome.tabs.sendMessage(tab.id, { type: 'ZOLM_SETTINGS_CHANGED' }).catch(() => null);
  }

  await sendRuntimeMessage({ type: 'ZOLM_BOOSTER_WAKE_ZOLM_TABS' }, 6000).catch(() => null);
  setStatus('Ayarlar kaydedildi.', 'ok');
}

async function testSession() {
  setStatus('Oturum kontrol ediliyor...', '');
  const value = normalizeBaseUrl(baseUrlInput.value);
  const lowVal = parseFloat(marginLowInput.value) || 5.0;
  const highVal = parseFloat(marginHighInput.value) || 20.0;
  const serviceFeeVal = Math.max(0, parseFloat(serviceFeeFixedInput.value) || 0);
  const taxEnabled = withholdingTaxEnabledInput.checked;
  await chrome.storage.sync.set({
    zolmBaseUrl: value,
    marginLow: lowVal,
    marginHigh: highVal,
    serviceFeeFixed: serviceFeeVal,
    withholdingTaxEnabled: taxEnabled
  });
  baseUrlInput.value = value;
  marginLowInput.value = lowVal;
  marginHighInput.value = highVal;
  serviceFeeFixedInput.value = serviceFeeVal;
  withholdingTaxEnabledInput.checked = taxEnabled;

  try {
    const panelTab = await findZolmPanelTab();
    const response = panelTab?.id
      ? await testPanelSession(panelTab.id)
      : await sendRuntimeMessage({ type: 'ZOLM_BOOSTER_SESSION' }, 12000);

    if (!response?.ok) {
      setStatus(response?.message || 'ZOLM oturumu bulunamadı.', 'err');
      return;
    }

    setStatus(`${response.user?.email || 'ZOLM'} oturumu aktif.`, 'ok');
  } catch (error) {
    setStatus(error instanceof Error ? error.message : 'Oturum kontrolü tamamlanamadı.', 'err');
  }
}

async function findZolmPanelTab() {
  const [activeTab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (isZolmPanelUrl(activeTab?.url)) {
    return activeTab;
  }

  const tabs = await chrome.tabs.query({ url: ZOLM_PANEL_TAB_PATTERNS });
  return tabs.find((tab) => isZolmPanelUrl(tab.url)) || null;
}

function isZolmPanelUrl(url) {
  return /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/marketplace-trendyol-booster/i.test(String(url || ''));
}

async function testPanelSession(tabId) {
  await chrome.scripting.executeScript({
    target: { tabId },
    files: ['zolm-bridge.js'],
  }).catch(() => null);

  return await sendTabMessage(tabId, { type: 'ZOLM_BOOSTER_PAGE_SESSION_CHECK' }, 12000);
}

function normalizeBaseUrl(value) {
  let url = String(value || DEFAULT_BASE_URL).trim().replace(/\/+$/, '');
  url = url.replace(/:+$/, '');

  if (url && !/^https?:\/\//i.test(url)) {
    url = `http://${url}`;
  }

  return url || DEFAULT_BASE_URL;
}

function setStatus(message, type) {
  statusBox.textContent = message;
  statusBox.className = `status ${type || ''}`.trim();
}

async function refreshPageStatus() {
  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    const url = String(tab?.url || '');

    if (/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/marketplace-trendyol-booster/i.test(url)) {
      setPageStatus('ZOLM Booster paneli açık. Oturumu buradan test edebilirsiniz.', 'ZOLM paneli', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\/pricing\//i.test(url)) {
      setPageStatus('Seller Panel fiyatlandırma sayfası algılandı. ZOLM karlılık kartları otomatik gösterilir.', 'Seller Panel', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\/promotions\/campaigns\/details\/[^/]+\/(?:add-new-products|campaign-products)/i.test(url)) {
      setPageStatus('Trendyol kampanya sayfası algılandı. Mevcut ve kampanya fiyatı karlılık kartları gösterilir.', 'Kampanya', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\/orders\/shipment-packages\//i.test(url)) {
      setPageStatus('Trendyol sipariş sayfası algılandı. Satırlarda ZOLM kârlılık kartları gösterilir.', 'Sipariş Kârı', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\//i.test(url)) {
      setPageStatus('Trendyol Seller Panel. Fiyatlandırma, kampanya ve sipariş sayfalarında kârlılık kartları gösterilir.', 'Seller Panel', true);
      return;
    }

    if (!/https:\/\/([^/]+\.)?trendyol\.com\//i.test(url)) {
      setPageStatus('Aktif sekme Trendyol ürün veya mağaza sayfası değil.', 'Trendyol değil', false);
      return;
    }

    chrome.tabs.sendMessage(tab.id, { type: 'ZOLM_BOOSTER_PAGE_STATUS' }, (response) => {
      if (chrome.runtime.lastError || !response?.ok) {
        setPageStatus('Sayfa verisi henüz okunamadı. Trendyol sayfasını yenileyip tekrar açın.', 'Okunamadı', false);
        return;
      }

      const summary = response.summary || {};
      const contextLabel = response.context === 'store' ? 'Mağaza' : 'Ürün';
      setPageStatus(summary.message || 'Sayfa ZOLM Booster için hazır.', contextLabel, Boolean(summary.ready));
    });
  } catch (error) {
    setPageStatus('Aktif sekme kontrol edilemedi.', 'Hata', false);
  }
}

function setPageStatus(message, label, ready) {
  pageMeta.textContent = message;
  pagePill.textContent = label;
  pagePill.className = ready ? 'pill' : 'pill warn';
}

function sendRuntimeMessage(message, timeoutMs = 10000) {
  return new Promise((resolve, reject) => {
    let settled = false;
    const timer = window.setTimeout(() => {
      if (settled) return;
      settled = true;
      reject(new Error('Chrome Companion yanıt vermedi. Eklentiyi chrome://extensions ekranından yeniden yükleyin.'));
    }, timeoutMs);

    chrome.runtime.sendMessage(message, (response) => {
      if (settled) return;
      settled = true;
      window.clearTimeout(timer);

      if (chrome.runtime.lastError) {
        reject(new Error(chrome.runtime.lastError.message || 'Chrome Companion arka plan servisi çalışmıyor.'));
        return;
      }

      resolve(response);
    });
  });
}

function sendTabMessage(tabId, message, timeoutMs = 10000) {
  return new Promise((resolve, reject) => {
    let settled = false;
    const timer = window.setTimeout(() => {
      if (settled) return;
      settled = true;
      reject(new Error('ZOLM panel köprüsü yanıt vermedi. Panel sayfasını yenileyip eklentiyi yeniden test edin.'));
    }, timeoutMs);

    chrome.tabs.sendMessage(tabId, message, (response) => {
      if (settled) return;
      settled = true;
      window.clearTimeout(timer);

      if (chrome.runtime.lastError) {
        reject(new Error(chrome.runtime.lastError.message || 'ZOLM panel köprüsü çalışmıyor.'));
        return;
      }

      resolve(response);
    });
  });
}
