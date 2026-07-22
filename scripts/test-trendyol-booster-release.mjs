import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';

const root = resolve(new URL('..', import.meta.url).pathname);
const packageResult = spawnSync(process.execPath, ['scripts/package-trendyol-booster-extension.mjs'], { cwd: root, stdio: 'inherit' });
if (packageResult.status !== 0) process.exit(packageResult.status || 1);

const manifestPath = resolve(root, 'build/trendyol-booster-companion/manifest.json');
const archivePath = resolve(root, 'build/trendyol-booster-companion.zip');
const checksumPath = `${archivePath}.sha256`;
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const failures = [];

if (manifest.host_permissions.some((host) => /localhost|127\.0\.0\.1|<all_urls>/i.test(host))) failures.push('development host permission leaked into store package');
if (!manifest.host_permissions.includes('https://m.zolm.com.tr/*')) failures.push('production ZOLM origin is missing');
if (!existsSync(archivePath)) failures.push('Chrome Web Store zip archive is missing');
if (!existsSync(checksumPath)) failures.push('Chrome Web Store zip checksum is missing');
if (existsSync(archivePath) && existsSync(checksumPath)) {
  const expected = readFileSync(checksumPath, 'utf8').trim().split(/\s+/)[0];
  const actual = createHash('sha256').update(readFileSync(archivePath)).digest('hex');
  if (expected !== actual) failures.push('Chrome Web Store zip checksum does not match archive');
}
if (manifest.version !== '1.0.0') failures.push('release manifest must be v1.0.0');
if (manifest.permissions.includes('camera') || manifest.permissions.includes('downloads') || manifest.permissions.includes('history')) failures.push('forbidden high-risk permission is present');
if (manifest.content_scripts.some((entry) => (entry.js || []).includes('zolm-bridge.js') && JSON.stringify(entry.matches) !== JSON.stringify(['https://m.zolm.com.tr/*']))) failures.push('ZOLM bridge is not production-scoped');

if (failures.length > 0) {
  console.error(`Release smoke failed:\n- ${failures.join('\n- ')}`);
  process.exit(1);
}

console.log(`Release smoke passed for v${manifest.version}.`);
