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
  await page.screenshot({ path: path.join(dir, `${name}.png`), ...options });
}

/** Text of the toastr notifications currently shown. */
async function toasts(page) {
  return (await page.locator('.toast-message').allInnerTexts()).join(' ').replace(/\s+/g, ' ').trim();
}

module.exports = { login, evidence, toasts };
