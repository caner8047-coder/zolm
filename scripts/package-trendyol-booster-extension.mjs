import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const rootDir = resolve(new URL('..', import.meta.url).pathname);
const extensionDir = join(rootDir, 'browser-extensions', 'trendyol-booster-companion');
const outputDir = join(rootDir, 'build', 'trendyol-booster-companion');
const archivePath = join(rootDir, 'build', 'trendyol-booster-companion.zip');
const checkOnly = process.argv.includes('--check');

const requiredFiles = [
  'manifest.json',
  'background.js',
  'content.js',
  'zolm-bridge.js',
  'popup.html',
  'popup.js',
  'README.md',
  'trendyol-category-dictionary.json',
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

const manifest = JSON.parse(readFileSync(join(extensionDir, 'manifest.json'), 'utf8'));

if (manifest.manifest_version !== 3) {
  fail('manifest_version must be 3.');
}

if (!/^\d+\.\d+\.\d+$/.test(String(manifest.version || ''))) {
  fail('manifest version must use x.y.z format.');
}

for (const permission of ['storage', 'activeTab', 'tabs']) {
  if (!manifest.permissions?.includes(permission)) {
    fail(`permission ${permission} is required.`);
  }
}

for (const host of ['http://localhost/*', 'https://www.trendyol.com/*']) {
  if (!manifest.host_permissions?.includes(host)) {
    fail(`host permission ${host} is required.`);
  }
}

for (const file of ['background.js', 'content.js', 'zolm-bridge.js', 'popup.js']) {
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
  copyFileSync(join(extensionDir, file), join(outputDir, file));
}

rmSync(archivePath, { force: true });

const zip = spawnSync('zip', ['-qr', archivePath, '.'], {
  cwd: outputDir,
  stdio: 'ignore',
});

console.log(`Extension package copied to ${outputDir}`);

if (zip.status === 0) {
  console.log(`Extension archive created at ${archivePath}`);
} else {
  console.log('zip command not available; unpacked extension package is ready.');
}

console.log(`Load unpacked: ${outputDir}`);
