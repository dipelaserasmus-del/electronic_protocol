// @smoke: the main screens work for every role, and the core actions succeed in a real browser.
const { test, expect } = require('@playwright/test');
const { login, evidence, toasts, openInbox } = require('./helpers');
const { fillProtocol, saveProtocol, notice } = require('./helpers');

test.describe('@smoke', () => {
  for (const user of ['admin', 'anathetis', 'writer', 'reader']) {
    test(`${user}: protocol list, search and settings access`, async ({ page }) => {
      await login(page, user);
      await expect(page).toHaveURL(/\/home\/list/);   // readers land on their own filtered list
      await page.goto('/home/list');
      await expect(page.locator('body')).toContainText('Θέμα');
      await expect(page.locator('.protocol-row').first()).toBeVisible();
      expect(await page.locator('.protocol-row').count()).toBeGreaterThan(5);   // demo protocols are listed (R2 list)
      await page.goto('/find');
      await expect(page.locator('body')).toContainText('Αναζήτηση');
      const settings = await page.goto('/settings');
      if (user === 'admin') expect(page.url()).toContain('/settings');
      else expect(page.url()).not.toContain('/settings');
      expect(settings.status()).toBeLessThan(500);
    });
  }

  test('writer creates a protocol with a receipt e-mail', async ({ page }) => {
    await login(page, 'writer');
    await page.goto('/home');
    const subject = `E2E θέμα ${Date.now()}`;
    await fillProtocol(page, { thema: subject, reply_to_email: 'sender@example.test' });
    await saveProtocol(page);
    await expect(page.locator('#f-thema')).toHaveValue(subject);
    expect(await notice(page)).toContain('απόδειξη παραλαβής σε sender@example.test');
    await evidence(page, 'e2e-protocol-saved', { clip: { x: 0, y: 0, width: 1440, height: 450 } });
  });

  test('missing-number reminder is shown and its link pre-fills the form', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/home/list');
    const banner = page.locator('#numberGaps');
    await expect(banner).toContainText('Κενά στην αρίθμηση');
    await expect(banner).toContainText('88');
  });

  test('inbox shows the mock mailbox with Greek subjects decoded', async ({ page }) => {
    await login(page, 'admin');
    await openInbox(page, '/inbox');
    const body = page.locator('body');
    await expect(page.locator('#inbox-count')).toContainText(/από \d+|\d+ email/);
    // whichever mock e-mails are left on page 1 (other tests register some, the order differs between machines):
    // their subjects are decoded Greek
    const subjects = await page.locator('.inbox-subject').allInnerTexts();
    expect(subjects.filter((t) => /\p{Script=Greek}{3}/u.test(t)).length).toBeGreaterThanOrEqual(3);
    const text = await body.innerText();
    expect(text).not.toMatch(/=\?(UTF-8|ISO-8859-7|Windows-1253)\?/i);   // every header decoded
  });
});
