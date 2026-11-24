// R3h: direct links /protocol/N (this year) and /protocol/N/YYYY open the protocol's form; a missing / forbidden /
// malformed link gets one «Δεν υπάρχει» page; the form's «Σύνδεσμος» pill copies the link.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

/** a new protocol saved by the admin; returns its number, year and form url */
async function newProtocol(page) {
  await page.goto('/home');
  await fillProtocol(page, { thema: `R3h σύνδεσμος ${Date.now()}`, fakelos: 'first' });
  await saveProtocol(page);
  const [, num, year] = (await page.title()).match(/Πρωτόκολλο (\d+)\/(\d{4})/);
  return { num, year, form: new URL(page.url()).pathname };
}

test('a direct link opens the form; «Σύνδεσμος» copies it (R3h)', async ({ page, context }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await login(page, 'admin');
  const { num, year, form } = await newProtocol(page);

  await page.goto(`/protocol/${num}/${year}`);
  await expect(page).toHaveURL(new RegExp(`${form}$`));
  await page.goto(`/protocol/${num}`);   // this year
  await expect(page).toHaveURL(new RegExp(`${form}$`));

  const pill = page.locator('[data-action=link]');
  await expect(pill).toHaveText(/Σύνδεσμος/);
  await pill.click();
  await expect(pill).toHaveText(/Αντιγράφηκε/);
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(new URL(`/protocol/${num}/${year}`, page.url()).href);
  await evidence(page, 'r3h-link-copied', { clip: { x: 0, y: 0, width: 1440, height: 420 } });
  await expect(pill).toHaveText(/Σύνδεσμος/, { timeout: 5000 });   // the label comes back

  // a guest logs in first and then lands on the protocol
  await page.context().clearCookies();
  await page.goto(`/protocol/${num}/${year}`);
  await expect(page).toHaveURL(/\/login$/);
  await page.fill('input[name=username]', 'admin');
  await page.fill('input[name=password]', 'password');
  await page.click('button[type=submit]');
  await expect(page).toHaveURL(new RegExp(`${form}$`));
});

test('missing and malformed links get the same «Δεν υπάρχει» page (R3h)', async ({ page }) => {
  await login(page, 'writer');
  const missing = await page.goto('/protocol/999999/1999');
  expect(missing.status()).toBe(404);
  const card = page.locator('#protocol-missing');
  await expect(card).toContainText('Δεν υπάρχει');
  await expect(card).toContainText('999999/1999');
  await evidence(page, 'r3h-missing', { clip: { x: 0, y: 0, width: 1440, height: 480 } });

  await card.locator('[data-action=search]').click();   // searches that number
  await expect(page).toHaveURL(/\/search\?q=/);

  const bad = await page.goto('/protocol/abc');
  expect(bad.status()).toBe(404);
  await expect(page.locator('#protocol-missing')).toContainText('Δεν υπάρχει');
});
