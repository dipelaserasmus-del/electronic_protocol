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
