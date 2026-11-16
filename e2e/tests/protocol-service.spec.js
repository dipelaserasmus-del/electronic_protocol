// R1a: saving a protocol goes through one service. Checked here in the protocol form:
// - what users type is stored and shown exactly, including -- ' " ; % _ \ < > & , Greek and emoji;
// - a number held by an ΕΛΕΥΘΕΡΟ placeholder is taken over only after the user confirms it.
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol, notice } = require('./helpers');

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

test('special characters are stored and shown exactly as typed (R1a)', async ({ page }) => {
  const subject = `R1a -- Ν' Αθηνών "εισαγωγικά" ; 50% _x_ \\ <b>όχι έντονα</b> & 📎 ${Date.now()}`;
  await page.goto('/home');
  await fillProtocol(page, { thema: subject });
  await saveProtocol(page);

  await expect(page.locator('#f-thema')).toHaveValue(subject);
  await evidence(page, 'r1a-special-characters-form', { clip: { x: 0, y: 0, width: 1440, height: 520 } });

  await page.goto('/home/list');
  await expect(page.locator('body')).toContainText(subject);
  expect(await page.locator('b', { hasText: 'όχι έντονα' }).count()).toBe(0);   // shown as text, not as HTML
});

test('number held by ΕΛΕΥΘΕΡΟ: confirm, then the new protocol takes its place (R1a)', async ({ page }) => {
  const subject = `R1a αντικατάσταση ${Date.now()}`;

  // an administrator keeps a number free: one above the next free number (shown in grey), so never used before
  await page.goto('/home');
  const next = Number(await page.locator('#f-protocolnum').getAttribute('placeholder'));
  const num = String(next + 100 + (Date.now() % 100));
  await fillProtocol(page, { protocolnum: num, thema: 'ΕΛΕΥΘΕΡΟ' });
  await saveProtocol(page);

  // a new protocol with that number: the form says so at once, and asks before saving
  await page.goto('/home');
  await fillProtocol(page, { protocolnum: num, thema: subject });
  await expect(page.locator('#number-status')).toContainText('Κρατημένος ως «ΕΛΕΥΘΕΡΟ»');
  await saveProtocol(page, { expectSaved: false });
  await expect(page.locator('#protocol-form')).toContainText('Επιβεβαιώστε ότι το νέο πρωτόκολλο παίρνει τη θέση του.');
  await expect(page.locator('#f-thema')).toHaveValue(subject);   // nothing typed is lost
  await evidence(page, 'r1a-placeholder-confirm', { clip: { x: 0, y: 0, width: 1440, height: 520 } });

  await page.locator('#f-replace').check();
  await saveProtocol(page);
  await expect(page.locator('#f-protocolnum')).toHaveValue(num);
  await expect(page.locator('#f-thema')).toHaveValue(subject);
  expect(await notice(page)).toContain('Αντικαταστάθηκε');
  await evidence(page, 'r1a-placeholder-replaced', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});
