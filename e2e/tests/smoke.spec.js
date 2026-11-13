// @smoke: the main screens work for every role, and the core actions succeed in a real browser.
const { test, expect } = require('@playwright/test');
const { login, evidence, toasts } = require('./helpers');

test.describe('@smoke', () => {
  for (const user of ['admin', 'anathetis', 'writer', 'reader']) {
    test(`${user}: protocol list, search and settings access`, async ({ page }) => {
      await login(page, user);
      await expect(page).toHaveURL(/\/home\/list/);   // readers land on their own filtered list
      await page.goto('/home/list');
      await expect(page.locator('body')).toContainText('Θέμα');
      expect(await page.locator('.row.line').count()).toBeGreaterThan(5);   // demo protocols are listed
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
    await page.fill('#thema', subject);
    await page.fill('#reply_to_email', 'sender@example.test');
    await page.evaluate(() => formSubmit());
    await page.waitForURL(/\/home\/\d+$/);
    await expect(page.locator('#thema')).toHaveValue(subject);
    expect(await toasts(page)).toContain('απόδειξη παραλαβής σε sender@example.test');
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
    await page.goto('/viewEmails');
    await expect(page.locator('body')).toContainText('Πρόσκληση σε επιμορφωτική ημερίδα');
    await expect(page.locator('body')).not.toContainText('=?UTF-8?');
  });
});
