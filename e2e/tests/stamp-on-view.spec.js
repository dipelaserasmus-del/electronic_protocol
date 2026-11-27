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

    // review of R4n: one save at a time: while it is under way the three wait
    let release;
    const held = new Promise((r) => { release = r; });
    const hold = async (route) => { await held; await route.continue(); };
    await page.route('**/stamp-place', hold);
    const answered = page.waitForResponse((r) => r.url().includes('/stamp-place'));
    await places.locator('[data-stamp-place="top-center"]').click();
    await expect(places).toHaveAttribute('aria-busy', 'true');
    for (const b of await places.locator('[data-stamp-place]').all()) await expect(b).toBeDisabled();
    release();
    await answered;
    await page.unroute('**/stamp-place', hold);
    await expect(places.locator('[data-stamp-place="top-right"]')).toBeEnabled();
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

// R4o (owner, 2026-11-26): JPG / PNG get the stamp too (drawn on a copy of the picture), also at a file's own place
test('a scanned picture is shown and downloaded with the stamp (R4o)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const JPG = fs.readFileSync(path.join(__dirname, '..', 'fixtures', 'preview', 'sarosi.jpg'));
  await login(page, 'admin');
  const previous = await setSetting(page, 'putStamp', 1);
  await stamped(page, 'yes');
  try {
    await page.goto('/home');
    await fillProtocol(page, { thema: `R4o σαρωμένη εικόνα ${Date.now()}`, fakelos: 'first' });
    await page.setInputFiles('#f-uploads', { name: 'Σάρωση.jpg', mimeType: 'image/jpeg', buffer: JPG });
    await expect(page.locator('.upload-row')).toHaveCount(1);
    await saveProtocol(page);
    const href = await page.locator('.attachment-row a[href*="/download/"]').first().getAttribute('href');
    const got = await (await page.request.get(href)).body();
    expect(got.subarray(0, 3).toString('hex')).toBe('ffd8ff');   // still a JPG
    expect(got.equals(JPG)).toBe(false);                          // the stamp drawn on the copy
    expect((await (await page.request.get(`${href}?original=1`)).body()).equals(JPG)).toBe(true);

    // the preview shows the stamped picture, with ↖ ↑ ↗ as for PDFs
    await page.locator('.attachment-row .preview-button').first().click();
    const img = page.locator('#preview-body img.preview-image');
    await expect(img).toBeVisible();
    await expect(page.locator('#preview-place')).toBeVisible();
    await page.locator('#preview-place [data-stamp-place="top-left"]').click();
    await expect(page.locator('#preview-place [data-stamp-place="top-left"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(img).toHaveAttribute('src', /v=\d+$/);
    await expect.poll(() => img.evaluate((el) => el.complete && el.naturalWidth)).toBeGreaterThan(0);
    await evidence(page, 'r4o-picture-preview');
  } finally {
    await setSetting(page, 'putStamp', previous);
  }
});

// R4p (owner, 2026-11-27): the stamp on one file as it is (see-through, the number and date on white) or opaque
test('the stamp made opaque on one file from the preview, and see-through again (R4p)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page, 'admin');
  // an A4 page full of grey lines (made in the browser), so that what shows through the stamp is seen
  const JPG = Buffer.from(await page.evaluate(() => {
    const c = document.createElement('canvas');
    [c.width, c.height] = [1050, 1485];
    const g = c.getContext('2d');
    g.fillStyle = '#fff';
    g.fillRect(0, 0, c.width, c.height);
    g.strokeStyle = '#555';
    g.lineWidth = 3;
    for (let i = -c.height; i < c.width; i += 18) { g.beginPath(); g.moveTo(i, 0); g.lineTo(i + c.height, c.height); g.stroke(); }
    return c.toDataURL('image/jpeg', 0.9).split(',')[1];
  }), 'base64');
  const previous = await setSetting(page, 'putStamp', 1);
  await stamped(page, 'yes');
  try {
    await page.goto('/home');
    await fillProtocol(page, { thema: `R4p αδιαφανής σφραγίδα ${Date.now()}`, fakelos: 'first' });
    await page.setInputFiles('#f-uploads', { name: 'Σάρωση.jpg', mimeType: 'image/jpeg', buffer: JPG });
    await expect(page.locator('.upload-row')).toHaveCount(1);
    await saveProtocol(page);
    const href = await page.locator('.attachment-row a[href*="/download/"]').first().getAttribute('href');
    const seeThrough = await (await page.request.get(href)).body();

    await page.locator('.attachment-row .preview-button').first().click();
    const img = page.locator('#preview-body img.preview-image');
    const opacity = page.locator('#preview-opacity');
    await expect(opacity).toBeVisible();
    await expect(opacity.locator('[data-stamp-opaque="0"]')).toHaveAttribute('aria-pressed', 'true');
    await expect.poll(() => img.evaluate((el) => el.complete && el.naturalWidth)).toBeGreaterThan(0);
    // evidence: the stamp's corner of the downloaded copy, at the picture's own size
    const corner = async (bytes, name) => {
      const view = await page.context().newPage();
      await view.setViewportSize({ width: 480, height: 150 });
      await view.setContent(`<body style="margin:0;overflow:hidden"><img style="margin-left:-570px"
        src="data:image/jpeg;base64,${bytes.toString('base64')}"></body>`);
      await view.waitForFunction(() => document.images[0].complete);
      await evidence(view, name);
      await view.close();
    };
    await corner(seeThrough, 'r4p-see-through');

    await opacity.locator('[data-stamp-opaque="1"]').click();
    await expect(opacity.locator('[data-stamp-opaque="1"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(opacity.locator('[data-stamp-opaque="0"]')).toHaveAttribute('aria-pressed', 'false');
    await expect(page.locator('#preview-place [data-stamp-place="top-right"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(img).toHaveAttribute('src', /v=\d+$/);
    await expect.poll(() => img.evaluate((el) => el.complete && el.naturalWidth)).toBeGreaterThan(0);
    await evidence(page, 'r4p-buttons', { clip: await page.locator('#preview-modal .modal-header').boundingBox() });
    const opaque = await (await page.request.get(href)).body();
    expect(opaque.subarray(0, 3).toString('hex')).toBe('ffd8ff');
    expect(opaque.equals(seeThrough)).toBe(false);
    await corner(opaque, 'r4p-opaque');
    expect((await (await page.request.get(`${href}?original=1`)).body()).equals(JPG)).toBe(true);   // stored as it came

    // kept for the file: opened again, opaque is pressed; then back to see-through
    await page.keyboard.press('Escape');
    await page.reload();
    await page.locator('.attachment-row .preview-button').first().click();
    await expect(opacity.locator('[data-stamp-opaque="1"]')).toHaveAttribute('aria-pressed', 'true');
    await opacity.locator('[data-stamp-opaque="0"]').click();
    await expect(opacity.locator('[data-stamp-opaque="0"]')).toHaveAttribute('aria-pressed', 'true');
    expect((await (await page.request.get(href)).body()).equals(seeThrough)).toBe(true);
  } finally {
    await setSetting(page, 'putStamp', previous);
  }
});
