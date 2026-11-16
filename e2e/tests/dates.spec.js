// D04: dates are stored as DATE columns. The form shows what was saved, and the search's date range finds it.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

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

  await page.goto('/find');
  await page.selectOption('#searchField1', 'thema');
  await page.fill('#searchData1', subject);
  await page.fill('#apoEiserxDate', '02/01/2025');
  await page.fill('#eosEiserxDate', '04/01/2025');
  await page.evaluate(() => getFindData());
  const results = page.locator('#showFindData').first();
  await expect(results).toContainText(subject);
  await page.addStyleTag({ content: '.datepicker-dropdown { display: none !important; }' });
  await evidence(page, 'd04-found-by-date-range', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  await page.fill('#apoEiserxDate', '04/01/2025');   // range after the date: not found
  await page.evaluate(() => getFindData());
  await expect(results).not.toContainText(subject);
});
