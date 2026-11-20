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

test('a missing number is a green ΚΕΝΟ row; its button registers that number (R2c, R2d)', async ({ page }) => {
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
    const gap = page.locator('.gap-row', { hasText: `${free}/${year} ΚΕΝΟ` });
    await expect(gap).toBeVisible();
    await expect(page.locator('.protocol-row').first()).toContainText(`${free + 1}/${year}`);   // the gap is right under it
    await expect(page.locator('tbody tr').nth(1)).toHaveClass(/gap-row/);
    await expect(gap.locator('td')).toHaveCSS('border-top-style', 'dashed');
    // R2d: green background, the button (with a + icon) in the middle of the row
    const bg = await gap.locator('td').evaluate((td) => getComputedStyle(td).backgroundColor);
    const [r, g, b] = bg.match(/\d+/g).map(Number);
    expect(g).toBeGreaterThan(r);
    expect(g).toBeGreaterThan(b);
    const button = gap.getByRole('link', { name: `Καταχώριση με αριθμό ${free}` });
    await expect(button.locator('svg.icon-plus')).toHaveCount(1);
    const row = await gap.locator('td').boundingBox();
    const btn = await button.boundingBox();
    expect(Math.abs((btn.x + btn.width / 2) - (row.x + row.width / 2))).toBeLessThan(20);
    await evidence(page, 'r2d-gap-row', { clip: { x: 0, y: 0, width: 1440, height: 560 } });

    await button.click();
    await expect(page.locator('#f-protocolnum')).toHaveValue(String(free));
    await fillProtocol(page, { thema: `R2c στο κενό ${Date.now()}`, fakelos: 'first' });
    await saveProtocol(page);
    await page.goto('/home/list');
    await expect(page.locator('.gap-row', { hasText: `${free}/${year} ΚΕΝΟ` })).toHaveCount(0);
  } finally {
    await setSetting(page, 'protocolValidate', previousValidate);
  }
});

test('pager above and below the list: previous, next and any page (R2e)', async ({ page }) => {
  await login(page, 'admin');
  const previousRows = await setSetting(page, 'showRowsInPage', 5);   // the least allowed: several pages whatever the demo holds
  try {
    await page.goto('/home/list');
    const top = page.locator('#pager-top');
    const bottom = page.locator('#pager-bottom');
    await expect(top).toBeVisible();
    await expect(bottom).toBeVisible();
    await expect(top.locator('[data-pager=previous]')).toBeDisabled();
    const pages = await top.locator('[data-pager=select] option').count();
    expect(pages).toBeGreaterThan(2);
    // both on the right: they end where the list ends (owner, 2026-11-19)
    const listRight = await page.locator('.protocol-list .card').evaluate((el) => el.getBoundingClientRect().right);
    for (const pager of [top, bottom]) {
      const right = await pager.evaluate((el) => Math.max(...[...el.children].map((c) => c.getBoundingClientRect().right)));
      expect(Math.abs(right - listRight)).toBeLessThan(2);
    }
    await evidence(page, 'r2e-pager-top', { clip: await top.boundingBox() });

    const firstRow = await page.locator('.protocol-row').first().getAttribute('data-id');
    await top.locator('[data-pager=next]').click();
    await expect(page).toHaveURL(/[?&]page=2/);
    await expect(top.locator('[data-pager=select]')).toHaveValue('2');
    await expect(bottom.locator('[data-pager=select]')).toHaveValue('2');
    await expect(page.locator('.protocol-row').first()).not.toHaveAttribute('data-id', firstRow);

    // any page from the list, from the lower pager: the list goes there and its top comes into view
    await bottom.locator('[data-pager=select]').selectOption(String(pages));
    await expect(page).toHaveURL(new RegExp(`[?&]page=${pages}`));
    await expect(top.locator('[data-pager=select]')).toHaveValue(String(pages));
    await expect(top.locator('[data-pager=next]')).toBeDisabled();
    await expect(top).toBeInViewport();

    await bottom.locator('[data-pager=previous]').click();
    await expect(top.locator('[data-pager=select]')).toHaveValue(String(pages - 1));
    await evidence(page, 'r2e-pager-bottom', { clip: await bottom.boundingBox() });

    await page.reload();   // the page is in the address
    await expect(top.locator('[data-pager=select]')).toHaveValue(String(pages - 1));
  } finally {
    await setSetting(page, 'showRowsInPage', previousRows);
  }
});

test('a date out of order: a warning in the form, ⚠ with its explanation in the list (R2f)', async ({ page }) => {
  await login(page, 'admin');
  const previous = await setSetting(page, 'allowWriterChangeDate', 1);
  const pad = (n) => String(n).padStart(2, '0');
  const ddmmyyyy = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
  const early = new Date();
  early.setDate(early.getDate() - 20);   // earlier than the last protocol of the demo
  try {
    await page.goto('/home');
    const thema = `R2f λάθος ημερομηνία ${Date.now()}`;
    await fillProtocol(page, { thema, fakelos: 'first', protocoldate: ddmmyyyy(early) });
    const warning = page.locator('#date-warning');
    await expect(warning).toContainText('δεν ταιριάζει με τη σειρά των αριθμών');
    await expect(warning).toContainText('ο προηγούμενος');
    await evidence(page, 'r2f-form-warning', { clip: await page.locator('.number-head-date').evaluate((el) => {
      const r = el.closest('.card, form').getBoundingClientRect();
      return { x: r.x, y: Math.max(0, r.y), width: r.width, height: 240 };
    }) });
    await saveProtocol(page);   // a warning does not stop the save
    const id = page.url().match(/\/home\/(\d+)$/)[1];

    await page.goto('/home/list');
    const row = page.locator(`.protocol-row[data-id="${id}"]`);
    await expect(row).toHaveClass(/date-flagged/);
    await row.locator('.date-flag').hover();
    const tip = page.locator('.tooltip.date-flag-tooltip');
    await expect(tip).toContainText('Μήπως γράφτηκε λάθος;');
    await page.waitForFunction(() => Number(getComputedStyle(document.querySelector('.tooltip.date-flag-tooltip')).opacity) >= 0.85);   // faded in (Bootstrap stops at 0.9)
    await evidence(page, 'r2f-list-flag', { clip: await row.evaluate((el) => {
      const r = el.getBoundingClientRect();
      return { x: r.x, y: Math.max(0, r.y - 70), width: r.width, height: r.height + 140 };
    }) });
    await expect(page).toHaveURL(/\/home\/list/);   // hovering the ⚠ does not open the protocol

    // corrected: today → no warning, no flag
    await page.goto(`/home/${id}`);
    await page.locator('#f-protocoldate').fill(ddmmyyyy(new Date()));
    await page.locator('#f-protocoldate').press('Tab');
    await expect(page.locator('#date-warning')).toHaveCount(0);
    await page.locator('#protocol-form button[type=submit]').first().click();   // same address: wait for the message
    await expect(page.locator('main > .alert', { hasText: 'Οι αλλαγές αποθηκεύτηκαν' })).toBeVisible();
    await page.goto('/home/list');
    await expect(page.locator(`.protocol-row[data-id="${id}"]`)).not.toHaveClass(/date-flagged/);
  } finally {
    await setSetting(page, 'allowWriterChangeDate', previous);
  }
});

test('a row\'s actions are under ⋮: copy and receipt (R2h)', async ({ page, context }) => {
  await login(page, 'admin');
  await page.goto('/home/list');
  // a row followed by one with attachment links, which the open menu covers (review of R2h)
  const id = await page.evaluate(() => [...document.querySelectorAll('.protocol-row')]
    .find((tr) => tr.nextElementSibling?.matches('.protocol-row') && tr.nextElementSibling.querySelector('a.z-2[href*="/download/"]'))?.dataset.id);
  expect(id, 'a row followed by one with attachments in the demo').toBeTruthy();
  const row = page.locator(`.protocol-row[data-id="${id}"]`);
  await expect(row.getByRole('link', { name: 'Αντιγραφή ως νέο' })).toBeHidden();   // only in the menu

  await row.locator('.row-actions').click();
  const menu = row.locator('.dropdown-menu');
  await expect(menu).toBeVisible();
  await expect(menu.locator('[data-action=copy]')).toBeVisible();
  await expect(menu.locator('[data-action=receipt]')).toBeVisible();
  await expect(page).toHaveURL(/\/home\/list/);   // the ⋮ does not open the protocol
  // review of R2h: where the open menu overlaps a later row's link, the menu is on top (it gets the click)
  const covered = await menu.evaluate((m) => {
    const box = m.getBoundingClientRect();
    const out = [];
    for (const a of document.querySelectorAll('.protocol-row a.z-2, .protocol-row .z-2 a')) {
      if (m.contains(a)) continue;
      const r = a.getBoundingClientRect();
      const x1 = Math.max(box.left, r.left), x2 = Math.min(box.right, r.right);
      const y1 = Math.max(box.top, r.top), y2 = Math.min(box.bottom, r.bottom);
      if (x2 - x1 < 2 || y2 - y1 < 2) continue;
      out.push(m.contains(document.elementFromPoint((x1 + x2) / 2, (y1 + y2) / 2)));
    }
    return out;
  });
  expect(covered.length, 'the menu overlaps a link of the rows below').toBeGreaterThan(0);
  expect(covered.every(Boolean), 'the menu is above those links').toBe(true);
  const box = await row.boundingBox();
  await evidence(page, 'r2h-row-menu', { clip: { x: box.x + box.width - 700, y: Math.max(0, box.y - 40), width: 700, height: box.height + 140 } });

  const [receipt] = await Promise.all([context.waitForEvent('page'), menu.locator('[data-action=receipt]').click()]);
  await receipt.waitForLoadState();
  await expect(receipt.locator('#receipt')).toBeVisible();
  await receipt.close();

  await page.keyboard.press('Escape');
  await row.locator('.row-actions').click();
  await menu.locator('[data-action=copy]').click();
  await expect(page).toHaveURL(new RegExp(`/home/${id}/1$`));
});

test('rows in alternating colours, fixed column widths, each row short until ▾ or Αναλυτικά (R2i)', async ({ page }) => {
  const subject = `R2i μακρύ θέμα ${Date.now()} ` + 'με πολλές λέξεις για να πιάσει αρκετές γραμμές στη στήλη του θέματος '.repeat(6);
  await login(page, 'admin');
  await page.evaluate(() => { try { localStorage.removeItem('protocolList.detailed'); } catch (e) {} });
  await page.goto('/home');
  await fillProtocol(page, { fakelos: 'first', thema: subject });
  await saveProtocol(page);
  const id = page.url().match(/\/home\/(\d+)$/)[1];
  await page.goto('/home/list');

  // alternating colours: two rows in a row differ
  const colours = await page.evaluate(() => [...document.querySelectorAll('.protocol-row')].slice(0, 4)
    .map((tr) => getComputedStyle(tr.querySelector('td'), null).boxShadow + getComputedStyle(tr.querySelector('td')).backgroundColor));
  expect(colours[0]).not.toEqual(colours[1]);
  expect(colours[0]).toEqual(colours[2]);
  expect(await page.locator('.protocol-table').evaluate((t) => getComputedStyle(t).tableLayout)).toBe('fixed');

  const row = page.locator(`.protocol-row[data-id="${id}"]`);
  const subjectCell = row.locator('.row-clamp').first();
  const lineHeight = await subjectCell.evaluate((el) => parseFloat(getComputedStyle(el).lineHeight));
  const short = (await subjectCell.boundingBox()).height;
  expect(short, 'two lines at most').toBeLessThan(lineHeight * 2.5);
  await expect(row.locator('.detailed')).toBeHidden();
  await evidence(page, 'r2i-list', { clip: { x: 0, y: 0, width: 1440, height: 720 } });

  // ▾ opens this row only, without opening the protocol; again closes it
  const toggle = row.locator('.row-toggle');
  await toggle.click();
  await expect(page).toHaveURL(/\/home\/list/);
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  expect((await subjectCell.boundingBox()).height).toBeGreaterThan(short * 1.5);
  await expect(row.locator('.detailed')).toBeVisible();
  await expect(page.locator('.protocol-row.row-open')).toHaveCount(1);
  await row.scrollIntoViewIfNeeded();
  await evidence(page, 'r2i-row-open');
  await toggle.click();
  await expect(row.locator('.detailed')).toBeHidden();
  expect((await subjectCell.boundingBox()).height).toBeLessThan(lineHeight * 2.5);

  // Αναλυτικά opens every row (and hides the ▾)
  await page.locator('#list-detailed').check();
  await expect(page.locator('.protocol-row:not(.row-open)')).toHaveCount(0);
  await expect(toggle).toBeHidden();
  await page.locator('#list-detailed').uncheck();

  // review of R2i: on medium screens the subject keeps a readable width
  for (const width of [768, 992, 1024]) {
    await page.setViewportSize({ width, height: 800 });
    const subjectWidth = (await page.locator('.protocol-table thead th').nth(2).boundingBox()).width;
    expect(subjectWidth, `subject column at ${width} px`).toBeGreaterThan(200);
    const fits = await page.locator('.protocol-table').evaluate((t) => t.getBoundingClientRect().right <= t.parentElement.getBoundingClientRect().right + 1);
    expect(fits, `the table inside its frame at ${width} px`).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth), `no sideways scroll at ${width} px`).toBeLessThanOrEqual(width);
  }
  await evidence(page, 'r2i-list-768', { clip: { x: 0, y: 0, width: 1024, height: 600 } });
});
