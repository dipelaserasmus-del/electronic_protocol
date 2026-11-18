// B07: registering an e-mail must store the protocol AND move the e-mail out of the inbox
// (before: "NO EXPUNGE failed" → error page, protocol stored, e-mail left in the inbox).
// D05: an e-mail with emoji is registered with them (before: utf8 tables refused them).
// R5c: registering happens on the page: the result appears in the e-mail's place, an error at its field.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

const inboxCount = async (page) => {
  const text = await page.locator('#inbox-count').innerText();
  return Number((text.match(/από (\d+)/) ?? text.match(/(\d+) email/))[1]);
};

/** The message of the inbox e-mail whose text contains `text` (whichever page it is on), and the inbox count. */
async function open(page, text) {
  // the order of the mock e-mails differs between machines: find the page that holds this e-mail
  for (const p of [1, 2, 3, 4]) {
    await page.goto(`/inbox?page=${p}`);
    const message = page.locator('article.inbox-message', { hasText: text }).first();
    if (await message.count()) return { message, before: await inboxCount(page) };
  }
  throw new Error(`e-mail "${text}" not found in the inbox`);
}

/** Registers it without its attachments; returns the notice shown in its place and the inbox count before. */
async function register(page, text) {
  const { message, before } = await open(page, text);
  for (const box of await message.locator('.inbox-attachment input[type=checkbox]').all()) await box.uncheck();
  await message.locator('[data-action="register"]').click();
  const done = message.locator('.inbox-done');
  await expect(done).toBeVisible();
  return { message, notice: (await done.innerText()).replace(/\s+/g, ' '), before };
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
  expect(await inboxCount(page)).toBe(before - 1);
});

test('register an e-mail with emoji: the protocol keeps them (D05)', async ({ page }) => {
  await login(page, 'admin');
  const { notice, before } = await register(page, 'Ομάδα "Δοκιμών"');   // synthetic e-mail 05-special-characters

  const [num, year] = numberOf(notice);
  await page.goto('/inbox');
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
  await message.locator('[data-action="register"]').click();
  const done = message.locator('.inbox-done');
  await expect(done).toContainText('Καταχωρίστηκε με αριθμό');
  await expect(message.locator('form.inbox-form')).toHaveCount(0);

  const [num, year] = numberOf((await done.innerText()).replace(/\s+/g, ' '));
  await page.goto(`/goto/${year}/${num}?find=1`);
  // the e-mail itself (.eml) and its PDF
  await expect(page.locator('a[href*="/download/"]', { hasText: /\.eml$/ })).toHaveCount(1);
  await expect(page.locator('a[href*="/download/"]', { hasText: /\.pdf$/ })).toHaveCount(1);
});
