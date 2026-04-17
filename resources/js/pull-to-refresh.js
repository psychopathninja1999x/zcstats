const SCROLL_TOP_EPS = 6;
const PULL_THRESHOLD = 72;
const EXCLUDE_SELECTORS = '.leaflet-container, [data-no-ptr]';

function scrollTopY() {
    return window.scrollY || document.documentElement.scrollTop || 0;
}

function isExcludedTarget(target) {
    if (!target || typeof target.closest !== 'function') {
        return false;
    }
    return target.closest(EXCLUDE_SELECTORS) !== null;
}

/**
 * Pull down from the top on touch devices to full-reload the page (PWA-friendly).
 */
export function initPullToRefresh() {
    const body = document.body;
    if (!body || body.dataset.zcPtrInit === '1') {
        return;
    }

    const touchCapable = 'ontouchstart' in window;
    const narrowOrCoarse =
        window.matchMedia('(max-width: 1023px)').matches ||
        window.matchMedia('(pointer: coarse)').matches;
    if (!touchCapable || !narrowOrCoarse) {
        return;
    }

    body.dataset.zcPtrInit = '1';

    const pullHint = body.dataset.ptrPull || 'Pull down to refresh';
    const releaseHint = body.dataset.ptrRelease || 'Release to refresh';

    const bar = document.createElement('div');
    bar.className = 'zc-ptr';
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML =
        '<span class="zc-ptr__icon material-symbols-outlined" aria-hidden="true">refresh</span><span class="zc-ptr__text"></span>';
    body.appendChild(bar);

    const textEl = bar.querySelector('.zc-ptr__text');
    const iconEl = bar.querySelector('.zc-ptr__icon');

    let startY = null;
    let startX = null;
    let tracking = false;
    let lastDy = 0;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function resetVisual() {
        bar.classList.remove('zc-ptr--visible', 'zc-ptr--ready');
        bar.style.opacity = '';
        if (!reduceMotion) {
            bar.style.transition = 'opacity 0.18s ease';
        }
        bar.setAttribute('aria-hidden', 'true');
        if (textEl) {
            textEl.textContent = '';
        }
        if (iconEl) {
            iconEl.style.transform = '';
        }
    }

    function updateVisual(pull) {
        const p = Math.max(0, pull);
        const progress = Math.min(p / PULL_THRESHOLD, 1);
        bar.style.transition = 'none';
        bar.style.opacity = String(Math.min(1, p / 36));
        bar.classList.add('zc-ptr--visible');
        if (p >= PULL_THRESHOLD) {
            bar.classList.add('zc-ptr--ready');
            if (textEl) {
                textEl.textContent = releaseHint;
            }
        } else {
            bar.classList.remove('zc-ptr--ready');
            if (textEl) {
                textEl.textContent = pullHint;
            }
        }
        bar.setAttribute('aria-hidden', 'false');
        if (iconEl && !reduceMotion) {
            iconEl.style.transform = `rotate(${progress * 280}deg)`;
        }
    }

    body.addEventListener(
        'touchstart',
        (e) => {
            if (isExcludedTarget(e.target)) {
                return;
            }
            if (scrollTopY() > SCROLL_TOP_EPS) {
                return;
            }
            startY = e.touches[0].clientY;
            startX = e.touches[0].clientX;
            tracking = true;
            lastDy = 0;
        },
        { passive: true },
    );

    body.addEventListener(
        'touchmove',
        (e) => {
            if (!tracking || startY === null || startX === null) {
                return;
            }
            if (scrollTopY() > SCROLL_TOP_EPS) {
                tracking = false;
                resetVisual();
                return;
            }
            const y = e.touches[0].clientY;
            const x = e.touches[0].clientX;
            const dy = y - startY;
            const dx = x - startX;
            if (dx > 18 && Math.abs(dx) > Math.abs(dy) + 6) {
                tracking = false;
                resetVisual();
                return;
            }
            if (dy > 0 && scrollTopY() <= SCROLL_TOP_EPS) {
                lastDy = dy;
                updateVisual(dy);
            } else if (dy <= 0) {
                tracking = false;
                resetVisual();
            }
        },
        { passive: true },
    );

    body.addEventListener(
        'touchend',
        () => {
            if (!tracking) {
                return;
            }
            const shouldReload = lastDy >= PULL_THRESHOLD;
            tracking = false;
            startY = null;
            startX = null;
            lastDy = 0;
            if (shouldReload) {
                window.location.reload();
                return;
            }
            resetVisual();
        },
        { passive: true },
    );

    body.addEventListener(
        'touchcancel',
        () => {
            tracking = false;
            startY = null;
            startX = null;
            lastDy = 0;
            resetVisual();
        },
        { passive: true },
    );
}
