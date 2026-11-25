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
