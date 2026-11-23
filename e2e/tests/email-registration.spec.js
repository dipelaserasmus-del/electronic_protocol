// B07: registering an e-mail must store the protocol AND move the e-mail out of the inbox
// (before: "NO EXPUNGE failed" → error page, protocol stored, e-mail left in the inbox).
// D05: an e-mail with emoji is registered with them (before: utf8 tables refused them).
// R5c: registering happens on the page: the result appears in the e-mail's place, an error at its field.
const { test, expect } = require('@playwright/test');
const { login, evidence, openInbox, inboxReady } = require('./helpers');

const inboxCount = async (page) => {
  const text = await page.locator('#inbox-count').innerText();
  return Number((text.match(/από (\d+)/) ?? text.match(/(\d+) email/))[1]);
};

/** The message of the inbox e-mail whose text contains `text` (whichever page it is on), and the inbox count. */
async function open(page, text) {
  // the order of the mock e-mails differs between machines: find the page that holds this e-mail
  for (const p of [1, 2, 3, 4]) {
    await openInbox(page, `/inbox?page=${p}`);
    const message = page.locator('article.inbox-message', { hasText: text }).first();
    if (await message.count()) return { message, before: await inboxCount(page) };
  }
  throw new Error(`e-mail "${text}" not found in the inbox`);
}

/** Registers it without its attachments; returns the notice shown in its place and the inbox count before. */
async function register(page, text) {
  const { message, before } = await open(page, text);
  for (const box of await message.locator('.inbox-attachment input[type=checkbox]').all()) await box.uncheck();
  await submit(message);
  const done = message.locator('.inbox-done');
  return { message, notice: (await done.innerText()).replace(/\s+/g, ' '), before };
}

/** «Καταχώριση»; if a similar protocol exists (earlier runs on this database), «Ναι, καταχώριση» (R5d). */
async function submit(message) {
  await message.locator('[data-action="register"]').click();
  const anyway = message.locator('[data-action="register-anyway"]');
  const done = message.locator('.inbox-done');
  await expect(done.or(anyway)).toBeVisible();
  if (await anyway.isVisible()) await anyway.click();
  await expect(done).toBeVisible();
}

const numberOf = (notice) => notice.match(/Καταχωρίστηκε με αριθμό (\d+)\/(\d{4})/).slice(1);

test('register an e-mail from the inbox: protocol created and e-mail leaves the inbox', async ({ page }) => {
  await login(page, 'admin');
  const { message, notice, before } = await register(page, 'Αίτηση γονέα');

  expect(notice).toContain('Καταχωρίστηκε με αριθμό');
  expect(notice).not.toContain('δεν μεταφέρθηκε');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'email-registered', { clip: await message.boundingBox() });

  await page.reload();
  await inboxReady(page);
  expect(await inboxCount(page)).toBe(before - 1);
});

test('register an e-mail with emoji: the protocol keeps them (D05)', async ({ page }) => {
  await login(page, 'admin');
  const { notice, before } = await register(page, 'Ομάδα "Δοκιμών"');   // synthetic e-mail 05-special-characters

  const [num, year] = numberOf(notice);
  await openInbox(page, '/inbox');
  expect(await inboxCount(page)).toBe(before - 1);
  await page.goto(`/goto/${year}/${num}?find=1`);
  await expect(page.locator('#f-thema')).toHaveValue(/σύμβολα: 50% ; <tag> \\ _ 📎$/);
  await expect(page.locator('#f-in_arxi_ekdosis')).toHaveValue('Ομάδα "Δοκιμών" & Συνεργάτες <oteam@example.test>');
  await evidence(page, 'd05-emoji-protocol', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});

test('register an e-mail with a 300-character subject: saved in full (D01)', async ({ page }) => {
  await login(page, 'admin');
  const { notice } = await register(page, 'ΠΥΣΠΕ Δοκιμής');   // synthetic e-mail 07-long-subject-plain-only

  const [num, year] = numberOf(notice);
  await page.goto(`/goto/${year}/${num}?find=1`);
  const thema = await page.locator('#f-thema').inputValue();
  expect(thema.length).toBe(300);
  expect(thema).toMatch(/^Ενημέρωση για τη διαδικασία υποβολής αιτήσεων μετάθεσης/);
  await evidence(page, 'd01-long-subject-protocol', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});

test('an e-mail with attachments needs a folder: said at the field, registered once chosen (R5c)', async ({ page }) => {
  await login(page, 'admin');
  const { message } = await open(page, 'Διαβιβαστικό εγγράφων μαθητή');   // synthetic e-mail with a PDF
  const uid = await message.getAttribute('data-uid');

  await message.locator('[data-action="register"]').click();
  const error = message.locator(`#f-${uid}-fakelos ~ .invalid-feedback`);
  await expect(error).toContainText('επιλέξτε Φάκελο');
  await expect(message.locator('form.inbox-form')).toBeVisible();     // nothing registered, the e-mail stays
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5c-folder-needed', { clip: await message.boundingBox() });

  await message.locator(`#f-${uid}-fakelos`).click();
  await message.locator(`#folder-list-${uid} li[role=option]`).nth(1).click();   // 0 is "— Χωρίς φάκελο"
  await submit(message);
  const done = message.locator('.inbox-done');
  await expect(done).toContainText('Καταχωρίστηκε με αριθμό');
  await expect(message.locator('form.inbox-form')).toHaveCount(0);

  const [num, year] = numberOf((await done.innerText()).replace(/\s+/g, ' '));
  await page.goto(`/goto/${year}/${num}?find=1`);
  // the e-mail itself (.eml) and its PDF
  await expect(page.locator('a[href*="/download/"]', { hasText: /\.eml$/ })).toHaveCount(1);
  await expect(page.locator('a[href*="/download/"]', { hasText: /\.pdf$/ })).toHaveCount(1);
});

test('keywords offered from the subject become tags and are registered (R5d)', async ({ page }) => {
  await login(page, 'admin');
  const { message } = await open(page, 'Αποστολή στοιχείων');   // a mock e-mail no other test registers
  const uid = await message.getAttribute('data-uid');
  const offer = message.locator('.keyword-suggestions [data-suggest]').first();
  await expect(offer).toBeVisible();
  const word = await offer.getAttribute('data-suggest');
  await offer.click();
  await expect(message.locator(`.keyword-pill[data-tag="${word}"]`)).toBeVisible();
  await expect(message.locator(`[data-suggest="${word}"]`)).toHaveCount(0);   // chosen: no longer offered
  await message.locator(`#f-${uid}-keywords`).fill('σεμινάριο');
  await message.locator(`#f-${uid}-keywords`).press('Enter');
  await expect(message.locator('.keyword-pill[data-tag="σεμινάριο"]')).toBeVisible();
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5d-keywords', { clip: await message.locator('form.inbox-form').boundingBox() });

  for (const box of await message.locator('.inbox-attachment input[type=checkbox]').all()) await box.uncheck();
  await submit(message);
  const [num, year] = numberOf((await message.locator('.inbox-done').innerText()).replace(/\s+/g, ' '));
  await page.goto(`/goto/${year}/${num}?find=1`);
  await expect(page.locator(`#keywords-box [data-tag="${word}"]`)).toBeVisible();
  await expect(page.locator('#keywords-box [data-tag="σεμινάριο"]')).toBeVisible();
});

test('same subject as a registered protocol: a notice, and a second click registers it (R5d)', async ({ page }) => {
  await login(page, 'admin');
  const { notice } = await register(page, 'Εγκύκλιος για την έναρξη');   // synthetic e-mail 02
  const [num, year] = numberOf(notice);

  const { message } = await open(page, 'Μεγάλο σαρωμένο');               // another e-mail: give it the same subject
  const uid = await message.getAttribute('data-uid');
  await page.goto(`/goto/${year}/${num}?find=1`);
  const subject = await page.locator('#f-thema').inputValue();
  const again = await open(page, 'Μεγάλο σαρωμένο');
  await again.message.locator(`#f-${uid}-thema`).fill(subject);
  for (const box of await again.message.locator('.inbox-attachment input[type=checkbox]').all()) await box.uncheck();
  await again.message.locator('[data-action="register"]').click();

  const anyway = again.message.locator('[data-action="register-anyway"]');
  await expect(anyway).toBeVisible();
  await expect(again.message.locator('.inbox-similar')).toContainText(`${num}/${year}`);
  await again.message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5d-similar', { clip: await again.message.locator('form.inbox-form').boundingBox() });
  await anyway.click();
  await expect(again.message.locator('.inbox-done')).toContainText('Καταχωρίστηκε με αριθμό');
});
