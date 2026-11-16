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

/** Text of the toastr notifications currently shown. */
async function toasts(page) {
  return (await page.locator('.toast-message').allInnerTexts()).join(' ').replace(/\s+/g, ' ').trim();
}

/**
 * Change one setting through Ρυθμίσεις (logged in as admin) and return its previous value, so the test can put it
 * back. The settings form posts every setting, as a user would.
 */
async function setSetting(page, name, value) {
  await page.goto('/settings');
  const field = page.locator(`[name="${name}"]`);
  const previous = await field.inputValue();
  if ((await field.evaluate((el) => el.tagName)) === 'SELECT') await field.selectOption(String(value));
  else await field.fill(String(value));
  // the page stays at /settings: wait for the saved page itself, not for an address the browser is already on
  const saved = page.waitForEvent('framenavigated', (frame) => frame === page.mainFrame());
  await page.evaluate(() => document.forms['configform'].submit());
  await saved;
  await page.waitForLoadState('load');
  return previous;
}

/**
 * R1b protocol form (Livewire): fill fields by name (form.<name> → #f-<name>), e.g.
 * fillProtocol(page, { thema: 'x', fakelos: 'first', in_date: '03/01/2025' }). fakelos: 'first' picks the first
 * folder. Each field is left with Tab, which sends it to the server (wire:model.blur).
 */
async function fillProtocol(page, fields) {
  for (const [name, value] of Object.entries(fields)) {
    const field = page.locator(`#f-${name}`);
    if ((await field.evaluate((el) => el.tagName)) === 'SELECT') {
      if (value === 'first') await field.selectOption({ index: 1 });
      else await field.selectOption(value);
    } else {
      await field.fill(value);
      await field.press('Tab');
    }
  }
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

module.exports = { login, evidence, toasts, setSetting, fillProtocol, saveProtocol, notice };
