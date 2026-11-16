// R4b: an attachment moved to the trash from the protocol form waits in "Κάδος" (administrators), with its protocol,
// who deleted it and when it goes for good; "Επαναφορά" puts it back on the protocol.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('to the trash from the form, back from the trash page (R4b)', async ({ page }) => {
  const name = `R4b-${Date.now()}.pdf`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R4b κάδος ${Date.now()}`, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', { name, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 R4b\n') });
  await expect(page.locator('#attachments .upload-row')).toHaveCount(1);
  await saveProtocol(page);
  const protocolUrl = page.url();

  // to the trash (with the form's confirmation)
  const row = page.locator('#attachments .attachment-row', { hasText: name });
  await row.locator('button[title="Στον κάδο"]').click();
  await page.locator('#app-confirm [data-confirm-yes]').click();
  await expect(row).toHaveCount(0);

  // the trash page shows it with its protocol
  await page.goto('/trash');
  const trashed = page.locator('.trash-row', { hasText: name });
  await expect(trashed).toBeVisible();
  await expect(trashed).toContainText('Διαχειριστής Δοκιμών');
  await expect(page.locator('#trash-retention')).toContainText('ημέρες');
  await evidence(page, 'r4b-trash-page');

  await trashed.getByRole('button', { name: 'Επαναφορά' }).click();
  await expect(trashed).toHaveCount(0);
  await page.goto(protocolUrl);
  await expect(page.locator('#attachments .attachment-row', { hasText: name })).toBeVisible();
  const link = page.locator('#attachments a[href*="/download/"]', { hasText: name });
  expect((await (await page.request.get(await link.getAttribute('href'))).body()).toString()).toContain('%PDF-1.4 R4b');
});

test('the trash is for administrators only (R4b)', async ({ page }) => {
  await login(page, 'writer');
  const response = await page.goto('/trash');
  expect(response.status()).toBe(403);
});
