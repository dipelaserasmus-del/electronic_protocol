// B10: Ρυθμίσεις → Backup must create a complete dump (before: empty .gz file + "success" message).
const { test, expect } = require('@playwright/test');
const zlib = require('zlib');
const fs = require('fs');
const { login, evidence, toasts } = require('./helpers');

test('backup: creates a complete dump that downloads and can be deleted', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/backups');

  await Promise.all([page.waitForURL(/\/backups/), page.click('a[href$="/backup"]')]);
  await page.locator('.toast-message').first().waitFor();
  const message = await toasts(page);
  expect(message).toContain('Επιτυχημένη δημιουργία αρχείου backup');
  const name = message.match(/(\S+_back_\d{14}\.sql\.gz)/)[1];
  await page.waitForFunction(() => !window.jQuery || window.jQuery(':animated').length === 0);   // toastr fade-in done
  await evidence(page, 'backup-created');

  const link = page.locator(`a[href$="/downloadBackup/${name}"]`);
  await expect(link).toBeVisible();
  const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
  const sql = zlib.gunzipSync(fs.readFileSync(await download.path())).toString('utf8');
  expect(sql).toContain('CREATE TABLE `protocols`');
  expect(sql).toMatch(/-- Dump completed/);

  // names outside the backup pattern are refused (path traversal, R6c): `../backups/dummy` is a real file
  // (nginx already rejects `../../…` with 400 before PHP; this one reaches the app)
  expect((await page.request.get('/downloadBackup/..%2Fbackups%2Fdummy')).status()).toBe(404);

  await page.goto(`/deleteBackup/${name}`);
  await page.goto('/backups');
  await expect(page.locator(`a[href$="/downloadBackup/${name}"]`)).toHaveCount(0);
});
