// B07: registering an e-mail must store the protocol AND move the e-mail out of the inbox
// (before: "NO EXPUNGE failed" → error page, protocol stored, e-mail left in the inbox).
const { test, expect } = require('@playwright/test');
const { login, evidence, toasts } = require('./helpers');

test('register an e-mail from the inbox: protocol created and e-mail leaves the inbox', async ({ page }) => {
  await login(page, 'admin');
  // the order of the mock e-mails differs between machines: find the page that holds this e-mail
  let form;
  for (const p of [1, 2, 3]) {
    await page.goto(`/viewEmails?imap_page=${p}`);
    form = page.locator('form[action$="storeFromEmail"]', { hasText: 'Αίτηση γονέα' }).first();
    if (await form.count()) break;
  }
  await expect(form).toBeVisible();
  const count = async () => Number((await page.locator('body').innerText()).match(/από (\d+) εισερχόμενα/)[1]);
  const before = await count();

  const posted = page.waitForResponse((r) => r.url().includes('/storeFromEmail') && r.request().method() === 'POST');
  await form.locator('a[href*="sendReceipt"][href*="val(0)"]').first().click();
  // "same subject already registered — continue?" dialog (until P07 replaces it) → yes
  const yes = page.locator('#confirmRevertYes');
  if (await yes.waitFor({ timeout: 3000 }).then(() => true, () => false)) await yes.click();
  expect((await posted).status()).toBeLessThan(500);
  await page.waitForURL(/viewEmails/);
  await page.waitForLoadState('load');
  await page.locator('.toast-message').first().waitFor();

  const message = await toasts(page);
  expect(message).toContain('Το email καταχωρίστηκε');
  expect(message).not.toContain('δεν μεταφέρθηκε');
  await evidence(page, 'email-registered', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

  expect(await count()).toBe(before - 1);
});
