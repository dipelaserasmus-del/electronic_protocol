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

module.exports = { login, evidence, toasts, setSetting };
