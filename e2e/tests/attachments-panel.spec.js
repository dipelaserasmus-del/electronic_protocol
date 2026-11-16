// R1c: attachments panel. A file dropped anywhere on the window is attached; its name is offered as the subject
// (grey), and becomes the subject on save if the field is left empty.
const { test, expect } = require('@playwright/test');
const { login, evidence, fillProtocol, saveProtocol } = require('./helpers');

async function dataTransfer(page, files) {
  return page.evaluateHandle((files) => {
    const dt = new DataTransfer();
    for (const f of files) dt.items.add(new File([f.body], f.name, { type: 'application/pdf' }));
    return dt;
  }, files);
}

test('drop files on the window: attached, subject from the file name (R1c)', async ({ page }) => {
  const stamp = Date.now();
  const name = `Πρόσκληση_σε_ημερίδα_${stamp}.pdf`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { fakelos: 'first' });

  const dt = await dataTransfer(page, [{ name, body: '%PDF-1.4 R1c\n%%EOF\n' }, { name: 'δεύτερο.pdf', body: '%PDF-1.4 2\n' }]);
  await page.dispatchEvent('main', 'dragenter', { dataTransfer: dt });
  await expect(page.locator('.drop-overlay')).toBeVisible();
  await expect(page.locator('.drop-overlay')).toHaveCSS('opacity', '1');   // after the fade-in
  await evidence(page, 'r1c-drop-overlay');
  await page.dispatchEvent('main', 'drop', { dataTransfer: dt });
  await expect(page.locator('.drop-overlay')).toBeHidden();

  const rows = page.locator('#attachments .upload-row');
  await expect(rows).toHaveCount(2);
  await expect(rows.first()).toContainText(name);
  const subject = page.locator('#f-thema');
  await expect(subject).toHaveAttribute('placeholder', `Πρόσκληση σε ημερίδα ${stamp}`);
  await expect(page.locator('#subject-from-file')).toBeVisible();
  await rows.first().locator('input[placeholder="ΑΔΑ"]').fill('ΑΔΑ-R1C');
  await page.addStyleTag({ content: '.app-topbar { position: static !important; }' });   // full-page capture
  await evidence(page, 'r1c-dropped-files', { fullPage: true });

  await saveProtocol(page);
  await expect(subject).toHaveValue(`Πρόσκληση σε ημερίδα ${stamp}`);
  await expect(page.locator('#attachments .attachment-row')).toHaveCount(2);
  await expect(page.locator('#attachments')).toContainText('ΑΔΑ ΑΔΑ-R1C');
  await page.addStyleTag({ content: '.app-topbar { position: static !important; }' });
  await evidence(page, 'r1c-saved', { fullPage: true });
});

test('click into the suggested subject to edit it (R1c)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  await page.setInputFiles('#f-uploads', { name: 'Αίτηση μετάθεσης.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n') });
  const subject = page.locator('#f-thema');
  await expect(subject).toHaveAttribute('placeholder', 'Αίτηση μετάθεσης');
  await subject.click();
  await expect(subject).toHaveValue('Αίτηση μετάθεσης');
  await subject.press('End');
  await page.keyboard.type(' (εκπαιδευτικού)');
  await fillProtocol(page, { fakelos: 'first' });
  await saveProtocol(page);
  await expect(subject).toHaveValue('Αίτηση μετάθεσης (εκπαιδευτικού)');
});
