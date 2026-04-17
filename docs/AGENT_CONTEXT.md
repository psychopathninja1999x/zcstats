# ZCStats — notes for future agents

This file is for **Cursor / AI agents** (and humans) picking up the project later. It summarizes architecture choices, localization, UI behavior, and where to change things.

## What this app is

**ZCStats** (“Everything, Zamboanga”) is a Laravel **civic dashboard**: live-ish widgets for Zamboanga City (weather, water, power, fuel, DA/DTI prices, emergency/hospital info). The main UI is a single Blade view: `resources/views/dashboard/index.blade.php`, layout `resources/views/layouts/civic.blade.php`.

## Stack

- **Laravel 12**, **PHP 8.2+**
- **Vite** + **Tailwind CSS v4** (`resources/css/app.css` uses `@import 'tailwindcss'`, `@theme { … }`, `@layer components`)
- **Front JS** (`resources/js/app.js`): `dashboard-search.js`, `header-clock.js`, `theme-toggle.js` (plus `./bootstrap`)

**Deploy:** `public/build` is **gitignored** — run `npm ci && npm run build` (or equivalent) where the app is deployed.

---

## Localization

All user-facing dashboard strings live in **`lang/{locale}/zcstats.php`** and are referenced as **`__('zcstats.*')`**.

### Supported locales (session + middleware)

Allowed values are defined in **`App\Http\Middleware\SetLocale::SUPPORTED`**:

| Code | Meaning | Translation file |
|------|---------|-------------------|
| `en` | English | `lang/en/zcstats.php` |
| `tl` | Filipino (Tagalog) | `lang/tl/zcstats.php` |
| `cbk` | Chavacano (Zamboanga) | `lang/cbk/zcstats.php` |
| `gly` | Gaylingo / swardspeak (playful PH Taglish register) | `lang/gly/zcstats.php` |

**Flow:** `GET /locale/{locale}` → **`LocaleController`** → session key `locale` → **`SetLocale`** middleware sets `App::setLocale()` on each request.

**HTML `lang` attribute** (`civic.blade.php`): `tl` → `fil`, `gly` → `en-PH`, others → locale string with `_` replaced by `-`.

### Adding or changing copy

1. Add keys to **`lang/en/zcstats.php`** first (canonical key set).
2. Mirror every key in **`lang/tl/zcstats.php`**, **`lang/cbk/zcstats.php`**, and **`lang/gly/zcstats.php`**.
3. For **Chavacano** tone and conventions, see **`docs/CHAVACANO_LOCALIZATION.md`**.
4. **Gaylingo (`gly`)** is intentional playful Taglish/swardspeak for the same keys; keep agency names, numbers, and safety copy accurate.

### Language switcher UI

Dashboard header: links to `route('locale.switch', …)` for `en`, `tl`, `cbk`, `gly`. Labels: `lang_en`, `lang_tl`, `lang_cbk`, `lang_gly`; tooltips: `lang_cbk_title`, `lang_gly_title`.

### Section search (dock)

**`App\Http\Controllers\DashboardController::searchIndex()`** builds JSON for in-page jump search. It merges translated phrases (`__()` under current locale) with fixed English (and some Filipino) aliases. The search input is in the **bottom dock**, not the header.

---

## Theming (light / dark)

- **Class `dark` on `<html>`** toggles dark palette.
- **Tailwind:** `@custom-variant dark (&:where(.dark, .dark *));` in `app.css`.
- **Tokens:** `html.dark { --color-* … }` overrides the same variables as `@theme`.
- **Persistence:** `localStorage` key **`zc-theme`**: `dark` | `light`. If unset, **`prefers-color-scheme`** is used.
- **FOUC:** inline script at top of **`civic.blade.php` `<head>`** sets class + `colorScheme` before paint.
- **Toggle:** `#zc-theme-toggle` in dashboard header; logic in **`resources/js/theme-toggle.js`**.

---

## Dashboard layout quirks

- **Sticky header:** logo, theme toggle, clock, language pills — **no search** in header.
- **Fixed bottom dock:** top row = **page search** (`#zc-dashboard-search`, `#zc-search-feedback` opens **above** the bar); second row = section tabs + report button.
- **Body bottom padding** in `civic.blade.php` (`pb-40 md:pb-44`) accounts for dock height.
- **Weather hero:** condition-based CSS animations (`weather_effect` from **`OpenWeatherService`**) in `.zc-weather-fx`; respects `prefers-reduced-motion`.

---

## Data sources (high level)

| Area | Typical service / config |
|------|---------------------------|
| Weather + AQI | `OpenWeatherService`, `config/services.php` `openweather` |
| Water | `ZcwdWaterService`, `zcwd` |
| Power | `ZamcelcoPowerService`, `zamcelco` — **High Voltage** rows are filtered out in the service for the dashboard list |
| Fuel | `GasmotoFuelService`, `gasmoto` |
| DA PDFs | `DaPriceMonitoringService` |
| DTI BNPC | `DtiBnpcSrpService` (+ import command / `resources/data` as applicable) |

---

## Assets

- App / favicon: `public/images/zcstatslogo.png` (referenced from layout; may be absent in a fresh clone).
- Partner logos: **`public/images/logo/`** (e.g. `zamcelco.png`, `zcwd.png`, `da.png`, `dti.png`, `gasmoto.png`).
- OpenWeather attribution image: **`public/images/sources/openweather.png`**.

---

## Conventions for agents

- Prefer **small, task-scoped diffs**; match existing Blade/Tailwind/PHP style.
- After changing **Blade** or **translations**, `php artisan view:cache` is a quick compile check.
- After changing **CSS/JS**, run **`npm run build`** locally.
- **`test_write.txt`** in repo root (if present) is junk — do not commit.

If this doc drifts from code, trust **`SetLocale::SUPPORTED`**, **`DashboardController`**, and **`lang/*/zcstats.php`** as source of truth.
