// R4d: ✎ next to an attachment renames it (the name shown; the file keeps its path; the extension stays).
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

/** the attachments card, for the screenshots */
async function around(page, row) {
  await row.scrollIntoViewIfNeeded();
  const box = await row.boundingBox();
  return { clip: { x: 0, y: Math.max(0, box.y - 110), width: 1440, height: 230 } };
}

test('rename an attachment: ✎, type, Enter; the extension stays and the download still works (R4d)', async ({ page }) => {
  const stamp = Date.now();
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R4d μετονομασία ${stamp}`, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', { name: 'scan0001.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n') });
  await expect(page.locator('.upload-row')).toHaveCount(1);
  await saveProtocol(page);

  const row = page.locator('.attachment-row').first();
  await expect(row).toContainText('scan0001.pdf');
  await row.locator('[data-action=rename]').click();
  const input = row.locator('.rename-input');
  await expect(input).toBeFocused();
  await expect(input).toHaveValue('scan0001');
  await expect(row.locator('.input-group-text')).toHaveText('.pdf');
  await input.fill(`Πρόσκληση σε ημερίδα ${stamp}`);
  await evidence(page, 'r4d-editing', await around(page, row));

  // Escape keeps the old name
  await input.press('Escape');
  await expect(row.locator('a').first()).toHaveText('scan0001.pdf');

  await row.locator('[data-action=rename]').click();
  await input.fill(`Πρόσκληση σε ημερίδα ${stamp}`);
  const saved = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
  await input.press('Enter');   // saves the name, not the whole form
  await saved;
  const link = row.locator('a').first();
  await expect(link).toHaveText(`Πρόσκληση σε ημερίδα ${stamp}.pdf`);
  await expect(page.locator('#app-toasts .toast').last()).toContainText('Μετονομάστηκε σε');
  await evidence(page, 'r4d-renamed', await around(page, row));

  // the same file downloads, under the new name
  const response = await page.request.get(await link.getAttribute('href'));
  expect(response.status()).toBe(200);
  expect(decodeURIComponent(response.headers()['content-disposition'])).toContain(`Πρόσκληση σε ημερίδα ${stamp}.pdf`);

  // found at once by συνημμένο:
  await page.goto(`/search?q=${encodeURIComponent(`συνημμένο:ημερίδα ${stamp}`)}`);
  await expect(page.locator('main')).toContainText(`R4d μετονομασία ${stamp}`);
});
