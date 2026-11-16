// R5a: inbox from the mail cache; sender names split inside a Greek letter; HTML preview behind login.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

/** The inbox panel (form + message) of the synthetic e-mail whose text contains `text`, on whichever page it is. */
async function findEmail(page, text) {
  for (const p of [1, 2, 3, 4]) {
    await page.goto(`/viewEmails?imap_page=${p}`);
    const panel = page.locator('div.panel.col-md-12:has(> form[id^="frm"])', { hasText: text });   // one e-mail each
    if (await panel.count()) return panel;
  }
  throw new Error(`e-mail "${text}" not found in the inbox`);
}

test('sender name split inside a Greek letter is shown without ?? (R5a)', async ({ page }) => {
  await login(page, 'admin');
  const panel = await findEmail(page, 'συνέλευση του συλλόγου');   // synthetic e-mail 09
  await expect(panel).toContainText('Σύλλογος Διδασκόντων Γυμνασίου Δοκιμής');
  expect(await panel.innerText()).not.toContain('??');
  await panel.scrollIntoViewIfNeeded();
  await evidence(page, 'r5a-split-sender', { clip: await panel.boundingBox() });   // this e-mail only
});

test('HTML preview comes from a logged-in route in a sandbox; no public copies (R5a)', async ({ page, request }) => {
  await login(page, 'admin');
  const panel = await findEmail(page, 'Πρόσκληση σε επιμορφωτική ημερίδα');   // synthetic e-mail 01 (HTML part)
  const frame = panel.locator('iframe[id^="ifr"]');
  await expect(frame).toHaveAttribute('src', /\/viewEmailHtml\/\d+$/);
  await expect(frame).toHaveAttribute('sandbox', /allow-same-origin/);
  await expect(panel.frameLocator('iframe[id^="ifr"]').locator('b')).toHaveText('συνημμένα');
  await expect.poll(async () => (await frame.boundingBox()).height).toBeGreaterThan(30);   // sized to its content

  const src = await frame.getAttribute('src');
  const preview = await page.request.get(src);
  expect(preview.status()).toBe(200);
  expect(preview.headers()['content-security-policy']).toMatch(/^sandbox/);

  // not logged in: no preview, and the old public copies (public/tmp/u<id>/<uid>.eml) no longer exist
  const anonymous = await request.get(src, { maxRedirects: 0 });
  expect(anonymous.status()).toBe(302);
  const uid = src.match(/(\d+)$/)[1];
  for (const ext of ['eml', 'html']) {
    expect((await request.get(`/tmp/u1/${uid}.${ext}`)).status()).toBe(404);
  }
});
