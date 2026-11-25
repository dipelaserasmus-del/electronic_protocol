// R6b: "Φάκελοι και διατήρηση" (/keep): administrators add a folder with automatic assignments and import a pasted
// list; every role sees the folders.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('add a folder with automatic assignment, then import a list (R6b)', async ({ page }) => {
  const n = String(Date.now()).slice(-6);
  const code = `Φ.R6b.${n}`;
  await login(page, 'admin');
  await page.goto('/keep');
  await page.locator('#folder-new').click();
  await page.locator('#f-fakelos').fill(code);
  await page.locator('#f-keep').fill('5');
  await page.locator('#f-describe').fill('Φάκελος δοκιμής με αυτόματη ανάθεση');
  await page.locator('#folder-rules').getByLabel('Συγγραφέας Δοκιμών').first().check();
  await evidence(page, 'r6b-folder-form');
  await page.locator('#folder-save').click();
  await expect(page.locator('#app-toasts .toast')).toContainText('προστέθηκε');

  await page.locator('#folder-search').fill(code);
  const row = page.locator(`.folder-row[data-code="${code}"]`);
  await expect(row).toContainText('5 έτη');
  await expect(row).toContainText('Δ: Συγγραφέας Δοκιμών');
  await evidence(page, 'r6b-folder-list');

  // import: a heading row, one new folder, one change to the folder above, one wrong code
  await page.locator('#folder-search').fill('');
  await page.locator('#folder-import-open').click();
  await page.locator('#import-text').fill(
    `Κωδικός\tΠεριγραφή\tΔιατήρηση\n${code}\tΑλλαγμένη περιγραφή\t\nΦ.R6b.${n}.1\tΝέος από τη λίστα\tΜόνιμα\nX.1\tΛάθος\n`);
  await page.locator('#import-check').click();
  await expect(page.locator('#import-summary')).toContainText('1 νέοι, 1 αλλαγές, 0 ίδιοι, 1 με λάθος');
  await evidence(page, 'r6b-import-preview');
  await page.locator('#import-run').click();
  await page.locator('#app-confirm [data-confirm-yes]').click();
  await expect(page.locator('#app-toasts .toast').last()).toContainText('1 νέοι φάκελοι, 1 ενημερώθηκαν');
  await page.locator('#folder-search').fill(`Φ.R6b.${n}`);
  await expect(page.locator(`.folder-row[data-code="Φ.R6b.${n}.1"]`)).toContainText('Μόνιμα');
  await expect(row).toContainText('Αλλαγμένη περιγραφή');
  await expect(row).toContainText('5 έτη');   // empty cell: kept
});

test('readers see the folders without the buttons (R6b)', async ({ page }) => {
  await login(page, 'reader');
  await page.goto('/keep');
  await expect(page.locator('#folders-table .folder-row').first()).toBeVisible();
  await expect(page.locator('#folder-new')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Επεξεργασία' })).toHaveCount(0);
});

// R6g (owner, 2026-11-25): Κωδικός and Πρωτ. sort the table; a folder's code shows the protocols of that folder at once
test('folders sort by code or count; a code opens its protocols (R6g)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  await page.goto('/keep');
  const codes = () => page.locator('#folders-table .folder-row').evaluateAll((rows) => rows.map((r) => r.dataset.code));
  const counts = () => page.locator('#folders-table .folder-row td:nth-last-child(2)').evaluateAll((tds) => tds.map((td) => Number(td.textContent.trim() || 0)));

  // Πρωτ.: the most used first, then the other way
  await page.locator('#folders-table [data-sort="count"]').click();
  await expect(page.locator('#folders-table [data-sort="count"]')).toHaveText('Πρωτ. ▼');
  await expect(page).toHaveURL(/sort=count/);
  let n = await counts();
  expect(n.length).toBeGreaterThan(2);
  expect([...n].sort((a, b) => b - a)).toEqual(n);
  await evidence(page, 'r6g-by-count', { clip: { x: 0, y: 0, width: 1440, height: 560 } });
  await page.locator('#folders-table [data-sort="count"]').click();
  await expect(page.locator('#folders-table [data-sort="count"]')).toHaveText('Πρωτ. ▲');
  n = await counts();
  expect([...n].sort((a, b) => a - b)).toEqual(n);

  // Κωδικός: the folders' order, and reversed
  await page.locator('#folders-table [data-sort="code"]').click();
  await expect(page.locator('#folders-table [data-sort="code"]')).toHaveText('Κωδικός ▲');
  const asc = await codes();
  await page.locator('#folders-table [data-sort="code"]').click();
  await expect(page.locator('#folders-table [data-sort="code"]')).toHaveText('Κωδικός ▼');
  expect(await codes()).toEqual([...asc].reverse());

  // the most used folder's code: its protocols, at once
  await page.locator('#folders-table [data-sort="count"]').click();
  await expect(page.locator('#folders-table [data-sort="count"]')).toHaveText('Πρωτ. ▼');
  const first = page.locator('#folders-table .folder-row').first();
  const code = await first.getAttribute('data-code');
  await first.locator('.folder-search-link').click();
  await expect(page).toHaveURL(/\/search\?q=/);   // the top bar's search: only the results
  await expect(page.locator('#search-count')).toContainText('Βρέθηκ');
  await expect(page.locator('#search-results')).toContainText(code);
  await evidence(page, 'r6g-folder-search', { clip: { x: 0, y: 0, width: 1440, height: 620 } });
});
