const DEFAULT_BASE_URL = 'https://m.zolm.com.tr';
const ZOLM_PANEL_TAB_PATTERNS = [
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
const baseUrlInput = document.getElementById('baseUrl');
const statusBox = document.getElementById('status');
const pageMeta = document.getElementById('pageMeta');
const pagePill = document.getElementById('pagePill');
const connectionPill = document.getElementById('connectionPill');
const researchModeButton = document.getElementById('researchMode');
const sellerModeButton = document.getElementById('sellerMode');
const researchGuide = document.getElementById('researchGuide');
const sellerGuide = document.getElementById('sellerGuide');
const sellerSettings = document.getElementById('sellerSettings');
const researchQuickSearch = document.getElementById('researchQuickSearch');
const discoveryQueryInput = document.getElementById('discoveryQuery');
const discoveryStatus = document.getElementById('discoveryStatus');
const discoveryRecent = document.getElementById('discoveryRecent');

const marginLowInput = document.getElementById('marginLow');
const marginHighInput = document.getElementById('marginHigh');
const serviceFeeFixedInput = document.getElementById('serviceFeeFixed');
const withholdingTaxEnabledInput = document.getElementById('withholdingTaxEnabled');

document.getElementById('save').addEventListener('click', saveSettings);
document.getElementById('test').addEventListener('click', testSession);
document.getElementById('openPanel').addEventListener('click', openPanel);
document.getElementById('discoverySearch').addEventListener('click', runDiscoverySearch);
discoveryQueryInput.addEventListener('keydown', (event) => {
  if (event.key === 'Enter') runDiscoverySearch();
});
researchModeButton.addEventListener('click', () => setMode('research'));
sellerModeButton.addEventListener('click', () => setMode('seller'));

load();

async function load() {
  const stored = await chrome.storage.sync.get({
    zolmBaseUrl: DEFAULT_BASE_URL,
    marginLow: 5.0,
    marginHigh: 20.0,
    serviceFeeFixed: 9.33,
    withholdingTaxEnabled: true,
    boosterMode: 'research',
  });
  baseUrlInput.value = stored.zolmBaseUrl || DEFAULT_BASE_URL;
  marginLowInput.value = stored.marginLow;
  marginHighInput.value = stored.marginHigh;
  serviceFeeFixedInput.value = stored.serviceFeeFixed;
  withholdingTaxEnabledInput.checked = Boolean(stored.withholdingTaxEnabled);
  setMode(stored.boosterMode === 'seller' ? 'seller' : 'research', false);
  await loadRecentDiscoveries();
  refreshPageStatus();
}

async function setMode(mode, persist = true) {
  const isSeller = mode === 'seller';
  researchModeButton.classList.toggle('is-active', !isSeller);
  researchModeButton.setAttribute('aria-selected', String(!isSeller));
  sellerModeButton.classList.toggle('is-active', isSeller);
  sellerModeButton.setAttribute('aria-selected', String(isSeller));
  researchGuide.classList.toggle('hidden', isSeller);
  sellerGuide.classList.toggle('hidden', !isSeller);
  sellerSettings.classList.toggle('hidden', !isSeller);
  researchQuickSearch.classList.toggle('hidden', isSeller);

  if (persist) {
    await chrome.storage.sync.set({ boosterMode: isSeller ? 'seller' : 'research' });
  }
}

async function runDiscoverySearch() {
  const rawQuery = String(discoveryQueryInput.value || '').trim();

  try {
    const target = ZolmDiscovery.target(rawQuery);
    discoveryStatus.textContent = `${target.label} Trendyol’da açılıyor...`;
    discoveryStatus.className = 'inline-status';
    await chrome.tabs.create({ url: target.url });
    await saveRecentDiscovery(rawQuery);
    discoveryStatus.textContent = `${target.label} açıldı. Sonuç sayfasında ZOLM Discovery panelini kullanın.`;
  } catch (error) {
    discoveryStatus.textContent = error instanceof Error ? error.message : 'Arama başlatılamadı.';
    discoveryStatus.className = 'inline-status err';
  }
}

async function loadRecentDiscoveries() {
  const stored = await chrome.storage.local.get({ discoveryRecentQueries: [] });
  renderRecentDiscoveries(Array.isArray(stored.discoveryRecentQueries) ? stored.discoveryRecentQueries : []);
}

async function saveRecentDiscovery(query) {
  const stored = await chrome.storage.local.get({ discoveryRecentQueries: [] });
  const recent = [String(query).slice(0, 180), ...(Array.isArray(stored.discoveryRecentQueries) ? stored.discoveryRecentQueries : [])]
    .filter(Boolean)
    .filter((value, index, values) => values.indexOf(value) === index)
    .slice(0, 5);
  await chrome.storage.local.set({ discoveryRecentQueries: recent });
  renderRecentDiscoveries(recent);
}

function renderRecentDiscoveries(queries) {
  const buttons = queries.map((query) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = query;
    button.title = query;
    button.addEventListener('click', () => {
      discoveryQueryInput.value = query;
      runDiscoverySearch();
    });
    return button;
  });

  if (queries.length > 0) {
    const clearButton = document.createElement('button');
    clearButton.type = 'button';
    clearButton.textContent = 'Geçmişi temizle';
    clearButton.addEventListener('click', async () => {
      await chrome.storage.local.remove('discoveryRecentQueries');
      renderRecentDiscoveries([]);
      discoveryStatus.textContent = 'Son aramalar temizlendi.';
      discoveryStatus.className = 'inline-status';
    });
    buttons.push(clearButton);
  }

  discoveryRecent.replaceChildren(...buttons);
  discoveryRecent.classList.toggle('hidden', queries.length === 0);
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
      setConnectionStatus('Oturum bulunamadı', false);
      return;
    }

    setStatus(`${response.user?.email || 'ZOLM'} oturumu aktif.`, 'ok');
    setConnectionStatus('Oturum aktif', true);
  } catch (error) {
    setStatus(error instanceof Error ? error.message : 'Oturum kontrolü tamamlanamadı.', 'err');
    setConnectionStatus('Bağlantı hatası', false);
  }
}

async function openPanel() {
  const baseUrl = normalizeBaseUrl(baseUrlInput.value);
  await chrome.storage.sync.set({ zolmBaseUrl: baseUrl });
  baseUrlInput.value = baseUrl;

  try {
    await chrome.tabs.create({ url: `${baseUrl}/marketplace-trendyol-booster` });
    setStatus('ZOLM Booster paneli yeni sekmede açıldı. Oturumunuzu doğrulayın.', '');
  } catch (error) {
    setStatus(error instanceof Error ? error.message : 'ZOLM paneli açılamadı.', 'err');
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
  return /^https:\/\/m\.zolm\.com\.tr\/marketplace-trendyol-booster/i.test(String(url || ''))
    || /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/marketplace-trendyol-booster/i.test(String(url || ''));
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

function setConnectionStatus(label, ready) {
  connectionPill.textContent = label;
  connectionPill.className = ready ? 'pill' : 'pill warn';
}

async function refreshPageStatus() {
  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    const url = String(tab?.url || '');

    if (isZolmPanelUrl(url)) {
      setMode('research', false);
      setPageStatus('ZOLM Booster paneli açık. Oturumu buradan test edebilirsiniz.', 'ZOLM paneli', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\/pricing\//i.test(url)) {
      setMode('seller', false);
      setPageStatus('Seller Panel fiyatlandırma sayfası algılandı. ZOLM karlılık kartları otomatik gösterilir.', 'Seller Panel', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\/promotions\/campaigns\/details\/[^/]+\/(?:add-new-products|campaign-products)/i.test(url)) {
      setMode('seller', false);
      setPageStatus('Trendyol kampanya sayfası algılandı. Mevcut ve kampanya fiyatı karlılık kartları gösterilir.', 'Kampanya', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\/orders\/shipment-packages\//i.test(url)) {
      setMode('seller', false);
      setPageStatus('Trendyol sipariş sayfası algılandı. Satırlarda ZOLM kârlılık kartları gösterilir.', 'Sipariş Kârı', true);
      return;
    }

    if (/^https:\/\/partner\.trendyol\.com\//i.test(url)) {
      setMode('seller', false);
      setPageStatus('Trendyol Seller Panel. Fiyatlandırma, kampanya ve sipariş sayfalarında kârlılık kartları gösterilir.', 'Seller Panel', true);
      return;
    }

    if (!/https:\/\/([^/]+\.)?trendyol\.com\//i.test(url)) {
      setPageStatus('Aktif sekme Trendyol liste, ürün veya mağaza sayfası değil.', 'Trendyol değil', false);
      return;
    }

    chrome.tabs.sendMessage(tab.id, { type: 'ZOLM_BOOSTER_PAGE_STATUS' }, (response) => {
      if (chrome.runtime.lastError || !response?.ok) {
        setPageStatus('Sayfa verisi henüz okunamadı. Trendyol sayfasını yenileyip tekrar açın.', 'Okunamadı', false);
        return;
      }

      const summary = response.summary || {};
      const contextLabel = response.context === 'listing'
        ? 'Liste Araştırması'
        : (response.context === 'store' ? 'Mağaza' : 'Ürün');
      setMode('research', false);
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
