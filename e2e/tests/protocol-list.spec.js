// R2: the rebuilt protocol list: filters, sorting, header that stays visible while scrolling, "Αναλυτικά" remembered.
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol } = require('./helpers');

test('filters, sorting and the sticky header (R2)', async ({ page }) => {
  await login(page, 'anathetis');
  await page.goto('/home/list');
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων');
  await expect(page.locator('.protocol-row').first()).toBeVisible();
  await evidence(page, 'r2-list');

  // filters: "to handle" for all users
  await page.locator('[data-filter=d]').click();
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων · προς Διεκπεραίωση');
  await page.locator('#list-who').selectOption('all');
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων · Όλοι οι χρήστες · προς Διεκπεραίωση');
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
  // R2t: right under the list's head, which stays under the top bar
  const head = await page.locator('#list-head').boundingBox();
  expect(Math.abs(header.y - (head.y + head.height))).toBeLessThan(2);
  await evidence(page, 'r2-sticky-header');
});

// R2t (owner, 2026-11-27): the title, the gaps notice, the filters and the pager stay in place under the top bar while
// the list scrolls; the column titles stay right under them; on a low window they scroll away as before
test('the list\'s head stays under the top bar, the column titles under it (R2t)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  await page.goto('/home/list');
  await expect(page.locator('.protocol-row').first()).toBeVisible();
  const head = page.locator('#list-head');
  const bar = await page.locator('.app-topbar').boundingBox();
  const before = await head.boundingBox();
  await expect(head).not.toHaveClass(/is-stuck/);

  await page.mouse.wheel(0, 3000);
  await expect(head).toHaveClass(/is-stuck/);
  const stuck = await head.boundingBox();
  expect(Math.abs(stuck.y - (bar.y + bar.height))).toBeLessThan(2);      // under the top bar
  expect(Math.abs(stuck.height - before.height)).toBeLessThan(2);         // all of it: title, filters, pager
  for (const part of ['#list-title', '[data-filter=d]', '#list-detailed', '#pager-top']) {
    await expect(page.locator(part).first()).toBeInViewport();
  }
  const th = await page.locator('.protocol-table thead th').first().boundingBox();
  expect(Math.abs(th.y - (stuck.y + stuck.height))).toBeLessThan(2);      // the column titles right under it
  // a row going under it is covered by it (the head is on top), so the filters stay clickable
  const hit = await page.evaluate(({ x, y }) => document.elementFromPoint(x, y)?.closest('#list-head') !== null,
    { x: stuck.x + 20, y: stuck.y + stuck.height - 4 });
  expect(hit).toBe(true);
  await page.locator('[data-filter=d]').click();
  await expect(page.locator('#list-title')).toContainText('προς Διεκπεραίωση');
  await page.locator('[data-filter=all]').click();   // the whole list again (the filtered one may be too short to scroll)
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων');
  await page.mouse.wheel(0, 1500);
  await expect(head).toHaveClass(/is-stuck/);
  await evidence(page, 'r2t-scrolled', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

  // review of R2t: the lower pager brings the list's first rows into view (the upper pager is always in view now)
  await page.locator('#pager-bottom').scrollIntoViewIfNeeded();
  expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(500);
  await page.locator('#pager-bottom [data-pager=next]').click();
  await expect(page).toHaveURL(/[?&]page=2/);
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBeLessThan(5);
  await expect(page.locator('.protocol-row').first()).toBeInViewport();

  // a low window: it scrolls away, the column titles under the top bar as before
  await page.setViewportSize({ width: 1440, height: 560 });
  await page.mouse.wheel(0, 800);
  await expect.poll(async () => (await head.boundingBox()).y).toBeLessThan(0);
  await expect.poll(async () => (await page.locator('.protocol-table thead th').first().boundingBox()).y).toBeLessThan(bar.y + bar.height + 2);
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
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων · Συγγραφέας Δοκιμών');
  await expect(page.locator('.protocol-row', { hasText: subject })).toBeVisible();
  const total = Number((await page.locator('text=Σύνολο:').innerText()).replace(/\D/g, ''));
  await who.selectOption('me');
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων');
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
    // a ΚΕΝΟ row spans only the columns shown (it made the hidden ones take the subject's room; CI's data has them)
    const spans = await page.evaluate(() => {
      const shown = [...document.querySelectorAll('.protocol-table thead th')].filter((th) => getComputedStyle(th).display !== 'none').length;
      return [...document.querySelectorAll('.protocol-table [data-full-row]')].map((td) => td.colSpan === shown);
    });
    expect(spans.every(Boolean), `full rows span the shown columns at ${width} px`).toBe(true);
    const fits = await page.locator('.protocol-table').evaluate((t) => t.getBoundingClientRect().right <= t.parentElement.getBoundingClientRect().right + 1);
    expect(fits, `the table inside its frame at ${width} px`).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth), `no sideways scroll at ${width} px`).toBeLessThanOrEqual(width);
  }
  await evidence(page, 'r2i-list-768', { clip: { x: 0, y: 0, width: 1024, height: 600 } });

  // review of R2i: switching to the narrow page width hides Από / Προς at once; a ΚΕΝΟ row follows without a reload.
  // The list here may have no ΚΕΝΟ row, so one is added as the list draws it.
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.evaluate(() => {
    const tr = document.createElement('tr');
    tr.className = 'gap-row';
    tr.innerHTML = '<td colspan="7" data-full-row>ΚΕΝΟ</td>';
    document.querySelector('.protocol-table tbody').prepend(tr);
  });
  const shownSpan = () => page.evaluate(() => {
    const shown = [...document.querySelectorAll('.protocol-table thead th')].filter((th) => getComputedStyle(th).display !== 'none').length;
    return [shown, document.querySelector('.protocol-table [data-full-row]').colSpan];
  });
  const bar = page.locator('nav.app-topbar');
  // R3j: the narrow page's own width may have been set (− / +): this test is about the default narrow width
  await page.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    await fetch('/preferences', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ key: 'narrowWidth', value: null }) });
    document.body.style.removeProperty('--narrow-width');
  });
  await bar.locator('#nav-user').click();
  await bar.locator('[data-preference="width"][data-value="narrow"]').click();
  try {
    await expect(page.locator('body')).toHaveClass(/layout-narrow/);
    await expect.poll(async () => { const [shown, span] = await shownSpan(); return shown === span && shown < 7; }).toBe(true);
  } finally {
    await bar.locator('#nav-user').click();
    await bar.locator('[data-preference="width"][data-value="full"]').click();
    await expect(page.locator('body')).not.toHaveClass(/layout-narrow/);
  }
  await expect.poll(async () => { const [shown, span] = await shownSpan(); return shown === span; }).toBe(true);
});

test('assignments are pills: ✎ for Διεκπεραίωση, 👁 for Ενημέρωση (R2j)', async ({ page }) => {
  const subject = `R2j ανάθεση ${Date.now()}`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { fakelos: 'first', thema: subject });
  await page.locator('#f-handle').selectOption({ label: 'Συγγραφέας Δοκιμών' });
  await page.locator('#f-inform').selectOption({ label: 'Αναγνώστης Δοκιμών' });
  await saveProtocol(page);
  const id = page.url().match(/\/home\/(\d+)$/)[1];
  await page.goto('/home/list');
  const row = page.locator(`.protocol-row[data-id="${id}"]`);
  const handle = row.locator('.assignee-pill[data-type=d]');
  const inform = row.locator('.assignee-pill[data-type=e]');
  await expect(handle).toHaveText('Διεκπεραίωση: Συγγραφέας Δοκιμών');
  await expect(handle.locator('i.bi-pencil-square')).toBeVisible();
  await row.locator('.row-toggle').click();   // R2k: a short row shows the first pill and +N; opened, all of them
  await expect(inform).toContainText('Αναγνώστης Δοκιμών');
  await expect(inform.locator('i.bi-eye')).toBeVisible();
  await expect(row).not.toContainText('ενημ.:');
  // review of R2j: the pill is above the row's link, so the mouse reaches it (its tooltip has the whole name)
  const onTop = await handle.evaluate((el) => { const b = el.getBoundingClientRect(); return el.contains(document.elementFromPoint(b.x + b.width / 2, b.y + b.height / 2)); });
  expect(onTop).toBe(true);
  await evidence(page, 'r2j-pills', { clip: { x: 0, y: 250, width: 1440, height: 260 } });
  // the menu uses the same icons
  await page.locator('nav.app-topbar').getByRole('button', { name: 'Περισσότερες: Λίστα' }).click();
  await expect(page.locator('nav.app-topbar .dropdown-menu.show a', { hasText: 'προς Διεκπεραίωση' }).first().locator('i.bi-pencil-square')).toBeVisible();
  await expect(page.locator('nav.app-topbar .dropdown-menu.show a', { hasText: 'προς Ενημέρωση' }).first().locator('i.bi-eye')).toBeVisible();
  await evidence(page, 'r2j-menu', { clip: { x: 200, y: 0, width: 500, height: 420 } });
});

test('every row two lines tall: one file and one pill with +N; Από / Προς details in a tooltip (R2k)', async ({ page }) => {
  const stamp = Date.now();
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, {
    thema: `R2k πολλά ${stamp}`, fakelos: 'first', in_num: `Φ.9/${stamp}`, in_date: '03/11/2026',
    in_topos_ekdosis: 'Δοκιμή', in_arxi_ekdosis: 'Διεύθυνση Δοκιμής με αρκετά μεγάλο όνομα', in_paraliptis: 'Διευθυντής',
  });
  await page.locator('#f-handle').selectOption([{ label: 'Συγγραφέας Δοκιμών' }, { label: 'Αναθέτων Δοκιμών' }]);
  await page.locator('#f-inform').selectOption({ label: 'Αναγνώστης Δοκιμών' });
  await page.setInputFiles('#f-uploads', [1, 2, 3].map((i) => ({ name: `R2k-αρχείο-${i}.pdf`, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n') })));
  await expect(page.locator('#protocol-form')).toContainText('R2k-αρχείο-3.pdf');
  await saveProtocol(page);
  const id = page.url().match(/\/home\/(\d+)$/)[1];
  await page.goto('/home');
  await fillProtocol(page, { thema: `R2k απλό ${stamp}`, fakelos: 'first' });
  await saveProtocol(page);
  const plainId = page.url().match(/\/home\/(\d+)$/)[1];

  await page.goto('/home/list');
  const row = page.locator(`.protocol-row[data-id="${id}"]`);
  const plain = page.locator(`.protocol-row[data-id="${plainId}"]`);
  await expect(row.locator('.more-files')).toHaveText('+2');
  await expect(row.locator('.assignee-more')).toHaveText('+2');
  await expect(row.locator('.attachment-name:visible')).toHaveCount(1);
  await expect(row.locator('.assignee-pill:visible')).toHaveCount(1);
  const [h1, h2] = [(await row.boundingBox()).height, (await plain.boundingBox()).height];
  expect(Math.abs(h1 - h2), `rows ${h1} and ${h2} px`).toBeLessThan(2);

  // the sender only; its number and date in the tooltip, without opening the protocol
  const sender = row.locator('.from-to-more').first();
  await expect(row.locator('.from-to')).not.toContainText(`Φ.9/${stamp}`, { useInnerText: true });   // only in the tooltip
  await sender.hover();
  await expect(page.locator('.tooltip')).toContainText(`Αρ. εισερχομένου: Φ.9/${stamp} Ημ/νία: 03/11/2026`);
  // R2q (owner, 2026-11-25): one line per part (the sender, the number, the date)
  const lines = await page.locator('.tooltip.from-to-tooltip .tooltip-inner').evaluate((el) => el.innerText.split('\n').map((l) => l.trim()).filter(Boolean));
  expect(lines.length).toBe(3);
  expect(lines.slice(1)).toEqual([`Αρ. εισερχομένου: Φ.9/${stamp}`, 'Ημ/νία: 03/11/2026']);
  await expect(page).toHaveURL(/\/home\/list/);
  await evidence(page, 'r2k-rows-tooltip', { clip: { x: 0, y: 230, width: 1440, height: 300 } });

  // opened: everything
  await row.locator('.row-toggle').click();
  await expect(row.locator('.attachment-name:visible')).toHaveCount(3);
  await expect(row.locator('.assignee-pill:visible')).toHaveCount(3);
  await expect(row.locator('.from-to')).toContainText(`Φ.9/${stamp}`);
  await expect(row.locator('.more-files')).toBeHidden();
});

test('the list has no «Αναζήτηση» button of its own; the search box has it (R2l)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/home/list');
  const head = page.locator('.protocol-list > div').first();
  await expect(head.getByText('Αναζήτηση', { exact: true })).toHaveCount(0);
  await expect(page.locator('#app-search-advanced')).toBeVisible();                 // Σύνθετη αναζήτηση, in the top bar
  await evidence(page, 'r2l-list-head', { clip: { x: 0, y: 0, width: 1440, height: 140 } });
});

// R2m (owner, 2026-11-25): the top bar stays in view on every page; the list is «Λίστα Πρωτοκόλλων», without its own
// «Νέο» (the top bar has it) and «Ανανέωση» (↻ next to «Λίστα»); a small ↑ at the bottom right goes back to the top
test('the top bar always in view; list title, ↻ next to Λίστα, ↑ back to the top (R2m)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 800 });
  await login(page, 'admin');
  const bar = page.locator('nav.app-topbar');

  // app-wide: scrolled down, the top bar is still at the top of the window
  for (const url of ['/home/list', '/home', '/inbox', '/keep']) {   // pages long enough to scroll in the demo (R6f: one settings section is short)
    await page.goto(url);
    await page.evaluate(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' }));
    expect(await page.evaluate(() => window.scrollY), url).toBeGreaterThan(100);
    const box = await bar.boundingBox();
    expect(Math.abs(box.y), url).toBeLessThan(1);
    await expect(bar.getByRole('link', { name: 'Νέο' }), url).toBeInViewport();
  }

  await page.goto('/home/list');
  await expect(page.locator('#list-title')).toHaveText('Λίστα Πρωτοκόλλων');
  const head = page.locator('.protocol-list > div').first();
  await expect(head.getByRole('link', { name: 'Νέο' })).toHaveCount(0);
  await expect(head.getByRole('button', { name: 'Ανανέωση' })).toHaveCount(0);

  // ↻ right after «Λίστα» (and its ▾), before «Εισερχόμενα»: refreshes the list in place
  const refresh = bar.locator('[data-list-refresh]');
  await expect(refresh).toBeVisible();
  const [lista, more, re, inbox] = await Promise.all([bar.locator('#nav-1-link'), bar.locator('#nav-1'), refresh,
    bar.getByRole('link', { name: /Εισερχόμενα/ })].map((l) => l.boundingBox()));
  expect(more.x).toBeGreaterThan(lista.x);
  expect(re.x).toBeGreaterThan(more.x);
  expect(inbox.x).toBeGreaterThan(re.x);
  const url = page.url();
  const asked = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST' && r.request().postData().includes('list-refresh'));
  await refresh.click();
  await asked;
  expect(page.url()).toBe(url);   // not a new page
  await page.waitForTimeout(700);   // the ↻ has turned
  await evidence(page, 'r2m-list-head', { clip: { x: 0, y: 0, width: 1440, height: 160 } });

  // ↑ at the bottom right, small, clear of the notepad's tab; back to the top
  const up = page.locator('[data-scroll-top]');
  await page.evaluate(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' }));
  await expect(up).toBeInViewport();
  const b = await up.boundingBox();
  expect(b.width).toBeLessThanOrEqual(44);
  expect(1440 - (b.x + b.width)).toBeLessThan(80);
  expect(800 - (b.y + b.height)).toBeLessThan(40);
  const tab = await page.locator('.notepad-tab').boundingBox();
  expect(b.x + b.width <= tab.x || b.y >= tab.y + tab.height || b.y + b.height <= tab.y).toBe(true);   // not over it
  // at the very bottom it covers nothing: not the lower pager, not the last row
  for (const el of await page.locator('.protocol-list nav, .protocol-list .protocol-row').all()) {
    const r = await el.boundingBox();
    if (!r) continue;
    const overlap = r.x < b.x + b.width && b.x < r.x + r.width && r.y < b.y + b.height && b.y < r.y + r.height;
    expect(overlap).toBe(false);
  }
  await evidence(page, 'r2m-scroll-top', { clip: { x: 1440 - 420, y: 800 - 260, width: 420, height: 260 } });
  await up.click();
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
});

// R2n (owner, 2026-11-25): a protocol without a folder is flagged in the list, as a date out of order is
test('a protocol without a folder is flagged in the list (R2n)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  const stamp = Date.now();
  await page.goto('/home');
  await fillProtocol(page, { thema: `R2n χωρίς φάκελο ${stamp}` });   // «— Χωρίς φάκελο» (on hold)
  await saveProtocol(page);
  await page.goto('/home/list');
  const row = page.locator('.protocol-row', { hasText: `R2n χωρίς φάκελο ${stamp}` });
  await expect(row).toHaveClass(/folder-flagged/);
  const flag = row.locator('td.col-folder .folder-flag, td:nth-child(2) .folder-flag').first();
  await expect(flag).toBeVisible();
  await expect(flag).toHaveText('Χωρίς φάκελο');   // R2o: in full
  await flag.hover();
  const tip = page.locator('.tooltip.date-flag-tooltip');
  await expect(tip).toContainText('δεν έχει φάκελο');
  await page.waitForFunction(() => Number(getComputedStyle(document.querySelector('.tooltip.date-flag-tooltip')).opacity) >= 0.85);
  await expect(page).toHaveURL(/\/home\/list/);   // hovering did not open the protocol
  const box = await row.boundingBox();
  await evidence(page, 'r2n-no-folder', { clip: { x: 0, y: Math.max(0, box.y - 60), width: 1440, height: box.height + 120 } });
  // a protocol with a folder: no flag
  await expect(page.locator('.protocol-row', { has: page.locator('td:nth-child(2) span[title]') }).first()).not.toHaveClass(/folder-flagged/);
});

// R2p (owner, 2026-11-25): an attachment that is only an ΑΔΑ: a pill «ΑΔΑ», then its code (the link), as R1m in the form
test('an ΑΔΑ without a file in the list: a pill and its code (R2p)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  const code = `9R2P${String(Date.now()).slice(-6)}-ΑΒΓ`;
  await page.goto('/home');
  await fillProtocol(page, { thema: `R2p ΑΔΑ ${code}`, fakelos: 'first' });
  await page.locator('#f-ada').fill(code);
  await saveProtocol(page);
  await page.goto('/home/list');
  const row = page.locator('.protocol-row', { hasText: `R2p ΑΔΑ ${code}` });
  const cell = row.locator('.attachment-name', { hasText: code });
  await expect(cell.locator('.badge')).toHaveText('ΑΔΑ');
  await expect(cell.locator('.attachment-ada')).toHaveText(code);
  const box = await row.boundingBox();
  await evidence(page, 'r2p-list-ada', { clip: { x: 0, y: box.y - 10, width: 1440, height: box.height + 20 } });
});

// R2r (owner, 2026-11-25): the e-mail's own file after the attachments, shown by its date and time
test('the e-mail file comes after the attachments, by its date and time (R2r)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  const stamp = Date.now();
  await page.goto('/home');
  await fillProtocol(page, { thema: `R2r e-mail ${stamp}`, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', [
    { name: 'email_20261112_103626.eml', mimeType: 'message/rfc822', buffer: Buffer.from('From: a@example.org\r\nSubject: x\r\n\r\nx\r\n') },
    { name: 'Άδεια.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 R2r\n') },
  ]);
  await expect(page.locator('.upload-row')).toHaveCount(2);
  await saveProtocol(page);
  await page.goto('/home/list');
  const row = page.locator('.protocol-row', { hasText: `R2r e-mail ${stamp}` });
  await row.locator('.row-toggle').click();   // opened: every file
  const names = row.locator('.attachment-name');
  await expect(names).toHaveCount(2);
  await expect(names.nth(0)).toContainText('Άδεια.pdf');
  await expect(names.nth(1)).toContainText('12/11/2026 · 10:36:26');
  await expect(names.nth(1).locator('.badge')).toHaveText('EML');
  const box = await row.boundingBox();
  await evidence(page, 'r2r-email-last', { clip: { x: 0, y: box.y - 10, width: 1440, height: box.height + 20 } });
});
