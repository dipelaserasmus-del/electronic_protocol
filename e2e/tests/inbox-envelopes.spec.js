// R5p: each e-mail of the inbox in an envelope (sender and date on the address strip, «N από M», a gap between them);
// three looks (setting inboxEnvelope) photographed for the owner to choose. Only invented e-mails (--no-private).
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, openInbox } = require('./helpers');

test('the three envelopes (R5p)', async ({ page }) => {
  await login(page, 'admin');
  const previousLook = await setSetting(page, 'inboxEnvelope', 'airmail');
  const previousCount = await setSetting(page, 'emailNumFetch', 3);
  try {
    for (const [look, label] of [['airmail', 'a-airmail'], ['paper', 'b-paper'], ['plain', 'c-plain']]) {
      await setSetting(page, 'inboxEnvelope', look);
      await openInbox(page);
      const envelopes = page.locator(`.inbox-envelope.inbox-envelope-${look}`);
      await expect(envelopes.first()).toBeVisible();
      await expect(envelopes.nth(1).locator('.inbox-envelope-n')).toHaveText(/^2 από \d+$/);
      await expect(envelopes.first().locator('.inbox-envelope-from')).not.toBeEmpty();
      // where the first e-mail ends and the second starts
      const gap = await envelopes.nth(1).evaluate((el) => {
        el.scrollIntoView({ block: 'start', behavior: 'instant' });
        window.scrollBy({ top: -300, behavior: 'instant' });
        return el.getBoundingClientRect().top - el.previousElementSibling.getBoundingClientRect().bottom;
      });
      expect(gap).toBeGreaterThan(20);   // a gap between envelopes
      const box = await envelopes.nth(1).boundingBox();
      await evidence(page, `r5p-${label}`, { clip: { x: box.x - 12, y: Math.max(0, box.y - 190), width: box.width + 24, height: 400 } });
    }
  } finally {
    await setSetting(page, 'inboxEnvelope', previousLook || 'airmail');
    await setSetting(page, 'emailNumFetch', previousCount);
  }
});

// R5q (owner, 2026-11-25): each user chooses the look in their menu (also «Καμία»: R5o's thin line); the inbox changes
// at once; the setting is only the default. The choice is removed at the end (shared database).
test('each user chooses the envelope in the user menu, also «Καμία» (R5q)', async ({ page }) => {
  await login(page, 'admin');
  const previousCount = await setSetting(page, 'emailNumFetch', 3);
  const clear = () => page.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    await fetch('/preferences', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ key: 'envelope', value: null }) });
  });
  try {
    await openInbox(page);
    const choose = async (value) => {
      await page.locator('#nav-user').click();
      const saved = page.waitForResponse((r) => r.url().endsWith('/preferences') && r.request().method() === 'POST');
      await page.locator(`[data-preference="envelope"][data-value="${value}"]`).click();
      expect((await saved).status()).toBe(200);
    };

    await page.locator('#nav-user').click();
    await expect(page.locator('.user-menu')).toContainText('Εμφάνιση των e-mail');
    await evidence(page, 'r5q-menu', { clip: await page.locator('.user-menu').boundingBox() });
    await page.keyboard.press('Escape');

    await choose('none');   // at once, without reloading
    await expect(page.locator('.inbox-envelope')).toHaveCount(0);
    await expect(page.locator('.inbox-sep').first()).toContainText(/2 από \d+/);
    const shown = await page.locator('.inbox-message').count();   // the e-mails on this page (other tests register some)
    expect(shown).toBeGreaterThan(1);
    await expect(page.locator('.inbox-noenvelope')).toHaveCount(shown);
    await expect(page.locator('.inbox-sep')).toHaveCount(shown - 1);   // between two e-mails
    const sep = page.locator('.inbox-sep').first();
    await sep.evaluate((el) => { el.scrollIntoView({ block: 'center', behavior: 'instant' }); });
    const box = await sep.boundingBox();
    await evidence(page, 'r5q-none', { clip: { x: box.x - 12, y: Math.max(0, box.y - 190), width: box.width + 24, height: 400 } });

    // R5u (owner, 2026-11-25): the choice is only in the inbox's menu
    await page.goto('/home/list');
    await page.locator('#nav-user').click();
    await expect(page.locator('.user-menu')).toBeVisible();
    await expect(page.locator('.user-menu')).not.toContainText('Εμφάνιση των e-mail');
    await evidence(page, 'r5u-list-menu', { clip: await page.locator('.user-menu').boundingBox() });
    await page.keyboard.press('Escape');
    await openInbox(page);

    await page.reload();   // kept with the account
    await expect(page.locator('.inbox-sep').first()).toBeVisible();
    await choose('paper');
    await expect(page.locator('.inbox-envelope.inbox-envelope-paper')).toHaveCount(shown);
    await expect(page.locator('.inbox-sep')).toHaveCount(0);
  } finally {
    await clear();
    await setSetting(page, 'emailNumFetch', previousCount);
  }
});

// R5x (owner, 2026-11-26): a registered e-mail gets a green stripe down the left of its envelope, in every look (and the
// card's left border in «Καμία»); the next, still new e-mail has none. Photographed in the four looks, without reloading.
test('a registered e-mail is marked with a green stripe on the left, in every look (R5x)', async ({ page }) => {
  await login(page, 'writer');
  const clear = () => page.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    await fetch('/preferences', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ key: 'envelope', value: null }) });
  });
  const choose = async (value) => {
    await page.locator('#nav-user').click();
    const saved = page.waitForResponse((r) => r.url().endsWith('/preferences') && r.request().method() === 'POST');
    await page.locator(`[data-preference="envelope"][data-value="${value}"]`).click();
    expect((await saved).status()).toBe(200);
  };
  const green = 'rgb(25, 135, 84)';   // Bootstrap's --bs-success
  try {
    await openInbox(page);
    await choose('airmail');
    const message = page.locator('article.inbox-message').first();
    const uid = await message.getAttribute('data-uid');
    const envelope = page.locator(`[wire\\:key="env-${uid}"]`);
    const next = page.locator('article.inbox-message').nth(1).locator('xpath=..');
    await expect(envelope).not.toHaveClass(/is-saved/);
    expect(await envelope.evaluate((el) => getComputedStyle(el, '::after').content)).toBe('none');

    await message.locator(`#f-${uid}-fakelos`).click();
    await message.locator(`#folder-list-${uid} li[role=option]`).first().click();
    await message.locator('[data-action="register"]').click();
    const anyway = message.locator('[data-action="register-anyway"]');   // a run before left the same protocol
    const done = message.locator('.inbox-done');
    await expect(done.or(anyway)).toBeVisible();
    if (await anyway.isVisible()) await anyway.click();
    await expect(done).toContainText('Καταχωρίστηκε με αριθμό');

    for (const look of ['airmail', 'paper', 'plain', 'none']) {
      if (look !== 'airmail') await choose(look);
      // this look's element (the previous look's is marked too, until Livewire replaces it)
      const saved = page.locator(`${look === 'none' ? '.inbox-noenvelope' : `.inbox-envelope-${look}`}[wire\\:key="env-${uid}"]`);
      await expect(saved).toHaveClass(/is-saved/);
      await expect(next).not.toHaveClass(/is-saved/);
      if (look === 'none') {
        await expect(saved.locator('.inbox-message')).toHaveCSS('border-left-color', green);
      } else {
        await expect.poll(() => saved.evaluate((el) => getComputedStyle(el, '::after').backgroundColor)).toBe(green);
        expect(await saved.evaluate((el) => parseFloat(getComputedStyle(el, '::after').width))).toBeGreaterThan(6);
      }
      await saved.evaluate((el) => { el.scrollIntoView({ block: 'start', behavior: 'instant' }); window.scrollBy({ top: -90, behavior: 'instant' }); });
      const box = await saved.boundingBox();
      await evidence(page, `r5x-${look}`, { clip: { x: box.x - 12, y: Math.max(0, box.y - 12), width: box.width + 24, height: 520 } });
    }
  } finally {
    await clear();
  }
});
