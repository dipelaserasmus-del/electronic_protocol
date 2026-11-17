// R2: the rebuilt protocol list: filters, sorting, header that stays visible while scrolling, "Αναλυτικά" remembered.
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol } = require('./helpers');

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

  // sorting by subject: a subject that sorts after all others is first descending, not first ascending
  // (checked on a marker, not on the whole order: the database's Greek collation is not the browser's)
  const marker = `ωωω R2 ταξινόμηση ${Date.now()}`;
  await page.goto('/home');
  await fillProtocol(page, { thema: marker, fakelos: 'first' });
  await saveProtocol(page);
  await page.goto('/home/list');
  await page.locator('[data-sort=thema]').click();
  await expect(page.locator('[data-sort=thema]')).toHaveAttribute('aria-sort', 'ascending');
  await expect(page.locator('.protocol-row').first()).not.toContainText(marker);
  await page.locator('[data-sort=thema]').click();
  await expect(page.locator('[data-sort=thema]')).toHaveAttribute('aria-sort', 'descending');
  await expect(page.locator('.protocol-row').first()).toContainText(marker);

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

test('a user chosen under "Όλα" filters the list; busy users first; file names shown (R2b)', async ({ page }) => {
  const subject = `R2b ${Date.now()}`;
  const file = `Πρόσκληση_σε_επιμορφωτική_ημερίδα_R2b_${Date.now()}.pdf`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', { name: file, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 R2b\n') });
  await expect(page.locator('#attachments .upload-row')).toHaveCount(1);
  await saveProtocol(page);

  await page.context().clearCookies();
  await login(page, 'admin');
  await page.goto('/home/list');
  const who = page.locator('#list-who');
  await expect(who.locator('option').first()).toHaveText('Όλοι οι χρήστες');
  await expect(who.locator('optgroup[label="Χωρίς εκκρεμείς αναθέσεις"]')).toHaveCount(1);
  const row = page.locator('.protocol-row', { hasText: subject });
  await expect(row).toBeVisible();
  // attachments column: the start of the name and the extension; the whole name on hover
  const name = row.locator('.attachment-name a');
  await expect(name).toContainText('Πρόσκληση_σε_επιμορ….pdf');
  await expect(name).toHaveAttribute('title', file);
  await evidence(page, 'r2b-user-menu-and-file-names', { clip: { x: 0, y: 0, width: 1440, height: 520 } });

  // the writer's protocols only (registered or assigned)
  const writerId = await who.locator('option', { hasText: 'Συγγραφέας Δοκιμών' }).getAttribute('value');
  await who.selectOption(writerId);
  await expect(page.locator('#list-title')).toHaveText('Συγγραφέας Δοκιμών · Πρωτόκολλο');
  await expect(page.locator('.protocol-row', { hasText: subject })).toBeVisible();
  const total = Number((await page.locator('text=Σύνολο:').innerText()).replace(/\D/g, ''));
  await who.selectOption('me');
  await expect(page.locator('#list-title')).toHaveText('Πρωτόκολλο');
  await expect.poll(async () => Number((await page.locator('text=Σύνολο:').innerText()).replace(/\D/g, ''))).toBeGreaterThan(total);

  // the detailed view: the whole file name, who registered it
  await page.locator('#list-detailed').check();
  await expect(row.locator('.attachment-name a')).toContainText(file);
  await expect(row.locator('.detailed')).toContainText('Καταχώριση: Συγγραφέας Δοκιμών');
  await evidence(page, 'r2b-detailed', { clip: { x: 0, y: 0, width: 1440, height: 620 } });
  await page.locator('#list-detailed').uncheck();
});

test('a missing number is a dashed row; its button registers that number (R2c)', async ({ page }) => {
  await login(page, 'admin');
  const previousValidate = await setSetting(page, 'protocolValidate', 0);
  try {
    // leave one number out: save with the next free number + 1
    await page.goto('/home');
    const free = Number(await page.locator('#f-protocolnum').getAttribute('placeholder'));
    const year = new Date().getFullYear();
    await page.locator('#f-protocolnum').fill(String(free + 1));
    await fillProtocol(page, { thema: `R2c μετά το κενό ${Date.now()}`, fakelos: 'first' });
    await saveProtocol(page);

    await page.goto('/home/list');
    const gap = page.locator('.gap-row', { hasText: `Κενός αριθμός ${free}/${year}` });
    await expect(gap).toBeVisible();
    await expect(page.locator('.protocol-row').first()).toContainText(`${free + 1}/${year}`);   // the gap is right under it
    await expect(page.locator('tbody tr').nth(1)).toHaveClass(/gap-row/);
    await expect(gap.locator('td')).toHaveCSS('border-top-style', 'dashed');
    await evidence(page, 'r2c-gap-row', { clip: { x: 0, y: 0, width: 1440, height: 560 } });

    await gap.getByRole('link', { name: `Καταχώριση με αριθμό ${free}` }).click();
    await expect(page.locator('#f-protocolnum')).toHaveValue(String(free));
    await fillProtocol(page, { thema: `R2c στο κενό ${Date.now()}`, fakelos: 'first' });
    await saveProtocol(page);
    await page.goto('/home/list');
    await expect(page.locator('.gap-row', { hasText: `Κενός αριθμός ${free}/${year}` })).toHaveCount(0);
  } finally {
    await setSetting(page, 'protocolValidate', previousValidate);
  }
});
