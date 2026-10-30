const { defineConfig } = require('./node_modules/@playwright/test');
const { resolve } = require('node:path');
process.env.PLAYWRIGHT_BROWSERS_PATH = resolve('node_modules/.cache/ms-playwright');
module.exports = defineConfig({
  testDir: './e2e/simple', workers: 1, retries: 0, timeout: 120000,
  reporter: [['list'], ['json', { outputFile: '../.local/smart-stock/playwright-results.json' }]],
  outputDir: '../.local/smart-stock/playwright-output',
  use: { baseURL: 'http://127.0.0.1:5178', actionTimeout: 15000, headless: true, serviceWorkers: 'block', trace: 'off', video: 'off', screenshot: 'off' },
});
