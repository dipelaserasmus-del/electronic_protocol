// R1d: a folder with automatic assignment (R6b): a writer registers a protocol in it; the form says who will get it,
// and the saved protocol is assigned to them.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol, notice } = require('./helpers');

test('a protocol saved in a folder with rules is assigned automatically (R1d)', async ({ page }) => {
  const code = `Φ.R1d.${String(Date.now()).slice(-6)}`;
  await login(page, 'admin');
  await page.goto('/keep');
  await page.locator('#folder-new').click();
  await page.locator('#f-fakelos').fill(code);
  await page.locator('#f-describe').fill('Αυτόματη ανάθεση δοκιμής');
  await page.locator('#folder-rules').getByLabel('Αναθέτων Δοκιμών').first().check();
  await page.locator('#folder-save').click();
  await expect(page.locator('#app-toasts .toast')).toContainText('προστέθηκε');
  await page.context().clearCookies();   // log out

  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { fakelos: code, thema: `R1d αυτόματη ανάθεση ${Date.now()}` });
  await expect(page.locator('#notice-auto-assign')).toContainText('Διεκπεραίωση: Αναθέτων Δοκιμών');
  await evidence(page, 'r1d-form-notice');
  await saveProtocol(page);
  expect(await notice(page)).toContain(`Αυτόματη ανάθεση (${code}): Διεκπεραίωση: Αναθέτων Δοκιμών`);
  await evidence(page, 'r1d-saved');
});
