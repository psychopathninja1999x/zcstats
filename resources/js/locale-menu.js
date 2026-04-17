function closeNotifyMenuIfOpen() {
    const dropdown = document.getElementById('zc-notify-dropdown');
    const btn = document.getElementById('zc-notify-menu-btn');
    if (dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
    }
}

export function initLocaleMenu() {
    const wrap = document.getElementById('zc-locale-wrap');
    const menuBtn = document.getElementById('zc-locale-menu-btn');
    const dropdown = document.getElementById('zc-locale-dropdown');

    if (!wrap || !menuBtn || !dropdown) {
        return;
    }

    function closeLocaleMenu() {
        dropdown.classList.add('hidden');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    function openLocaleMenu() {
        closeNotifyMenuIfOpen();
        dropdown.classList.remove('hidden');
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function toggleLocaleMenu() {
        if (dropdown.classList.contains('hidden')) {
            openLocaleMenu();
        } else {
            closeLocaleMenu();
        }
    }

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleLocaleMenu();
    });

    wrap.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    document.addEventListener('click', () => {
        closeLocaleMenu();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeLocaleMenu();
        }
    });
}
