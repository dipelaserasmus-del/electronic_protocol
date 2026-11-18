// R6a: the users page (administrators): add a user, the role's description, deactivate (the user can no longer log
// in), and the rules that keep at least one active administrator.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('add, deactivate and refused delete on the users page (R6a)', async ({ page, browser }) => {
  const username = `r6a${Date.now()}`;
  await login(page, 'admin');
  await page.goto('/users');
  await expect(page.locator('#roles .card')).toHaveCount(4);

  await page.locator('#user-new').click();
  await page.locator('#f-name').fill('Δοκιμαστική Χρήστρια R6a');
  await page.locator('#f-username').fill(username);
  await page.locator('#f-email').fill(`${username}@example.org`);
  await page.locator('#f-role_id').selectOption({ label: 'Συγγραφέας' });
  await expect(page.locator('#role-help')).toContainText('Καταχωρίζει πρωτόκολλα');
  await page.locator('#f-password').fill('r6a-secret');
  await page.locator('#f-password_confirmation').fill('r6a-secret');
  await evidence(page, 'r6a-new-user');
  await page.locator('#user-save').click();
  await expect(page.locator('#app-toasts .toast')).toContainText('προστέθηκε');
  await expect(page.locator('#user-form')).toHaveCount(0);

  // the new user logs in
  const other = await browser.newContext();
  const theirs = await other.newPage();
  await theirs.goto('/login');
  await theirs.fill('input[name=username]', username);
  await theirs.fill('input[name=password]', 'r6a-secret');
  await theirs.click('button[type=submit]');
  await expect(theirs).not.toHaveURL(/\/login/);

  // deactivate: they are logged out at their next click, and cannot log in again
  await page.locator('#user-search').fill(username);
  const row = page.locator(`.user-row[data-username="${username}"]`);
  await expect(page.locator('.user-row')).toHaveCount(1);
  await row.getByRole('button', { name: 'Επεξεργασία' }).click();
  await page.locator('#f-active').uncheck();
  await expect(page.locator('#user-form')).toContainText('αποσυνδέεται');
  await page.locator('#user-save').click();
  await expect(row).toContainText('ανενεργός');
  await evidence(page, 'r6a-users-list');

  await theirs.goto('/home/list');
  await expect(theirs).toHaveURL(/\/login/);
  await theirs.fill('input[name=username]', username);
  await theirs.fill('input[name=password]', 'r6a-secret');
  await theirs.click('button[type=submit]');
  await expect(theirs).toHaveURL(/\/login/);
  await other.close();
});

test('an administrator cannot deactivate or delete their own account (R6a)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/users');
  await page.locator('#user-search').fill('admin');
  const me = page.locator('.user-row[data-username="admin"]');
  await expect(me).toContainText('εσείς');
  await expect(me.getByRole('button', { name: 'Διαγραφή' })).toBeDisabled();
  await me.getByRole('button', { name: 'Επεξεργασία' }).click();
  await page.locator('#f-active').uncheck();
  await page.locator('#user-save').click();
  await expect(page.locator('#user-form .invalid-feedback')).toContainText('δικό σας λογαριασμό');
  await evidence(page, 'r6a-own-account');
});

test('the users page is for administrators only (R6a)', async ({ page }) => {
  await login(page, 'writer');
  await page.goto('/users');
  await expect(page).not.toHaveURL(/\/users/);
});
