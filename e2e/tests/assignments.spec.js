// D02: assignments are stored in their own table. A protocol assigned to one user for διεκπεραίωση and to another
// for ενημέρωση is found by searching the first user's name (before: not found, because the search compared the
// whole text "d3,e4" with "d3"), and it shows in both users' lists.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('protocol assigned to a writer (διεκπεραίωση) and a reader (ενημέρωση): search and both lists (D02)', async ({ page }) => {
  const subject = `D02 ανάθεση ${Date.now()}`;
  await login(page, 'admin');
  await page.goto('/home');
  await page.fill('#thema', subject);
  await page.evaluate(() => {   // bootstrap-select hides the real <select>s
    const f = document.querySelector('select[name=fakelos]');
    f.value = f.options[1].value;
    f.dispatchEvent(new Event('change', { bubbles: true }));
    const s = document.querySelector('#diekperaiosi');
    for (const o of s.options) {
      o.selected = (o.value.startsWith('d') && o.text.includes('Συγγραφέας Δοκιμών'))
        || (o.value.startsWith('e') && o.text.includes('Αναγνώστης Δοκιμών'));
    }
    s.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await page.evaluate(() => formSubmit());
  await page.waitForURL(/\/home\/\d+$/);

  // search by the assignee's name
  await page.goto('/find');
  await page.selectOption('#searchField1', 'diekperaiosi');
  await page.fill('#searchData1', 'Συγγραφέας');
  await page.selectOption('#searchField2', 'thema');   // narrow to this test's protocol (demo data has more)
  await page.fill('#searchData2', subject);
  const results = page.locator('#showFindData').first();
  await expect(results).toContainText(subject);
  await expect(results).toContainText('Συγγραφέας Δοκιμών');   // the assignee's name in the results
  await evidence(page, 'd02-search-by-assignee', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  // the writer sees it "προς Διεκπεραίωση", the reader "προς Ενημέρωση"
  await page.context().clearCookies();
  await login(page, 'writer');
  await page.goto('/home/list/d');
  await expect(page.locator('body')).toContainText(subject);
  await evidence(page, 'd02-writer-list-d', { clip: { x: 0, y: 0, width: 1440, height: 700 } });

  await page.context().clearCookies();
  await login(page, 'reader');
  await page.goto('/home/list/e');
  await expect(page.locator('body')).toContainText(subject);
  await page.goto('/home/list/d');
  await expect(page.locator('body')).not.toContainText(subject);
});
