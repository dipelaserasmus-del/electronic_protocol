// D02: assignments are stored in their own table. A protocol assigned to one user for διεκπεραίωση and to another
// for ενημέρωση is found by searching the first user's name (before: not found, because the search compared the
// whole text "d3,e4" with "d3"), and it shows in both users' lists.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol, search } = require('./helpers');

test('protocol assigned to a writer (διεκπεραίωση) and a reader (ενημέρωση): search and both lists (D02)', async ({ page }) => {
  const subject = `D02 ανάθεση ${Date.now()}`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'first' });
  await page.locator('#f-handle').selectOption({ label: 'Συγγραφέας Δοκιμών' });
  await page.locator('#f-inform').selectOption({ label: 'Αναγνώστης Δοκιμών' });
  await saveProtocol(page);

  // search by the assignee (R3a: chosen from the users), narrowed to this test's protocol (demo data has more)
  await page.goto('/find');
  await page.locator('#s-assignee').selectOption({ label: 'Συγγραφέας Δοκιμών' });
  await search(page, { thema: subject }, { open: false });
  const results = page.locator('#search-results');
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
