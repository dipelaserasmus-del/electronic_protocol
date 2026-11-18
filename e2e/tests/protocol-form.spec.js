// R1b: the rebuilt protocol form. The number says at once whether it is free; TAB from the number goes to the date
// and typing the day completes it; an existing subject is a notice next to the field (no YES/NO dialog).
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol, notice } = require('./helpers');

test.describe('administrator with registration checks off', () => {
  let previousValidate;
  test.beforeEach(async ({ page }) => {
    await login(page, 'admin');
    previousValidate = await setSetting(page, 'protocolValidate', 0);
  });
  test.afterEach(async ({ page }) => {
    await page.context().clearCookies();
    await login(page, 'admin');
    await setSetting(page, 'protocolValidate', previousValidate);
  });

  test('number: free or "Δεσμευμένος", TAB goes to the day (R1b)', async ({ page }) => {
    await page.goto('/home');
    const number = page.locator('#f-protocolnum');
    const status = page.locator('#number-status');
    await expect(status).toHaveText('');   // R1g: nothing to say until the number is changed

    await number.fill('1');   // the demo book starts at 1
    await expect(status).toContainText('Δεσμευμένος από το');
    await evidence(page, 'r1b-number-used', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

    await number.fill(String(Number(await number.getAttribute('placeholder')) + 100 + (Date.now() % 100)));   // never used
    await expect(status).toContainText('Ελεύθερος');
    await number.press('Tab');
    await expect(page.locator('#f-protocoldate')).toBeFocused();
    await page.keyboard.type('9');
    await page.keyboard.press('Tab');
    const d = new Date();
    const expected = `09/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    await expect(page.locator('#f-protocoldate')).toHaveValue(expected);
    await evidence(page, 'r1b-number-free-date', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

    // ▶ (next day) changes the date without focusing the field: the saved date must be the shown one
    await page.locator('[data-date-input]:has(#f-protocoldate) [data-date-action=next]').click();
    const next = `10/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    await expect(page.locator('#f-protocoldate')).toHaveValue(next);
    await fillProtocol(page, { thema: `R1b ημερομηνία ${Date.now()}` });
    await saveProtocol(page);
    await expect(page.locator('#f-protocoldate')).toHaveValue(next);
  });
});

test('same subject: a notice with links, saving is not blocked (R1b)', async ({ page }) => {
  const subject = `R1b ίδιο θέμα ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: subject, fakelos: 'first' });
  await saveProtocol(page);
  const first = page.url();

  await page.goto('/home');
  await fillProtocol(page, { thema: subject });
  const note = page.locator('#notice-thema');
  await expect(note).toContainText('Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα');
  await expect(note.locator('a')).toHaveAttribute('href', first);
  await evidence(page, 'r1b-same-subject-notice', { clip: { x: 0, y: 0, width: 1440, height: 700 } });
  await saveProtocol(page);
  expect(await notice(page)).toContain('Καταχωρίστηκε με αριθμό');
  await evidence(page, 'r1b-saved', { fullPage: true });
});

test('reader sees the protocol read-only, without save (R1b)', async ({ page }) => {
  await login(page, 'reader');
  await page.goto('/home/list');
  await page.locator('.protocol-row a.fw-bold').first().click();
  await expect(page).toHaveURL(/\/home\/\d+$/);
  await expect(page.locator('#f-thema')).toBeDisabled();
  await expect(page.locator('#protocol-form button[type=submit]')).toHaveCount(0);
});

test('a 2 MB attachment is uploaded and saved (R1b: nginx allowed only 1 MB)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R1b μεγάλο αρχείο ${Date.now()}`, fakelos: 'first' });
  const body = Buffer.concat([Buffer.from('%PDF-1.4 R1b\n'), Buffer.alloc(2 * 1024 * 1024, 0x20), Buffer.from('\n%%EOF\n')]);
  await page.setInputFiles('#f-uploads', { name: 'σαρωμένο.pdf', mimeType: 'application/pdf', buffer: body });
  await expect(page.locator('#attachments')).toContainText('σαρωμένο.pdf');
  await saveProtocol(page);
  const link = page.locator('#attachments a[href*="/download/"]', { hasText: 'σαρωμένο.pdf' });
  await expect(link).toBeVisible();
  const file = await page.request.get(await link.getAttribute('href'));
  expect((await file.body()).length).toBe(body.length);
  await evidence(page, 'r1b-attachment-saved', { fullPage: true });
});

test('related numbers: green ✔ when the protocol exists (↗ opens it), orange ⚠ otherwise, editable (R1e, R1f)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R1e σχετικό ${Date.now()}`, fakelos: 'first' });
  await saveProtocol(page);
  const first = page.url();
  const [, num, year] = (await notice(page)).match(/Καταχωρίστηκε με αριθμό (\d+)\/(\d{4})/);

  await page.goto('/home');
  const box = page.locator('#f-sxetiko');
  const pills = page.locator('#related-box .related-pill');
  await box.fill(num);
  await box.press('Enter');                                   // Enter makes a pill (and does not save); the year is added
  await expect(pills.first()).toHaveClass(/tag-ok/);
  await expect(pills.first()).toContainText(`${num}/${year}`);
  await expect(pills.first().locator('svg.icon-check')).toHaveCount(1);
  const link = page.locator('#related-box a');
  await expect(link).toHaveText('↗');
  await expect(link).toHaveAttribute('href', first);
  await expect(link).toHaveAttribute('target', '_blank');
  await expect(page).toHaveURL(/\/home$/);
  await box.pressSequentially('999999,');                     // a comma too; no such protocol = orange with ⚠
  await expect(pills).toHaveCount(2);
  await expect(pills.last()).toHaveClass(/tag-missing/);
  await expect(pills.last().locator('svg.icon-warning')).toHaveCount(1);
  await expect(pills.last()).toHaveAttribute('title', `Δεν υπάρχει πρωτόκολλο 999999/${year}`);
  await expect(box).toHaveValue('');

  // a click on a tag's text edits it in place; Enter keeps it where it was
  await pills.last().locator('.tag-text').click();
  const edit = pills.last().locator('.tag-edit');
  await expect(edit).toBeVisible();
  await expect(edit).toHaveValue(`999999/${year}`);
  await edit.fill('888888');
  await edit.press('Enter');
  await expect(pills).toHaveCount(2);
  await expect(pills.last()).toContainText(`888888/${year}`);
  await expect(pills.last()).toHaveClass(/tag-missing/);
  // Escape leaves a tag unchanged
  await pills.first().locator('.tag-text').click();
  await pills.first().locator('.tag-edit').fill('1');
  await pills.first().locator('.tag-edit').press('Escape');
  await expect(pills.first()).toContainText(`${num}/${year}`);
  await box.click();
  await box.pressSequentially('5');
  await evidence(page, 'r1f-related-tags', { clip: { x: 0, y: 0, width: 1440, height: 700 } });
  await box.press('Backspace');
  await box.press('Backspace');                               // empty box: removes the last pill
  await expect(pills).toHaveCount(1);
  const [tab] = await Promise.all([page.context().waitForEvent('page'), link.click()]);
  await tab.waitForLoadState();
  expect(tab.url()).toBe(first);
});

test('keywords are tags with suggestions that ignore case and accents (R1f)', async ({ page }) => {
  const stamp = String(Date.now()).slice(-6);
  const keyword = `Ψηφιακός Φάκελος ${stamp}`;
  await login(page, 'writer');
  await page.goto('/home');
  await fillProtocol(page, { thema: `R1f λέξεις ${stamp}`, fakelos: 'first' });
  const box = page.locator('#f-keywords');
  const pills = page.locator('#keywords-box .keyword-pill');
  await box.fill(keyword);
  await box.press('Enter');
  await expect(pills).toHaveCount(1);
  await saveProtocol(page);

  await page.goto('/home');
  const suggestions = page.locator('#keywords-suggest li');
  // review of R1f: a suggestion highlighted, the list closed with Escape: Enter adds what was typed
  await box.pressSequentially(`ψηφιακ ${stamp}`);
  await expect(suggestions.first()).toBeVisible();
  await box.press('ArrowDown');
  await box.press('Escape');
  await expect(page.locator('#keywords-suggest')).toBeHidden();
  await box.press('Enter');
  await expect(pills).toHaveCount(1);
  await expect(pills.last()).toHaveAttribute('data-tag', `ψηφιακ ${stamp}`);
  await pills.last().locator('.btn-close').click();
  await expect(pills).toHaveCount(0);

  await box.pressSequentially(`ψηφιακος ${stamp}`);         // no accents, lower case, any order of words
  await expect(suggestions.first()).toContainText(keyword);
  await expect(suggestions.first().locator('mark').first()).toHaveText('Ψηφιακός');
  await evidence(page, 'r1f-keyword-suggestions', { clip: { x: 0, y: 0, width: 1440, height: 760 } });
  await box.press('ArrowDown');
  await expect(suggestions.first()).toHaveClass(/active/);
  await box.press('Enter');
  await expect(pills).toHaveCount(1);
  await expect(pills.first()).toHaveAttribute('data-tag', keyword);   // the known spelling
  await expect(page.locator('#keywords-suggest')).toBeHidden();

  // typed again without accents: the same keyword, not a second one
  await box.fill(`ψηφιακος φακελος ${stamp}`);
  await box.press('Enter');
  await expect(pills).toHaveCount(1);
  // a new keyword with a comma; then edited in place
  await box.pressSequentially('νέα λέξη,');
  await expect(pills).toHaveCount(2);
  await pills.last().locator('.tag-text').click();
  await pills.last().locator('.tag-edit').fill('άλλη λέξη');
  await pills.last().locator('.tag-edit').press('Enter');
  await expect(pills.last()).toHaveAttribute('data-tag', 'άλλη λέξη');
  await expect(pills.first()).toHaveAttribute('data-tag', keyword);
});

test('the folder picker narrows the list while typing (R1f)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  const folder = page.locator('#f-fakelos');
  const list = page.locator('#folder-list');
  await folder.click();
  await expect(list).toBeVisible();
  expect(await list.locator('li[role=option]').count()).toBeGreaterThan(20);   // everything until something is typed
  await folder.fill('αποζημιωσεις εκδρομων');                // words of the description, no accents
  await expect(list.locator('li[role=option]')).toHaveCount(1);
  await expect(list.locator('li[role=option]').first()).toContainText('Φ.14.1.4');
  await evidence(page, 'r1f-folder-filter', { clip: { x: 0, y: 0, width: 1440, height: 760 } });
  await folder.press('Enter');
  await expect(list).toBeHidden();
  await expect(folder).toHaveValue(/^Φ\.14\.1\.4 · Αποζημιώσεις εκδρομών/);
  await expect(page.locator('#folder-help')).toContainText('Αποζημιώσεις εκδρομών');

  // by code; Escape keeps the chosen folder
  await folder.click();
  await folder.fill('19.2');
  await expect(list.locator('li[role=option]').first()).toContainText('Φ.19.2');
  await folder.press('Escape');
  await expect(folder).toHaveValue(/^Φ\.14\.1\.4/);

  // review of R1f: the folder can be cleared again: "—" first in the list, or the text deleted and Enter
  await folder.click();
  await expect(list.locator('li[role=option]').first()).toContainText('Χωρίς φάκελο');
  await list.locator('li[role=option]').first().click();
  await expect(folder).toHaveValue('');
  await expect(page.locator('#folder-help')).toHaveCount(0);
  await fillProtocol(page, { fakelos: 'αποζημιωσεις εκδρομων' });
  await expect(folder).toHaveValue(/^Φ\.14\.1\.4/);
  await folder.click();
  await folder.pressSequentially('φ');
  await folder.press('Backspace');                           // typed, then deleted: no folder
  await folder.press('Enter');
  await expect(folder).toHaveValue('');
  await fillProtocol(page, { fakelos: 'αποζημιωσεις εκδρομων' });

  await fillProtocol(page, { thema: `R1f φάκελος ${Date.now()}` });
  await saveProtocol(page);
  await expect(folder).toHaveValue(/^Φ\.14\.1\.4/);
});

test('the browser ignores case and accents like the server (R1f)', async ({ page }) => {
  const fs = require('fs');
  const path = require('path');
  const cases = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'tests', 'fixtures', 'fold-cases.json'), 'utf8'));
  await login(page, 'writer');
  await page.goto('/home');
  const got = await page.evaluate((cases) => cases.map(([input]) => window.appFold(input)), cases);
  expect(got).toEqual(cases.map(([, out]) => out));
});

test('the number is large in the middle; assignment and receipt are below the attachments (R1g)', async ({ page }) => {
  await login(page, 'admin');
  const previousValidate = await setSetting(page, 'protocolValidate', 0);   // the administrator may type the number
  try {
    await page.goto('/home');
    const number = page.locator('#f-protocolnum');
    await expect(page.locator('#number-status')).toHaveText('');
    await expect(number).toHaveClass(/is-valid/);                                // green border
    const head = await page.locator('.number-head').boundingBox();
    const box = await number.boundingBox();
    expect(Math.abs((box.x + box.width / 2) - (head.x + head.width / 2))).toBeLessThan(20);
    const size = async (sel) => parseFloat(await page.locator(sel).evaluate((el) => getComputedStyle(el).fontSize));
    expect(await size('#f-protocolnum')).toBeGreaterThan(2 * (await size('#f-etos')));
    await evidence(page, 'r1g-head', { clip: { x: 0, y: 0, width: 1440, height: 330 } });

    // typing a number says whether it is free
    await number.fill(String(Number(await number.getAttribute('placeholder')) + 500 + (Date.now() % 100)));
    await expect(page.locator('#number-status')).toContainText('Ελεύθερος');
    await number.fill('');
    await expect(page.locator('#number-status')).toContainText('ο επόμενος ελεύθερος');

    const y = async (sel) => (await page.locator(sel).boundingBox()).y;
    expect(await y('#f-diekp_date')).toBeLessThan(await y('#attachments'));    // used often: high
    expect(await y('#f-handle')).toBeGreaterThan(await y('#attachments'));     // used less: below
    expect(await y('#f-reply_to_email')).toBeGreaterThan(await y('#attachments'));
    await page.setViewportSize({ width: 1440, height: 2000 });
    await evidence(page, 'r1g-form', { fullPage: true });
  } finally {
    await setSetting(page, 'protocolValidate', previousValidate);
  }
});
