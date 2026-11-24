// R3i: relative dates (πότε:σήμερα / χθες / εβδομάδα / 7μέρες / μήνας / φέτος / πέρσι) and who registered (χρήστης:),
// in the top-bar box and on the advanced screen (quick date buttons, «Καταχωρίστηκε από»).
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('πότε:σήμερα χρήστης: in the search box; quick dates and «Καταχωρίστηκε από» (R3i)', async ({ page }) => {
  const stamp = Date.now();
  const subject = `R3i καταχώριση συγγραφέα ${stamp}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'first' });
  await saveProtocol(page);
  await page.context().clearCookies();
  await login(page, 'admin');

  // the top-bar box: today, registered by the writer
  const box = page.locator('#app-search-q');
  await box.fill(`πότε:σήμερα χρήστης:Συγγραφέας ${stamp}`);
  await Promise.all([page.waitForURL(/\/search\?q=/), box.press('Enter')]);
  await expect(page.locator('main')).toContainText(subject);
  await evidence(page, 'r3i-search-box', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
  // … by somebody else, or yesterday: not there
  await page.goto(`/search?q=${encodeURIComponent(`πότε:σήμερα χρήστης:Αναγνώστης ${stamp}`)}`);
  await expect(page.locator('main')).not.toContainText(subject);
  await page.goto(`/search?q=${encodeURIComponent(`πότε:χθες ${stamp}`)}`);
  await expect(page.locator('main')).not.toContainText(subject);

  // the box offers the new search words while typing
  await box.fill('');
  await box.pressSequentially('πότ');
  await expect(page.locator('#app-search-list .search-hint', { hasText: 'πότε:' })).toBeVisible();
  await box.press('Escape');

  // advanced screen: «Καταχωρίστηκε από» + a quick date button (fills από / έως, clears the year, searches)
  await page.goto('/find');
  await page.locator('#s-words').fill(String(stamp));
  await page.locator('#s-by').selectOption({ label: 'Συγγραφέας Δοκιμών' });
  const searched = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
  await page.locator('[data-quick-date="7μέρες"]').click();
  await searched;
  await expect(page.locator('#s-year')).toHaveValue('');
  const today = new Date().toLocaleDateString('el-GR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Athens' });
  await expect(page.locator('#s-to')).toHaveValue(today);
  await expect(page.locator('.search-page')).toContainText(subject);
  await expect(page.locator('#search-as-text')).toContainText('χρήστης:"Συγγραφέας Δοκιμών"');
  await evidence(page, 'r3i-advanced', { fullPage: true });

  // another registrar: nothing
  await page.locator('#s-by').selectOption({ label: 'Αναγνώστης Δοκιμών' });
  const again = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
  await page.locator('#search-submit').click();
  await again;
  await expect(page.locator('.search-page')).not.toContainText(subject);
});
