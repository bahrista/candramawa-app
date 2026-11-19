// Form dates are plain wall-clock strings: "2026-12-01T19:00" (no time zone).
// Nothing here touches the browser's time zone; the server converts to and from UTC.
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const RE = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/;
const pad = (n) => String(n).padStart(2, '0');

export function parseLocal(value) {
    const m = RE.exec(value || '');
    if (!m) return null;
    const [, y, mo, d, hh, mm] = m;
    const utc = new Date(Date.UTC(+y, +mo - 1, +d));
    return {
        year: +y,
        monthIndex: +mo - 1,
        day: +d,
        hh,
        mm,
        weekday: DAYS[utc.getUTCDay()],
        month: MONTHS[+mo - 1],
    };
}

/** "Friday, 20 November 2026, 14:30 WIB" */
export function longLabel(value, abbr = '') {
    const p = parseLocal(value);
    if (!p) return '';
    return `${p.weekday}, ${p.day} ${p.month} ${p.year}, ${p.hh}:${p.mm}${abbr ? ' ' + abbr : ''}`;
}

/** "20 November" */
export function dayMonth(value) {
    const p = parseLocal(value);
    return p ? `${p.day} ${p.month}` : '';
}

/** Wall-clock arithmetic, e.g. the automatic deadline (publish date minus 24 hours). */
export function minusHours(value, hours) {
    const p = parseLocal(value);
    if (!p) return '';
    const d = new Date(Date.UTC(p.year, p.monthIndex, p.day, +p.hh, +p.mm) - hours * 3600000);
    return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`;
}
