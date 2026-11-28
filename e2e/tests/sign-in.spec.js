// Z01b: the sign-in pages on the new look (Bootstrap 5, the top bar with only the name); the old layout is gone.
const { test, expect } = require('@playwright/test');
const { evidence } = require('./helpers');

test('sign in: a wrong password is shown under the field, then the right one opens the app (Z01b)', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('#login-card h1')).toHaveText('Είσοδος');
  await expect(page.locator('nav.app-topbar')).toBeVisible();
  await expect(page.locator('#nav-user')).toHaveCount(0);
  await evidence(page, 'z01b-login', { clip: { x: 0, y: 0, width: 1440, height: 640 } });

  await page.fill('#username', 'writer');
  await page.fill('#password', 'λάθος');
  await page.click('#login-form button[type=submit]');
  await expect(page.locator('#username')).toHaveClass(/is-invalid/);
  await expect(page.locator('#login-form .invalid-feedback').first()).toBeVisible();
  await expect(page.locator('#username')).toHaveValue('writer');
  await evidence(page, 'z01b-login-error', { clip: { x: 0, y: 0, width: 1440, height: 640 } });

  await page.fill('#password', 'password');
  await Promise.all([page.waitForURL(/\/home/), page.click('#login-form button[type=submit]')]);
  await expect(page.locator('#nav-user')).toBeVisible();
});

test('the forgotten-password page (Z01b)', async ({ page }) => {
  await page.goto('/login');
  await page.click('#login-forgot');
  await expect(page).toHaveURL(/\/password\/resetForm/);
  await expect(page.locator('#forgot-card h1')).toHaveText('Νέος κωδικός');
  await expect(page.locator('#email')).toBeVisible();
  await evidence(page, 'z01b-forgot', { clip: { x: 0, y: 0, width: 1440, height: 640 } });
});
