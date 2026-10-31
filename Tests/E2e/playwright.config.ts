import { defineConfig, devices } from '@playwright/test'

// The ddev project locally; CI sets TYPO3_BASE_URL
const baseURL = process.env['TYPO3_BASE_URL'] ?? 'https://link-alchemy.ddev.site'

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  workers: 1,
  forbidOnly: process.env['CI'] !== undefined,
  retries: process.env['CI'] === undefined ? 0 : 2,
  reporter: 'list',

  use: {
    baseURL,
    // ddev uses a self-signed certificate
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],

  outputDir: 'test-results/',
})
