import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';

const rootDir = resolve(new URL('..', import.meta.url).pathname);
const extensionDir = join(rootDir, 'browser-extensions', 'trendyol-booster-companion');
const outputDir = join(rootDir, 'build', 'trendyol-booster-companion');
const archivePath = join(rootDir, 'build', 'trendyol-booster-companion.zip');
const checksumPath = `${archivePath}.sha256`;
const checkOnly = process.argv.includes('--check');
const productionZolmOrigin = 'https://m.zolm.com.tr';

const requiredFiles = [
  'manifest.json',
  'background.js',
  'content.js',
  'seller-panel.js',
  'campaign-panel.js',
  'orders-panel.js',
  'seller-profit-overlay.js',
  'zolm-bridge.js',
  'popup.html',
  'discovery.js',
  'popup.js',
  'README.md',
  'PRIVACY.md',
  'STORE_LISTING_TR.md',
  'DATA_USE_DECLARATION_TR.md',
  'RELEASE_CHECKLIST.md',
  'trendyol-category-dictionary.json',
  'icons/icon-16.png',
  'icons/icon-32.png',
  'icons/icon-48.png',
  'icons/icon-128.png',
];

const requiredStoreAssets = [
  'store-assets/showcase.html',
  'store-assets/README.md',
];

function fail(message) {
  console.error(`Extension package check failed: ${message}`);
  process.exit(1);
}

for (const file of requiredFiles) {
  if (!existsSync(join(extensionDir, file))) {
    fail(`${file} is missing.`);
  }
}

for (const file of requiredStoreAssets) {
  if (!existsSync(join(extensionDir, file))) {
    fail(`${file} is missing.`);
  }
}

const showcase = readFileSync(join(extensionDir, 'store-assets/showcase.html'), 'utf8');
for (const marker of ['width:1280px;height:800px', 'id="analysis"', 'id="tracking"', 'id="seller"', 'Temsili ürün verileriyle']) {
  if (!showcase.includes(marker)) {
    fail(`store showcase is missing marker: ${marker}.`);
  }
}

function productionManifest(source) {
  const localPattern = /^(?:https?:\/\/(?:localhost|127\.0\.0\.1)(?::\*)?\/\*)$/i;
  const clone = JSON.parse(JSON.stringify(source));
  clone.host_permissions = (clone.host_permissions || []).filter((host) => !localPattern.test(host));
  if (!clone.host_permissions.includes(`${productionZolmOrigin}/*`)) {
    clone.host_permissions.unshift(`${productionZolmOrigin}/*`);
  }
  clone.content_scripts = (clone.content_scripts || []).map((entry) => {
    if (!(entry.js || []).includes('zolm-bridge.js')) return entry;
    return { ...entry, matches: [`${productionZolmOrigin}/*`] };
  });
  return clone;
}

const manifest = JSON.parse(readFileSync(join(extensionDir, 'manifest.json'), 'utf8'));
const storeManifest = productionManifest(manifest);
const readme = readFileSync(join(extensionDir, 'README.md'), 'utf8');
const popup = readFileSync(join(extensionDir, 'popup.html'), 'utf8');
const discoveryScript = readFileSync(join(extensionDir, 'discovery.js'), 'utf8');
const popupScript = readFileSync(join(extensionDir, 'popup.js'), 'utf8');
const contentScript = readFileSync(join(extensionDir, 'content.js'), 'utf8');
const backgroundScript = readFileSync(join(extensionDir, 'background.js'), 'utf8');
const storeListing = readFileSync(join(extensionDir, 'STORE_LISTING_TR.md'), 'utf8');

if (manifest.manifest_version !== 3) {
  fail('manifest_version must be 3.');
}

if (!/^\d+\.\d+\.\d+$/.test(String(manifest.version || ''))) {
  fail('manifest version must use x.y.z format.');
}

if (!readme.includes(`\`${manifest.version}\``)) {
  fail('README.md must mention the current manifest version.');
}

if (!popup.includes(`v${manifest.version}`)) {
  fail('popup.html must display the current manifest version.');
}

for (const marker of ['id="discoveryQuery"', 'id="discoverySearch"', 'Ek izin yok']) {
  if (!popup.includes(marker)) {
    fail(`popup.html is missing the quick discovery marker: ${marker}.`);
  }
}

for (const marker of ['ZolmDiscovery.target', "chrome.storage.local.remove('discoveryRecentQueries')", 'Sonuç sayfasında ZOLM Discovery panelini kullanın']) {
  if (!popupScript.includes(marker)) {
    fail(`popup.js is missing the quick discovery marker: ${marker}.`);
  }
}

for (const marker of ['function target', 'Barkod araması', 'Yalnız Trendyol veya ty.gl']) {
  if (!discoveryScript.includes(marker)) {
    fail(`discovery.js is missing the quick discovery marker: ${marker}.`);
  }
}

const discovery = Function(`
  const globalThis = {};
  ${discoveryScript}
  return globalThis.ZolmDiscovery;
`)();

const barcodeTarget = discovery.target('8681234567890');
if (barcodeTarget.label !== 'Barkod araması' || !barcodeTarget.url.includes('q=8681234567890')) {
  fail('discovery.js must turn a barcode into a Trendyol barcode search.');
}

const productTarget = discovery.target('https://www.trendyol.com/zolm/ornek-urun-p-123456');
if (productTarget.label !== 'Ürün bağlantısı' || productTarget.url !== 'https://www.trendyol.com/zolm/ornek-urun-p-123456') {
  fail('discovery.js must preserve valid Trendyol product URLs.');
}

try {
  discovery.target('https://example.com/urun/123');
  fail('discovery.js must reject non-Trendyol URLs.');
} catch (error) {
  if (!String(error?.message || '').includes('Yalnız Trendyol veya ty.gl')) {
    throw error;
  }
}

for (const marker of ['context === \'listing\'', 'function extractListingProducts', 'Listeyi raporla', 'Karar merkezine al', 'Fırsatları tara', 'Toplu karar kuyruğu', 'Kuyruğu temizle', 'renderListingOpportunities', 'renderListingDecisionSummary', 'Maliyet gerekli', 'Ürün medya merkezi', 'downloadSelectedProductMedia', 'ZOLM_BOOSTER_CAPTURE_LISTING', 'ZOLM_BOOSTER_COMPARE_LISTING', 'ZOLM_BOOSTER_DECIDE_LISTING_PRODUCT', 'ZOLM_BOOSTER_SCAN_LISTING_OPPORTUNITIES', 'ZOLM_BOOSTER_START_DECISION_QUEUE', 'ZOLM_BOOSTER_TRACK_LISTING_SELECTION']) {
  if (!contentScript.includes(marker)) {
    fail(`content.js is missing the list research marker: ${marker}.`);
  }
}

for (const marker of ["message.type === 'ZOLM_BOOSTER_OPEN_DASHBOARD'", "message.type === 'ZOLM_BOOSTER_DOWNLOAD_MEDIA'", "message.type === 'ZOLM_BOOSTER_CAPTURE_LISTING'", "message.type === 'ZOLM_BOOSTER_SCAN_LISTING_OPPORTUNITIES'", "message.type === 'ZOLM_BOOSTER_START_DECISION_QUEUE'", "message.type === 'ZOLM_BOOSTER_CLEAR_DECISION_QUEUE'", 'processDecisionQueue', "message.type === 'ZOLM_BOOSTER_COMPARE_LISTING'", "message.type === 'ZOLM_BOOSTER_DECIDE_LISTING_PRODUCT'", "message.type === 'ZOLM_BOOSTER_TRACK_LISTING_SELECTION'", "companionPost('opportunity_scan'", "bestseller_capture: 'bestseller-capture'", "url.searchParams.set('compare_now', '1')", "url.searchParams.set('decision_product'", "buffer.byteLength > 15 * 1024 * 1024"]) {
  if (!backgroundScript.includes(marker)) {
    fail(`background.js is missing the list capture marker: ${marker}.`);
  }
}

if (!storeListing.includes('Arama, kategori ve Çok Satanlar')) {
  fail('STORE_LISTING_TR.md must describe list research capability.');
}

for (const permission of ['storage', 'activeTab', 'tabs']) {
  if (!manifest.permissions?.includes(permission)) {
    fail(`permission ${permission} is required.`);
  }
}

const forbiddenPermissions = ['camera', 'history', 'cookies', 'downloads', 'webRequest', 'webRequestBlocking', '<all_urls>'];
for (const permission of forbiddenPermissions) {
  if (manifest.permissions?.includes(permission) || manifest.host_permissions?.includes(permission)) {
    fail(`high-risk permission ${permission} is not allowed without a security review.`);
  }
}

for (const [size, iconPath] of Object.entries(manifest.icons || {})) {
  if (!['16', '32', '48', '128'].includes(size) || !requiredFiles.includes(iconPath)) {
    fail(`manifest icon ${size}:${iconPath} is not part of the verified store package.`);
  }

  const icon = readFileSync(join(extensionDir, iconPath));
  const isPng = icon.length >= 24 && icon.subarray(1, 4).toString('ascii') === 'PNG';
  const width = isPng ? icon.readUInt32BE(16) : 0;
  const height = isPng ? icon.readUInt32BE(20) : 0;
  if (!isPng || width !== Number(size) || height !== Number(size)) {
    fail(`${iconPath} must be a ${size}x${size} PNG.`);
  }
}

if (!manifest.action?.default_icon?.['16'] || !manifest.action?.default_icon?.['32']) {
  fail('action.default_icon must include 16px and 32px icons.');
}

const privacy = readFileSync(join(extensionDir, 'PRIVACY.md'), 'utf8');
for (const heading of ['## Toplanan veriler', '## Verilerin kullanım amacı', '## Paylaşım ve satış', '## Silme ve iletişim']) {
  if (!privacy.includes(heading)) {
    fail(`PRIVACY.md must include ${heading}.`);
  }
}

for (const host of ['http://localhost/*', 'https://m.zolm.com.tr/*', 'https://www.trendyol.com/*', 'https://*.dsmcdn.com/*']) {
  if (!manifest.host_permissions?.includes(host)) {
    fail(`host permission ${host} is required.`);
  }
}

if (!storeManifest.host_permissions.includes(`${productionZolmOrigin}/*`)
  || storeManifest.host_permissions.some((host) => /localhost|127\.0\.0\.1/i.test(host))) {
  fail('production manifest must include only the production ZOLM bridge origin.');
}

const productionBridge = storeManifest.content_scripts.find((entry) => (entry.js || []).includes('zolm-bridge.js'));
if (JSON.stringify(productionBridge?.matches) !== JSON.stringify([`${productionZolmOrigin}/*`])) {
  fail('production bridge content script must be restricted to m.zolm.com.tr.');
}

for (const file of ['background.js', 'content.js', 'seller-panel.js', 'campaign-panel.js', 'orders-panel.js', 'seller-profit-overlay.js', 'zolm-bridge.js', 'discovery.js', 'popup.js']) {
  const result = spawnSync(process.execPath, ['--check', join(extensionDir, file)], {
    stdio: 'inherit',
  });

  if (result.status !== 0) {
    fail(`${file} has a JavaScript syntax error.`);
  }
}

if (checkOnly) {
  console.log('Extension package check passed.');
  process.exit(0);
}

rmSync(outputDir, { recursive: true, force: true });
mkdirSync(outputDir, { recursive: true });

for (const file of requiredFiles) {
  const targetPath = join(outputDir, file);
  mkdirSync(dirname(targetPath), { recursive: true });
  copyFileSync(join(extensionDir, file), targetPath);
}

writeFileSync(join(outputDir, 'manifest.json'), `${JSON.stringify(storeManifest, null, 2)}\n`, 'utf8');

rmSync(archivePath, { force: true });
rmSync(checksumPath, { force: true });

const zip = spawnSync('zip', ['-qr', archivePath, '.'], {
  cwd: outputDir,
  stdio: 'ignore',
});

console.log(`Extension package copied to ${outputDir}`);

if (zip.status === 0) {
  const checksum = createHash('sha256').update(readFileSync(archivePath)).digest('hex');
  writeFileSync(checksumPath, `${checksum}  trendyol-booster-companion.zip\n`, 'utf8');
  console.log(`Extension archive created at ${archivePath}`);
  console.log(`Extension checksum created at ${checksumPath}`);
} else {
  console.log('zip command not available; unpacked extension package is ready.');
}

console.log(`Load unpacked: ${outputDir}`);
