// R7: Εκτύπωση: choose the range, see how many protocols, open the printout (book or attachments), download Excel;
// the receipt of one protocol.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

test('print page: count, protocol book, Excel, attachments (R7)', async ({ page, context }) => {
  const year = String(new Date().getFullYear());
  await login(page, 'admin');
  await page.goto('/print');
  await expect(page.locator('#p-year')).toHaveValue(year);
  await expect(page.locator('#print-summary')).toContainText('πρωτόκολλ');
  await evidence(page, 'r7-print-page');

  const [book] = await Promise.all([context.waitForEvent('page'), page.locator('#print-open').click()]);
  await book.waitForLoadState();
  await expect(book.locator('.print-head h1')).toContainText(`Πρωτόκολλο έτους ${year}`);
  await expect(book.locator('#protocol-book thead')).toContainText('13, 12');
  await expect(book.locator('#protocol-book .book-row').first()).toBeVisible();
  await book.emulateMedia({ media: 'print' });
  await expect(book.locator('.toolbar')).toBeHidden();
  await evidence(book, 'r7-protocol-book');
  await book.close();

  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#print-xls').click()]);
  expect(download.suggestedFilename()).toMatch(/πρωτόκολλο έτους \d{4} - \d{8}-\d{6}\.xlsx$/);

  // review of R7: a number typed just before the click is in the printout (the link is built at the click)
  await page.locator('#p-to').fill('3');
  const [quick] = await Promise.all([context.waitForEvent('page'), page.locator('#print-open').click()]);
  expect(quick.url()).toContain('eosnum=3');
  await quick.waitForLoadState();
  await expect(quick.locator('#protocol-book')).toBeVisible();
  expect(await quick.locator('#protocol-book .book-row').count()).toBeLessThanOrEqual(3);   // numbers 1–3 only
  await quick.close();
  await page.locator('#p-to').fill('');

  // a wrong number is said, not guessed
  await page.locator('#p-from').fill('12α');
  await expect(page.locator('#print-summary')).toContainText('Από αριθμό «12α»');
  await page.locator('#p-from').fill('');

  await page.locator('#mode-attachments').click();
  await expect(page.locator('#print-summary')).toContainText('με συνημμένα');
  const [files] = await Promise.all([context.waitForEvent('page'), page.locator('#print-open').click()]);
  await files.waitForLoadState();
  await expect(files.locator('#attachments-print thead')).toContainText('Διατήρηση');
  await evidence(files, 'r7-attachments-print');
});

test('receipt of a protocol on the print layout (R7)', async ({ page, context }) => {
  const subject = `R7 απόδειξη ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { fakelos: 'first', thema: subject });
  await saveProtocol(page);
  const [receipt] = await Promise.all([context.waitForEvent('page'), page.getByRole('link', { name: 'Απόδειξη' }).click()]);
  await receipt.waitForLoadState();
  await expect(receipt.locator('#receipt')).toContainText(subject);
  await evidence(receipt, 'r7-receipt');
});
