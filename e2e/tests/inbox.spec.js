// R5a: inbox from the mail cache; sender names split inside a Greek letter; HTML preview behind login.
// R5c: the rebuilt inbox: list on the left, text/HTML sections that open and close (remembered), sizes.
const { test, expect } = require('@playwright/test');
const { login, evidence, setSetting, openInbox, inboxReady } = require('./helpers');

/** The message (<article>) of the synthetic e-mail whose text contains `text`, on whichever page it is. */
async function findEmail(page, text) {
  for (const p of [1, 2, 3, 4, 5, 6, 7, 8]) {
    await openInbox(page, `/inbox?page=${p}`);
    const message = page.locator('article.inbox-message', { hasText: text });   // one e-mail each
    if (await message.count()) return message;
  }
  throw new Error(`e-mail "${text}" not found in the inbox`);
}

test('sender name split inside a Greek letter is shown without ?? (R5a)', async ({ page }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'συνέλευση του συλλόγου');   // synthetic e-mail 09
  await expect(message).toContainText('Σύλλογος Διδασκόντων Γυμνασίου Δοκιμής');
  expect(await message.innerText()).not.toContain('??');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5a-split-sender', { clip: await message.boundingBox() });   // this e-mail only
});

test('HTML preview comes from a logged-in route in a sandbox; no public copies (R5a)', async ({ page, request }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'Πρόσκληση σε επιμορφωτική ημερίδα');   // synthetic e-mail 01 (HTML part)
  const frame = message.locator('iframe.inbox-html');
  await expect(frame).toHaveAttribute('src', /\/viewEmailHtml\/\d+$/);
  await expect(frame).toHaveAttribute('sandbox', /allow-same-origin/);
  await expect(message.frameLocator('iframe.inbox-html').locator('b')).toHaveText('συνημμένα');
  await expect.poll(async () => (await frame.boundingBox()).height).toBeGreaterThan(30);   // sized to its content

  const src = await frame.getAttribute('src');
  const preview = await page.request.get(src);
  expect(preview.status()).toBe(200);
  expect(preview.headers()['content-security-policy']).toMatch(/^sandbox/);

  // not logged in: no preview, and the old public copies (public/tmp/u<id>/<uid>.eml) no longer exist
  const anonymous = await request.get(src, { maxRedirects: 0 });
  expect(anonymous.status()).toBe(302);
  const uid = src.match(/(\d+)$/)[1];
  for (const ext of ['eml', 'html']) {
    expect((await request.get(`/tmp/u1/${uid}.${ext}`)).status()).toBe(404);
  }
});

test('attachment name cut inside a Greek letter is shown and downloaded correctly (R5a)', async ({ page }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'Διαβιβαστικό αίτησης άδειας');   // synthetic e-mail 10
  const link = message.getByRole('link', { name: 'ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ (1).pdf' });
  await expect(link).toBeVisible();
  expect(await message.innerText()).not.toContain('??');
  const file = await page.request.get(await link.getAttribute('href'));
  expect(file.status()).toBe(200);
  expect(decodeURIComponent(file.headers()['content-disposition'])).toContain('ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ (1).pdf');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5a-split-attachment-name', { clip: await message.boundingBox() });   // this e-mail only
});

test('inbox: the list on the left goes to a message; attachments show type and size (R5c)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'admin');
  await openInbox(page, '/inbox');
  const items = page.locator('#inbox-sidebar a.inbox-sidebar-item');
  const n = await items.count();
  expect(n).toBeGreaterThan(1);
  expect(n).toBe(await page.locator('article.inbox-message').count());
  await evidence(page, 'r5c-inbox', { fullPage: false });

  // the last message of the page: a click on its line in the list brings it to the top, under the top bar
  const last = items.nth(n - 1);
  const target = (await last.getAttribute('href')).slice(1);
  await last.click();
  const message = page.locator(`#${target}`);
  await expect.poll(async () => Math.round((await message.boundingBox()).y)).toBeLessThan(200);
  await expect(last).toHaveClass(/active/);
  await expect(page.locator('#inbox-sidebar')).toBeInViewport();   // the list stays in view

  // every attachment: a type label and a size
  const attachment = page.locator('.inbox-attachment').first();
  await expect(attachment.locator('.badge')).toHaveText(/^[A-ZΑ-Ω]+$/);
  await expect(attachment).toContainText(/\d+(,\d)? (B|KB|MB)/);
});

test('inbox: text and HTML open and close together, and the browser remembers it (R5c)', async ({ page }) => {
  await login(page, 'admin');
  await openInbox(page, '/inbox');
  const texts = page.locator('.inbox-text');
  await expect(texts.first()).toBeVisible();

  await page.locator('[data-part-toggle="text"]').first().click();
  await expect(texts.first()).toBeHidden();
  for (const t of await texts.all()) await expect(t).toBeHidden();   // every message's text

  await page.reload();
  await inboxReady(page);
  await expect(texts.first()).toBeHidden();                           // remembered
  await expect(page.locator('[data-part-toggle="text"]').first()).toHaveAttribute('aria-expanded', 'false');
  await page.locator('[data-part-toggle="text"]').first().click();
  await expect(texts.first()).toBeVisible();
});

test('winmail.dat (Outlook): its text and files are shown and registered instead of it (R5b)', async ({ page }) => {
  await login(page, 'admin');
  const message = await findEmail(page, 'Outlook winmail');   // synthetic e-mail 11
  const uid = await message.getAttribute('data-uid');
  await expect(message.locator('.inbox-winmail')).toContainText('winmail.dat');
  await expect(message.locator('.inbox-text')).toContainText('Σας στέλνουμε την αίτηση μετακίνησης');   // from the compressed RTF
  await expect(message.getByRole('link', { name: 'Αίτηση μετακίνησης εκπαιδευτικών.pdf' })).toBeVisible();
  await expect(message.getByRole('link', { name: 'Πίνακας μετακινήσεων.csv' })).toBeVisible();
  await expect(message.getByRole('link', { name: 'winmail.dat' })).toHaveCount(0);
  const pdf = await page.request.get(await message.getByRole('link', { name: 'Αίτηση μετακίνησης εκπαιδευτικών.pdf' }).getAttribute('href'));
  expect(pdf.headers()['content-type']).toBe('application/pdf');
  expect((await pdf.body()).subarray(0, 5).toString()).toBe('%PDF-');
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5b-winmail', { clip: await message.boundingBox() });

  await message.locator(`#f-${uid}-fakelos`).click();
  await message.locator(`#folder-list-${uid} li[role=option]`).nth(1).click();
  await message.locator('[data-action="register"]').click();
  const anyway = message.locator('[data-action="register-anyway"]');   // a run before left the same protocol
  const done = message.locator('.inbox-done');
  await expect(done.or(anyway)).toBeVisible();
  if (await anyway.isVisible()) await anyway.click();
  await expect(done).toContainText('Καταχωρίστηκε με αριθμό');
  const [, num, year] = (await done.innerText()).match(/(\d+)\/(\d{4})/);
  await page.goto(`/goto/${year}/${num}?find=1`);
  await expect(page.locator('a[href*="/download/"]', { hasText: 'Αίτηση μετακίνησης εκπαιδευτικών.pdf' })).toHaveCount(1);
  await expect(page.locator('a[href*="/download/"]', { hasText: /\.csv$/ })).toHaveCount(1);
});

test('incoming date: the app\'s date field; keyword suggestions from the file names too (R5e)', async ({ page }) => {
  await login(page, 'admin');
  // synthetic e-mail 10, which no test registers: subject «Διαβιβαστικό αίτησης άδειας εκπαιδευτικού»,
  // file «ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ (1).pdf» (αιτηση and δοκιμη are only in the file's name)
  const message = await findEmail(page, 'Διαβιβαστικό αίτησης άδειας');
  const pad = (n) => String(n).padStart(2, '0');
  const now = new Date();

  // the date field: "9" → 09/<this month>/<this year>; ◀ one day earlier
  const date = message.locator('input[id$="-in_date"]');
  await date.fill('9');
  await date.press('Tab');
  await expect(date).toHaveValue(`09/${pad(now.getMonth() + 1)}/${now.getFullYear()}`);
  await message.locator('[data-section="incoming"] [data-date-input]').locator('[data-date-action=prev]').click();
  await expect(date).toHaveValue(`08/${pad(now.getMonth() + 1)}/${now.getFullYear()}`);
  await expect(message.locator('[data-section="incoming"] [data-date-input] [data-date-action=calendar]')).toBeVisible();

  // suggestions: words of the file names, not only of the subject
  const suggestions = message.locator('.keyword-suggestions');
  await expect(suggestions).toContainText('Προτάσεις από το θέμα και τα συνημμένα');
  const fold = (w) => w.normalize('NFD').replace(/\p{Mn}/gu, '').toLowerCase().replace(/ς/g, 'σ');
  await expect(suggestions.locator('[data-suggest]').first()).toBeVisible();
  const offered = (await suggestions.locator('[data-suggest]').evaluateAll((els) => els.map((e) => e.dataset.suggest))).map(fold);
  expect(offered).toContain('αιτηση');   // with its accents when earlier subjects have the word
  expect(offered).toContain('δοκιμη');
  const box = await date.evaluate((el) => {
    const form = el.closest('.row, form') || el;
    const r = form.getBoundingClientRect();
    return { x: r.x, y: r.y, width: r.width, height: r.height };
  });
  await evidence(page, 'r5e-date-and-suggestions', { clip: { x: box.x, y: box.y, width: box.width, height: Math.min(box.height, 520) } });
  await suggestions.scrollIntoViewIfNeeded();
  const field = await suggestions.evaluate((el) => {
    const r = el.parentElement.getBoundingClientRect();
    return { x: r.x, y: r.y - 6, width: r.width, height: r.height + 12 };
  });
  await evidence(page, 'r5e-suggestions', { clip: field });
});

test('a red badge next to Εισερχόμενα counts the e-mails waiting; checked again when coming back to the tab (R5f)', async ({ page }) => {
  await login(page, 'writer');
  await openInbox(page, '/inbox');
  const total = Number(await page.locator('#inbox-count').getAttribute('data-total'));   // R5v: the range is only at the pager
  expect(total).toBeGreaterThan(0);
  const badge = page.locator('nav.app-topbar [data-inbox-count]');
  await expect(badge).toHaveText(String(total));
  await expect(badge).toBeVisible();

  // on other pages too, from the shared count
  await page.goto('/home/list');
  await expect(badge).toHaveText(String(total));
  await evidence(page, 'r5f-badge', { clip: { x: 0, y: 0, width: 900, height: 60 } });

  // another user emptied the inbox: the next check (here: coming back to the tab) hides the badge
  await page.route('**/inbox/count', (route) => route.fulfill({ json: { count: 0 } }));
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await expect(badge).toBeHidden();
  // the mail server did not answer: what is shown stays
  await page.unroute('**/inbox/count');
  await page.route('**/inbox/count', (route) => route.fulfill({ json: { count: 3 } }));
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await expect(badge).toHaveText('3');
  await page.unroute('**/inbox/count');
  await page.route('**/inbox/count', (route) => route.fulfill({ json: { count: null } }));
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForTimeout(300);
  await expect(badge).toHaveText('3');
});

test('every e-mail can be reached: the pager above and below, and in the list on the left (R5g)', async ({ page }) => {
  // two per page, so there are pages whatever the registration tests left in the mock inbox
  await login(page, 'admin');
  const previous = await setSetting(page, 'emailNumFetch', 2);
  try {
  await openInbox(page, '/inbox');
  const top = page.locator('#pager-top');
  await expect(top).toBeVisible();   // above the first e-mail, no scrolling needed
  const side = page.locator('.inbox-sidebar-pager');
  await expect(side).toContainText('Σελίδα 1 από');
  const pages = Number((await side.innerText()).match(/από (\d+)/)[1]);
  expect(pages).toBeGreaterThan(1);
  const firstSubject = await page.locator('.inbox-subject').first().innerText();
  await evidence(page, 'r5g-inbox-pagers', { clip: { x: 0, y: 0, width: 1440, height: 520 } });

  // any page from the list of pages
  await top.locator('[data-pager=select]').selectOption(String(pages));
  await expect(side).toContainText(`Σελίδα ${pages} από ${pages}`);
  await expect(page.locator('.inbox-subject').first()).not.toHaveText(firstSubject);
  await expect(page).toHaveURL(new RegExp(`[?&]page=${pages}`));
  // back with the list on the left
  await side.locator('[data-pager=side-previous]').click();
  await expect(side).toContainText(`Σελίδα ${pages - 1} από ${pages}`);
  // and below the last e-mail
  await page.locator('#pager-bottom [data-pager=next]').click();
  await expect(side).toContainText(`Σελίδα ${pages} από ${pages}`);
  await expect(page.locator('#pager-top')).toBeInViewport();
  } finally {
    await setSetting(page, 'emailNumFetch', previous);
  }
});

test('the list on the left scrolls to the e-mail and it stays there; the folder list does not open (R5h)', async ({ page }) => {
  await login(page, 'writer');
  await openInbox(page, '/inbox');
  const items = page.locator('.inbox-sidebar-item');
  const n = await items.count();
  expect(n).toBeGreaterThan(2);
  const last = items.nth(n - 1);
  const uid = (await last.getAttribute('href')).replace('#msg-', '');
  const target = page.locator(`#msg-${uid}`);
  const aligned = () => target.evaluate((el) => Math.abs(el.getBoundingClientRect().top - parseFloat(getComputedStyle(el).scrollMarginTop)));

  await last.click();
  // an e-mail above it grows while the page is scrolling (as a late HTML frame does)
  await page.evaluate(() => {
    const first = document.querySelector('.inbox-message');
    setTimeout(() => { const d = document.createElement('div'); d.style.height = '300px'; d.className = 'r5h-late'; first.append(d); }, 300);
    setTimeout(() => { first.querySelector('.r5h-late').style.height = '120px'; }, 1200);
  });
  await page.waitForTimeout(1800);
  expect(await aligned(), 'the e-mail at the top, after the page above it changed').toBeLessThan(3);
  await expect(page.locator('.folder-list:visible')).toHaveCount(0);   // the folder picker's list did not open
  expect(await page.evaluate((id) => document.activeElement?.id === `msg-${id}`, uid)).toBe(true);
  await expect(page).toHaveURL(new RegExp(`#msg-${uid}$`));

  // the user's own scrolling is never undone
  await page.mouse.wheel(0, -400);
  await page.waitForTimeout(600);
  expect(await aligned()).toBeGreaterThan(100);
});

test('a long Προς list shows three addresses and «και άλλοι N», which opens the rest (R5i)', async ({ page }) => {
  await login(page, 'writer');
  const message = await findEmail(page, 'Κοινοποιείται σε όλα τα σχολεία');   // synthetic e-mail 12, 41 recipients
  const to = message.locator('[data-addresses=to]');
  await expect(to).toContainText('school03@example.test');
  await expect(to).not.toContainText('school40@example.test', { useInnerText: true });
  const toggle = to.locator('[data-addresses-toggle]');
  await expect(toggle).toHaveText('και άλλοι 38 ▾', { useInnerText: true });
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5i-folded');
  await toggle.click();
  await expect(to).toContainText('school40@example.test', { useInnerText: true });
  await expect(toggle).toHaveText('λιγότεροι ▴', { useInnerText: true });
  await toggle.click();
  await expect(to).not.toContainText('school40@example.test', { useInnerText: true });
  await expect(message.locator('[data-addresses=cc] [data-addresses-toggle]')).toHaveCount(0);   // two: in full
});

test('the files are above the buttons; the e-mail (Από, Προς, Θέμα, text) is tinted apart (R5j)', async ({ page }) => {
  await login(page, 'writer');
  const message = await findEmail(page, 'Πρόσκληση ημερίδας 2026.pdf');   // synthetic e-mail with a PDF
  const files = message.locator('.inbox-attachments');
  const register = message.locator('[data-action=register]');
  expect((await files.boundingBox()).y).toBeLessThan((await register.boundingBox()).y);
  const mail = message.locator('.inbox-mail');
  await expect(mail.locator('.inbox-mail-subject')).toHaveText(/Πρόσκληση/);
  const [tint, form] = await Promise.all([mail.evaluate((el) => getComputedStyle(el).backgroundColor), message.locator('.inbox-form').evaluate((el) => getComputedStyle(el).backgroundColor)]);
  expect(tint).not.toBe(form);
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5j-mail-view');
});

test('registering says so until it is done; meanwhile no other e-mail can be registered or marked (R5k)', async ({ page }) => {
  await login(page, 'writer');
  const message = await findEmail(page, 'Κοινοποιείται σε όλα τα σχολεία');   // synthetic e-mail 12, no attachments
  // R5v: «:not(#id)», since «hasNot» looks only inside each article and so never left this one out (it failed whenever
  // this e-mail was the first of its page)
  const other = page.locator(`article.inbox-message:not(#${await message.getAttribute('id')})`).first();
  // hold the registration request until the checks below are done
  let release;
  const held = new Promise((r) => { release = r; });
  await page.route('**/update', async (route) => {
    if ((route.request().postData() ?? '').includes('"method":"register"')) await held;
    await route.continue();
  });
  const uid = await message.getAttribute('data-uid');
  await message.locator(`#f-${uid}-fakelos`).click();   // R5s: every e-mail needs a folder
  await message.locator(`#folder-list-${uid} li[role=option]`).first().click();
  await message.locator('[data-action=register]').click();
  await expect(message.locator('.inbox-saving')).toBeVisible();
  await expect(message.locator('.inbox-saving')).toContainText('Καταχωρίζεται');
  await expect(message.locator('[data-action=register] .spinner-border')).toBeVisible();
  await expect(other.locator('[data-action=register]')).toBeDisabled();   // one registration at a time
  await expect(other.locator('[data-action=mark-read]')).toBeDisabled();
  await expect(other.locator('.inbox-saving')).toBeHidden();
  await message.scrollIntoViewIfNeeded();
  await evidence(page, 'r5k-saving');
  release();
  // registered before (an earlier run on this database): the question; its yellow button also shows the progress
  const anyway = message.locator('[data-action=register-anyway]');
  await expect(message.locator('.inbox-done').or(anyway)).toBeVisible();
  if (await anyway.isVisible()) {
    await page.unroute('**/update');
    let again;
    const heldAgain = new Promise((r) => { again = r; });
    await page.route('**/update', async (route) => {
      if ((route.request().postData() ?? '').includes('"method":"register"')) await heldAgain;
      await route.continue();
    });
    await anyway.click();
    await expect(message.locator('.inbox-saving')).toBeVisible();
    await expect(anyway.locator('.spinner-border')).toBeVisible();
    await expect(anyway).toBeDisabled();
    again();
  }
  await expect(message.locator('.inbox-done')).toContainText('Καταχωρίστηκε με αριθμό');
  await expect(message.locator('.inbox-saving')).toHaveCount(0);
  await expect(other.locator('[data-action=register]')).toBeEnabled();
  await page.unroute('**/update');
});

test('a done e-mail folds: «Εμφάνιση email» opens it again, «Επόμενο email» goes on (R5l)', async ({ page }) => {
  await login(page, 'writer');
  await openInbox(page, '/inbox');
  const first = page.locator('article.inbox-message').first();
  await first.locator('[data-action=mark-read]').click();
  await expect(first.locator('.inbox-done')).toContainText('Μεταφέρθηκε στα αναγνωσμένα');
  await expect(first.locator('.inbox-mail')).toBeHidden();
  await first.scrollIntoViewIfNeeded();
  await evidence(page, 'r5l-folded');
  await first.locator('[data-action=show-mail]').click();
  await expect(first.locator('.inbox-mail')).toBeVisible();
  await first.locator('[data-action=show-mail]').click();
  await expect(first.locator('.inbox-mail')).toBeHidden();
  const next = first.locator('[data-action=next-mail]');
  const target = (await next.getAttribute('x-on:click')).match(/go\((\d+)\)/)[1];
  await next.click();
  await expect(page).toHaveURL(new RegExp(`#msg-${target}$`));
  await expect(page.locator(`#msg-${target}`)).toBeInViewport();
});

test('the inbox appears at once and says it is waiting for the mail server (R5m)', async ({ page }) => {
  await login(page, 'admin');
  // hold the component's first request back for a while, as a busy mail server would
  let held = false;
  await page.route('**/livewire*/update', async (route) => {
    if (!held) { held = true; await new Promise((r) => setTimeout(r, 4000)); }
    await route.continue();
  });
  await page.goto('/inbox');
  const waiting = page.locator('#inbox-connecting');
  await expect(waiting).toBeVisible();
  await expect(waiting).toContainText('Σύνδεση με τον διακομιστή email');
  await expect(waiting).toContainText('Παρακαλώ περιμένετε…');
  await expect(waiting.getByText(/^[1-9]\d* δευτ\.$/)).toBeVisible();         // the seconds counter, running
  await evidence(page, 'r5m-waiting', { clip: await waiting.boundingBox() });

  await inboxReady(page);
  await expect(page.locator('#inbox-connecting')).toHaveCount(0);
  await expect(page.locator('article.inbox-message').first()).toBeVisible();
  // owner: when the e-mails last came from the mail server, next to «Ανανέωση»
  await expect(page.locator('#inbox-checked-at')).toHaveText(/^Τελευταία ανανέωση: \d\d:\d\d$/);
});

/** nginx's own page when it gives up waiting for PHP (what the owner saw in Livewire's dark popup) */
const GATEWAY_TIMEOUT = { status: 504, contentType: 'text/html',
  body: '<html><head><title>504 Gateway Time-out</title></head><body><center><h1>504 Gateway Time-out</h1></center><hr><center>nginx</center></body></html>' };

test('a web server time-out is said in the banner, not in a popup (R5m)', async ({ page }) => {
  await login(page, 'admin');
  let first = true;
  await page.route('**/livewire*/update', async (route) => {
    if (first) { first = false; await route.fulfill(GATEWAY_TIMEOUT); } else await route.continue();
  });
  await page.goto('/inbox');
  const banner = page.locator('#inbox-connecting');
  await expect(banner).toContainText('Ο διακομιστής email δεν απάντησε εγκαίρως.');
  await expect(banner).toHaveClass(/alert-warning/);
  await expect(banner.getByText('Παρακαλώ περιμένετε…')).toBeHidden();        // the waiting line gave way
  await expect(page.locator('#livewire-error')).toHaveCount(0);              // Livewire's popup
  await evidence(page, 'r5m-timeout', { clip: await banner.boundingBox() });

  await banner.getByRole('button', { name: 'Δοκιμή ξανά' }).click();         // connects again: the server answers now
  await inboxReady(page);
  await expect(page.locator('article.inbox-message').first()).toBeVisible();
});

test('a refresh the web server gave up on is said above the e-mails (R5m)', async ({ page }) => {
  await login(page, 'admin');
  await openInbox(page);
  let fail = true;
  await page.route('**/livewire*/update', async (route) => {
    if (fail) { fail = false; await route.fulfill(GATEWAY_TIMEOUT); } else await route.continue();
  });
  await page.getByRole('button', { name: 'Ανανέωση' }).click();
  const box = page.locator('#inbox-unreachable');
  await expect(box).toBeVisible();
  await expect(box).toContainText('Ο διακομιστής email δεν απάντησε εγκαίρως.');
  await expect(page.locator('#livewire-error')).toHaveCount(0);
  await expect(page.locator('article.inbox-message').first()).toBeVisible();   // the e-mails already shown stay

  await box.getByRole('button', { name: 'Δοκιμή ξανά' }).click();
  await expect(box).toBeHidden();
  await expect(page.locator('#inbox-checked-at')).toBeVisible();
});

test('the last check\'s e-mails are shown at once while the server is slow (R5n)', async ({ page }) => {
  await login(page, 'admin');
  await openInbox(page);                                                      // a check: the shared snapshot
  const subjects = await page.locator('article.inbox-message .inbox-subject').allInnerTexts();
  expect(subjects.length).toBeGreaterThan(0);

  // a slow morning: the server's answer takes a while
  let held = false;
  await page.route('**/livewire*/update', async (route) => {
    if (!held) { held = true; await new Promise((r) => setTimeout(r, 4000)); }
    await route.continue();
  });
  await page.reload();
  const banner = page.locator('#inbox-connecting');
  await expect(banner).toBeVisible();
  await expect(page.locator('#inbox-stale-note')).toContainText(/τελευταίας ανανέωσης \(\d\d:\d\d\)/);
  // the same e-mails, at once, before the server has answered
  await expect(page.locator('article.inbox-message .inbox-subject').first()).toHaveText(subjects[0]);
  await expect(page.locator('[data-inbox-state="connecting"]')).toHaveCount(1);
  await evidence(page, 'r5n-from-cache', { clip: await banner.boundingBox() });

  await inboxReady(page);                                                     // the fresh list
  await expect(page.locator('#inbox-connecting')).toHaveCount(0);
  await expect(page.locator('#inbox-stale-note')).toHaveCount(0);
});

test('opening Κείμενο / HTML keeps the clicked e-mail in place; form sections as in the protocol form (R5o)', async ({ page }) => {
  await login(page, 'admin');
  const previous = await setSetting(page, 'emailNumFetch', 5);
  try {
    await openInbox(page);
    const messages = page.locator('article.inbox-message');
    expect(await messages.count()).toBeGreaterThan(2);
    // start with every Κείμενο closed (it opens / closes in all e-mails at once)
    const firstText = page.locator('[data-part-toggle="text"]').first();
    if ((await firstText.getAttribute('aria-expanded')) === 'true') {
      await firstText.click();
      await page.waitForTimeout(2200);   // that click keeps its toggle in place for 2 s (a user's scroll would stop it)
    }

    // a lower e-mail's toggle: the e-mails above it grow when it is clicked
    const toggle = page.locator('[data-part-toggle="text"]').nth(2);
    // in the middle of the window (a toggle out of view would be scrolled to by the click itself)
    await toggle.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
    const before = (await toggle.boundingBox()).y;
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.waitForTimeout(600);
    expect(Math.abs((await toggle.boundingBox()).y - before)).toBeLessThan(4);

    // and closing it again
    const again = (await toggle.boundingBox()).y;
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await page.waitForTimeout(600);
    expect(Math.abs((await toggle.boundingBox()).y - again)).toBeLessThan(4);

    // HTML: frames load late and are fitted to their content; the clicked toggle still stays
    const html = page.locator('[data-part-toggle="html"]');
    if (await html.count() > 1) {
      if ((await html.first().getAttribute('aria-expanded')) === 'true') await html.first().click();
      const h = html.nth(1);
      await h.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
      const top = (await h.boundingBox()).y;
      await h.click();
      await page.waitForTimeout(1500);
      expect(Math.abs((await h.boundingBox()).y - top)).toBeLessThan(4);
      await h.click();
    }

    // the form: Εισερχόμενο (green) open, Εξερχόμενο (blue) folded; HTML in a new tab is an icon by «Εμφάνιση HTML»
    const first = messages.first();
    await expect(first.locator('[data-section="incoming"] legend')).toHaveClass(/bg-success-subtle/);
    const out = first.locator('[data-section="outgoing"]');
    await expect(out.locator('legend')).toHaveClass(/bg-info-subtle/);
    await expect(out.locator('input[id$="-out_to"]')).toBeHidden();
    await out.locator('[data-section-toggle="outgoing"]').click();
    await expect(out.locator('input[id$="-out_to"]')).toBeVisible();
    await expect(page.getByText('HTML σε νέα καρτέλα')).toHaveCount(0);
    await expect(page.getByText('αποθηκεύεται με το πρωτόκολλο')).toHaveCount(0);
    await first.scrollIntoViewIfNeeded();
    await evidence(page, 'r5o-form-sections', { clip: await first.boundingBox() });

    // the list on the left: each e-mail's place, as in its (lavender) header
    const side = page.locator('#inbox-sidebar');
    await expect(side.locator('.inbox-sidebar-n').first()).toHaveText(/^\d+ από \d+$/);
    await expect(side.locator('.inbox-sidebar-n').first()).toHaveText((await first.locator('header .badge').innerText()).trim());
    expect(await first.locator('header').evaluate((el) => getComputedStyle(el).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await evidence(page, 'r5o-list-and-headers', { fullPage: false });

    // R5p (replaced R5o's thin line): each e-mail in an envelope with «N από M» on its address strip
    const second = page.locator('.inbox-envelope').nth(1);
    await expect(second.locator('.inbox-envelope-n')).toHaveText(/^2 από \d+$/);
  } finally {
    await setSetting(page, 'emailNumFetch', previous);
  }
});

// R5v (owner, 2026-11-26): the range («11–20 από 55») only at the pager; the order swaps with a click, arrow and all
test('the inbox order swaps with a click; the range is shown once (R5v)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, 'writer');
  await openInbox(page, '/inbox');
  const top = page.locator('#inbox-count');
  await expect(top).not.toContainText(/από \d+/);
  const order = page.locator('#inbox-order');
  const before = await order.getAttribute('data-order');
  const other = before === 'oldest' ? 'newest' : 'oldest';
  await expect(order).toContainText(before === 'oldest' ? 'παλαιότερα πρώτα' : 'νεότερα πρώτα');
  await expect(order.locator(`i.bi-arrow-${before === 'oldest' ? 'up' : 'down'}`)).toHaveCount(1);
  const head = { x: 0, y: 40, width: 1440, height: 110 };
  await evidence(page, `r5v-order-${before}`, { clip: head });

  await order.click();
  await expect(order).toHaveAttribute('data-order', other);
  await expect(order).toContainText(other === 'oldest' ? 'παλαιότερα πρώτα' : 'νεότερα πρώτα');
  await expect(order.locator(`i.bi-arrow-${other === 'oldest' ? 'up' : 'down'}`)).toHaveCount(1);
  await evidence(page, `r5v-order-${other}`, { clip: head });
  await page.reload();
  await inboxReady(page);
  await expect(page.locator('#inbox-order')).toHaveAttribute('data-order', other);   // kept for this user

  await page.locator('#inbox-order').click();   // back as it was
  await expect(page.locator('#inbox-order')).toHaveAttribute('data-order', before);
});

// R5w (owner, 2026-11-26): the round ↑ «back to the top» (R2m, the list) on the inbox page too, covering nothing
test('the inbox has the back-to-top button (R5w)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 800 });
  await login(page, 'writer');
  await openInbox(page, '/inbox');
  const up = page.locator('[data-scroll-top]');
  await page.evaluate(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' }));
  await expect(up).toBeInViewport();
  const b = await up.boundingBox();
  expect(1440 - (b.x + b.width)).toBeLessThan(80);
  expect(800 - (b.y + b.height)).toBeLessThan(40);
  // at the very bottom it covers no button and not the lower pager
  for (const el of await page.locator('.inbox-page button:visible, .inbox-page nav').all()) {
    if (await el.getAttribute('data-scroll-top') !== null) continue;
    const r = await el.boundingBox();
    if (!r) continue;
    const overlap = r.x < b.x + b.width && b.x < r.x + r.width && r.y < b.y + b.height && b.y < r.y + r.height;
    expect(overlap).toBe(false);
  }
  await evidence(page, 'r5w-scroll-top', { clip: { x: 1440 - 520, y: 800 - 260, width: 520, height: 260 } });
  await up.click();
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
});
