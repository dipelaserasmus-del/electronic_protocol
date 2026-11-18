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
  for (const item of ['Νέο', 'Λίστα', 'Εισερχόμενα', 'Διαχείριση']) {
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
  await expect(bar.locator('.nav-link', { hasText: 'Εισερχόμενα' })).toHaveCount(0);
  // R3d: Διαχείριση holds Εκτύπωση/Διατήρηση for everybody; the administrators' items only for them
  await bar.getByRole('button', { name: 'Διαχείριση' }).click();
  await expect(bar.getByRole('link', { name: 'Διατήρηση' })).toBeVisible();
  await expect(bar.getByRole('link', { name: 'Ρυθμίσεις' })).toHaveCount(0);

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
  await expect(bar.getByRole('link', { name: 'Νέο' })).toBeHidden();
  await bar.getByRole('button', { name: 'Μενού' }).click();
  await expect(bar.getByRole('link', { name: 'Νέο' })).toBeVisible();
  await expect(bar.locator('.collapsing')).toHaveCount(0);   // opening animation finished
  // nothing wider than the screen
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await evidence(page, 'n03-about-mobile-menu');
});

test('top bar: one row on 1920 and 1440 screens, icons, the institution under the name, search buttons (R3d)', async ({ page }) => {
  await login(page, 'admin');
  for (const width of [1920, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/home/list');
    const bar = page.locator('nav.app-topbar');
    const height = (await bar.boundingBox()).height;
    expect(height, `one row at ${width} px`).toBeLessThan(70);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect(bar.locator('.nav-link .bi').first()).toBeVisible();
    // the icon font loaded: an icon has a width
    expect((await bar.locator('.nav-link .bi').first().boundingBox()).width).toBeGreaterThan(8);
    if (width === 1920) await evidence(page, 'top-bar-1920', { clip: { x: 0, y: 0, width: 1920, height: 300 } });
  }
  const bar = page.locator('nav.app-topbar');
  await expect(bar.locator('.brand-institution')).toBeVisible();
  // the advanced search is the box's second button
  await Promise.all([page.waitForURL(/\/find$/), page.locator('#app-search-advanced').click()]);
  await page.locator('#app-search-q').fill('αρ:1');
  await Promise.all([page.waitForURL(/\/search\?q=/), bar.getByRole('button', { name: 'Αναζήτηση', exact: true }).click()]);
  await bar.getByRole('button', { name: 'Διαχείριση' }).click();
  await evidence(page, 'top-bar-manage-menu', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});

test('page width: narrow and centred or the whole window, saved for the user (R3d)', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 1000 });
  await login(page, 'writer');
  await page.goto('/home/list');
  const bar = page.locator('nav.app-topbar');
  const mainWidth = async () => (await page.locator('main').boundingBox()).width;
  const full = await mainWidth();
  expect(full).toBeGreaterThan(1800);

  await bar.locator('#nav-user').click();
  const saved = page.waitForResponse((r) => r.url().includes('/preferences'));
  await bar.locator('[data-preference="width"][data-value="narrow"]').click();
  expect((await saved).status()).toBe(200);
  await expect(page.locator('body')).toHaveClass(/layout-narrow/);
  expect(await mainWidth()).toBeLessThan(1000);   // about half of the screen
  await evidence(page, 'width-narrow', { clip: { x: 0, y: 0, width: 1920, height: 700 } });

  await page.reload();   // kept with the account
  await expect(page.locator('body')).toHaveClass(/layout-narrow/);
  const box = await page.locator('main').boundingBox();
  expect(Math.abs(box.x - (1920 - box.x - box.width))).toBeLessThan(30);   // centred

  await bar.locator('#nav-user').click();
  await expect(bar.locator('[data-preference="width"][data-value="narrow"]')).toHaveAttribute('aria-pressed', 'true');
  await bar.locator('[data-preference="width"][data-value="full"]').click();
  await expect(page.locator('body')).not.toHaveClass(/layout-narrow/);
  await page.reload();
  expect(await mainWidth()).toBeGreaterThan(1800);
});
