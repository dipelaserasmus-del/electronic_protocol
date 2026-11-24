// N05: the notepad: a tab on the right of every page; markdown shown formatted (sections that fold, to-dos ticked with
// a click), nothing from the text ever runs; saved about a second after typing; an older copy in another tab is told.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

const NOTE = [
  '# Σήμερα',
  '- [ ] Τηλέφωνο στη Διεύθυνση',
  '- [x] Πρωτόκολλο εγκυκλίου',
  '**Σημαντικό:** η *προθεσμία* λήγει την Παρασκευή.',
  '# Χρήσιμα',
  '- [Διαύγεια](https://diavgeia.gov.gr)',
  '- [κακός σύνδεσμος](javascript:alert(1))',
  '<img src=x onerror="window.__xss = 1">',
].join('\n');

async function saved(page) {
  await expect(page.locator('#notepad-status')).toContainText('Αποθηκεύτηκε', { timeout: 10000 });
}

test('the notepad: markdown, to-dos, autosave, nothing runs (N05)', async ({ page, context }) => {
  await login(page, 'reader');   // every role has one
  await page.goto('/home/list');
  // start from an empty note and a closed panel (the tests share one database)
  await page.evaluate(async () => {
    localStorage.removeItem('notes.open');
    localStorage.removeItem('notes.folded');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' };
    await fetch('/notes', { method: 'POST', headers, body: JSON.stringify({ body: '', version: 0, force: true }) });
  });
  await page.reload();
  await expect(page.locator('#notepad-panel')).toBeHidden();   // closed by default

  await page.locator('#notepad-tab').click();
  await expect(page.locator('#notepad-panel')).toBeVisible();
  await page.locator('#notepad-edit').click();
  const text = page.locator('#notepad-text');
  await text.fill(NOTE);
  await saved(page);

  await page.locator('#notepad-view').click();
  const view = page.locator('#notepad-rendered');
  await expect(view.locator('details.note-section > summary')).toHaveText(['Σήμερα', 'Χρήσιμα']);
  await expect(view.locator('strong')).toHaveText('Σημαντικό:');
  await expect(view.locator('em')).toHaveText('προθεσμία');
  await expect(view.locator('a[href="https://diavgeia.gov.gr/"]')).toHaveAttribute('rel', /noopener/);
  await expect(view.locator('a')).toHaveCount(1);   // the javascript: one stays text
  await expect(view).toContainText('[κακός σύνδεσμος](javascript:alert(1))');
  await expect(view.locator('img')).toHaveCount(0);
  await expect(view).toContainText('<img src=x');
  expect(await page.evaluate(() => window.__xss)).toBeUndefined();
  await evidence(page, 'n05-notepad');

  // a to-do ticked with a click is saved
  await view.locator('input[data-line="1"]').click();   // the list is drawn again from the text
  await expect(view.locator('input[data-line="1"]')).toBeChecked();
  await saved(page);
  await page.locator('#notepad-edit').click();
  await expect(text).toHaveValue(/- \[x\] Τηλέφωνο στη Διεύθυνση/);

  // a section folds and stays folded
  await page.locator('#notepad-view').click();
  await view.locator('details[data-title="Χρήσιμα"] > summary').click();
  await expect(view.locator('details[data-title="Χρήσιμα"]')).not.toHaveAttribute('open', '');

  // open on the next page (remembered in this browser), with the same note
  await page.goto('/about');
  await expect(page.locator('#notepad-panel')).toBeVisible();
  await expect(page.locator('#notepad-rendered')).toContainText('Τηλέφωνο στη Διεύθυνση');
  await expect(page.locator('#notepad-rendered details[data-title="Χρήσιμα"]')).not.toHaveAttribute('open', '');

  // a second tab saves; this one, with the older copy, is told and chooses
  const other = await context.newPage();
  await other.goto('/home/list');
  await other.locator('#notepad-edit').click();
  await other.locator('#notepad-text').fill('Από την άλλη καρτέλα');
  await saved(other);
  await page.locator('#notepad-edit').click();
  await page.locator('#notepad-text').fill('Από αυτή την καρτέλα');
  await expect(page.locator('#notepad-conflict')).toBeVisible({ timeout: 10000 });
  await evidence(page, 'n05-conflict');
  await page.locator('#notepad-mine').click();
  await saved(page);
  await other.reload();
  await expect(other.locator('#notepad-rendered')).toContainText('Από αυτή την καρτέλα');

  // leave it empty for the other tests
  await page.locator('#notepad-text').fill('');
  await saved(page);
});
