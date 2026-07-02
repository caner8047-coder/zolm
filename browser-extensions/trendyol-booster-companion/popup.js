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

document.getElementById('save').addEventListener('click', saveBaseUrl);
document.getElementById('test').addEventListener('click', testSession);

load();

async function load() {
  const stored = await chrome.storage.sync.get({ zolmBaseUrl: DEFAULT_BASE_URL });
  baseUrlInput.value = stored.zolmBaseUrl || DEFAULT_BASE_URL;
  refreshPageStatus();
}

async function saveBaseUrl() {
  const value = normalizeBaseUrl(baseUrlInput.value);
  await chrome.storage.sync.set({ zolmBaseUrl: value });
  baseUrlInput.value = value;
  await sendRuntimeMessage({ type: 'ZOLM_BOOSTER_WAKE_ZOLM_TABS' }, 6000).catch(() => null);
  setStatus('ZOLM adresi kaydedildi.', 'ok');
}

async function testSession() {
  setStatus('Oturum kontrol ediliyor...', '');
  const value = normalizeBaseUrl(baseUrlInput.value);
  await chrome.storage.sync.set({ zolmBaseUrl: value });
  baseUrlInput.value = value;

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
