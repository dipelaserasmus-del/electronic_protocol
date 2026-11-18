// R6c: Ρυθμίσεις (grouped, explained, checked at the field) and Εκκαθάριση (expired attachments → trash).
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('settings: a wrong value is refused at its field, a right one is saved (R6c)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/settings');
  await expect(page.locator('#set-office')).toContainText('Υπηρεσία και αρίθμηση');
  await evidence(page, 'r6c-settings', { fullPage: true });

  const rows = page.locator('#s-showRowsInPage');
  const previous = await rows.inputValue();
  await rows.fill('abc');
  await page.locator('#settings-save').click();
  await expect(page.locator('[data-setting="showRowsInPage"] .invalid-feedback')).toBeVisible();
  await expect(page.locator('#settings-errors')).toBeVisible();
  await page.locator('#set-display').scrollIntoViewIfNeeded();
  await evidence(page, 'r6c-settings-error');

  await rows.fill(previous === '30' ? '31' : '30');
  await page.locator('#settings-save').click();
  await expect(page.locator('#app-toasts .toast', { hasText: 'Αποθηκεύτηκαν' })).toBeVisible();
  await expect(page.locator('#settings-errors')).toHaveCount(0);
  await page.reload();
  await expect(page.locator('#s-showRowsInPage')).toHaveValue(previous === '30' ? '31' : '30');

  // put it back
  await page.locator('#s-showRowsInPage').fill(previous);
  await page.locator('#settings-save').click();
  await expect(page.locator('#app-toasts .toast', { hasText: 'Αποθηκεύτηκαν' }).last()).toBeVisible();
});

test('clean-up page: expired attachments, printable list (R6c)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/arxeio');
  await expect(page.locator('#archive-summary')).toBeVisible();
  await evidence(page, 'r6c-archive');
  const print = await page.goto('/expired');
  expect(print.status()).toBe(200);
  await expect(page.locator('#print-now')).toBeVisible();
  await expect(page.locator('.print-head h1')).toHaveText('Εκκαθάριση αρχείου');
  await evidence(page, 'r6c-expired-print');
});
