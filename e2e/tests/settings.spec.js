// R6c: Ρυθμίσεις (grouped, explained, checked at the field) and Εκκαθάριση (expired attachments → trash).
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, fillProtocol, saveProtocol, notice } = require('./helpers');

test('settings: a wrong value is refused at its field, a right one is saved (R6c)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/settings?section=display');   // R6f: one section at a time
  await expect(page.locator('#set-display')).toContainText('Λίστες και αναζήτηση');
  await evidence(page, 'r6c-settings', { fullPage: true });

  const rows = page.locator('#s-showRowsInPage');
  const previous = await rows.inputValue();
  await rows.fill('abc');
  await page.locator('#settings-save').click();
  await expect(page.locator('[data-setting="showRowsInPage"] .invalid-feedback')).toBeVisible();
  await expect(page.locator('#settings-errors')).toBeVisible();
  await evidence(page, 'r6c-settings-error');

  await rows.fill(previous === '30' ? '31' : '30');
  await page.locator('#settings-save').click();
  await expect(page.locator('#app-toasts .toast', { hasText: 'Αποθηκεύτηκαν' })).toBeVisible();
  await expect(page.locator('#settings-errors')).toHaveCount(0);
  await page.reload();
  await expect(page.locator('#s-showRowsInPage')).toHaveValue(previous === '30' ? '31' : '30');

  // put it back
  await page.locator('#s-showRowsInPage').fill(previous);
  await page.locator('#settings-save').click();
  await expect(page.locator('#app-toasts .toast', { hasText: 'Αποθηκεύτηκαν' }).last()).toBeVisible();
});

test('every setting explains itself in its ⓘ; the numbering settings are framed together (R6d)', async ({ page }) => {
  await login(page, 'admin');
  await page.setViewportSize({ width: 1400, height: 900 });
  await page.goto('/settings?section=numbering');   // R6f

  const box = page.locator('#box-numbering');
  await expect(box).toBeVisible();
  await expect(box).toContainText('Αρχή αρίθμησης και κενοί αριθμοί');
  await expect(box.locator('#s-firstProtocolNum')).toBeVisible();
  await expect(box.locator('#s-gapCheckMonths')).toBeVisible();
  await expect(page.locator('#s-safeNewProtocolNum, #s-protocolArrowStep, #s-updatesAutoCheck')).toHaveCount(0);

  const count = await page.locator('.setting').count();
  await expect(page.locator('.setting-more')).toHaveCount(count);
  expect(count).toBeGreaterThan(0);

  // the mouse over the ⓘ: the explanation appears; away: it goes
  const info = page.locator('[data-more=gapCheckMonths]');
  await info.hover();
  const popover = page.locator('.popover.setting-popover');
  await expect(popover).toBeVisible();
  await expect(popover).toContainText('Έλεγχος κενών αριθμών: τελευταίοι μήνες');
  await expect(popover).toContainText('Αριθμό για ξεκίνημα');
  await page.waitForFunction(() => getComputedStyle(document.querySelector('.popover.setting-popover')).opacity === '1');   // faded in
  await evidence(page, 'r6d-numbering-box', { clip: await box.evaluate((el) => {
    const r = el.closest('.settings-section').getBoundingClientRect();
    return { x: r.x, y: Math.max(0, r.y - 10), width: window.innerWidth - r.x, height: r.height + 10 };   // with the explanation beside it
  }) });
  await page.mouse.move(5, 5);
  await expect(popover).toHaveCount(0);

  // the keyboard too; pressing the ⓘ of a switch does not flip it
  await page.goto('/settings?setting=putStamp');   // R6f: its section
  const stamp = page.locator('#s-putStamp');
  const wasOn = await stamp.isChecked();
  await page.locator('[data-more=putStamp]').focus();
  await expect(page.locator('.popover.setting-popover')).toContainText('Κάθε χρήστης επιλέγει «Με σφραγίδα»');   // R4f
  await page.locator('[data-more=putStamp]').click();
  await expect(stamp).toBeChecked({ checked: wasOn });
  await evidence(page, 'r6d-settings', { fullPage: true });
});

test('clean-up page: expired attachments, printable list (R6c)', async ({ page }) => {
  await login(page, 'admin');
  await page.goto('/arxeio');
  await expect(page.locator('#archive-summary')).toBeVisible();
  await evidence(page, 'r6c-archive');
  const print = await page.goto('/expired');
  expect(print.status()).toBe(200);
  await expect(page.locator('#print-now')).toBeVisible();
  await expect(page.locator('.print-head h1')).toHaveText('Εκκαθάριση αρχείου');
  await evidence(page, 'r6c-expired-print');
});

// R6f (owner, 2026-11-25): Ρυθμίσεις as GitHub's settings: the menu of sections on the left, one section at a time,
// saved on its own; the address says which (back / forward and links work)
test('settings: a menu of sections, one section at a time, saved on its own (R6f)', async ({ page }) => {
  await login(page, 'admin');
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/settings');
  const nav = page.locator('.settings-nav');
  await expect(nav.locator('.settings-nav-heading')).toHaveText(['Πρωτόκολλο', 'Αρχεία', 'E-mail', 'Συντήρηση']);
  await expect(nav.locator('.settings-nav-item.active')).toHaveText('Υπηρεσία');
  await expect(page.locator('#set-office h2')).toHaveText('Υπηρεσία');
  await evidence(page, 'r6f-office', { clip: { x: 0, y: 0, width: 1440, height: 640 } });

  // another section: by the menu, without a new page; the address follows
  for (const [section, title, shot] of [['numbering', 'Αρίθμηση', 'r6f-numbering'], ['rights', 'Δικαιώματα', 'r6f-rights'],
    ['inbox', 'Εισερχόμενα', 'r6f-inbox'], ['files', 'Συνημμένα', 'r6f-files']]) {
    await nav.locator(`[data-section="${section}"]`).click();
    await expect(page.locator(`#set-${section} h2`)).toHaveText(title);
    await expect(nav.locator('.settings-nav-item.active')).toHaveText(title);
    await expect(page).toHaveURL(new RegExp(`section=${section}`));
    await evidence(page, shot, { clip: { x: 0, y: 0, width: 1440, height: 760 } });
  }

  // saved on its own; the menu stays in view while the section scrolls
  await nav.locator('[data-section="display"]').click();
  const rows = page.locator('#s-showRowsInPage');
  const previous = await rows.inputValue();
  await rows.fill(previous === '30' ? '31' : '30');
  await page.locator('#settings-save').click();
  await expect(page.locator('#app-toasts .toast', { hasText: 'Αποθηκεύτηκαν (1 αλλαγή)' }).last()).toBeVisible();
  await rows.fill(previous);
  await page.locator('#settings-save').click();
  await expect(page.locator('#app-toasts .toast', { hasText: 'Αποθηκεύτηκαν' }).last()).toBeVisible();

  // review of R6f: a link to a setting opens its section once; the address then follows the section shown
  await page.goto('/settings?setting=mysqldumpPath');
  await expect(page.locator('#set-backup h2')).toHaveText('Αντίγραφα ασφαλείας');
  await nav.locator('[data-section="office"]').click();
  await expect(page.locator('#set-office h2')).toHaveText('Υπηρεσία');
  await expect(page).not.toHaveURL(/setting=/);
  await page.reload();
  await expect(page.locator('#set-office h2')).toHaveText('Υπηρεσία');

  // narrow screens: the menu above the section
  await page.setViewportSize({ width: 600, height: 900 });
  await page.goto('/settings?section=trash');
  const menu = await nav.boundingBox();
  const section = await page.locator('#set-trash').boundingBox();
  expect(section.y).toBeGreaterThan(menu.y + menu.height - 1);
  await evidence(page, 'r6f-narrow', { fullPage: true });
});

// R6l (owner, 2026-11-26): «Σχετικοί αριθμοί και στα δύο πρωτόκολλα» also for protocols saved before the setting was on
test('related numbers written in both protocols, also for existing ones (R6l)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const n = String(Date.now()).slice(-6);
  await login(page, 'admin');
  const previous = await setSetting(page, 'saveCycleSxetiko', 0);
  try {
    // with the setting off: B names A, A does not name B
    await page.goto('/home');
    await fillProtocol(page, { thema: `R6l πρώτο ${n}`, fakelos: 'first' });
    await saveProtocol(page);
    const first = page.url();
    const [, num, year] = (await notice(page)).match(/Καταχωρίστηκε με αριθμό (\d+)\/(\d{4})/);
    await page.goto('/home');
    await fillProtocol(page, { thema: `R6l δεύτερο ${n}`, fakelos: 'first' });
    await page.locator('#f-sxetiko').fill(num);
    await page.locator('#f-sxetiko').press('Enter');
    await saveProtocol(page);
    const [, second] = (await notice(page)).match(/Καταχωρίστηκε με αριθμό (\d+\/\d{4})/);
    await page.goto(first);
    await expect(page.locator('#related-box .related-pill')).toHaveCount(0);

    // no button while the setting is off; on: the button, the question with the counts, then applied
    await page.goto('/settings?section=registration');
    await expect(page.locator('#related-existing')).toHaveCount(0);
    await setSetting(page, 'saveCycleSxetiko', 1);
    await expect(page.locator('#related-existing-open')).toContainText('Εφαρμογή και στα υπάρχοντα πρωτόκολλα');
    await page.locator('#app-toasts').evaluate((el) => el.replaceChildren());   // the saved toast off the screenshots
    await evidence(page, 'r6l-apply-button', { clip: await page.locator('.settings-section').boundingBox() });
    await page.locator('#related-existing-open').click();
    await expect(page.locator('#related-existing-plan')).toContainText(/\d+ πρωτόκολλ(ο|α) παίρν(ει|ουν) \d+ αριθμ/);
    await evidence(page, 'r6l-apply-question', { clip: await page.locator('.settings-section').boundingBox() });
    await page.locator('#related-existing-run').click();
    await expect(page.locator('#app-toasts .toast').last()).toContainText('Οι σχετικοί αριθμοί γράφτηκαν');
    await page.locator('#related-existing-open').click();
    await expect(page.locator('#related-existing-plan')).toContainText('Κανένα πρωτόκολλο δεν χρειάζεται αλλαγή');

    // A now names B
    await page.goto(first);
    await expect(page.locator('#related-box .related-pill')).toHaveCount(1);
    await expect(page.locator('#related-box .related-pill').first()).toContainText(second);
    expect(year).toMatch(/^\d{4}$/);
  } finally {
    await setSetting(page, 'saveCycleSxetiko', previous);
  }
});
