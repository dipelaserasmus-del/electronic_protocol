// N04: shared components of the rebuilt pages, on the development-only page /dev/ui.
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { login, evidence } = require('./helpers');

const shared = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'tests', 'fixtures', 'date-input-cases.json'), 'utf8'));

test.beforeEach(async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/dev/ui');
});

test('date rules in the browser match the server (same cases as PHPUnit)', async ({ page }) => {
  const results = await page.evaluate(({ cases, shift, today }) => {
    const [y, m, d] = today.split('-').map(Number);
    const t = new Date(y, m - 1, d);
    return {
      expand: cases.map(([typed]) => window.appDate.expandDate(typed, t)),
      shift: shift.map(([from, days]) => window.appDate.shiftDate(from, days, t)),
    };
  }, shared);
  expect(results.expand).toEqual(shared.cases.map(c => c[1]));
  expect(results.shift).toEqual(shared.shift.map(c => c[2]));
});

test('date input: a day number becomes a full date; ◀ ▶ Σήμερα; invalid input is marked', async ({ page }) => {
  const field = page.locator('#the-date');
  const now = new Date();
  const pad = n => String(n).padStart(2, '0');
  const thisMonth = `${pad(now.getMonth() + 1)}/${now.getFullYear()}`;

  await field.fill('9');
  await field.press('Tab');
  await expect(field).toHaveValue(`09/${thisMonth}`);
  await evidence(page, 'n04-date-expanded', { clip: { x: 0, y: 60, width: 1440, height: 330 } });

  const box = page.locator('[data-date-input]').filter({ has: field });
  await box.getByRole('button', { name: 'Επόμενη ημέρα' }).click();
  await expect(field).toHaveValue(`10/${thisMonth}`);
  await box.getByRole('button', { name: 'Προηγούμενη ημέρα' }).click();
  await box.getByRole('button', { name: 'Προηγούμενη ημέρα' }).click();
  await expect(field).toHaveValue(`08/${thisMonth}`);
  await field.press('ArrowUp');
  await expect(field).toHaveValue(`09/${thisMonth}`);
  await box.getByRole('button', { name: 'Σήμερα' }).click();
  await expect(field).toHaveValue(`${pad(now.getDate())}/${thisMonth}`);

  await field.fill('32/13');
  await field.press('Tab');
  await expect(field).toHaveClass(/is-invalid/);
  await expect(box.locator('.invalid-feedback')).toBeVisible();
  await evidence(page, 'n04-date-invalid', { clip: { x: 0, y: 60, width: 1440, height: 330 } });

  // read-only: buttons do nothing
  const locked = page.locator('#locked-date');
  await expect(page.locator('[data-date-input]').filter({ has: locked }).getByRole('button', { name: 'Σήμερα' })).toBeDisabled();
  await expect(locked).toHaveValue('01/02/2026');
});

test('confirm dialog: Άκυρο keeps the page, the confirm button goes ahead', async ({ page }) => {
  await page.locator('#the-date').fill('5/4/2026');
  await page.locator('#form-pills').getByRole('button', { name: 'Υποβολή' }).click();
  const dialog = page.locator('#app-confirm');
  await expect(dialog).toBeVisible();
  await expect(dialog).toContainText('Να υποβληθεί η φόρμα;');
  await evidence(page, 'n04-confirm-dialog');
  await dialog.getByRole('button', { name: 'Άκυρο' }).click();
  await expect(dialog).toBeHidden();
  expect(page.url()).not.toContain('submitted');

  await page.locator('#form-pills').getByRole('button', { name: 'Υποβολή' }).click();
  await dialog.getByRole('button', { name: 'Υποβολή' }).click();
  await expect(page.locator('#submitted')).toContainText('05/04/2026');   // typed 5/4/2026, sent as dd/mm/yyyy

  // a link with data-confirm
  await page.locator('#actions').getByRole('link', { name: 'Διαγραφή' }).click();
  await expect(dialog).toContainText('Να διαγραφεί το πρωτόκολλο 123/2026;');
  await dialog.getByRole('button', { name: 'Διαγραφή' }).click();
  await expect(page).toHaveURL(/#deleted$/);
});

test('confirm dialog: a form\'s own submit handler (AJAX, Livewire) runs only after Ναι, once', async ({ page }) => {
  await page.evaluate(() => {
    const form = document.createElement('form');
    form.id = 'js-form';
    form.dataset.confirm = 'Να σταλεί;';
    form.innerHTML = '<button type="submit">Αποστολή</button>';
    window.jsFormCalls = 0;
    form.addEventListener('submit', e => { e.preventDefault(); window.jsFormCalls++; });   // like wire:submit
    document.querySelector('main, body').append(form);
  });
  const dialog = page.locator('#app-confirm');
  await page.locator('#js-form').getByRole('button', { name: 'Αποστολή' }).click();
  await expect(dialog).toBeVisible();
  expect(await page.evaluate(() => window.jsFormCalls)).toBe(0);   // nothing sent while the question is open
  await dialog.getByRole('button', { name: 'Άκυρο' }).click();
  await expect(dialog).toBeHidden();
  expect(await page.evaluate(() => window.jsFormCalls)).toBe(0);

  await page.locator('#js-form').getByRole('button', { name: 'Αποστολή' }).click();
  await dialog.getByRole('button', { name: 'Ναι' }).click();
  await expect.poll(() => page.evaluate(() => window.jsFormCalls)).toBe(1);
});

test('toasts show text, never HTML', async ({ page }) => {
  await page.locator('#actions').getByRole('button', { name: 'Αποθήκευση' }).click();
  const toast = page.locator('#app-toasts .toast');
  await expect(toast.first()).toContainText('Αποθηκεύτηκε');
  await expect(toast.first()).toHaveClass(/text-bg-success/);
  await page.evaluate(() => window.appToast('<img src=x onerror=alert(1)>', 'error'));
  await expect(toast.last()).toContainText('<img src=x onerror=alert(1)>');   // shown as text
  await expect(page.locator('#app-toasts img')).toHaveCount(0);
  await evidence(page, 'n04-toasts');
});

test('toasts sit under the top bar in the middle, a click closes them, links stay same-site (R1e)', async ({ page }) => {
  await page.evaluate(() => window.appToast('Με σύνδεσμο', 'success', { url: '/home', label: 'Άνοιγμα' }));
  await page.evaluate(() => window.appToast('Ξένος σύνδεσμος', 'info', { url: 'https://example.org/x', label: 'Έξω' }));
  const toast = page.locator('#app-toasts .toast', { hasText: 'Με σύνδεσμο' });
  await expect(toast).toBeVisible();
  await expect(toast).toHaveCSS('opacity', '1');   // after the fade-in
  const box = await toast.boundingBox();
  const bar = await page.locator('.app-topbar').boundingBox();
  expect(box.y).toBeGreaterThanOrEqual(bar.y + bar.height - 1);              // under the top bar, not covering it
  expect(Math.abs(box.x + box.width / 2 - 1440 / 2)).toBeLessThan(4);        // centred
  await expect(toast.locator('a')).toHaveAttribute('href', '/home');
  await expect(page.locator('#app-toasts .toast', { hasText: 'Ξένος σύνδεσμος' }).locator('a')).toHaveCount(0);
  await evidence(page, 'r1e-toast-top', { clip: { x: 0, y: 0, width: 1440, height: 260 } });
  await toast.locator('.toast-body').click({ position: { x: 5, y: 5 } });
  await expect(toast).toHaveCount(0);
});

test('pill buttons stay on one row; on a narrow screen the rest go to the ⋯ menu and still work', async ({ page }) => {
  const bar = page.locator('#actions');
  const more = bar.getByRole('button', { name: 'Περισσότερες ενέργειες' });
  await expect(more).toBeHidden();   // wide screen: everything fits
  const tops = await bar.locator('.pill').evaluateAll(els => els.map(e => e.getBoundingClientRect().top));
  expect(new Set(tops).size).toBe(1);   // one row

  await page.setViewportSize({ width: 390, height: 800 });
  await expect(more).toBeVisible();
  const visible = await bar.locator('.pill:visible').count();
  expect(visible).toBeGreaterThan(0);
  expect(visible).toBeLessThan(7);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await more.click();
  await evidence(page, 'n04-pills-narrow');
  // the last action (Διαγραφή) is now in the menu; it still asks first
  await bar.locator('.dropdown-menu').getByRole('button', { name: 'Διαγραφή' }).click();
  await expect(page.locator('#app-confirm')).toContainText('Να διαγραφεί');
});

test('file badges and notices', async ({ page }) => {
  const files = page.locator('#files');
  await expect(files).toContainText('PDF');
  await expect(files).toContainText('1,2 MB');
  await expect(files).toContainText('20 MB');
  await expect(page.locator('.notice.alert-warning')).toContainText('δεσμευμένος');
  await evidence(page, 'n04-files-notices', { fullPage: true });
});
