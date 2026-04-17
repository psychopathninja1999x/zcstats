const LS_PRAYER = 'zc-notify-pref-prayer';
const LS_LIVE = 'zc-notify-pref-live';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function hasNotificationApi() {
    return typeof window !== 'undefined' && 'Notification' in window;
}

/** Browsers only allow notification permission on HTTPS (or localhost / 127.0.0.1). */
function isSecureNotificationContext() {
    return (
        window.isSecureContext === true ||
        window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1'
    );
}

function webPushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window;
}

export function initDashboardNotifications() {
    const panel = document.getElementById('zc-notify-panel');
    const wrap = document.getElementById('zc-notify-wrap');
    const menuBtn = document.getElementById('zc-notify-menu-btn');
    const dropdown = document.getElementById('zc-notify-dropdown');
    const cbPrayer = document.getElementById('zc-notify-prayer');
    const cbLive = document.getElementById('zc-notify-live');
    const btn = document.getElementById('zc-notify-enable');
    const statusEl = document.getElementById('zc-notify-status');

    if (!panel || !wrap || !menuBtn || !dropdown || !cbPrayer || !cbLive || !btn || !statusEl) {
        return;
    }

    if (!hasNotificationApi()) {
        return;
    }

    wrap.hidden = false;

    function closeNotifyMenu() {
        dropdown.classList.add('hidden');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    function openNotifyMenu() {
        const localeDd = document.getElementById('zc-locale-dropdown');
        const localeBtn = document.getElementById('zc-locale-menu-btn');
        if (localeDd && !localeDd.classList.contains('hidden')) {
            localeDd.classList.add('hidden');
            if (localeBtn) {
                localeBtn.setAttribute('aria-expanded', 'false');
            }
        }
        dropdown.classList.remove('hidden');
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function toggleNotifyMenu() {
        if (dropdown.classList.contains('hidden')) {
            openNotifyMenu();
        } else {
            closeNotifyMenu();
        }
    }

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleNotifyMenu();
    });

    document.addEventListener('click', () => {
        closeNotifyMenu();
    });

    wrap.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeNotifyMenu();
        }
    });

    if (!isSecureNotificationContext()) {
        statusEl.textContent = panel.getAttribute('data-notify-requires-https') || '';
        cbPrayer.disabled = true;
        cbLive.disabled = true;
        btn.disabled = true;

        return;
    }

    const digestUrl = panel.getAttribute('data-digest-url') || '';
    const prayerEnabled = panel.getAttribute('data-prayer-enabled') === '1';
    const webpushEnabled = panel.getAttribute('data-webpush-enabled') === '1';
    let prayerLabels = {};
    try {
        prayerLabels = JSON.parse(panel.getAttribute('data-prayer-labels') || '{}');
    } catch {
        prayerLabels = {};
    }

    const icon = panel.getAttribute('data-notify-icon') || undefined;

    if (!digestUrl) {
        return;
    }

    const notifyIcon = icon || undefined;
    const prayerWindowMs = 120000;
    const digestPollMs = 180000;

    let prayerIntervalId = 0;
    let digestIntervalId = 0;
    let prayerTimeouts = [];
    let digestBaseline = null;

    const vapidUrl = panel.getAttribute('data-push-vapid-url') || '';
    const subscribeUrl = panel.getAttribute('data-push-subscribe-url') || '';
    const patchUrl = panel.getAttribute('data-push-patch-url') || '';
    const deleteUrl = panel.getAttribute('data-push-delete-url') || '';
    const appLocale = panel.getAttribute('data-app-locale') || 'en';

    function savePrefs() {
        localStorage.setItem(LS_PRAYER, cbPrayer.checked ? '1' : '0');
        localStorage.setItem(LS_LIVE, cbLive.checked ? '1' : '0');
    }

    cbPrayer.checked = localStorage.getItem(LS_PRAYER) === '1';
    cbLive.checked = localStorage.getItem(LS_LIVE) === '1';

    function clearPrayerTimeouts() {
        prayerTimeouts.forEach((id) => clearTimeout(id));
        prayerTimeouts = [];
    }

    function prayerStorageKey(key, atMs) {
        const d = new Date(atMs);
        return `zc-prayer-notify-${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}-${key}`;
    }

    function tryPrayerNotify(key, atMs) {
        if (Notification.permission !== 'granted' || !cbPrayer.checked || !prayerEnabled) {
            return;
        }
        const sk = prayerStorageKey(key, atMs);
        if (sessionStorage.getItem(sk)) {
            return;
        }
        sessionStorage.setItem(sk, '1');
        const label = prayerLabels[key] || key;
        const title = panel.getAttribute('data-notify-prayer-title') || 'Prayer time';
        const bodyTpl = panel.getAttribute('data-notify-prayer-body') || ':prayer';
        try {
            new Notification(title, {
                body: bodyTpl.replace(/:prayer/g, label),
                icon: notifyIcon,
                tag: sk,
            });
        } catch {
            /* ignore */
        }
    }

    function collectPrayerSlots() {
        const root = document.getElementById('zc-prayer-next-root');
        if (!root) {
            return [];
        }
        const now = Date.now();
        const slots = [];
        const seen = new Set();

        root.querySelectorAll('[data-prayer-card]').forEach((card) => {
            const ms = parseInt(card.getAttribute('data-prayer-at-ms'), 10);
            const key = card.getAttribute('data-prayer-key');
            if (!Number.isFinite(ms) || !key || key === 'Sunrise') {
                return;
            }
            const sig = `${key}:${ms}`;
            if (seen.has(sig)) {
                return;
            }
            seen.add(sig);
            slots.push({ key, atMs: ms });
        });

        const fajrTom = parseInt(root.getAttribute('data-fajr-tomorrow-ms'), 10);
        if (Number.isFinite(fajrTom) && fajrTom > now) {
            const sig = `Fajr:${fajrTom}`;
            if (!seen.has(sig)) {
                slots.push({ key: 'Fajr', atMs: fajrTom });
            }
        }

        slots.sort((a, b) => a.atMs - b.atMs);
        return slots;
    }

    function schedulePrayerAlarms() {
        clearPrayerTimeouts();
        if (Notification.permission !== 'granted' || !cbPrayer.checked || !prayerEnabled) {
            return;
        }
        const now = Date.now();
        collectPrayerSlots().forEach(({ key, atMs }) => {
            if (atMs <= now) {
                if (now < atMs + prayerWindowMs) {
                    tryPrayerNotify(key, atMs);
                }
                return;
            }
            const delay = atMs - now;
            const maxDelay = 24 * 60 * 60 * 1000;
            if (delay > maxDelay) {
                return;
            }
            prayerTimeouts.push(
                setTimeout(() => {
                    tryPrayerNotify(key, atMs);
                }, delay)
            );
        });
    }

    function prayerPoll() {
        if (Notification.permission !== 'granted' || !cbPrayer.checked || !prayerEnabled) {
            return;
        }
        const now = Date.now();
        collectPrayerSlots().forEach(({ key, atMs }) => {
            if (now >= atMs && now < atMs + prayerWindowMs) {
                tryPrayerNotify(key, atMs);
            }
        });
    }

    async function pollDigest() {
        if (Notification.permission !== 'granted' || !cbLive.checked || !digestUrl) {
            return;
        }
        try {
            const r = await fetch(digestUrl, { credentials: 'same-origin', cache: 'no-store' });
            if (!r.ok) {
                return;
            }
            const j = await r.json();
            const d = j.digest;
            if (typeof d !== 'string') {
                return;
            }
            if (digestBaseline === null) {
                digestBaseline = d;
                return;
            }
            if (d === digestBaseline) {
                return;
            }
            digestBaseline = d;
            const tag = `zc-live-${d.slice(0, 24)}`;
            if (sessionStorage.getItem(tag)) {
                return;
            }
            sessionStorage.setItem(tag, '1');
            const title = panel.getAttribute('data-notify-live-title') || 'ZCStats';
            const body = panel.getAttribute('data-notify-live-body') || '';
            try {
                new Notification(title, { body, icon: notifyIcon, tag });
            } catch {
                /* ignore */
            }
        } catch {
            /* ignore */
        }
    }

    function stopWatchers() {
        clearPrayerTimeouts();
        if (prayerIntervalId) {
            clearInterval(prayerIntervalId);
            prayerIntervalId = 0;
        }
        if (digestIntervalId) {
            clearInterval(digestIntervalId);
            digestIntervalId = 0;
        }
    }

    function startWatchers() {
        stopWatchers();
        if (Notification.permission !== 'granted') {
            return;
        }
        savePrefs();

        if (prayerEnabled && cbPrayer.checked) {
            schedulePrayerAlarms();
            prayerPoll();
            prayerIntervalId = window.setInterval(prayerPoll, 15000);
        }

        if (cbLive.checked) {
            pollDigest();
            digestIntervalId = window.setInterval(pollDigest, digestPollMs);
        }
    }

    async function syncWebPushSubscription() {
        if (!webpushEnabled || !webPushSupported() || !vapidUrl || !subscribeUrl) {
            return;
        }
        if (Notification.permission !== 'granted') {
            return;
        }
        if (!cbPrayer.checked && !cbLive.checked) {
            return;
        }

        try {
            const vr = await fetch(vapidUrl, { credentials: 'same-origin' });
            const vj = await vr.json();
            if (!vj.enabled || !vj.publicKey) {
                return;
            }

            const reg = await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vj.publicKey),
                });
            }

            const payload = {
                subscription: sub.toJSON(),
                wants_prayer: cbPrayer.checked && prayerEnabled,
                wants_live: cbLive.checked,
                locale: appLocale,
            };

            await fetch(subscribeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(payload),
            });
        } catch {
            /* ignore */
        }
    }

    async function patchWebPushPreferences() {
        if (!webpushEnabled || !webPushSupported() || !patchUrl) {
            return;
        }
        if (Notification.permission !== 'granted') {
            return;
        }

        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (!sub) {
                if (cbPrayer.checked || cbLive.checked) {
                    await syncWebPushSubscription();
                }
                return;
            }

            await fetch(patchUrl, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    subscription: sub.toJSON(),
                    wants_prayer: cbPrayer.checked && prayerEnabled,
                    wants_live: cbLive.checked,
                    locale: appLocale,
                }),
            });
        } catch {
            /* ignore */
        }
    }

    async function teardownWebPushSubscription() {
        if (!webpushEnabled || !webPushSupported() || !deleteUrl) {
            return;
        }

        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (!sub) {
                return;
            }

            await fetch(deleteUrl, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });

            await sub.unsubscribe();
        } catch {
            /* ignore */
        }
    }

    async function syncPushAfterPreferenceChange() {
        savePrefs();
        if (!webpushEnabled || Notification.permission !== 'granted') {
            return;
        }
        if (!cbPrayer.checked && !cbLive.checked) {
            await teardownWebPushSubscription();
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) {
            await patchWebPushPreferences();
        } else {
            await syncWebPushSubscription();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible' || Notification.permission !== 'granted') {
            return;
        }
        if (prayerEnabled && cbPrayer.checked) {
            schedulePrayerAlarms();
            prayerPoll();
        }
        if (cbLive.checked) {
            pollDigest();
        }
    });

    btn.addEventListener('click', async () => {
        savePrefs();
        if (!cbPrayer.checked && !cbLive.checked) {
            statusEl.textContent = panel.getAttribute('data-notify-pick-one') || '';
            return;
        }
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            statusEl.textContent = panel.getAttribute('data-notify-denied') || '';
            return;
        }
        statusEl.textContent = panel.getAttribute('data-notify-granted') || '';
        startWatchers();
        await syncWebPushSubscription();
    });

    cbPrayer.addEventListener('change', () => {
        void syncPushAfterPreferenceChange();
        if (Notification.permission === 'granted') {
            startWatchers();
        }
    });
    cbLive.addEventListener('change', () => {
        void syncPushAfterPreferenceChange();
        if (Notification.permission === 'granted') {
            digestBaseline = null;
            startWatchers();
        }
    });

    if (
        Notification.permission === 'granted' &&
        (localStorage.getItem(LS_PRAYER) === '1' || localStorage.getItem(LS_LIVE) === '1')
    ) {
        cbPrayer.checked = localStorage.getItem(LS_PRAYER) === '1';
        cbLive.checked = localStorage.getItem(LS_LIVE) === '1';
        statusEl.textContent = panel.getAttribute('data-notify-active') || '';
        startWatchers();
        void syncWebPushSubscription();
    }
}
