// R4h: «Διαγραφή» moves a protocol to the trash (its number is free again); «Επαναφορά» gives it back its number,
// or a new one when that number was given again (with a note in Παρατηρήσεις).
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol } = require('./helpers');

test('a deleted protocol waits in the trash and comes back (R4h)', async ({ page }) => {
  const stamp = Date.now();
  await login(page, 'admin');
  const previous = await setSetting(page, 'protocolValidate', 0);   // the administrator may delete and choose numbers
  try {
    await page.goto('/home');
    await fillProtocol(page, { thema: `R4h κάδος πρωτοκόλλων ${stamp}`, fakelos: 'first' });
    await page.setInputFiles('#f-uploads', { name: `R4h-${stamp}.pdf`, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 R4h\n') });
    await saveProtocol(page);
    const [, num, year] = (await page.title()).match(/Πρωτόκολλο (\d+)\/(\d{4})/);

    // to the trash, with the confirmation
    await page.getByRole('button', { name: 'Διαγραφή' }).click();
    await expect(page.locator('#app-confirm')).toContainText(`Να μεταφερθεί στον κάδο το πρωτόκολλο ${num}/${year}`);
    await Promise.all([page.waitForURL(/\/home\/list/), page.locator('#app-confirm [data-confirm-yes]').click()]);
    const notice = page.locator('main > .alert');
    await expect(notice).toHaveText(`Το πρωτόκολλο ${num}/${year} μεταφέρθηκε στον κάδο.`);   // R4l: only that
    await expect(notice.getByRole('link', { name: 'κάδο' })).toHaveAttribute('href', /\/trash\?tab=protocols$/);
    await evidence(page, 'r4l-trash-notice', { clip: { x: 0, y: 40, width: 1440, height: 110 } });

    // the link says «Στον κάδο» to an administrator
    await page.goto(`/protocol/${num}/${year}`);
    await expect(page.locator('#protocol-in-trash')).toContainText('Στον κάδο');

    // its number is free: a new protocol takes it
    await page.goto(`/home?num=${num}`);
    await fillProtocol(page, { thema: `R4h νέο με τον ίδιο αριθμό ${stamp}`, fakelos: 'first' });
    await saveProtocol(page);
    await expect(page).toHaveTitle(new RegExp(`Πρωτόκολλο ${num}/${year}`));

    // the trash: tab «Πρωτόκολλα»
    await page.goto('/trash?tab=protocols');
    const row = page.locator('.trash-protocol-row', { hasText: `R4h κάδος πρωτοκόλλων ${stamp}` });
    await expect(row).toContainText(`${num}/${year}`);
    await expect(row).toContainText('Διαχειριστής Δοκιμών');
    await evidence(page, 'r4h-trash', { clip: { x: 0, y: 0, width: 1440, height: 520 } });

    // back, with a new number (its own was given again); the note says where it came from
    await row.getByRole('button', { name: 'Επαναφορά' }).click();
    await page.locator('#app-confirm [data-confirm-yes]').click();
    const toast = page.locator('#app-toasts .toast').last();
    await expect(toast).toContainText(`ο αρχικός ${num}/${year} έχει δοθεί`);
    await expect(toast).toHaveCSS('opacity', '1');
    await evidence(page, 'r4h-restored-new-number', { clip: { x: 0, y: 0, width: 1440, height: 300 } });
    await toast.locator('a', { hasText: 'Άνοιγμα πρωτοκόλλου' }).click();
    await expect(page.locator('#f-paratiriseis')).toHaveValue(new RegExp(`Επαναφορά από τον κάδο .* αρχικός αρ\\. πρωτ\\. ${num}/${year}`));
    await expect(page.locator('#attachments .attachment-row', { hasText: `R4h-${stamp}.pdf` })).toBeVisible();   // its file came back
  } finally {
    await setSetting(page, 'protocolValidate', previous);
  }
});
