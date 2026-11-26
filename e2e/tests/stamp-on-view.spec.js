// R4f: files are stored as they came; opening / downloading / printing gives the stamped copy, unless the user turns
// «Με σφραγίδα» off (saved with the account; the switch is in the attachments panel and in the preview window).
const path = require('path');
const fs = require('fs');
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol } = require('./helpers');

const PDF = fs.readFileSync(path.join(__dirname, '..', 'fixtures', 'preview', 'prosklisi.pdf'));
const MARK = '%EP-STAMP';

/** the logged-in user's «Με σφραγίδα» choice, set directly (the tests share one database) */
async function stamped(page, value) {
  await page.evaluate(async (v) => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    await fetch('/preferences', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ key: 'stamped', value: v }) });
  }, value);
}

test('stamp on view: stamped download by default, the original with «Με σφραγίδα» off (R4f)', async ({ page }) => {
  await login(page, 'admin');
  const previous = await setSetting(page, 'putStamp', 1);
  await stamped(page, 'yes');
  try {
    await page.goto('/home');
    await fillProtocol(page, { thema: `R4f σφραγίδα ${Date.now()}`, fakelos: 'first' });
    await page.setInputFiles('#f-uploads', { name: 'Πρόσκληση.pdf', mimeType: 'application/pdf', buffer: PDF });
    await expect(page.locator('.upload-row')).toHaveCount(1);
    await expect(page.locator('#f-nostamp')).toHaveCount(0);   // «Χωρίς σφραγίδα» is gone
    await saveProtocol(page);

    const href = await page.locator('.attachment-row a[href*="/download/"]').first().getAttribute('href');
    const body = async () => (await (await page.request.get(href)).body()).toString('latin1');

    // by default: the stamped copy (the original bytes first, the stamp after them)
    const toggle = page.locator('#form-stamp-input');
    await expect(toggle).toBeChecked();
    let got = await body();
    expect(got.startsWith(PDF.toString('latin1'))).toBe(true);
    expect(got).toContain(MARK);
    await page.locator('.attachment-row').first().scrollIntoViewIfNeeded();
    await evidence(page, 'r4f-switch', { clip: await page.locator('#attachments-title').evaluate((el) => {
      const r = el.closest('section, .card').getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: Math.min(r.height, 260) };
    }) });

    // off: the file as stored; the preview window's switch follows
    const saved = page.waitForResponse((r) => r.url().endsWith('/preferences') && r.request().method() === 'POST');
    await toggle.uncheck();
    await saved;
    got = await body();
    expect(got).toBe(PDF.toString('latin1'));
    await page.locator('.attachment-row .preview-button').first().click();
    await expect(page.locator('#preview-modal')).toBeVisible();
    await expect(page.locator('#preview-stamp-input')).not.toBeChecked();
    const frame = page.locator('#preview-body iframe');
    await expect(frame).toHaveAttribute('src', /\/download\/\d+\?v=\d+$/);
    const before = await frame.getAttribute('src');

    // on again, from the preview window: the PDF is shown again, stamped
    const again = page.waitForResponse((r) => r.url().endsWith('/preferences') && r.request().method() === 'POST');
    await page.locator('#preview-stamp-input').check();
    await again;
    await expect(frame).not.toHaveAttribute('src', before);   // loaded again
    expect(await body()).toContain(MARK);
    await page.reload();
    await expect(page.locator('#form-stamp-input')).toBeChecked();   // kept with the account

    // R4i (owner, 2026-11-25): with the setting off the switch is still there, and the user's own choice holds
    const form = page.url();
    await setSetting(page, 'putStamp', 0);
    await page.goto(form);
    await expect(page.locator('#form-stamp-input')).toBeVisible();
    await expect(page.locator('#form-stamp-input')).toBeChecked();
    expect(await body()).toContain(MARK);
    await page.locator('.attachment-row').first().scrollIntoViewIfNeeded();
    await evidence(page, 'r4i-switch-setting-off', { clip: await page.locator('#attachments-title').evaluate((el) => {
      const r = el.closest('section, .card').getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: Math.min(r.height, 260) };
    }) });
    await page.locator('.attachment-row .preview-button').first().click();
    await expect(page.locator('#preview-stamp-input')).toBeChecked();
    await expect(page.locator('#preview-modal')).toHaveCSS('opacity', '1');
    const head = await page.locator('#preview-modal .modal-content').boundingBox();
    await evidence(page, 'r4i-preview-setting-off', { clip: { x: head.x, y: head.y, width: head.width, height: Math.min(head.height, 420) } });
  } finally {
    await stamped(page, 'yes');
    await setSetting(page, 'putStamp', previous);
  }
});

// R4n (owner, 2026-11-26): the stamp's place on one file (↖ ↑ ↗ in the preview), for every user who sees the protocol
test('the stamp moved to the top left or centre of one file from the preview (R4n)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  const previous = await setSetting(page, 'putStamp', 1);
  await stamped(page, 'yes');
  try {
    await page.goto('/home');
    await fillProtocol(page, { thema: `R4n θέση σφραγίδας ${Date.now()}`, fakelos: 'first' });
    await page.setInputFiles('#f-uploads', { name: 'Πρόσκληση.pdf', mimeType: 'application/pdf', buffer: PDF });
    await expect(page.locator('.upload-row')).toHaveCount(1);
    await saveProtocol(page);
    const protocolUrl = page.url();
    const href = await page.locator('.attachment-row a[href*="/download/"]').first().getAttribute('href');
    // where the stamp is: the x of its placement on page 1
    const stampX = async () => {
      const pdf = (await (await page.request.get(href)).body()).toString('latin1');
      return Number(pdf.match(/q ([-\d. ]+) cm \/EPStamp Do/)[1].split(' ')[4]);
    };
    const right = await stampX();

    await page.locator('.attachment-row .preview-button').first().click();
    const places = page.locator('#preview-place');
    await expect(places).toBeVisible();
    await expect(places.locator('[data-stamp-place="top-right"]')).toHaveAttribute('aria-pressed', 'true');
    const frame = page.locator('#preview-body iframe');
    const before = await frame.getAttribute('src');

    const saved = page.waitForResponse((r) => r.url().includes('/stamp-place') && r.request().method() === 'POST');
    await places.locator('[data-stamp-place="top-left"]').click();
    expect((await saved).ok()).toBe(true);
    await expect(places.locator('[data-stamp-place="top-left"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(frame).not.toHaveAttribute('src', before);   // shown again
    expect(await stampX()).toBeLessThan(right / 4);
    if (process.env.EVIDENCE) fs.writeFileSync('test-results/r4n-left.pdf', await (await page.request.get(href)).body());
    await evidence(page, 'r4n-place-buttons', { clip: await page.locator('#preview-modal .modal-header').boundingBox() });

    await places.locator('[data-stamp-place="top-center"]').click();
    await expect(places.locator('[data-stamp-place="top-center"]')).toHaveAttribute('aria-pressed', 'true');
    await expect.poll(stampX).toBeGreaterThan(right / 4);
    expect(await stampX()).toBeLessThan(right);
    // the headless browser draws no PDF in a frame: the stamped file itself is kept for the report (page 1 as a picture)
    if (process.env.EVIDENCE) fs.writeFileSync('test-results/r4n-centre.pdf', await (await page.request.get(href)).body());

    // with «Με σφραγίδα» off the three are hidden; another user sees the file's place
    await page.locator('#preview-stamp-input').uncheck();
    await expect(places).toBeHidden();
    await page.locator('#preview-stamp-input').check();
    const reader = await page.context().browser().newPage();
    await login(reader, 'reader');
    await reader.goto(protocolUrl);
    await expect(reader.locator('.attachment-row .preview-button').first()).toHaveAttribute('data-preview-place', 'top-center');
    await reader.close();
  } finally {
    await setSetting(page, 'putStamp', previous);
  }
});
