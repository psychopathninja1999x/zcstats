const STORAGE_KEY = 'zc-theme';

function applyHtmlTheme(isDark) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark);
    root.style.colorScheme = isDark ? 'dark' : 'light';
}

export function initThemeToggle() {
    const btn = document.getElementById('zc-theme-toggle');
    if (!btn) {
        return;
    }

    const labelLight = btn.dataset.labelLight || 'Switch to light mode';
    const labelDark = btn.dataset.labelDark || 'Switch to dark mode';

    const sync = () => {
        const dark = document.documentElement.classList.contains('dark');
        btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        btn.setAttribute('aria-label', dark ? labelLight : labelDark);
        const icon = btn.querySelector('.zc-theme-icon');
        if (icon) {
            icon.textContent = dark ? 'light_mode' : 'dark_mode';
        }
    };

    btn.addEventListener('click', () => {
        const nextDark = !document.documentElement.classList.contains('dark');
        applyHtmlTheme(nextDark);
        try {
            localStorage.setItem(STORAGE_KEY, nextDark ? 'dark' : 'light');
        } catch (_) {
            /* ignore */
        }
        sync();
    });

    try {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (ev) => {
            if (localStorage.getItem(STORAGE_KEY)) {
                return;
            }
            applyHtmlTheme(ev.matches);
            sync();
        });
    } catch (_) {
        /* ignore */
    }

    sync();
}
