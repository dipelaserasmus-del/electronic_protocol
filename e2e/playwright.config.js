// Browser (end-to-end) tests against the running dev stack.
//   ./dev.sh e2e                       run all
//   ./dev.sh e2e --grep @smoke         only the smoke tests
//   EVIDENCE=B04 ./dev.sh e2e          also save screenshots to docs/reports/img/B04/
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 60_000,
  retries: process.env.CI ? 1 : 0,
  workers: 1,                          // tests share one demo database
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env.APP_URL || 'http://localhost:8080',
    viewport: { width: 1440, height: 900 },
    locale: 'el-GR',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    // Claude Code cloud sandbox: use the preinstalled Chromium
    launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
  },
});
