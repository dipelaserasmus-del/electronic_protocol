// R4e: the stamp designer (Διαχείριση → Σφραγίδα αρχείων): emblem, lines, colour, position; the preview follows every
// change; saved for the files added from then on. The stamp is put back to the default at the end (shared database).
const path = require('path');
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

const livewire = (page) => page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');

test('the stamp designer: edit, live preview, save (R4e)', async ({ page }) => {
  await login(page, 'admin');   // administrators only (R4i, owner, 2026-11-25)
  await page.goto('/stamp');
  const preview = page.locator('#stamp-preview');
  const before = await preview.getAttribute('src');

  // an invented emblem, three lines like an official stamp
  let done = livewire(page);
  await page.setInputFiles('#st-emblem-file', path.join(__dirname, '..', 'fixtures', 'stamp', 'emblem.png'));
  await done;
  await expect(page.locator('#st-emblem-remove')).toBeVisible();
  const lines = page.locator('[data-line-text]');
  await lines.nth(0).fill('ΔΗΜΟΤΙΚΟ ΣΧΟΛΕΙΟ ΔΟΚΙΜΩΝ');
  await lines.nth(1).fill('ΑΡΙΘΜ. ΠΡΩΤ. {αριθμός}');
  done = livewire(page);
  await page.locator('#st-dots-1').check();
  await done;
  done = livewire(page);
  await page.locator('#st-add-line').click();
  await done;
  await lines.nth(2).fill('ΔΟΚΙΜΟΥΠΟΛΗ {ημερομηνία}');
  done = livewire(page);
  await page.locator('#st-dots-2').check();
  await done;
  done = livewire(page);
  await page.locator('#st-bold-0').uncheck();
  await done;
  await page.locator('#st-border').uncheck();
  await page.locator('#st-width').fill('55');
  await expect(page.locator('#stamp-size')).toContainText('Μέγεθος: 55,0 ×');
  expect(await preview.getAttribute('src')).not.toBe(before);
  await expect(page.locator('#stamp-on-page')).toBeVisible();
  await evidence(page, 'r4e-designer', { fullPage: true });

  done = livewire(page);
  await page.locator('#stamp-save').click();
  await done;
  await expect(page.locator('#app-toasts .toast').last()).toContainText('Η σφραγίδα αποθηκεύτηκε');
  await page.reload();
  await expect(page.locator('[data-line-text]').nth(2)).toHaveValue('ΔΟΚΙΜΟΥΠΟΛΗ {ημερομηνία}');
  await expect(page.locator('#st-width')).toHaveValue('55');

  // the others cannot open it, nor see it in the menu (R4i)
  for (const who of ['writer', 'reader']) {
    await page.context().clearCookies();
    await login(page, who);
    expect((await page.goto('/stamp')).status()).toBe(403);
    await page.goto('/home/list');
    await page.locator('#nav-user').click();
    await expect(page.locator('.user-menu a[href$="/stamp"]')).toHaveCount(0);
  }

  // back to the default for the other tests
  await page.context().clearCookies();
  await login(page, 'admin');
  await page.goto('/stamp');
  done = livewire(page);
  await page.locator('#st-emblem-remove').click();
  await done;
  await page.getByText('Αρχική σφραγίδα').click();
  done = livewire(page);
  await page.locator('#app-confirm [data-confirm-yes]').click();
  await done;
  done = livewire(page);
  await page.locator('#stamp-save').click();
  await done;
});
