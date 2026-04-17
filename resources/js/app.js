import './bootstrap';
import './dashboard-search';
import './header-clock';
import { initThemeToggle } from './theme-toggle';
import { initPullToRefresh } from './pull-to-refresh';
import { initEarthquakeMap } from './earthquake-map';
import { initTyphoonMap } from './typhoon-map';
import { initDashboardNotifications } from './dashboard-notifications';

initThemeToggle();
initPullToRefresh();
initDashboardNotifications();

if (document.getElementById('zc-earthquake-map')) {
    requestAnimationFrame(() => initEarthquakeMap());
}
if (document.getElementById('zc-typhoon-map')) {
    requestAnimationFrame(() => initTyphoonMap());
}

(function registerServiceWorker() {
    const meta = document.querySelector('meta[name="sw-url"]');
    const url = meta && meta.getAttribute('content');
    if (!url || !('serviceWorker' in navigator)) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(url).catch(() => {});
    });
})();
