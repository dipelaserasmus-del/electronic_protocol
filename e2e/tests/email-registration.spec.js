// B07: registering an e-mail must store the protocol AND move the e-mail out of the inbox
// (before: "NO EXPUNGE failed" → error page, protocol stored, e-mail left in the inbox).
// D05: an e-mail with emoji is registered with them (before: utf8 tables refused them).
const { test, expect } = require('@playwright/test');
const { login, evidence, toasts } = require('./helpers');

const inboxCount = async (page) => Number((await page.locator('body').innerText()).match(/από (\d+) εισερχόμενα/)[1]);

/** Registers the inbox e-mail whose form contains `text`; returns the toast text and the inbox count before. */
async function register(page, text) {
  // the order of the mock e-mails differs between machines: find the page that holds this e-mail
  let form;
  for (const p of [1, 2, 3]) {
    await page.goto(`/viewEmails?imap_page=${p}`);
    form = page.locator('form[action$="storeFromEmail"]', { hasText: text }).first();
    if (await form.count()) break;
  }
  await expect(form).toBeVisible();
  const before = await inboxCount(page);

  const posted = page.waitForResponse((r) => r.url().includes('/storeFromEmail') && r.request().method() === 'POST');
  await form.locator('a[href*="sendReceipt"][href*="val(0)"]').first().click();
  // "same subject already registered — continue?" dialog (until P07 replaces it) → yes
  const yes = page.locator('#confirmRevertYes');
  if (await yes.waitFor({ timeout: 3000 }).then(() => true, () => false)) await yes.click();
  expect((await posted).status()).toBeLessThan(500);
  await page.waitForURL(/viewEmails/);
  await page.waitForLoadState('load');
  await page.locator('.toast-message').first().waitFor();

  return { message: await toasts(page), before };
}

test('register an e-mail from the inbox: protocol created and e-mail leaves the inbox', async ({ page }) => {
  await login(page, 'admin');
  const { message, before } = await register(page, 'Αίτηση γονέα');

  expect(message).toContain('Το email καταχωρίστηκε');
  expect(message).not.toContain('δεν μεταφέρθηκε');
  await evidence(page, 'email-registered', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

  expect(await inboxCount(page)).toBe(before - 1);
});

test('register an e-mail with emoji: the protocol keeps them (D05)', async ({ page }) => {
  await login(page, 'admin');
  const { message, before } = await register(page, 'Ομάδα "Δοκιμών"');   // synthetic e-mail 05-special-characters

  expect(message).toContain('Το email καταχωρίστηκε');
  expect(await inboxCount(page)).toBe(before - 1);

  const [, num, year] = message.match(/Αρ\.Πρωτ: (\d+) \/ \d\d-\d\d-(\d{4})/);
  await page.goto(`/goto/${year}/${num}?find=1`);
  await expect(page.locator('[name=thema]')).toHaveValue(/σύμβολα: 50% ; <tag> \\ _ 📎$/);
  await expect(page.locator('[name=in_arxi_ekdosis]')).toHaveValue('Ομάδα "Δοκιμών" & Συνεργάτες <oteam@example.test>');
  await evidence(page, 'd05-emoji-protocol', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});

test('register an e-mail with a 300-character subject: saved in full (D01)', async ({ page }) => {
  await login(page, 'admin');
  const { message, before } = await register(page, 'ΠΥΣΠΕ Δοκιμής');   // synthetic e-mail 07-long-subject-plain-only

  expect(message).toContain('Το email καταχωρίστηκε');   // before D01: the inbox just reloaded, nothing registered
  expect(await inboxCount(page)).toBe(before - 1);

  const [, num, year] = message.match(/Αρ\.Πρωτ: (\d+) \/ \d\d-\d\d-(\d{4})/);
  await page.goto(`/goto/${year}/${num}?find=1`);
  const thema = await page.locator('[name=thema]').inputValue();
  expect(thema.length).toBe(300);
  expect(thema).toMatch(/^Ενημέρωση για τη διαδικασία υποβολής αιτήσεων μετάθεσης/);
  await evidence(page, 'd01-long-subject-protocol', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});
