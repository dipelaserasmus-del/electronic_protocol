const path = require('path');
const fs = require('fs');

/** Log in as one of the DevSeeder users: admin / anathetis / writer / reader (password: password). */
async function login(page, username = 'admin') {
  await page.goto('/login');
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', 'password');
  await Promise.all([page.waitForURL(/\/home/), page.click('button[type=submit]')]);
}

/** Saves a screenshot for an evidence report when EVIDENCE=<TASK-ID> is set; no-op otherwise. */
async function evidence(page, name, options = {}) {
  const task = process.env.EVIDENCE;
  if (!task) return;
  const dir = path.join(__dirname, '..', '..', 'docs', 'reports', 'img', task);
  fs.mkdirSync(dir, { recursive: true });
  // the development debug toolbar is not part of the app: keep it out of the evidence
  await page.addStyleTag({ content: '.phpdebugbar { display: none !important; }' });
  await page.screenshot({ path: path.join(dir, `${name}.png`), ...options });
}

/**
 * Change one setting through Ρυθμίσεις (logged in as admin) and return its previous value, so the test can put it
 * back. R6c: the settings page (Livewire) saves every setting, as a user would; switches take 0/1.
 */
async function setSetting(page, name, value) {
  await page.goto(`/settings?setting=${name}`);   // R6f: the section of that setting
  const field = page.locator(`#s-${name}`);
  let previous;
  if ((await field.getAttribute('type')) === 'checkbox') {
    previous = (await field.isChecked()) ? '1' : '0';
    await field.setChecked(String(value) === '1');
  } else if ((await field.evaluate((el) => el.tagName)) === 'SELECT') {
    previous = await field.inputValue();
    await field.selectOption(String(value));
  } else {
    previous = await field.inputValue();
    await field.fill(String(value));
  }
  const saved = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
  await page.locator('#settings-save').click();
  await saved;
  await page.locator('#app-toasts .toast', { hasText: /Αποθηκεύτηκαν|Καμία αλλαγή/ }).first().waitFor();
  return previous;
}

/**
 * R1b protocol form (Livewire): fill fields by name (form.<name> → #f-<name>), e.g.
 * fillProtocol(page, { thema: 'x', fakelos: 'first', in_date: '03/01/2025' }). fakelos: 'first' picks the first
 * folder (not "— Χωρίς φάκελο"), other text is typed into the folder picker (R1f) and its best match chosen. Each field is left with Tab,
 * which sends it to the server (wire:model.blur).
 */
async function fillProtocol(page, fields) {
  for (const [name, value] of Object.entries(fields)) {
    const field = page.locator(`#f-${name}`);
    if ((await field.getAttribute('data-combobox')) !== null) {
      await field.click();
      const chosen = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
      if (value === 'first') {
        await page.locator('#folder-list li[role=option]').nth(1).click();   // 0 is "— Χωρίς φάκελο"
      } else {
        await field.fill(value);
        await field.press('Enter');
      }
      await chosen;
    } else if ((await field.evaluate((el) => el.tagName)) === 'SELECT') {
      if (value === 'first') await field.selectOption({ index: 1 });
      else await field.selectOption(value);
    } else {
      await field.fill(value);
      await field.press('Tab');
    }
  }
}

/**
 * R3a search screen: open it, fill fields by id (s-<name>: thema, in_num, sxetiko, num, year, from, in-from …) and press
 * Enter in the last one. Fields under "Περισσότερα πεδία" open that section first. Returns the results area.
 */
async function search(page, fields, { open = true } = {}) {
  if (open) await page.goto('/find');
  for (const [name, value] of Object.entries(fields)) {
    const field = page.locator(`#s-${name}`);
    if (!(await field.isVisible()) && (await page.locator('#more-fields-body').isHidden())) await page.locator('#more-toggle').click();
    await field.fill(String(value));
  }
  const last = Object.keys(fields).at(-1);
  const done = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
  await page.locator(`#s-${last}`).press('Enter');
  await done;
  return page.locator('.search-page');
}

/** Save the protocol form and wait for the saved protocol's page (or for the form to show its messages). */
async function saveProtocol(page, { expectSaved = true } = {}) {
  await page.locator('#protocol-form button[type=submit]').first().click();
  if (expectSaved) await page.waitForURL(/\/home\/\d+$/);
  else await page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST');
}

/** The message shown at the top of the rebuilt pages after a save. */
async function notice(page) {
  return (await page.locator('main > .alert').allInnerTexts()).join(' ').replace(/\s+/g, ' ').trim();
}

/** R5m: the inbox page arrives at once and then waits for the mail server: wait until it has answered (or failed). */
async function inboxReady(page) {
  await page.locator('[data-inbox-state="ready"], [data-inbox-state="failed"]').first().waitFor({ timeout: 60000 });
}

/** R5m: open the inbox (or one of its pages) and wait for the mail server's answer. */
async function openInbox(page, url = '/inbox') {
  await page.goto(url);
  await inboxReady(page);
}

module.exports = {
  search, login, evidence, setSetting, fillProtocol, saveProtocol, notice, openInbox, inboxReady };
