// R4c: 👁 previews a file in a large modal; ‹ › (and the arrow keys) go through the protocol's files. PDFs in the
// browser's viewer, images, text / Word / spreadsheets converted on the server in a sandboxed frame, other types: a
// message and the download.
const path = require('path');
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

const fixture = (name) => path.join(__dirname, '..', 'fixtures', 'preview', name);
// a 1×1 PNG
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

test('👁 previews the protocol\'s files; ‹ › go through them (R4c)', async ({ page }) => {
  const stamp = Date.now();
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R4c προεπισκόπηση ${stamp}`, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', [
    { name: 'Πρόσκληση ημερίδας.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: require('fs').readFileSync(fixture('prosklisi.docx')) },
    { name: 'Μαθητές ανά τάξη.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: require('fs').readFileSync(fixture('mathites.xlsx')) },
    { name: 'Σημείωμα.txt', mimeType: 'text/plain', buffer: Buffer.from('Καλημέρα <b>σας</b>\nδεύτερη γραμμή\n') },
    { name: 'Λογότυπο.png', mimeType: 'image/png', buffer: PNG },
    { name: 'Αρχεία.zip', mimeType: 'application/zip', buffer: Buffer.from('PK\u0005\u0006' + '\u0000'.repeat(18)) },
  ]);
  await expect(page.locator('.upload-row')).toHaveCount(5);
  await saveProtocol(page);

  const rows = page.locator('.attachment-row');
  await expect(rows).toHaveCount(5);
  const modal = page.locator('#preview-modal');
  const title = page.locator('#preview-title');
  const opened = async (name) => {
    await expect(modal).toBeVisible();
    await expect(modal).toHaveCSS('opacity', '1');   // the fade has ended (screenshots)
    await expect(title).toHaveText(name);
  };
  const close = async () => { await modal.locator('.btn-close').click(); await expect(modal).toBeHidden(); };

  // Word: converted, sandboxed, "approximate" note
  await rows.filter({ hasText: 'Πρόσκληση ημερίδας.docx' }).locator('.preview-button').click();
  await opened('Πρόσκληση ημερίδας.docx');
  await expect(page.locator('#preview-count')).toHaveText(/\d από 4/);   // R4j: the .zip (no preview) is left out
  const frame = page.locator('#preview-body iframe.preview-frame');
  await expect(frame).toHaveAttribute('sandbox', '');
  await expect(page.frameLocator('#preview-body iframe').locator('body')).toContainText('Ψηφιακά εργαλεία στην τάξη');
  await expect(page.locator('#preview-note')).toBeVisible();
  await evidence(page, 'r4c-word');

  // through the files with › / the arrow keys, wrapping round
  const seen = new Set([await title.textContent()]);
  for (let i = 0; i < 3; i++) {
    await (i % 2 ? page.keyboard.press('ArrowRight') : page.locator('[data-preview-step="1"]').click());
    seen.add(await title.textContent());
  }
  expect(seen.size).toBe(4);   // R4j: the .zip is not among them
  expect(seen.has('Αρχεία.zip')).toBe(false);
  await page.keyboard.press('ArrowRight');
  await expect(title).toHaveText('Πρόσκληση ημερίδας.docx');   // round again

  // spreadsheet: both sheets, the values saved in the file
  await close();
  await rows.filter({ hasText: 'Μαθητές ανά τάξη.xlsx' }).locator('.preview-button').click();
  await opened('Μαθητές ανά τάξη.xlsx');
  const sheet = page.frameLocator('#preview-body iframe').locator('body');
  await expect(sheet).toContainText('Εκπαιδευτικοί');
  await expect(sheet).toContainText('103');   // =SUM(C2:C4) as saved
  await evidence(page, 'r4c-sheet');

  // text: escaped, no note
  await close();
  await rows.filter({ hasText: 'Σημείωμα.txt' }).locator('.preview-button').click();
  await opened('Σημείωμα.txt');
  await expect(page.frameLocator('#preview-body iframe').locator('pre')).toContainText('Καλημέρα <b>σας</b>');
  await expect(page.locator('#preview-note')).toBeHidden();

  // image: shown as such
  await page.locator('[data-preview-step="1"]').click();
  await opened('Λογότυπο.png');
  await expect(page.locator('#preview-body img.preview-image')).toHaveJSProperty('naturalWidth', 1);

  // R4j (owner, 2026-11-25): a type without preview is not in ‹ ›: after the picture, round to the first file again
  await page.locator('[data-preview-step="1"]').click();
  await expect(page.locator('#preview-title')).not.toHaveText('Αρχεία.zip');
  await close();

  // and its 👁 is grey, not a button: a click opens nothing
  const zip = rows.filter({ hasText: 'Αρχεία.zip' });
  await expect(zip.locator('.preview-button')).toHaveCount(0);
  const grey = zip.locator('[data-preview-none]');
  await expect(grey).toBeVisible();
  await expect(grey).toHaveAttribute('title', /Δεν υπάρχει προεπισκόπηση/);
  expect(Number(await grey.evaluate((el) => getComputedStyle(el).opacity))).toBeLessThan(0.5);
  await grey.click();
  await expect(page.locator('#preview-modal')).toBeHidden();
  await zip.scrollIntoViewIfNeeded();
  const box = await zip.boundingBox();
  await evidence(page, 'r4j-no-preview', { clip: { x: box.x, y: box.y - 4, width: Math.min(box.width, 700), height: box.height + 8 } });
});

// R4q (owner, 2026-11-27): an e-mail file (.eml) is previewed as an e-mail (headers, then its HTML), and the list has
// 👁 next to each protocol's files too (‹ › go through that protocol's files)
test('e-mail files are previewed, and the list has 👁 for each protocol\'s files (R4q)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  const thema = `R4q προεπισκόπηση email ${Date.now()}`;
  const b64 = (s) => Buffer.from(s).toString('base64');
  const eml = Buffer.from([
    `From: =?UTF-8?B?${b64('Διεύθυνση Δοκιμής')}?= <directorate@example.test>`, 'To: school@example.test',
    `Subject: =?UTF-8?B?${b64('Πρόσκληση σε ημερίδα')}?=`, 'Date: Mon, 02 Nov 2026 07:15:00 +0000', 'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', '',
    '<html><head><style>.lead{color:#0b3d91;font-size:18px}</style></head><body><p class="lead">Σας προσκαλούμε στην ημερίδα.</p>'
      + '<p>Με εκτίμηση,<br>Η Διεύθυνση</p><img src="https://tracker.example/pixel.gif" alt=""><script>document.body.innerHTML = "ran"</script></body></html>',
  ].join('\r\n'), 'utf8');
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { thema, fakelos: 'first' });
  await page.setInputFiles('#f-uploads', [
    { name: 'email_20261102_091500.eml', mimeType: 'message/rfc822', buffer: eml },
    { name: 'Πρόσκληση.pdf', mimeType: 'application/pdf', buffer: require('fs').readFileSync(fixture('prosklisi.pdf')) },
  ]);
  await expect(page.locator('.upload-row')).toHaveCount(2);
  await saveProtocol(page);

  // in the form: the e-mail file has a 👁 (before R4q it had the grey one) and opens as an e-mail
  const emlRow = page.locator('.attachment-row', { hasText: '.eml' });
  await emlRow.locator('.preview-button').click();
  const frame = page.frameLocator('#preview-body iframe');
  await expect(frame.locator('body')).toContainText('Πρόσκληση σε ημερίδα');
  await expect(frame.locator('body')).toContainText('Διεύθυνση Δοκιμής <directorate@example.test>');
  await expect(frame.locator('.lead')).toHaveText('Σας προσκαλούμε στην ημερίδα.');
  await expect(frame.locator('body')).not.toContainText('ran');   // its script never runs
  await expect(page.locator('#preview-note')).toBeHidden();       // not an approximation: no «κατά προσέγγιση»
  await evidence(page, 'r4q-eml-preview');
  await page.keyboard.press('Escape');

  // the list: 👁 next to the file; ‹ › go through this protocol's two files
  await page.goto('/home/list');
  const row = page.locator('tr.protocol-row', { hasText: thema });
  await expect(row.locator('.preview-button')).toHaveCount(2);
  await expect(row.locator('.preview-button').first()).toBeVisible();
  await evidence(page, 'r4q-list', { clip: await row.boundingBox() });
  await row.locator('.preview-button').first().click();
  await expect(page.locator('#preview-modal')).toBeVisible();
  await expect(page.locator('#preview-count')).toHaveText('1 από 2');
  const first = await page.locator('#preview-title').textContent();
  await page.locator('#preview-modal [data-preview-step="1"]').click();
  await expect(page.locator('#preview-count')).toHaveText('2 από 2');
  expect(await page.locator('#preview-title').textContent()).not.toBe(first);
  await evidence(page, 'r4q-list-preview');
});
