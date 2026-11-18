// B10: Ρυθμίσεις → Backup must create a complete dump (before: empty .gz file + "success" message).
// R6c: the backups page is rebuilt; creating and deleting are buttons of the page (were plain links).
const { test, expect } = require('@playwright/test');
const zlib = require('zlib');
const fs = require('fs');
const { login, evidence } = require('./helpers');

test('backup: creates a complete dump that downloads and can be deleted', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/backups');

  await page.locator('#backup-create').click();
  const toast = page.locator('#app-toasts .toast', { hasText: 'Δημιουργήθηκε το αντίγραφο' });
  await expect(toast).toBeVisible({ timeout: 60000 });
  const name = (await toast.innerText()).match(/(\S+_back_\d{14}\.sql\.gz)/)[1];
  const row = page.locator(`.backup-row[data-name="${name}"]`);
  await expect(row).toBeVisible();
  await evidence(page, 'backup-created');

  const link = row.getByRole('link', { name: 'Λήψη' });
  const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
  const sql = zlib.gunzipSync(fs.readFileSync(await download.path())).toString('utf8');
  expect(sql).toContain('CREATE TABLE `protocols`');
  expect(sql).toMatch(/-- Dump completed/);

  // names outside the backup pattern are refused (path traversal): `../backups/dummy` is a real file
  // (nginx already rejects `../../…` with 400 before PHP; this one reaches the app)
  expect((await page.request.get('/downloadBackup/..%2Fbackups%2Fdummy')).status()).toBe(404);
  // R6c: no more delete by a link
  expect((await page.request.get(`/deleteBackup/${name}`)).status()).toBe(404);

  await row.getByRole('button', { name: 'Διαγραφή' }).click();
  await page.locator('#app-confirm [data-confirm-yes]').click();
  await expect(row).toHaveCount(0);
});
