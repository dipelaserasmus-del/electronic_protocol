// R5f (owner, 2026-11-20): the red badge next to Εισερχόμενα: how many e-mails wait in the inbox. Asked every minute
// while the tab is shown, and at once when the user comes back to it; the server keeps one count for everybody, so when
// another user empties the inbox the badge goes at the next check. The inbox page itself updates it at once.
const MINUTE = 60 * 1000;

function show(badge, count) {
    if (typeof count !== 'number') return;   // the mail server did not answer: keep what is shown
    badge.textContent = count > 99 ? '99+' : String(count);
    badge.hidden = count < 1;
    badge.title = count < 1 ? '' : `${count} e-mail στα Εισερχόμενα`;
}

async function check(badge) {
    try {
        const r = await fetch(badge.dataset.inboxCount, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (r.ok) show(badge, (await r.json()).count);
    } catch {
        // offline for a moment: the next check will tell
    }
}

function start() {
    const badge = document.querySelector('[data-inbox-count]');
    if (!badge) return;
    if (badge.hidden && badge.textContent.trim() === '') check(badge);   // not known when the page was drawn
    setInterval(() => { if (!document.hidden) check(badge); }, MINUTE);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(badge); });
    window.addEventListener('inbox-count', (e) => show(badge, e.detail?.count));   // Livewire: the inbox page
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
