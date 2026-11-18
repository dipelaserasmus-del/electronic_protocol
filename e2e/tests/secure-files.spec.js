// R4a: only safe files are taken in; attachments come back with safe headers (an uploaded web page is downloaded,
// never opened as a page of the application).
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('a program is refused at once, the PDF is kept (R4a)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R4a αρχεία ${Date.now()}`, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', [
    { name: 'Απόφαση.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 R4a\n%%EOF\n') },
    { name: 'τιμολόγιο.pdf.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ') },
  ]);
  await expect(page.locator('#upload-refused')).toContainText('Το «τιμολόγιο.pdf.exe» δεν επιτρέπεται');
  await expect(page.locator('#attachments .upload-row')).toHaveCount(1);
  await page.addStyleTag({ content: '.app-topbar { position: static !important; }' });   // full-page capture
  await evidence(page, 'r4a-refused-upload', { fullPage: true });
  await saveProtocol(page);

  const link = page.locator('#attachments a[href*="/download/"]', { hasText: 'Απόφαση.pdf' });
  const response = await page.request.get(await link.getAttribute('href'));
  expect(response.headers()['content-type']).toBe('application/pdf');
  expect(response.headers()['content-disposition']).toContain("filename*=utf-8''" + encodeURIComponent('Απόφαση.pdf'));
  expect(response.headers()['x-content-type-options']).toBe('nosniff');
});

test('inbox attachments come with safe headers (R4a)', async ({ page }) => {
  // the first attachment shown in the inbox: never served as a page, opened only when PDF/image/text
  await login(page, 'admin');
  await page.goto('/inbox');
  const link = page.locator('a[href*="/viewEmailAttachment/"]').first();
  await expect(link).toBeVisible();
  const response = await page.request.get(await link.getAttribute('href'));
  expect(response.status()).toBe(200);
  expect(response.headers()['x-content-type-options']).toBe('nosniff');
  const type = response.headers()['content-type'];
  expect(type).not.toMatch(/html|svg|javascript/);
  if (!/^(application\/pdf|image\/|text\/plain)/.test(type)) {
    expect(response.headers()['content-disposition']).toMatch(/^attachment;/);
  }
});
