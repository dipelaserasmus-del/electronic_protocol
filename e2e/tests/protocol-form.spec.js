// R1b: the rebuilt protocol form. The number says at once whether it is free; TAB from the number goes to the date
// and typing the day completes it; an existing subject is a notice next to the field (no YES/NO dialog).
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol, notice } = require('./helpers');

test.describe('administrator with registration checks off', () => {
  let previousValidate;
  test.beforeEach(async ({ page }) => {
    await login(page, 'admin');
    previousValidate = await setSetting(page, 'protocolValidate', 0);
  });
  test.afterEach(async ({ page }) => {
    await page.context().clearCookies();
    await login(page, 'admin');
    await setSetting(page, 'protocolValidate', previousValidate);
  });

  test('number: free or "Δεσμευμένος", TAB goes to the day (R1b)', async ({ page }) => {
    await page.goto('/home');
    const number = page.locator('#f-protocolnum');
    const status = page.locator('#number-status');
    await expect(status).toContainText('ο επόμενος ελεύθερος');

    await number.fill('1');   // the demo book starts at 1
    await expect(status).toContainText('Δεσμευμένος από το');
    await evidence(page, 'r1b-number-used', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

    await number.fill(String(Number(await number.getAttribute('placeholder')) + 100 + (Date.now() % 100)));   // never used
    await expect(status).toContainText('Ελεύθερος');
    await number.press('Tab');
    await expect(page.locator('#f-protocoldate')).toBeFocused();
    await page.keyboard.type('9');
    await page.keyboard.press('Tab');
    const d = new Date();
    const expected = `09/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    await expect(page.locator('#f-protocoldate')).toHaveValue(expected);
    await evidence(page, 'r1b-number-free-date', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

    // ▶ (next day) changes the date without focusing the field: the saved date must be the shown one
    await page.locator('[data-date-input]:has(#f-protocoldate) [data-date-action=next]').click();
    const next = `10/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    await expect(page.locator('#f-protocoldate')).toHaveValue(next);
    await fillProtocol(page, { thema: `R1b ημερομηνία ${Date.now()}` });
    await saveProtocol(page);
    await expect(page.locator('#f-protocoldate')).toHaveValue(next);
  });
});

test('same subject: a notice with links, saving is not blocked (R1b)', async ({ page }) => {
  const subject = `R1b ίδιο θέμα ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'first' });
  await saveProtocol(page);
  const first = page.url();

  await page.goto('/home');
  await fillProtocol(page, { thema: subject });
  const note = page.locator('#notice-thema');
  await expect(note).toContainText('Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα');
  await expect(note.locator('a')).toHaveAttribute('href', first);
  await evidence(page, 'r1b-same-subject-notice', { clip: { x: 0, y: 0, width: 1440, height: 700 } });
  await saveProtocol(page);
  expect(await notice(page)).toContain('Καταχωρίστηκε με αριθμό');
  await evidence(page, 'r1b-saved', { fullPage: true });
});

test('reader sees the protocol read-only, without save (R1b)', async ({ page }) => {
  await login(page, 'reader');
  await page.goto('/home/list');
  await page.locator('.protocol-row a.fw-bold').first().click();
  await expect(page).toHaveURL(/\/home\/\d+$/);
  await expect(page.locator('#f-thema')).toBeDisabled();
  await expect(page.locator('#protocol-form button[type=submit]')).toHaveCount(0);
});

test('a 2 MB attachment is uploaded and saved (R1b: nginx allowed only 1 MB)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R1b μεγάλο αρχείο ${Date.now()}`, fakelos: 'first' });
  const body = Buffer.concat([Buffer.from('%PDF-1.4 R1b\n'), Buffer.alloc(2 * 1024 * 1024, 0x20), Buffer.from('\n%%EOF\n')]);
  await page.setInputFiles('#f-uploads', { name: 'σαρωμένο.pdf', mimeType: 'application/pdf', buffer: body });
  await expect(page.locator('#attachments')).toContainText('σαρωμένο.pdf');
  await saveProtocol(page);
  const link = page.locator('#attachments a[href*="/download/"]', { hasText: 'σαρωμένο.pdf' });
  await expect(link).toBeVisible();
  const file = await page.request.get(await link.getAttribute('href'));
  expect((await file.body()).length).toBe(body.length);
  await evidence(page, 'r1b-attachment-saved', { fullPage: true });
});

test('related numbers open the protocol in a new tab (R1e)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R1e σχετικό ${Date.now()}`, fakelos: 'first' });
  await saveProtocol(page);
  const first = page.url();
  const [, num, year] = (await notice(page)).match(/Καταχωρίστηκε με αριθμό (\d+)\/(\d{4})/);

  await page.goto('/home');
  await page.locator('#f-sxetiko').fill(`${num}/${year}, 999999`);
  await page.locator('#f-sxetiko').press('Tab');
  const link = page.locator('#related-links a');
  await expect(link).toHaveText(`↗ ${num}/${year}`);
  await expect(link).toHaveAttribute('href', first);
  await expect(link).toHaveAttribute('target', '_blank');
  await expect(page.locator('#related-links')).toContainText('999999');
  await evidence(page, 'r1e-related-links', { clip: { x: 0, y: 0, width: 1440, height: 700 } });
  const [tab] = await Promise.all([page.context().waitForEvent('page'), link.click()]);
  await tab.waitForLoadState();
  expect(tab.url()).toBe(first);
});
