// R6e: the log viewer (administrators): a day at a time, levels, search with the matches marked, details on a click.
// The dev stack's own log is used: whatever it holds, the page must show it in this shape.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('the log viewer: days, levels, search, details (R6e)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/logs');
  await expect(page.locator('h1')).toHaveText('Αρχεία καταγραφής');
  await expect(page.locator('#log-summary')).toContainText('καταγραφές στις');
  const entries = page.locator('.log-entry');
  test.skip((await entries.count()) === 0, 'the dev log is empty');

  // a level filter shows only that level
  const level = await entries.first().getAttribute('data-level');
  await page.locator(`label[for="lvl-${level}"]`).click();
  await expect(page.locator('.log-entry').first()).toHaveAttribute('data-level', level);
  await expect(page.locator(`.log-entry:not([data-level="${level}"])`)).toHaveCount(0);
  await page.locator(`label[for="lvl-${level}"]`).click();

  // search: the first word of the first message, marked
  const word = (await entries.first().locator('.log-message').innerText()).split(/[\s:]+/).find((w) => w.length >= 4);
  if (word) {
    await page.locator('#log-search').fill(word);
    await expect(page.locator('.log-entry .log-message mark').first()).toBeVisible();
    await page.locator('#log-search').fill('');
  }

  // an entry with details opens on a click
  const withDetails = page.locator('.log-entry .log-row:not([disabled])').first();
  if (await withDetails.count()) {
    await withDetails.click();
    await expect(page.locator('.log-details').first()).toBeVisible();
  }
  await evidence(page, 'r6e-logs', { clip: { x: 0, y: 0, width: 1440, height: 760 } });

  // the previous day with entries
  if (await page.locator('#log-prev:not([disabled])').count()) {
    const before = await page.locator('#log-day').inputValue();
    await page.locator('#log-prev').click();
    await expect(page.locator('#log-day')).not.toHaveValue(before);
  }

  // not for other roles
  await page.context().clearCookies();
  await login(page, 'writer');
  await page.goto('/logs');
  await expect(page).not.toHaveURL(/\/logs$/);
});

// R6j (owner, 2026-11-25): «Σήμερα» next to the day (the browser's calendar has none)
test('the log viewer has a Σήμερα button (R6j)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/logs');
  const today = await page.evaluate(() => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; });
  await page.locator('#log-day').fill('2020-01-01');
  await expect(page.locator('#log-today')).toBeEnabled();
  await page.locator('#log-today').click();
  await expect(page.locator('#log-day')).toHaveValue(today);
  await expect(page.locator('#log-today')).toBeDisabled();
  const bar = await page.locator('#log-day').locator('..').boundingBox();
  await evidence(page, 'r6j-today', { clip: { x: Math.max(0, bar.x - 20), y: Math.max(0, bar.y - 10), width: bar.width + 40, height: bar.height + 20 } });
});
