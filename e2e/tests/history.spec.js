// X02: «Ιστορικό ενεργειών» (/history): the audit log (X01) for administrators: a change to a protocol, its history
// from the form, the filters and the CSV export; other users get 403.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('a protocol changed, then its history from the form and the whole log (X02)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/home');
  const stamp = Date.now();
  await fillProtocol(page, { thema: `X02 ιστορικό ${stamp}`, fakelos: 'first' });
  await saveProtocol(page);
  const id = page.url().match(/\/home\/(\d+)$/)[1];
  await page.locator('#f-thema').fill(`X02 ιστορικό ${stamp} (διορθωμένο)`);
  await page.locator('#protocol-form button[type=submit]').first().click();   // same address: wait for the message
  await expect(page.locator('main > .alert', { hasText: 'Οι αλλαγές αποθηκεύτηκαν' })).toBeVisible();

  // the protocol's «Ιστορικό»: this protocol's entries only, newest first
  await page.locator('[data-form-actions] [data-action="history"]').click();
  await expect(page).toHaveURL(/\/history\?protocol=\d+%2F\d{4}/);
  await expect(page.locator('h1')).toHaveText('Ιστορικό ενεργειών');
  await expect(page.locator('#history-protocol-link a')).toHaveAttribute('href', new RegExp(`/home/${id}$`));
  const rows = page.locator('#history-table .history-row');
  await expect(rows.first()).toHaveAttribute('data-action', 'updated');
  await expect(rows.first().locator('.history-changes')).toContainText(`Θέμα: X02 ιστορικό ${stamp} → X02 ιστορικό ${stamp} (διορθωμένο)`);
  await expect(rows.first()).toContainText('admin');
  await expect(rows.last()).toHaveAttribute('data-action', 'created');
  await evidence(page, 'x02-protocol-history', { fullPage: true });

  // the whole log, filtered by action; the export downloads it
  await page.locator('#history-clear').click();
  await page.locator('#history-action').selectOption('login');
  await page.locator('#history-apply').click();
  await expect(page).toHaveURL(/action=login/);
  await expect(rows.first()).toHaveAttribute('data-action', 'login');
  await expect(page.locator('#history-table .history-row:not([data-action="login"])')).toHaveCount(0);
  await evidence(page, 'x02-logins', { clip: { x: 0, y: 0, width: 1440, height: 700 } });
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#history-export').click()]);
  expect(download.suggestedFilename()).toMatch(/^istoriko-.*\.csv$/);

  // the menu entry
  await page.locator('nav.app-topbar #nav-user').click();
  await expect(page.locator('.user-menu-manage').getByRole('link', { name: 'Ιστορικό ενεργειών' })).toBeVisible();
});

test('other users have no history (X02)', async ({ page }) => {
  await login(page, 'writer');
  const response = await page.goto('/history');
  expect(response.status()).toBe(403);
});
