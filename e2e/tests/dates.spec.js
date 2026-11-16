// D04: dates are stored as DATE columns. The form shows what was saved, and the search's date range finds it.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('incoming date is saved, shown again and found by a date range (D04)', async ({ page }) => {
  const subject = `D04 ημερομηνίες ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  await page.fill('#thema', subject);
  await page.evaluate(() => {   // first folder (bootstrap-select hides the real <select>)
    const s = document.querySelector('select[name=fakelos]');
    s.value = s.options[1].value;
    s.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await page.fill('#in_num', `Φ.4/${Date.now()}`);   // unique: an existing one asks for confirmation
  await page.fill('input[name=in_date]', '03/01/2025');
  await page.fill('input[name=in_topos_ekdosis]', 'Δοκιμή');
  await page.fill('input[name=in_arxi_ekdosis]', 'Διεύθυνση Δοκιμής');
  await page.fill('input[name=in_paraliptis]', 'Διευθυντής');
  await page.evaluate(() => formSubmit());
  await page.waitForURL(/\/home\/\d+$/);
  await expect(page.locator('input[name=in_date]')).toHaveValue('03/01/2025');
  await evidence(page, 'd04-saved-incoming-date', { clip: { x: 0, y: 0, width: 1440, height: 450 } });

  await page.goto('/find');
  await page.selectOption('#searchField1', 'thema');
  await page.fill('#searchData1', subject);
  await page.fill('#apoEiserxDate', '02/01/2025');
  await page.fill('#eosEiserxDate', '04/01/2025');
  await page.evaluate(() => getFindData());
  const results = page.locator('#showFindData').first();
  await expect(results).toContainText(subject);
  await evidence(page, 'd04-found-by-date-range', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  await page.fill('#apoEiserxDate', '04/01/2025');   // range after the date: not found
  await page.evaluate(() => getFindData());
  await expect(results).not.toContainText(subject);
});
