// D04: dates are stored as DATE columns. The form shows what was saved, and the search's date range finds it.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol, search } = require('./helpers');

test('incoming date is saved, shown again and found by a date range (D04)', async ({ page }) => {
  const subject = `D04 ημερομηνίες ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, {
    thema: subject, fakelos: 'first', in_num: `Φ.4/${Date.now()}`, in_date: '03/01/2025',
    in_topos_ekdosis: 'Δοκιμή', in_arxi_ekdosis: 'Διεύθυνση Δοκιμής', in_paraliptis: 'Διευθυντής',
  });
  await saveProtocol(page);
  await expect(page.locator('#f-in_date')).toHaveValue('03/01/2025');
  await evidence(page, 'd04-saved-incoming-date', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  // R3a search screen: the incoming date range is under "Περισσότερα πεδία"
  const results = await search(page, { thema: subject, 'in-from': '02/01/2025', 'in-to': '04/01/2025' });
  await expect(results.locator('#search-results')).toContainText(subject);
  await evidence(page, 'd04-found-by-date-range', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  await search(page, { 'in-from': '04/01/2025' }, { open: false });   // range after the date: not found
  await expect(results.locator('#search-count')).toContainText('Δεν βρέθηκε');
});
