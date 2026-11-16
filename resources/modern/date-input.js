// N04: rules of the date input, the browser twin of App\Support\DateInput (same cases:
// tests/fixtures/date-input-cases.json). "9" → 09/<this month>/<this year>, "9/4", "9/4/25", "0904", "09042025"…

const pad = n => String(n).padStart(2, '0');
const fmt = d => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
const isRealDate = (y, m, d) => {
    const t = new Date(y, m - 1, d);
    return t.getFullYear() === y && t.getMonth() === m - 1 && t.getDate() === d;
};

/** dd/mm/yyyy, '' for empty input, null when it isn't a real date. */
export function expandDate(text, today = new Date()) {
    text = String(text ?? '').trim();
    if (text === '') return '';
    let parts = null;
    if (/^\d+$/.test(text)) {
        parts = { 1: [text], 2: [text], 4: [text.slice(0, 2), text.slice(2, 4)],
                  6: [text.slice(0, 2), text.slice(2, 4), text.slice(4, 6)],
                  8: [text.slice(0, 2), text.slice(2, 4), text.slice(4, 8)] }[text.length] ?? null;
    } else if (/^\d{1,2}([/.\- ]\d{1,2}([/.\- ](\d{2}|\d{4}))?)?$/.test(text)) {
        parts = text.split(/[/.\- ]/);
    }
    if (!parts) return null;
    const day = parseInt(parts[0], 10);
    const month = parts[1] !== undefined ? parseInt(parts[1], 10) : today.getMonth() + 1;
    let year = parts[2] !== undefined ? parseInt(parts[2], 10) : today.getFullYear();
    if (year < 100) year += 2000;
    if (!isRealDate(year, month, day)) return null;
    return `${pad(day)}/${pad(month)}/${year}`;
}

/** ◀ / ▶: one day earlier/later; from today when the field is empty or not a date. */
export function shiftDate(text, days, today = new Date()) {
    const current = expandDate(text, today);
    let d;
    if (current) {
        const [dd, mm, yyyy] = current.split('/').map(Number);
        d = new Date(yyyy, mm - 1, dd);
    } else {
        d = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    }
    d.setDate(d.getDate() + days);
    return fmt(d);
}

/** dd/mm/yyyy ↔ yyyy-mm-dd for the browser's own calendar (<input type="date">). */
export const toIso = text => { const v = expandDate(text); if (!v) return ''; const [d, m, y] = v.split('/'); return `${y}-${m}-${d}`; };
export const fromIso = iso => { if (!iso) return ''; const [y, m, d] = iso.split('-'); return `${d}/${m}/${y}`; };
