// R3c: the search box in the top bar. Suggestions while typing (↓ Enter opens a protocol), search words offered as
// they are typed, Enter → the results page, "Σύνθετη αναζήτηση" → the advanced screen with the same search, "/" key.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('suggestions while typing, results page, advanced screen with the same search (R3c)', async ({ page }) => {
  const stamp = Date.now();
  const subject = `R3c Εκδρομή Γ τάξης ${stamp}`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'Φ.1.1' });
  await saveProtocol(page);
  const url = page.url();

  // "/" puts the cursor in the box; typing shows the protocol, marked, without accents
  await page.goto('/home/list');
  await page.keyboard.press('/');
  const box = page.locator('#app-search-q');
  await expect(box).toBeFocused();
  await box.pressSequentially(`εκδρομη ${stamp}`);
  const first = page.locator('#app-search-list .search-suggestion').first();
  await expect(first).toContainText(subject);
  await expect(first.locator('mark').first()).toHaveText('Εκδρομή');
  await evidence(page, 'suggestions', { clip: { x: 0, y: 0, width: 1440, height: 450 } });

  // review of R3c: typing on removes the earlier protocols at once (↓ Enter must not open one of them)
  await box.press('x');
  await expect(page.locator('#app-search-list .search-suggestion')).toHaveCount(0);
  await box.press('Backspace');
  await expect(first).toContainText(subject);

  // ↓ Enter opens it
  await box.press('ArrowDown');
  await Promise.all([page.waitForURL(url), box.press('Enter')]);

  // search words are offered while typing their start
  await box.fill('');
  await box.pressSequentially(`${stamp} φακ`);
  const hint = page.locator('#app-search-list .search-hint', { hasText: 'φάκελος:' });
  await expect(hint).toBeVisible();
  await evidence(page, 'search-word-hint', { clip: { x: 0, y: 0, width: 1440, height: 450 } });
  await hint.click();
  await expect(box).toHaveValue(`${stamp} φάκελος:`);
  await box.pressSequentially('φ.1.1');

  // Enter (nothing chosen) → the results page
  await Promise.all([page.waitForURL(/\/search\?q=/), box.press('Enter')]);
  await expect(page.locator('#search-results')).toContainText(subject);
  await expect(page.locator('#app-search-q')).toHaveValue(`${stamp} φάκελος:φ.1.1`);
  await evidence(page, 'results-page', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  // the advanced screen with the same search
  await page.locator('#to-advanced').click();
  await page.waitForURL(/\/find\?/);
  await expect(page.locator('#s-words')).toHaveValue(String(stamp));
  await expect(page.locator('#s-folder')).toHaveValue(/^Φ\.1\.1/);
  await expect(page.locator('#s-year')).toHaveValue('');
  await expect(page.locator('#search-results')).toContainText(subject);
});

test('the old pages have the box too; it opens the results page (R3c)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/keep');
  const box = page.locator('form[role=search] input[name=q]');
  await box.fill('αρ:1');
  await Promise.all([page.waitForURL(/\/search\?q=/), box.press('Enter')]);
  await expect(page.locator('#results-query')).toContainText('αρ:1');
});
