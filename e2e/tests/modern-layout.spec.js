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
  for (const item of ['Νέο', 'Λίστα', 'Εισερχόμενα']) {
    await expect(bar.locator('.nav-link', { hasText: item }).first()).toBeVisible();
  }
  // Bootstrap 5 CSS from Vite: the top bar is a flex container
  expect(await bar.evaluate(el => getComputedStyle(el).display)).toBe('flex');
  expect(failed, 'Vite assets load').toEqual([]);

  // Bootstrap's JavaScript: dropdowns open; R3g: Διαχείριση is in the user menu
  await bar.locator('#nav-user').click();
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
  // R3k (owner, 2026-11-26): no «Νέο» for readers, who cannot create; Λίστα is there
  await expect(bar.locator('.nav-link', { hasText: 'Νέο' })).toHaveCount(0);
  await expect(bar.locator('#nav-1-link')).toContainText('Λίστα');
  await evidence(page, 'r3k-reader-bar', { clip: { x: 0, y: 0, width: 1280, height: 70 } });
  await page.goto('/find');
  await expect(page.locator('.search-page').getByRole('link', { name: 'Νέο', exact: true })).toHaveCount(0);
  await page.goto('/about');
  // R3d/R3g: Διαχείριση (user menu) holds Εκτύπωση/Διατήρηση for everybody; the administrators' items only for them
  await bar.locator('#nav-user').click();
  await expect(bar.getByRole('link', { name: 'Φάκελοι και διατήρηση' })).toBeVisible();
  await expect(bar.getByRole('link', { name: 'Ρυθμίσεις' })).toHaveCount(0);

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
  await bar.locator('#nav-user').click();
  await expect(bar.locator('.user-menu')).toBeVisible();
  await evidence(page, 'top-bar-manage-menu', { clip: { x: 0, y: 0, width: 1440, height: 520 } });
});

test('narrow page: − / + / ↺ set its width, saved for the user (R3j)', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 1000 });
  await login(page, 'writer');
  await page.goto('/home/list');
  const bar = page.locator('nav.app-topbar');
  const steps = bar.locator('[data-width-steps]');
  const mainWidth = async () => (await page.locator('main').boundingBox()).width;
  const save = () => page.waitForResponse((r) => r.url().includes('/preferences') && r.request().postData().includes('narrowWidth'));

  await bar.locator('#nav-user').click();
  await expect(steps).toBeHidden();   // only for the narrow page
  await bar.locator('[data-preference="width"][data-value="narrow"]').click();
  await expect(page.locator('body')).toHaveClass(/layout-narrow/);
  if (!(await steps.isVisible())) await bar.locator('#nav-user').click();
  await expect(steps).toBeVisible();
  const start = await mainWidth();   // the default: half of 1920
  expect(Math.abs(start - 960)).toBeLessThan(5);
  await expect(steps.locator('.width-steps-value')).toHaveText('50%');
  await expect(steps.locator('[data-width-step="0"]')).toBeDisabled();   // already the default

  // + twice: 10rem wider at once, the menu stays open; one save, of the last width
  let saved = save();
  await steps.locator('[data-width-step="1"]').click();
  await steps.locator('[data-width-step="1"]').click();
  await expect(steps).toBeVisible();
  expect((await saved).request().postDataJSON()).toEqual({ key: 'narrowWidth', value: 70 });
  expect(Math.abs((await mainWidth()) - 1120)).toBeLessThan(5);
  await expect(steps.locator('.width-steps-value')).toHaveText('58%');
  await evidence(page, 'r3j-wider', { clip: { x: 0, y: 0, width: 1920, height: 560 } });

  // −: narrower than the default
  saved = save();
  for (let i = 0; i < 4; i++) await steps.locator('[data-width-step="-1"]').click();
  expect((await saved).request().postDataJSON().value).toBe(50);
  expect(Math.abs((await mainWidth()) - 800)).toBeLessThan(5);
  const box = await page.locator('main').boundingBox();
  expect(Math.abs(box.x - (1920 - box.x - box.width))).toBeLessThan(30);   // still centred
  await evidence(page, 'r3j-narrower', { clip: { x: 0, y: 0, width: 1920, height: 560 } });

  await page.reload();   // kept with the account
  expect(Math.abs((await mainWidth()) - 800)).toBeLessThan(5);

  // ↺: back to the default, also after a reload
  await bar.locator('#nav-user').click();
  saved = save();
  await steps.locator('[data-width-step="0"]').click();
  expect((await saved).request().postDataJSON().value).toBeNull();
  expect(Math.abs((await mainWidth()) - 960)).toBeLessThan(5);
  await page.reload();
  expect(Math.abs((await mainWidth()) - 960)).toBeLessThan(5);

  await bar.locator('#nav-user').click();
  await bar.locator('[data-preference="width"][data-value="full"]').click();   // as the other tests expect
  await expect(page.locator('body')).not.toHaveClass(/layout-narrow/);
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

test('each user chooses their home page from the user menu; the brand and Λίστα lead there at once and after login (R3e)', async ({ page }) => {
  await login(page, 'reader');
  await page.goto('/about');
  const bar = page.locator('nav.app-topbar');
  const choose = async (name) => {
    await bar.locator('#nav-user').click();
    const item = bar.locator('[data-preference=home]', { hasText: name });
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/preferences')), item.click()]);
    await expect(item).toHaveAttribute('aria-pressed', 'true');
    await expect(bar.locator('[data-preference=home][aria-pressed=true]')).toHaveCount(1);
  };
  try {
    await choose('προς Ενημέρωση');
    await evidence(page, 'r3e-home-menu', { clip: { x: 900, y: 0, width: 540, height: 520 } });
    await page.keyboard.press('Escape');
    // at once, without reloading
    await expect(bar.locator('.app-brand')).toHaveAttribute('href', /\/home\/list\/e$/);
    await Promise.all([page.waitForURL(/\/home\/list\/e(\?filter=e)?$/), bar.getByRole('link', { name: 'Λίστα', exact: true }).click()]);
    // after logging in again
    await bar.locator('#nav-user').click();
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/home')), bar.getByRole('button', { name: 'Έξοδος' }).click()]);
    await login(page, 'reader');
    await expect(page).toHaveURL(/\/home\/list\/e(\?filter=e)?$/);
  } finally {
    await page.goto('/about');
    await choose('προς Διεκπεραίωση');   // the readers' default in the dev set-up
  }
});

test('top bar: the menu stays open on medium screens, nothing wider than the screen (R3e)', async ({ page }) => {
  await login(page, 'admin');
  for (const width of [768, 950, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto('/home/list');
    const bar = page.locator('nav.app-topbar');
    await expect(bar.getByRole('button', { name: 'Μενού' })).toBeHidden();
    for (const item of ['Νέο', 'Λίστα', 'Εισερχόμενα']) {
      await expect(bar.locator('.nav-link', { hasText: item }).first(), `${item} at ${width} px`).toBeVisible();
    }
    await expect(page.locator('#app-search-q')).toBeVisible();
    await expect(bar.locator('#nav-user')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth), `no sideways scroll at ${width} px`).toBeLessThanOrEqual(width);
    const height = (await bar.boundingBox()).height;
    expect(height, `at most two rows at ${width} px`).toBeLessThan(120);
    if (width >= 950) expect(height, `one row at ${width} px`).toBeLessThan(70);
    await evidence(page, `r3e-top-bar-${width}`, { clip: { x: 0, y: 0, width, height: 160 } });
  }
});

test('Διαχείριση is in the user menu, in groups, beside the user\'s own choices (R3g)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/home/list');
  const bar = page.locator('nav.app-topbar');
  await expect(bar.getByRole('button', { name: 'Διαχείριση' })).toHaveCount(0);
  await bar.locator('#nav-user').click();
  const menu = bar.locator('.user-menu');
  const manage = menu.locator('.user-menu-manage');
  const own = menu.locator('.user-menu-own');
  for (const text of ['Εκτύπωση πρωτοκόλλου', 'Φάκελοι και διατήρηση', 'Χρήστες', 'Ρυθμίσεις', 'Backup', 'Εκκαθάριση', 'Κάδος']) {
    await expect(manage.getByRole('link', { name: text })).toBeVisible();
  }
  await expect(own).toContainText('Πλάτος σελίδας');
  await expect(own).toContainText('Αρχική σελίδα');
  await expect(manage.locator('.dropdown-header', { hasText: 'Διαχειριστής' })).toHaveCount(0);
  // two columns side by side on a wide screen, inside the window
  const [m, o] = [await manage.boundingBox(), await own.boundingBox()];
  expect(o.x).toBeGreaterThan(m.x + m.width - 2);
  expect((await menu.boundingBox()).x).toBeGreaterThanOrEqual(0);
  await evidence(page, 'r3g-user-menu', { clip: { x: 640, y: 0, width: 800, height: 560 } });
  await Promise.all([page.waitForURL(/\/settings$/), manage.getByRole('link', { name: 'Ρυθμίσεις' }).click()]);

  // a phone: one column, in the folded menu
  await page.setViewportSize({ width: 390, height: 800 });
  await bar.getByRole('button', { name: 'Μενού' }).click();
  await bar.locator('#nav-user').click();
  const [m2, o2] = [await manage.boundingBox(), await own.boundingBox()];
  expect(o2.y).toBeGreaterThan(m2.y + m2.height - 2);
  // Προτιμήσεις is its own section: a line above it (owner, 2026-11-20)
  expect(await own.evaluate((el) => parseFloat(getComputedStyle(el).borderTopWidth))).toBeGreaterThan(0);
  await expect(manage.locator('.dropdown-header', { hasText: 'Διαχείριση' })).toHaveCount(1);
  await own.scrollIntoViewIfNeeded();
  await evidence(page, 'r3g-user-menu-phone');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
