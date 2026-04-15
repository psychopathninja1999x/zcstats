const MANILA_TZ = 'Asia/Manila';

const clockFormatter = new Intl.DateTimeFormat('en-PH', {
    timeZone: MANILA_TZ,
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
});

function padManilaDateForDatetimeAttr(d) {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: MANILA_TZ,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    })
        .formatToParts(d)
        .reduce((acc, p) => {
            if (p.type !== 'literal') {
                acc[p.type] = p.value;
            }
            return acc;
        }, {});

    if (!parts.year || !parts.month || !parts.day || parts.hour === undefined || !parts.minute || !parts.second) {
        return '';
    }

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}+08:00`;
}

function tick() {
    const el = document.getElementById('zc-header-clock');
    if (!el) {
        return;
    }
    const now = new Date();
    el.textContent = clockFormatter.format(now);
    const iso = padManilaDateForDatetimeAttr(now);
    if (iso) {
        el.setAttribute('datetime', iso);
    }
}

function init() {
    const el = document.getElementById('zc-header-clock');
    if (!el) {
        return;
    }
    tick();
    window.setInterval(tick, 1000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
