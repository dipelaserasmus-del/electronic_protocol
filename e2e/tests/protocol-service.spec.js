// R1a: saving a protocol goes through one service. Checked here in the (old) protocol form:
// - what users type is stored and shown exactly, including -- ' " ; % _ \ < > & , Greek and emoji;
// - a number held by an ΕΛΕΥΘΕΡΟ placeholder is taken over only after the user confirms it.
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting } = require('./helpers');

let previousValidate;

test.beforeEach(async ({ page }) => {
  await login(page, 'admin');
  previousValidate = await setSetting(page, 'protocolValidate', 0);   // the administrator may choose the number
});

test.afterEach(async ({ page }) => {
  await page.context().clearCookies();
  await login(page, 'admin');
  await setSetting(page, 'protocolValidate', previousValidate);
});

// the old form hides the number while more than one user is online: set it as typing would
async function typeNumber(page, num) {
  await page.$eval('#protocolnum', (el, v) => { el.value = v; el.dispatchEvent(new Event('change', { bubbles: true })); }, num);
}

// the form may come back to the same address (/home with messages): wait for the POST's page, not for a URL
async function saveForm(page) {
  // the old page asks the server for the inbox count when it loads; a reply arriving between the POST and the
  // redirected page used up the flashed messages and typed values (a property of the old page, replaced in R1b)
  await page.waitForLoadState('networkidle');
  const posted = page.waitForResponse((r) => r.request().method() === 'POST' && /\/home(\/\d+)?$/.test(r.url()), { timeout: 15000 });
  const submitted = await page.evaluate(() => formSubmit());
  expect(submitted, 'the old form\'s own checks let it submit').not.toBe(false);
  const response = await posted;
  await page.waitForURL(response.headers()['location']);
  await page.waitForLoadState('load');
}

test('special characters are stored and shown exactly as typed (R1a)', async ({ page }) => {
  const subject = `R1a -- Ν' Αθηνών "εισαγωγικά" ; 50% _x_ \\ <b>όχι έντονα</b> & 📎 ${Date.now()}`;
  await page.goto('/home');
  await page.fill('#thema', subject);
  await saveForm(page);
  await expect(page).toHaveURL(/\/home\/\d+$/);

  await expect(page.locator('#thema')).toHaveValue(subject);
  await evidence(page, 'r1a-special-characters-form', { clip: { x: 0, y: 0, width: 1440, height: 520 } });

  await page.goto('/home/list');
  await expect(page.locator('body')).toContainText(subject);
  expect(await page.locator('b', { hasText: 'όχι έντονα' }).count()).toBe(0);   // shown as text, not as HTML
});

test('number held by ΕΛΕΥΘΕΡΟ: confirm, then the new protocol takes its place (R1a)', async ({ page }) => {
  const subject = `R1a αντικατάσταση ${Date.now()}`;

  // an administrator keeps a number free: one above the next free number, so never used before
  await page.goto('/home');
  const proposed = Number(await page.locator('input[name=protocolnum_proposed]').inputValue());
  const num = String(proposed + 100 + (Date.now() % 100));
  await typeNumber(page, num);
  await page.fill('#thema', 'ΕΛΕΥΘΕΡΟ');
  await saveForm(page);

  // a new protocol with that number: the form asks first
  await page.goto('/home');
  await typeNumber(page, num);
  await page.fill('#thema', subject);
  await saveForm(page);
  const box = page.locator('#replacePlaceholderDiv');
  await expect(box).toContainText('ΕΛΕΥΘΕΡΟ');
  await expect(page.locator('#thema')).toHaveValue(subject);   // nothing typed is lost
  await evidence(page, 'r1a-placeholder-confirm', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

  await box.locator('input[type=checkbox]').check();
  await saveForm(page);
  await expect(page).toHaveURL(/\/home\/\d+$/);
  await expect(page.locator('#protocolnum')).toHaveValue(num);
  await expect(page.locator('#thema')).toHaveValue(subject);
  await expect(page.locator('body')).toContainText('Αντικαταστάθηκε');
  await evidence(page, 'r1a-placeholder-replaced', { clip: { x: 0, y: 0, width: 1440, height: 420 } });
});
