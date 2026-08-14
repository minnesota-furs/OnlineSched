/** Open-now badges for structured Hours entries. */

const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

// Minutes past midnight, or null for anything that is not a clock time.
function minutes(value) {
    const match = /^(\d{1,2}):([0-5]\d)$/.exec((value || '').trim());
    if (!match) return null;
    const hour = Number(match[1]);
    const minute = Number(match[2]);
    if (hour > 24 || (hour === 24 && minute !== 0)) return null;
    return hour * 60 + minute;
}

// An end at or before the start runs into the next day, which is how a desk
// that closes at 2am is authored.
function windowFor(el, dayOffset) {
    if (el.hasAttribute('data-all-day')) {
        return { from: dayOffset, to: dayOffset + 24 * 60 };
    }
    const start = minutes(el.getAttribute('data-start'));
    const end = minutes(el.getAttribute('data-end'));
    if (start === null || end === null) return null;
    const from = dayOffset + start;
    let to = dayOffset + end;
    if (to <= from) to += 24 * 60;
    return { from, to };
}

function wallTime(now, timezone) {
    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            weekday: 'long',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(now);
        const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
        return {
            day: values.weekday.toLowerCase(),
            date: `${values.year}-${values.month}-${values.day}`,
            minute: Number(values.hour) * 60 + Number(values.minute),
        };
    } catch (_) {
        return null;
    }
}

function isOperationalDate(hours, date) {
    const start = hours?.getAttribute('data-operational-start') || '';
    const end = hours?.getAttribute('data-operational-end') || '';
    const isDate = /^\d{4}-\d{2}-\d{2}$/;
    if (!isDate.test(start) || !isDate.test(end) || start > end) return false;
    return date >= start && date <= end;
}

// Pair each day label with the adjacent time entries.
function entriesByDay(dept) {
    const days = {};
    const list = dept.querySelector('.os-hours__days');
    if (!list) return days;
    let current = null;
    for (const node of list.children) {
        if (node.tagName === 'DT') {
            current = node.textContent.trim().replace(/:$/, '').toLowerCase();
            continue;
        }
        if (node.tagName === 'DD' && current) {
            days[current] = (days[current] || []).concat(
                Array.from(node.querySelectorAll('.os-hours__time'))
            );
        }
    }
    return days;
}

function statusFor(dept, now) {
    const byDay = entriesByDay(dept);
    const hours = dept.closest('.os-hours');
    const timezone = hours?.getAttribute('data-timezone') || '';
    const at = wallTime(now, timezone);
    if (!at || !DAYS.includes(at.day) || !isOperationalDate(hours, at.date)) return 'unknown';
    const today = DAYS.indexOf(at.day);
    let judged = false;
    let open = false;

    // Include yesterday so overnight windows remain open after midnight.
    for (const offset of [0, -1]) {
        const name = DAYS[(today + offset + DAYS.length) % DAYS.length];
        for (const el of byDay[name] || []) {
            const range = windowFor(el, offset * 24 * 60);
            if (!range) continue;
            judged = true;
            if (at.minute < range.from || at.minute >= range.to) continue;
            // Closed periods override overlapping open periods.
            if (el.hasAttribute('data-closed')) return 'closed';
            open = true;
        }
    }

    if (!judged) return 'unknown';
    return open ? 'open' : 'closed';
}

function badgeFor(dept) {
    let badge = dept.querySelector('.os-hours__open');
    if (badge) return badge;
    badge = document.createElement('p');
    badge.className = 'os-hours__open';
    badge.innerHTML =
        '<i class="fa-solid fa-circle" aria-hidden="true"></i> ' +
        '<span>' + (dept.getAttribute('data-open-label') || 'Open now') + '</span>';
    const days = dept.querySelector('.os-hours__days');
    if (!days) return null;
    dept.insertBefore(badge, days);
    return badge;
}

function paint(now) {
    for (const dept of document.querySelectorAll('.os-hours__dept')) {
        const isOpen = statusFor(dept, now) === 'open';
        const badge = isOpen ? badgeFor(dept) : dept.querySelector('.os-hours__open');
        if (!badge) continue;
        badge.hidden = !isOpen;
    }
}

function start() {
    if (!document.querySelector('.os-hours__dept')) return;
    paint(new Date());
    window.setInterval(() => paint(new Date()), 30000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
