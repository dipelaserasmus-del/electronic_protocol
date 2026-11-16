// R2: the rebuilt protocol list: filters, sorting, header that stays visible while scrolling, "Αναλυτικά" remembered.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('filters, sorting and the sticky header (R2)', async ({ page }) => {
  await login(page, 'anathetis');
  await page.goto('/home/list');
  await expect(page.locator('#list-title')).toHaveText('Πρωτόκολλο');
  await expect(page.locator('.protocol-row').first()).toBeVisible();
  await evidence(page, 'r2-list');

  // filters: "to handle" for all users
  await page.locator('[data-filter=d]').click();
  await expect(page.locator('#list-title')).toHaveText('προς Διεκπεραίωση');
  await page.locator('#list-who').selectOption('all');
  await expect(page.locator('#list-title')).toHaveText('Όλοι οι χρήστες · προς Διεκπεραίωση');
  await expect(page).toHaveURL(/filter=d/);
  await expect(page).toHaveURL(/user=all/);
  await evidence(page, 'r2-filter-all-users');

  // sorting by subject, ascending then descending
  await page.locator('[data-filter=all]').click();
  await page.locator('[data-sort=thema]').click();
  await expect(page.locator('[data-sort=thema]')).toHaveAttribute('aria-sort', 'ascending');
  const subjects = await page.locator('.protocol-row td:nth-child(3) > div:first-child').allInnerTexts();
  const sorted = [...subjects].sort((a, b) => a.localeCompare(b, 'el'));
  expect(subjects.slice(0, 3).map((s) => s.trim() === '' ? '' : s[0].toLowerCase()))
    .toEqual(sorted.slice(0, 3).map((s) => s.trim() === '' ? '' : s[0].toLowerCase()));
  await page.locator('[data-sort=thema]').click();
  await expect(page.locator('[data-sort=thema]')).toHaveAttribute('aria-sort', 'descending');

  // header stays visible under the top bar while scrolling
  await page.mouse.wheel(0, 2500);
  await page.waitForTimeout(200);
  const header = await page.locator('.table-sticky thead th').first().boundingBox();   // the cells stick, not the row
  expect(header.y).toBeGreaterThanOrEqual(0);
  expect(header.y).toBeLessThan(120);
  await evidence(page, 'r2-sticky-header');
});

test('"Αναλυτικά" shows more and is remembered by the browser (R2)', async ({ page }) => {
  const subject = `R2 αναλυτικά ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'first', paratiriseis: 'Παρατήρηση R2' });
  await saveProtocol(page);

  await page.goto('/home/list');
  const row = page.locator('.protocol-row', { hasText: subject });
  await expect(row).toBeVisible();
  const note = row.getByText('Παρατήρηση R2');
  await expect(note).toBeHidden();
  await page.locator('#list-detailed').check();
  await expect(note).toBeVisible();
  await page.reload();
  await expect(page.locator('#list-detailed')).toBeChecked();
  await expect(page.locator('.protocol-row', { hasText: subject }).getByText('Παρατήρηση R2')).toBeVisible();
  await evidence(page, 'r2-detailed');
  await page.locator('#list-detailed').uncheck();

  // a click anywhere on the row opens the protocol
  await page.locator('.protocol-row', { hasText: subject }).click({ position: { x: 400, y: 10 } });   // on the subject
  await expect(page).toHaveURL(/\/home\/\d+$/);
  await expect(page.locator('#f-thema')).toHaveValue(subject);
});
