// A01: «Έλεγχοι» (/checks): the integrity checks over a date range, opened from the user menu and from the list's
// gaps notice. The demo data has deliberate gaps (A02), so this year has at least one missing number.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol, setSetting } = require('./helpers');

test('the checks page: a date range, the gaps with their neighbours (A01)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/home/list');
  await page.locator('nav.app-topbar #nav-user').click();
  await page.locator('.user-menu-manage').getByRole('link', { name: 'Έλεγχοι πρωτοκόλλου' }).click();
  await expect(page).toHaveURL(/\/checks/);
  await expect(page.locator('h1')).toHaveText('Έλεγχοι πρωτοκόλλου');
  await expect(page.locator('#checks-summary')).toBeVisible();

  // the last 30 days by default; «Φέτος» checks from 1 January
  const year = new Date().getFullYear();
  await page.locator('#checks-year').click();
  await expect(page.locator('#checks-from')).toHaveValue(`01/01/${year}`);
  const gaps = page.locator('#check-gaps');
  await expect(gaps).not.toHaveAttribute('data-count', '0');
  await expect(page.locator('#checks-summary')).toContainText('ευρήμα');
  const first = gaps.locator('.checks-finding').first();
  await expect(first.locator('.checks-ref')).toContainText(`/${year}`);
  await expect(first.locator('.checks-message')).toContainText(/Λείπ(ει|ουν)/);
  await evidence(page, 'a01-checks', { fullPage: true });

  // a typed range is kept in the address
  await page.locator('#checks-from').fill(`1/1/${year}`);
  await page.locator('#checks-to').fill(`31/1/${year}`);
  await page.locator('#checks-run').click();
  await expect(page).toHaveURL(new RegExp(`from=01%2F01%2F${year}`));
  await expect(page.locator('#checks-to')).toHaveValue(`31/01/${year}`);

  // a wrong range is refused
  await page.locator('#checks-from').fill(`31/1/${year}`);
  await page.locator('#checks-to').fill(`1/1/${year}`);
  await page.locator('#checks-run').click();
  await expect(page.locator('#checks-range .invalid-feedback').filter({ hasText: 'πριν από την αρχική' })).toBeVisible();
});

test('a date out of order on the checks page and in the list\'s line (A03)', async ({ page }) => {
  await login(page, 'admin');
  const previous = await setSetting(page, 'allowWriterChangeDate', 1);
  const pad = (n) => String(n).padStart(2, '0');
  const ddmmyyyy = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
  const early = new Date();
  early.setDate(early.getDate() - 20);   // earlier than the last protocol of the demo
  try {
    await page.goto('/home');
    await fillProtocol(page, { thema: `A03 λάθος ημερομηνία ${Date.now()}`, fakelos: 'first', protocoldate: ddmmyyyy(early) });
    await saveProtocol(page);
    const id = page.url().match(/\/home\/(\d+)$/)[1];

    // the list's line counts it and links to the page
    await page.goto('/home/list');
    const notice = page.locator('#checks-notice');
    await expect(notice).toContainText(/Έλεγχοι:\s+\d+ ευρήμα/);
    await notice.getByRole('link', { name: 'Προβολή' }).click();
    await expect(page).toHaveURL(/\/checks/);

    const dates = page.locator('#check-dates');
    await expect(dates.locator('h2, .card-header').first()).toContainText('Ημερομηνίες εκτός σειράς');
    const finding = dates.locator('.checks-finding').filter({ has: page.locator(`a[href$="/home/${id}"]`) });
    await expect(finding.locator('.checks-message')).toContainText('δεν ταιριάζει με τη σειρά των αριθμών');
    await expect(finding.locator('.checks-message')).toContainText('ο προηγούμενος');
    await evidence(page, 'a03-dates', { fullPage: true, clip: await dates.evaluate((el) => {
      const r = el.getBoundingClientRect();   // page coordinates (the card may be below the fold)
      return { x: r.x, y: Math.max(0, r.y + window.scrollY - 10), width: r.width, height: Math.min(r.height + 20, 600) };
    }) });

    // the finding opens the protocol, where the date is corrected; then the check has it no more
    await finding.locator(`a[href$="/home/${id}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/home/${id}$`));
    await page.locator('#f-protocoldate').fill(ddmmyyyy(new Date()));
    await page.locator('#f-protocoldate').press('Tab');
    await page.locator('#protocol-form button[type=submit]').first().click();
    await expect(page.locator('main > .alert', { hasText: 'Οι αλλαγές αποθηκεύτηκαν' })).toBeVisible();
    await page.goto('/checks');
    await expect(page.locator('#check-dates')).toBeVisible();
    await expect(page.locator(`#check-dates a[href$="/home/${id}"]`)).toHaveCount(0);
  } finally {
    await setSetting(page, 'allowWriterChangeDate', previous);
  }
});

test('a pending protocol on the checks page (A04)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  const thema = `A04 Εκκρεμότητα: αναμένεται το έγγραφο ${Date.now()}`;
  await fillProtocol(page, { thema, fakelos: 'first' });
  await saveProtocol(page);
  const id = page.url().match(/\/home\/(\d+)$/)[1];

  await page.goto('/checks');
  const pending = page.locator('#check-pending');
  await expect(pending.locator('h2')).toHaveText('Εκκρεμότητες');
  const finding = pending.locator('.checks-finding').filter({ has: page.locator(`a[href$="/home/${id}"]`) });
  await expect(finding.locator('.checks-message')).toHaveText('Εκκρεμότητα στο θέμα.');
  await expect(finding.locator('.checks-subject')).toHaveText(thema);
  await evidence(page, 'a04-pending-card', { fullPage: true, clip: await pending.evaluate((el) => {
    const r = el.getBoundingClientRect();   // page coordinates (the card may be below the fold)
    return { x: r.x, y: Math.max(0, r.y + window.scrollY - 10), width: r.width, height: 160 };
  }) });
  await evidence(page, 'a04-pending-finding', { fullPage: true, clip: await finding.evaluate((el) => {
    const r = el.getBoundingClientRect();   // the dev data has many protocols without a folder: the new one's line
    return { x: r.x, y: r.y + window.scrollY - 6, width: r.width, height: r.height + 12 };
  }) });
});

test('the list\'s gaps notice links to the checks; readers have no checks (A01)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/home/list');
  const notice = page.locator('#numberGaps');
  if (await notice.count()) {
    await notice.getByRole('link', { name: 'Έλεγχοι' }).click();
    await expect(page).toHaveURL(/\/checks/);
  }

  await page.context().clearCookies();
  await login(page, 'reader');
  const response = await page.goto('/checks');
  expect(response.status()).toBe(403);
});
