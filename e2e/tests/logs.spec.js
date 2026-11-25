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
  // review of R6j: ‹ back to an earlier day with entries, when there is one (the day's min = the oldest day with entries)
  const oldest = await page.locator('#log-day').getAttribute('min');
  if (oldest && oldest < today) await expect(page.locator('#log-prev')).toBeEnabled();
  else await expect(page.locator('#log-prev')).toBeDisabled();
  const bar = await page.locator('#log-day').locator('..').boundingBox();
  await evidence(page, 'r6j-today', { clip: { x: Math.max(0, bar.x - 20), y: Math.max(0, bar.y - 10), width: bar.width + 40, height: bar.height + 20 } });
});

// R6k (owner, 2026-11-25): «Εξαγωγή» downloads the day's entries as shown; personal data masked unless switched off
test('the log viewer exports the day\'s entries, masked by default (R6k)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  await page.goto('/logs');
  const run = page.locator('#log-export-run');
  if (await run.isDisabled()) test.skip(true, 'no entries on the newest day of this copy');
  await expect(page.locator('#log-mask')).toBeChecked();
  await evidence(page, 'r6k-export', { clip: { x: 0, y: 50, width: 1440, height: 220 } });
  const [download] = await Promise.all([page.waitForEvent('download'), run.click()]);
  expect(download.suggestedFilename()).toMatch(/^log-\d{4}-\d{2}-\d{2}\.txt$/);
  const text = require('fs').readFileSync(await download.path(), 'utf8');
  expect(text).toMatch(/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \w+\.[A-Z]+: /);
  // every e-mail address in it is masked: «***@domain»
  for (const address of text.match(/[^\s"'<>(),;:]*@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/g) ?? []) expect(address).toMatch(/^\*\*\*@/);

  // the switch off: the full text, and the warning
  await page.locator('#log-mask').uncheck();
  await expect(page.locator('#log-export-note')).toContainText('μπορεί να περιέχει προσωπικά στοιχεία');
  const [full] = await Promise.all([page.waitForEvent('download'), run.click()]);
  expect(full.suggestedFilename()).toMatch(/-full\.txt$/);
  await evidence(page, 'r6k-export-full', { clip: { x: 0, y: 50, width: 1440, height: 220 } });
  await page.locator('#log-mask').check();
});
