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

test('folder codes in the browser follow the same rules as the server (R1h)', async ({ page }) => {
  const fs = require('fs');
  const path = require('path');
  const cases = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'tests', 'fixtures', 'folder-code-cases.json'), 'utf8'));
  await login(page, 'writer');
  await page.goto('/home');
  const got = await page.evaluate((c) => ({
    keys: c.keys.map(([code]) => window.appFolderCode.key(code)),
    same: c.same.map(([typed, code]) => window.appFolderCode.same(typed, code)),
    startsWith: c.startsWith.map(([typed, code]) => window.appFolderCode.startsWith(typed, code)),
  }), cases);
  expect(got.keys).toEqual(cases.keys.map(([, key]) => key));
  expect(got.same).toEqual(cases.same.map((c) => c[2]));
  expect(got.startsWith).toEqual(cases.startsWith.map((c) => c[2]));
});

test('folder picker: "12" puts Φ.12 first and shows the list from the top; "φ11" never offers Φ.1.1 (R1h)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/home');
  const folder = page.locator('#f-fakelos');
  const options = page.locator('#folder-list li[role=option]');
  await folder.click();
  await page.locator('#folder-list').evaluate((el) => el.scrollTo(0, 400));   // scrolled down, as after browsing the list
  await folder.pressSequentially('12');
  await expect(options.first()).toContainText('Φ.12');
  await expect(options.first()).toHaveAttribute('aria-selected', 'true');
  expect(await page.locator('#folder-list').evaluate((el) => el.scrollTop)).toBe(0);   // the best match is in view
  await expect(options.nth(1)).toContainText('Φ.12.1');                               // then codes starting with 12
  await evidence(page, 'folder-12', { clip: { x: 0, y: 0, width: 1440, height: 800 } });

  await folder.fill('');
  await folder.pressSequentially('φ11');
  await expect(options.first()).toContainText('Φ.11');
  const codes = await options.locator('.fw-semibold').allInnerTexts();
  expect(codes).not.toContain('Φ.1.1');
  await folder.press('Enter');
  await expect(folder).toHaveValue(/^Φ\.11( ·|$)/);
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

/** Drags like a person: press on the handle, move in small steps, release over the upper part of the target. */
async function drag(page, handle, target) {
  const from = await handle.boundingBox();
  const to = await target.boundingBox();
  await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
  await page.mouse.down();
  for (let i = 1; i <= 20; i++) {
    await page.mouse.move(from.x + (to.x + 40 - from.x) * i / 20, from.y + (to.y + 4 - from.y) * i / 20);
    await page.waitForTimeout(15);
  }
  await page.mouse.up();
}

test('sections: drag to the other column, collapse, one column, kept for the user, reset (R1i)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page, 'anathetis');
  await page.goto('/home');
  const zone = (z) => page.locator(`.form-zone[data-zone="${z}"] > .form-section`);
  const ids = async (z) => zone(z).evaluateAll((els) => els.map((e) => e.dataset.section));
  await page.locator('#layout-reset').click();   // start from the default (earlier runs)
  await expect.poll(() => ids('left')).toEqual(['general', 'incoming']);

  // drag "Συνημμένα" by its handle onto the left column, above "Γενικά"
  const saved = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().postData()?.includes('moveSection'));
  await drag(page, page.locator('[data-section="attachments"] .section-handle'), page.locator('[data-section="general"] .section-head'));
  await saved;
  await expect.poll(() => ids('left')).toEqual(['attachments', 'general', 'incoming']);
  await expect.poll(() => ids('right')).toEqual(['done', 'outgoing']);
  const left = await page.locator('[data-section="attachments"]').boundingBox();
  const right = await page.locator('[data-section="done"]').boundingBox();
  expect(left.x).toBeLessThan(right.x);   // really in the left column

  // collapse "Εισερχόμενο": its fields hide, a click opens it again later
  await page.locator('[data-section-toggle="incoming"]').click();
  await expect(page.locator('#f-in_num')).toBeHidden();
  await expect(page.locator('[data-section-toggle="incoming"]')).toHaveAttribute('aria-expanded', 'false');
  await evidence(page, 'sections-moved-collapsed', { fullPage: true });

  // one column: every section is as wide as the page
  await page.locator('#layout-one').click();
  await expect(page.locator('#form-sections')).toHaveClass(/one-column/);
  const a = await page.locator('[data-section="attachments"]').boundingBox();
  const d = await page.locator('[data-section="done"]').boundingBox();
  expect(Math.abs(a.x - d.x)).toBeLessThan(2);
  expect(Math.abs(a.width - d.width)).toBeLessThan(2);
  await evidence(page, 'sections-one-column', { clip: { x: 0, y: 0, width: 1440, height: 1000 } });

  // kept after a reload, for this user only
  await page.reload();
  await expect(page.locator('#form-sections')).toHaveClass(/one-column/);
  await expect(page.locator('#f-in_num')).toBeHidden();
  await expect.poll(() => ids('left')).toEqual(['attachments', 'general', 'incoming']);
  await page.context().clearCookies();
  await login(page, 'writer');
  await page.goto('/home');
  await expect(page.locator('#form-sections')).toHaveClass(/two-columns/);
  await expect(page.locator('#f-in_num')).toBeVisible();

  // back to the default
  await page.context().clearCookies();
  await login(page, 'anathetis');
  await page.goto('/home');
  await page.locator('#layout-reset').click();
  await expect(page.locator('#form-sections')).toHaveClass(/two-columns/);
  await expect.poll(() => ids('left')).toEqual(['general', 'incoming']);
  await expect(page.locator('#f-in_num')).toBeVisible();
});

test('one Αποθήκευση always at the bottom right; copy and receipt at the far right; Λίστα of the top bar is a link (R1j)', async ({ page }) => {
  const subject = `R1j ενέργειες ${Date.now()}`;
  await login(page, 'writer');
  await page.goto('/home');
  const save = page.locator('#form-save');
  await expect(page.locator('#protocol-form button[type=submit]')).toHaveCount(1);
  await fillProtocol(page, { fakelos: 'first', thema: subject });
  await saveProtocol(page);

  // on the screen at the bottom right, wherever the page is scrolled
  const view = page.viewportSize();
  for (const y of [0, 100000]) {
    await page.evaluate((top) => window.scrollTo(0, top), y);
    await expect(save).toBeInViewport();
    const box = await save.boundingBox();
    expect(view.width - (box.x + box.width)).toBeLessThan(40);
    expect(view.height - (box.y + box.height)).toBeLessThan(40);
  }
  await page.evaluate(() => window.scrollTo(0, 0));

  // no Νέο / Λίστα in the form; copy and receipt last, at the right end of the row, with icons
  const row = page.locator('#protocol-form [data-pill-actions]').first();
  await expect(row.getByRole('link', { name: /^(Νέο|Λίστα)$/ })).toHaveCount(0);
  const copy = row.locator('[data-action=copy]');
  const receipt = row.locator('[data-action=receipt]');
  await expect(copy.locator('i.bi-copy')).toBeVisible();
  await expect(receipt.locator('i.bi-receipt')).toBeVisible();
  const next = await row.getByRole('link', { name: 'Επόμενο ▶' }).boundingBox();
  const c = await copy.boundingBox();
  const r = await receipt.boundingBox();
  const rowBox = await row.boundingBox();
  expect(c.x).toBeGreaterThan(next.x + next.width + 40);      // set apart from the others
  expect(r.x).toBeGreaterThan(c.x);
  expect(rowBox.x + rowBox.width - (r.x + r.width)).toBeLessThan(20);   // at the far right
  await evidence(page, 'r1j-form-actions', { clip: { x: 0, y: 0, width: view.width, height: 420 } });

  // saving by the floating button stays on the protocol
  await page.locator('#f-thema').fill(`${subject} αλλαγή`);
  await save.click();
  await expect(page.locator('main > .alert', { hasText: 'Οι αλλαγές αποθηκεύτηκαν' })).toBeVisible();
  await expect(page.locator('#f-thema')).toHaveValue(`${subject} αλλαγή`);
  await page.evaluate(() => window.scrollTo(0, 100000));
  await evidence(page, 'r1j-floating-save');

  // the top bar's Λίστα goes to the list; its ▾ opens the other lists
  const bar = page.locator('nav.app-topbar');
  await bar.getByRole('button', { name: 'Περισσότερες: Λίστα' }).click();
  await expect(bar.getByRole('link', { name: 'Όλα τα πρωτόκολλα' })).toBeVisible();
  await evidence(page, 'r1j-list-menu', { clip: { x: 0, y: 0, width: view.width, height: 500 } });
  const all = new URL(await bar.locator('.app-brand').getAttribute('href')).pathname;   // the user's home page (R3e)
  await page.keyboard.press('Escape');
  await Promise.all([page.waitForURL((u) => u.pathname === all), bar.getByRole('link', { name: 'Λίστα', exact: true }).click()]);
  await expect(page.locator('.protocol-row').first()).toBeVisible();
});

test('changes not saved: a red dot on Αποθήκευση; leaving asks, lists them, saves or drops them (R1k)', async ({ page }) => {
  const subject = `R1k αλλαγές ${Date.now()}`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { fakelos: 'first', thema: subject });
  await saveProtocol(page);
  const url = page.url();
  const dot = page.locator('#form-save .unsaved-dot');
  const dialog = page.locator('#unsaved-dialog');
  const bar = page.locator('nav.app-topbar');
  await expect(dot).toBeHidden();

  // no changes: links leave without a question
  await Promise.all([page.waitForURL(/\/home$/), bar.getByRole('link', { name: 'Νέο', exact: true }).click()]);
  await page.goto(url);

  // a change: the dot; undoing it: no dot
  await page.locator('#f-thema').fill(`${subject} διορθωμένο`);
  await expect(dot).toBeVisible();
  await page.locator('#f-thema').fill(subject);
  await expect(dot).toBeHidden();
  await page.locator('#f-thema').fill(`${subject} διορθωμένο`);
  await page.locator('#f-paratiriseis').fill('Σημείωση R1k');
  await expect(dot).toBeVisible();
  await page.evaluate(() => window.scrollTo(0, 100000));
  await evidence(page, 'r1k-dot', { clip: { x: 1100, y: 740, width: 340, height: 160 } });

  // leaving: the question with what changed; Ακύρωση stays
  await bar.getByRole('link', { name: 'Νέο', exact: true }).click();
  await expect(dialog).toBeVisible();
  await expect(dialog.locator('tbody tr')).toHaveCount(2);
  await expect(dialog.locator('tbody tr', { hasText: 'Θέμα' })).toContainText(`${subject} διορθωμένο`);
  await expect(dialog.locator('tbody tr', { hasText: 'Παρατηρήσεις' })).toContainText('Σημείωση R1k');
  await evidence(page, 'r1k-dialog');
  await dialog.locator('#unsaved-stay').click();
  await expect(dialog).toBeHidden();
  await expect(page).toHaveURL(url);

  // Χωρίς αποθήκευση: goes, nothing saved
  await bar.getByRole('link', { name: 'Νέο', exact: true }).click();
  await Promise.all([page.waitForURL(/\/home$/), dialog.locator('#unsaved-discard').click()]);
  await page.goto(url);
  await expect(page.locator('#f-thema')).toHaveValue(subject);

  // Αποθήκευση: saved, then on to where the user was going
  await page.locator('#f-thema').fill(`${subject} τελικό`);
  await bar.getByRole('link', { name: 'Λίστα', exact: true }).click();
  const home = new URL(await bar.locator('.app-brand').getAttribute('href')).pathname;
  await Promise.all([page.waitForURL((u) => u.pathname === home), dialog.locator('#unsaved-save').click()]);
  await expect(page.locator('main > .alert')).toContainText('Οι αλλαγές αποθηκεύτηκαν');
  await page.goto(url);
  await expect(page.locator('#f-thema')).toHaveValue(`${subject} τελικό`);
  await expect(dot).toBeHidden();

  // a save with the button: no question afterwards
  await page.locator('#f-thema').fill(subject);
  await page.locator('#form-save').click();
  await expect(page.locator('main > .alert', { hasText: 'Οι αλλαγές αποθηκεύτηκαν' })).toBeVisible();
  await expect(dot).toBeHidden();
  await Promise.all([page.waitForURL(/\/home$/), bar.getByRole('link', { name: 'Νέο', exact: true }).click()]);

  // review of R1k: a keyword typed but not added yet is a change too
  await page.goto(url);
  await page.locator('#f-keywords').fill('εκκρεμής λέξη');
  await expect(dot).toBeVisible();
  await bar.getByRole('link', { name: 'Νέο', exact: true }).click();
  await expect(dialog.locator('tbody tr', { hasText: 'Λέξεις κλειδιά' })).toContainText('εκκρεμής λέξη');
  await dialog.locator('#unsaved-stay').click();
  // leaving the box added it as a tag (as always): still a change, still the dot
  await expect(page.locator('[data-tag="εκκρεμής λέξη"]')).toHaveCount(1);
  await expect(dot).toBeVisible();

  // review of R1k: a tag changed in place, its editor still open
  await page.goto(url);
  await page.locator('#f-keywords').fill('παλιά λέξη');
  await page.locator('#f-keywords').press('Enter');
  await expect(page.locator('[data-tag="παλιά λέξη"]')).toHaveCount(1);
  await page.locator('#form-save').click();
  await expect(page.locator('main > .alert', { hasText: 'Οι αλλαγές αποθηκεύτηκαν' })).toBeVisible();
  await expect(dot).toBeHidden();
  await page.locator('[data-tag="παλιά λέξη"]').first().click();
  const editor = page.locator('input.tag-edit:visible');
  await editor.fill('αλλαγμένη λέξη');
  await expect(dot).toBeVisible();
  await page.evaluate(() => document.querySelector('a.app-brand').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 })));
  await expect(dialog.locator('tbody tr', { hasText: 'Λέξεις κλειδιά' })).toContainText('αλλαγμένη λέξη');
  await dialog.locator('#unsaved-stay').click();

  // review of R1k: a keyword added and another one typed: one row for the field, both shown
  await page.goto(url);
  await page.locator('#f-keywords').fill('πρώτη');
  await page.locator('#f-keywords').press('Enter');
  await expect(page.locator('[data-tag="πρώτη"]')).toHaveCount(1);
  await page.locator('#f-keywords').fill('δεύτερη');
  await page.evaluate(() => document.querySelector('a.app-brand').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 })));
  const keywordsRow = dialog.locator('tbody tr', { hasText: 'Λέξεις κλειδιά' });
  await expect(keywordsRow).toHaveCount(1);
  await expect(keywordsRow).toContainText('πρώτη');
  await expect(keywordsRow).toContainText('δεύτερη (νέο)');
  await dialog.locator('#unsaved-stay').click();

  // review of R1k: a file still uploading is a change
  await page.goto(url);
  await page.route('**/upload-file**', () => new Promise(() => {}));   // the upload never finishes here
  await page.setInputFiles('#f-uploads', { name: 'R1k-ανεβαίνει.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n') });
  await expect(dot).toBeVisible();
  await page.evaluate(() => document.querySelector('a.app-brand').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 })));
  await expect(dialog.locator('tbody tr', { hasText: 'Νέα συνημμένα' })).toContainText('ανεβαίνει');
  await dialog.locator('#unsaved-stay').click();
  await page.unroute('**/upload-file**');

  // review of R1k: a tag cleared in its editor, while the deletion is on its way, is a change too
  await page.goto(url);
  await page.route('**/update', async (route) => {
    if ((route.request().postData() ?? '').includes('"method":"editTag"')) return;   // never answered here
    await route.continue();
  });
  await page.locator('[data-tag="παλιά λέξη"]').first().click();
  await page.locator('input.tag-edit:visible').fill('');
  await page.locator('input.tag-edit:visible').press('Enter');
  await expect(dot).toBeVisible();
  await page.evaluate(() => document.querySelector('a.app-brand').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 })));
  await expect(dialog.locator('tbody tr', { hasText: 'Λέξεις κλειδιά' })).toContainText('(διαγραφή)');
  await dialog.locator('#unsaved-stay').click();
  await page.unroute('**/update');

  // review of R1k: the ✕ of a tag, while its removal is on its way
  await page.goto(url);
  await page.route('**/update', async (route) => {
    if ((route.request().postData() ?? '').includes('"method":"removeTag"')) return;   // never answered here
    await route.continue();
  });
  await page.locator('[data-tag="παλιά λέξη"]').first().locator('.btn-close').click();
  await expect(dot).toBeVisible();
  await page.unroute('**/update');

  // closing or reloading the tab with changes: the browser's own question
  await page.goto(url);
  await page.locator('#f-thema').fill(`${subject} ξανά`);
  let asked = false;
  page.once('dialog', async (d) => { asked = d.type() === 'beforeunload'; await d.dismiss(); });
  await page.reload({ timeout: 3000 }).catch(() => {});   // the question cancels the reload
  await expect.poll(() => asked).toBe(true);
});

test('review of R1k: who may only assign (no Αποθήκευση) leaves with links as before, never stuck', async ({ page }) => {
  const subject = `R1k μόνο ανάθεση ${Date.now()}`;
  await login(page, 'admin');
  await page.goto('/home');
  await fillProtocol(page, { fakelos: 'first', thema: subject });
  await saveProtocol(page);
  const url = page.url();
  const previous = await setSetting(page, 'allowWriterUpdateProtocol', 0);
  try {
    await page.context().clearCookies();   // log out
    await login(page, 'anathetis');
    await page.goto(url);
    await expect(page.locator('#form-save')).toHaveCount(0);   // may assign (Ανάθεση), not save the protocol
    await page.locator('#f-handle').selectOption({ label: 'Συγγραφέας Δοκιμών' });
    await Promise.all([page.waitForURL(/\/home$/), page.locator('nav.app-topbar').getByRole('link', { name: 'Νέο', exact: true }).click()]);
  } finally {
    await page.context().clearCookies();
    await login(page, 'admin');
    await setSetting(page, 'allowWriterUpdateProtocol', previous);
  }
});
