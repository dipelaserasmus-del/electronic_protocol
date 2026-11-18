// R5a: inbox from the mail cache; sender names split inside a Greek letter; HTML preview behind login.
// R5c: the rebuilt inbox: list on the left, text/HTML sections that open and close (remembered), sizes.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

/** The message (<article>) of the synthetic e-mail whose text contains `text`, on whichever page it is. */
async function findEmail(page, text) {
  for (const p of [1, 2, 3, 4]) {
    await page.goto(`/inbox?page=${p}`);
    const message = page.locator('article.inbox-message', { hasText: text });   // one e-mail each
    if (await message.count()) return message;
  }
  throw new Error(`e-mail "${text}" not found in the inbox`);
}

test('sender name split inside a Greek letter is shown without ?? (R5a)', async ({ page }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'συνέλευση του συλλόγου');   // synthetic e-mail 09
  await expect(message).toContainText('Σύλλογος Διδασκόντων Γυμνασίου Δοκιμής');
  expect(await message.innerText()).not.toContain('??');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5a-split-sender', { clip: await message.boundingBox() });   // this e-mail only
});

test('HTML preview comes from a logged-in route in a sandbox; no public copies (R5a)', async ({ page, request }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'Πρόσκληση σε επιμορφωτική ημερίδα');   // synthetic e-mail 01 (HTML part)
  const frame = message.locator('iframe.inbox-html');
  await expect(frame).toHaveAttribute('src', /\/viewEmailHtml\/\d+$/);
  await expect(frame).toHaveAttribute('sandbox', /allow-same-origin/);
  await expect(message.frameLocator('iframe.inbox-html').locator('b')).toHaveText('συνημμένα');
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

test('attachment name cut inside a Greek letter is shown and downloaded correctly (R5a)', async ({ page }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'Διαβιβαστικό αίτησης άδειας');   // synthetic e-mail 10
  const link = message.getByRole('link', { name: 'ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ (1).pdf' });
  await expect(link).toBeVisible();
  expect(await message.innerText()).not.toContain('??');
  const file = await page.request.get(await link.getAttribute('href'));
  expect(file.status()).toBe(200);
  expect(decodeURIComponent(file.headers()['content-disposition'])).toContain('ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ (1).pdf');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5a-split-attachment-name', { clip: await message.boundingBox() });   // this e-mail only
});

test('inbox: the list on the left goes to a message; attachments show type and size (R5c)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  await page.goto('/inbox');
  const items = page.locator('#inbox-sidebar a.inbox-sidebar-item');
  const n = await items.count();
  expect(n).toBeGreaterThan(1);
  expect(n).toBe(await page.locator('article.inbox-message').count());
  await evidence(page, 'r5c-inbox', { fullPage: false });

  // the last message of the page: a click on its line in the list brings it to the top, under the top bar
  const last = items.nth(n - 1);
  const target = (await last.getAttribute('href')).slice(1);
  await last.click();
  const message = page.locator(`#${target}`);
  await expect.poll(async () => Math.round((await message.boundingBox()).y)).toBeLessThan(200);
  await expect(last).toHaveClass(/active/);
  await expect(page.locator('#inbox-sidebar')).toBeInViewport();   // the list stays in view

  // every attachment: a type label and a size
  const attachment = page.locator('.inbox-attachment').first();
  await expect(attachment.locator('.badge')).toHaveText(/^[A-ZΑ-Ω]+$/);
  await expect(attachment).toContainText(/\d+(,\d)? (B|KB|MB)/);
});

test('inbox: text and HTML open and close together, and the browser remembers it (R5c)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/inbox');
  const texts = page.locator('.inbox-text');
  await expect(texts.first()).toBeVisible();

  await page.locator('[data-part-toggle="text"]').first().click();
  await expect(texts.first()).toBeHidden();
  for (const t of await texts.all()) await expect(t).toBeHidden();   // every message's text

  await page.reload();
  await expect(texts.first()).toBeHidden();                           // remembered
  await expect(page.locator('[data-part-toggle="text"]').first()).toHaveAttribute('aria-expanded', 'false');
  await page.locator('[data-part-toggle="text"]').first().click();
  await expect(texts.first()).toBeVisible();
});

test('winmail.dat (Outlook): its text and files are shown and registered instead of it (R5b)', async ({ page }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'Outlook winmail');   // synthetic e-mail 11
  const uid = await message.getAttribute('data-uid');
  await expect(message.locator('.inbox-winmail')).toContainText('winmail.dat');
  await expect(message.locator('.inbox-text')).toContainText('Σας στέλνουμε την αίτηση μετακίνησης');   // from the compressed RTF
  await expect(message.getByRole('link', { name: 'Αίτηση μετακίνησης εκπαιδευτικών.pdf' })).toBeVisible();
  await expect(message.getByRole('link', { name: 'Πίνακας μετακινήσεων.csv' })).toBeVisible();
  await expect(message.getByRole('link', { name: 'winmail.dat' })).toHaveCount(0);
  const pdf = await page.request.get(await message.getByRole('link', { name: 'Αίτηση μετακίνησης εκπαιδευτικών.pdf' }).getAttribute('href'));
  expect(pdf.headers()['content-type']).toBe('application/pdf');
  expect((await pdf.body()).subarray(0, 5).toString()).toBe('%PDF-');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5b-winmail', { clip: await message.boundingBox() });

  await message.locator(`#f-${uid}-fakelos`).click();
  await message.locator(`#folder-list-${uid} li[role=option]`).nth(1).click();
  await message.locator('[data-action="register"]').click();
  const anyway = message.locator('[data-action="register-anyway"]');   // a run before left the same protocol
  const done = message.locator('.inbox-done');
  await expect(done.or(anyway)).toBeVisible();
  if (await anyway.isVisible()) await anyway.click();
  await expect(done).toContainText('Καταχωρίστηκε με αριθμό');
  const [, num, year] = (await done.innerText()).match(/(\d+)\/(\d{4})/);
  await page.goto(`/goto/${year}/${num}?find=1`);
  await expect(page.locator('a[href*="/download/"]', { hasText: 'Αίτηση μετακίνησης εκπαιδευτικών.pdf' })).toHaveCount(1);
  await expect(page.locator('a[href*="/download/"]', { hasText: /\.csv$/ })).toHaveCount(1);
});
