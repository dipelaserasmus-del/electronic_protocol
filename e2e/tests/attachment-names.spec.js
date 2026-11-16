// F08: a file with a long Greek name (over Linux's 255 bytes once the "number-date_" prefix is added) is stored
// and can be downloaded; before, the file was not written.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('upload a file with a long Greek name: stored, listed with its whole name, downloadable (F08)', async ({ page }) => {
  const name = 'Αίτηση'.repeat(25) + '.pdf';   // 154 characters, 304 bytes
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `F08 μακρύ όνομα ${Date.now()}`, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', { name, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 F08\n%%EOF\n') });
  await expect(page.locator('#attachments')).toContainText('Αίτηση');   // uploaded, listed before saving
  await saveProtocol(page);

  const link = page.locator('a[href*="/download/"]', { hasText: 'Αίτηση' }).first();
  await expect(link).toBeVisible();
  const file = await page.request.get(await link.getAttribute('href'));
  expect(file.status()).toBe(200);
  expect((await file.body()).toString()).toContain('%PDF-1.4 F08');
  await link.scrollIntoViewIfNeeded();
  await evidence(page, 'f08-long-greek-name', { clip: { x: 0, y: 0, width: 1440, height: 900 } });
});
