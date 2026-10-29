import { defineConfig, devices } from '@playwright/test'
import { resolve } from 'node:path'

process.env.PLAYWRIGHT_BROWSERS_PATH ??= resolve('node_modules/.cache/ms-playwright')

export default defineConfig({
  testDir: './e2e',
  testIgnore: '**/ux/**',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: 'list',
  use: {
    ...devices['Desktop Chrome'],
    baseURL: process.env.PARTSHELF_E2E_BASE_URL ?? 'http://127.0.0.1:5176',
    headless: true,
    serviceWorkers: 'block',
    trace: 'retain-on-failure',
  },
})
