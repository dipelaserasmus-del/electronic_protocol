// N03: new layout (Bootstrap 5 via Vite, Livewire) on the rebuilt pages; first page: /about.
const { test, expect } = require('@playwright/test');
const { login, evidence } = require('./helpers');

test('new layout: top bar with the role\'s menu, styles and scripts loaded', async ({ page }) => {
  const failed = [];
  page.on('response', r => { if (r.url().includes('/build/') && r.status() >= 400) failed.push(r.url()); });
  await login(page, 'admin');
  await page.goto('/about');

  const bar = page.locator('nav.app-topbar');
  await expect(bar).toBeVisible();
  for (const item of ['Νέο', 'Λίστα', 'Αναζήτηση', 'Διατήρηση', 'Διαχείριση']) {
    await expect(bar.locator('.nav-link', { hasText: item }).first()).toBeVisible();
  }
  // Bootstrap 5 CSS from Vite: the top bar is a flex container
  expect(await bar.evaluate(el => getComputedStyle(el).display)).toBe('flex');
  expect(failed, 'Vite assets load').toEqual([]);

  // Bootstrap's JavaScript: dropdowns open
  await bar.getByRole('button', { name: 'Διαχείριση' }).click();
  await expect(bar.getByRole('link', { name: 'Ρυθμίσεις' })).toBeVisible();
  await evidence(page, 'n03-about-admin-menu', { clip: { x: 0, y: 0, width: 1440, height: 420 } });

  await bar.getByRole('button', { name: 'Λίστα' }).click();
  await expect(bar.getByRole('link', { name: 'Όλα τα πρωτόκολλα' })).toBeVisible();
  await evidence(page, 'n03-about-list-menu', { clip: { x: 0, y: 0, width: 1440, height: 720 } });
});

test('new layout: readers get a shorter menu, and logging out works from the user menu', async ({ page }) => {
  await login(page, 'reader');
  await page.goto('/about');
  const bar = page.locator('nav.app-topbar');
  await expect(bar.locator('.nav-link', { hasText: 'Διαχείριση' })).toHaveCount(0);
  await expect(bar.locator('.nav-link', { hasText: 'Εισερχόμενα' })).toHaveCount(0);

  await bar.locator('#nav-user').click();
  await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/about')), bar.getByRole('button', { name: 'Έξοδος' }).click()]);
  await page.goto('/about');
  await expect(page).toHaveURL(/\/login/);   // logged out
});

test('new layout: on a narrow screen the menu folds behind a button', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 800 });
  await login(page, 'writer');
  await page.goto('/about');
  const bar = page.locator('nav.app-topbar');
  await expect(bar.getByRole('link', { name: 'Αναζήτηση' })).toBeHidden();
  await bar.getByRole('button', { name: 'Μενού' }).click();
  await expect(bar.getByRole('link', { name: 'Αναζήτηση' })).toBeVisible();
  await expect(bar.locator('.collapsing')).toHaveCount(0);   // opening animation finished
  // nothing wider than the screen
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await evidence(page, 'n03-about-mobile-menu');
});
