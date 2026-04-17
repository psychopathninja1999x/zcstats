import './bootstrap';
import './dashboard-search';
import './header-clock';
import { initThemeToggle } from './theme-toggle';
import { initPullToRefresh } from './pull-to-refresh';
import { initEarthquakeMap } from './earthquake-map';
import { initTyphoonMap } from './typhoon-map';

initThemeToggle();
initPullToRefresh();

if (document.getElementById('zc-earthquake-map')) {
    requestAnimationFrame(() => initEarthquakeMap());
}
if (document.getElementById('zc-typhoon-map')) {
    requestAnimationFrame(() => initTyphoonMap());
}
