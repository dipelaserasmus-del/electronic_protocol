// R3a: the advanced search screen (/find). Year filled in, Enter searches from any field (also Σχετικοί αριθμοί),
// part of the text without accents/case, the folder picker (code without dots, «Χωρίς φάκελο»), the pending check.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol, search } = require('./helpers');

test('subject, incoming number and related numbers: part of the text, Enter searches, kept in the address (R3a)', async ({ page }) => {
  const stamp = Date.now();
  const subject = `R3a Εκδρομή στο Μουσείο ${stamp}`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, {
    thema: subject, fakelos: 'first', in_num: `Φ.7/${stamp}/Δ2`, in_date: '03/01/2025',
    in_topos_ekdosis: 'Δοκιμή', in_arxi_ekdosis: 'Διεύθυνση Δοκιμής', in_paraliptis: 'Διευθυντής',
  });
  await saveProtocol(page);

  await page.goto('/find');
  const year = await page.locator('#s-year').inputValue();
  expect(year).toMatch(/^\d{4}$/);   // filled in with the current year

  // lower case, no accents, a part only; Enter in the field searches
  const results = await search(page, { thema: `εκδρομη στο μουσειο ${stamp}` }, { open: false });
  await expect(results.locator('#search-count')).toContainText('Βρέθηκαν 1 πρωτόκολλο');
  await expect(results.locator('#search-results mark').first()).toHaveText(`Εκδρομή στο Μουσείο ${stamp}`);
  await evidence(page, 'search-subject', { fullPage: true });

  // the search is in the address: a reload shows the same results
  expect(page.url()).toMatch(/[?&]s=(1|true)/);
  await page.reload();
  await expect(page.locator('#search-results')).toContainText(subject);

  // the middle of the incoming number
  await page.locator('#search-clear').click();
  await search(page, { in_num: `${stamp}/Δ` }, { open: false });
  await expect(page.locator('#search-results')).toContainText(subject);

  // Enter in "Σχετικοί αριθμοί" searches too (the old page's field swallowed it)
  await page.locator('#search-clear').click();
  await search(page, { sxetiko: '987654/1999' }, { open: false });
  await expect(page.locator('#search-count')).toContainText('Δεν βρέθηκε κανένα πρωτόκολλο');
});

test('folder picker: code without dots, «Χωρίς φάκελο»; the pending check says when nothing is pending (R3a)', async ({ page }) => {
  const stamp = Date.now();
  await login(page, 'admin');
  // one protocol in folder Φ.1.1, one without folder, one with ΕΚΚΡΕΜΟΤΗΤΑ in the subject
  await page.goto('/home');
  await fillProtocol(page, { thema: `R3a στον φάκελο ${stamp}`, fakelos: 'Φ.1.1' });
  await saveProtocol(page);
  await page.goto('/home');
  await fillProtocol(page, { thema: `R3a χωρίς φάκελο ${stamp}` });
  await saveProtocol(page);
  await page.goto('/home');
  await fillProtocol(page, { thema: `R3a αίτηση ${stamp} - ΕΚΚΡΕΜΟΤΗΤΑ`, fakelos: 'Φ.1.1' });
  await saveProtocol(page);

  // "φ11" finds Φ.1.1 (no dots); Enter chooses it, the next Enter searches
  await page.goto('/find');
  const folder = page.locator('#s-folder');
  await folder.click();
  await folder.fill('φ11');
  await expect(page.locator('#s-folder-list li[role=option]').first()).toContainText('Φ.1.1');
  await evidence(page, 'folder-without-dots', { clip: { x: 0, y: 0, width: 1440, height: 800 } });
  await folder.press('Enter');
  await expect(folder).toHaveValue(/^Φ\.1\.1/);
  await search(page, { thema: String(stamp) }, { open: false });
  const rows = page.locator('#search-results');
  await expect(rows).toContainText(`R3a στον φάκελο ${stamp}`);
  await expect(rows).not.toContainText(`R3a χωρίς φάκελο ${stamp}`);

  // «Χωρίς φάκελο»
  await folder.click();
  await page.locator('#s-folder-list li', { hasText: 'Χωρίς φάκελο' }).click();
  await search(page, { thema: String(stamp) }, { open: false });
  await expect(rows).toContainText(`R3a χωρίς φάκελο ${stamp}`);
  await expect(rows).not.toContainText(`R3a στον φάκελο ${stamp}`);

  // the pending check for today: both pending protocols, with the reason, and not the filed one
  const today = new Date();
  const d = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
  await page.goto('/find');
  await page.locator('#s-from').fill(d);
  await page.locator('#s-to').fill(d);
  await page.locator('#pending-check').click();
  await expect(page.locator('#pending-count')).toContainText('εκκρεμ');
  await expect(rows).toContainText(`R3a χωρίς φάκελο ${stamp}`);
  await expect(rows).toContainText(`R3a αίτηση ${stamp} - ΕΚΚΡΕΜΟΤΗΤΑ`);
  await expect(rows).not.toContainText(`R3a στον φάκελο ${stamp}`);
  await expect(page.locator('.protocol-row', { hasText: `R3a αίτηση ${stamp}` }).locator('.pending-reason')).toHaveText('ΕΚΚΡΕΜΟΤΗΤΑ στο θέμα');
  await evidence(page, 'pending-check', { fullPage: true });

  // a range with nothing pending says so clearly
  await page.goto('/find');
  await page.locator('#s-year').fill('');
  await page.locator('#s-from').fill('01/01/2001');
  await page.locator('#s-to').fill('02/01/2001');
  await page.locator('#pending-check').click();
  await expect(page.locator('#no-pending')).toContainText('Καμία εκκρεμότητα από 01/01/2001 έως 02/01/2001');
  await evidence(page, 'no-pending', { clip: { x: 0, y: 0, width: 1440, height: 900 } });
});

test('«Οπουδήποτε»: words anywhere and the search words (από:, φάκελος: …); the search shown as text (R3b)', async ({ page }) => {
  const stamp = Date.now();
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, {
    thema: `R3b μετάθεση ${stamp}`, fakelos: 'Φ.1.1', in_num: `Φ.9/${stamp}`, in_date: '03/01/2025',
    in_topos_ekdosis: 'Δοκιμή', in_arxi_ekdosis: 'Δήμος Δοκιμαστικός', in_paraliptis: 'Διευθυντής',
  });
  await saveProtocol(page);

  // two words in different fields (subject, sender), without accents
  await search(page, { words: `μεταθεση ${stamp} δοκιμαστικοσ` });
  await expect(page.locator('#search-results')).toContainText(`R3b μετάθεση ${stamp}`);
  await expect(page.locator('#search-as-text')).toContainText(`μεταθεση ${stamp} δοκιμαστικοσ έτος:`);

  // search words: the folder, without dots
  await search(page, { words: `${stamp} φάκελος:φ11 από:δήμος` });
  await expect(page.locator('#search-results')).toContainText(`R3b μετάθεση ${stamp}`);
  await evidence(page, 'anywhere-with-operators', { clip: { x: 0, y: 0, width: 1440, height: 900 } });
  await search(page, { words: `${stamp} φάκελος:κανένας` });
  await expect(page.locator('#search-count')).toContainText('Δεν βρέθηκε');

  // a value that cannot be used is shown on the field
  await search(page, { words: 'έτος:πέρσι' });
  await expect(page.locator('#s-words')).toHaveClass(/is-invalid/);
  await expect(page.locator('.search-page')).toContainText('Δεν χρησιμοποιήθηκαν: «έτος:πέρσι»');
});
