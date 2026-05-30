import { chromium } from '@playwright/test';

const url = process.argv[2] || 'http://127.0.0.1:5176/';
const executablePath = process.argv[3];
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const canvasPath = `home-server-three-${stamp}.png`;
const pagePath = `home-page-three-${stamp}.png`;
const browser = await chromium.launch({
  headless: true,
  executablePath,
});
const page = await browser.newPage({ viewport: { width: 1440, height: 920 }, deviceScaleFactor: 1 });

const messages = [];
page.on('console', (message) => messages.push(`${message.type()}: ${message.text()}`));
page.on('pageerror', (error) => messages.push(`pageerror: ${error.message}`));

await page.goto(url, { waitUntil: 'networkidle' });
await page.waitForTimeout(1000);

const canvas = page.locator('.home-server-three__canvas').first();
await canvas.waitFor({ state: 'visible', timeout: 15000 });
await canvas.screenshot({ path: canvasPath });
await page.screenshot({ path: pagePath, fullPage: true });

const stats = await canvas.evaluate((node) => {
  const rect = node.getBoundingClientRect();
  const probe = document.createElement('canvas');
  probe.width = node.width;
  probe.height = node.height;
  const context = probe.getContext('2d');
  context.drawImage(node, 0, 0);
  const sample = context.getImageData(0, 0, probe.width, probe.height).data;
  let nonBlank = 0;
  let blueish = 0;
  for (let index = 0; index < sample.length; index += 4) {
    const r = sample[index];
    const g = sample[index + 1];
    const b = sample[index + 2];
    const a = sample[index + 3];
    if (a > 0 && (r < 248 || g < 248 || b < 248)) nonBlank += 1;
    if (b > r + 24 && b > g + 10) blueish += 1;
  }
  return {
    cssWidth: Math.round(rect.width),
    cssHeight: Math.round(rect.height),
    backingWidth: node.width,
    backingHeight: node.height,
    nonBlank,
    blueish,
  };
});

console.log(JSON.stringify({ url, canvasPath, pagePath, stats, messages: messages.slice(-20) }, null, 2));
await browser.close();
