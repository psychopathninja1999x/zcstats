# Notifications & Web Push (ZCStats)

This document summarizes how **in-browser alerts**, **PWA shell**, and **VAPID Web Push** work in this Laravel app so a new agent or developer can work on them without rediscovering the stack.

## Concepts

| Layer | What it does | When it fires |
|--------|----------------|----------------|
| **DOM / `Notification` API** | `new Notification(...)` from open JS | Tab often needs to be open or foreground; see HTTPS below. |
| **Service Worker (`public/sw.js`)** | Handles **push** events → `showNotification` | When the server sends a Web Push to the browser’s subscription endpoint. |
| **Scheduled Laravel commands** | Compute digest / prayer window → `minishlink/web-push` | Only if **cron** runs `php artisan schedule:run` every minute. |

Browser notifications (first column) and Web Push (second/third) are **related but not the same**. Local timers/polling can run in the page; **background** delivery when the site is closed generally needs Web Push + scheduler.

## HTTPS & secure context

- **`Notification.requestPermission()`** and **Push** require a **secure context** in practice: **`https://`**, or **`http://localhost`** / **`127.0.0.1`**.
- Plain **`http://192.168.x.x`** (LAN IP) is usually **not** a secure context → permission/push will fail. Use TLS (e.g. reverse proxy, ngrok, mkcert) for real devices.

The UI shows the bell whenever `window.Notification` exists. On insecure HTTP (non-localhost), controls are **disabled** and copy from `notify_requires_https` explains the limitation (`data-notify-requires-https` on `#zc-notify-panel`).

## User-facing UI

- **Header**: bell button → **`<dialog id="zc-notify-dialog">`** opened with **`showModal()`** (native modal **top layer** — sits above the floating dock without z-index hacks).
- **All viewports**: centered **popup** card; dimmed **`::backdrop`** (tap to close where supported), **close** control in the corner, **Escape** closes. Page behind the modal is **inert** while open.
- **Language switcher**: separate collapsible (`#zc-locale-wrap`, `resources/js/locale-menu.js`); opening one menu closes the other where wired.

### Key Blade — `resources/views/dashboard/index.blade.php`: `#zc-notify-wrap`, `#zc-notify-menu-btn`, `#zc-notify-dialog`, `#zc-notify-close`, `#zc-notify-panel` (data attributes for URLs, locale, Web Push flags).

### Key JS

- `resources/js/dashboard-notifications.js`: `showModal` / `close`, backdrop + close button, prayer timers + digest polling, Web Push subscribe/patch/delete, HTTPS gate.
- `resources/js/app.js`: `initLocaleMenu()`, `initDashboardNotifications()`, service worker registration (reads `<meta name="sw-url">`).

## PWA shell

- `public/manifest.webmanifest` — linked from `resources/views/layouts/civic.blade.php`.
- `public/sw.js` — minimal install/lifecycle + **`push`** + **`notificationclick`** (payload JSON: `title`, `body`, `url`, `tag`).
- Layout also sets `theme-color`, `sw-url`, `manifest` link.

## Server: VAPID & config

- `config/webpush.php` — **`webpush.enabled`** is true only if `WEBPUSH_ENABLED` and **both** VAPID keys are non-empty in `.env`.
- `.env` (not in git): `WEBPUSH_VAPID_PUBLIC_KEY`, `WEBPUSH_VAPID_PRIVATE_KEY`, `WEBPUSH_VAPID_SUBJECT` (must be `mailto:...` or `https:...`), `WEBPUSH_ENABLED`.
- Generate keys: `php artisan zcstats:webpush-vapid` (may fail on some Windows PHP/OpenSSL builds) or `npx --yes web-push generate-vapid-keys`.
- **Outbound TLS to FCM** (`fcm.googleapis.com`): if `zcstats:push-test` reports **`cURL error 60: SSL certificate problem: unable to get local issuer certificate`**, PHP/cURL cannot verify Google’s certificate. Fix the **CA bundle** on the server (`php.ini` `curl.cainfo` / `openssl.cafile`), or set **`WEBPUSH_CURL_CAINFO`** in `.env` to a readable PEM (e.g. download [cacert.pem](https://curl.se/ca/cacert.pem) into `storage/app/cacert.pem`, or on many Linux images use `/etc/ssl/certs/ca-certificates.crt`). Laravel Cloud: ensure the runtime image has current CA certs or use that env path after uploading a bundle.

## Database

- Migration: `database/migrations/2026_04_17_000001_create_push_subscriptions_table.php`.
- Model: `App\Models\PushSubscription` — `endpoint` (unique), keys, `wants_prayer`, `wants_live`, `locale`.

## HTTP routes (web middleware, CSRF)

Defined in `routes/web.php`:

- `GET /push/vapid-public-key` — JSON public key when enabled.
- `POST /push/subscribe` — upsert subscription + flags.
- `PATCH /push/subscribe` — update flags.
- `DELETE /push/subscribe` — body: `{ "endpoint": "..." }`.

Controller: `App\Http\Controllers\PushSubscriptionController`.  
Validation: `App\Http\Requests\PushSubscriptionRequest`.

## Live digest (change detection)

- `GET /live-digest.json` — SHA-256 of cached dashboard fingerprints.
- Logic: `App\Services\LiveDigestService` (also used by `DashboardController::liveDigest()`).

## Sending pushes from the server

- **Library**: `minishlink/web-push` (`App\Services\ZcWebPushService::sendToMany`).
- **Scheduled** (see `bootstrap/app.php` → `withSchedule`):
  - `zcstats:push-live-digest` — every **2 minutes**; if digest ≠ last cached value, notify subscribers with `wants_live`, then update cache key `zcstats_push_last_live_digest`.
  - `zcstats:push-prayer-times` — every **minute**; within ~±45s / +90s of each salāh (Fajr, Dhuhr, Asr, Maghrib, Isha; not Sunrise), notify `wants_prayer` once per slot (cache dedupe).

**Production**: cron must run `* * * * * cd /path-to-app && php artisan schedule:run` (or equivalent).

**Manual test**: `php artisan zcstats:push-test` (optional `--message=`). Requires rows in `push_subscriptions` and VAPID configured.

**VAPID sanity check**: `php artisan zcstats:webpush-verify` — confirms public/private keys are a real pair and a sample JWT verifies for `https://web.push.apple.com`.

### Apple / Safari: `403` + `BadJwtToken`

Apple’s endpoint (`web.push.apple.com`) returns this when it rejects the **VAPID JWT**. Typical fixes:

1. **Key pair mismatch** — public key in `.env` does not match the private key (copy/paste or truncation in Laravel Cloud). Run `zcstats:webpush-verify`; if it fails, regenerate keys and redeploy.
2. **Stale subscription** — the device subscribed with an **old** public key. After rotating VAPID keys, delete affected rows in `push_subscriptions` and have the user subscribe again from the bell UI.
3. **`WEBPUSH_VAPID_SUBJECT`** — use `mailto:…` or `https://…` (RFC 8292). If problems persist with `mailto:`, set `WEBPUSH_VAPID_SUBJECT=https://your-live-host` (no path), matching the host users open (same as `APP_URL`).
4. **Server time** — sync NTP so JWT `exp` is valid.

## iOS / Safari notes

Web Push and notification behavior on iOS change by version; installing the PWA to the home screen is often required for a fuller experience. Do not assume parity with Chrome/Android.

## Translations

Strings live under `lang/*/zcstats.php` with prefix `notify_*`, `notify_push_test_*`, `notify_close_overlay`, `notify_requires_https`, etc.

## Operational checklist for a new environment

1. Copy `.env` / set `APP_URL` (used in push payload links).
2. `composer install`, `npm run build`, `php artisan migrate`.
3. Set `WEBPUSH_*` if server push is desired.
4. Enable HTTPS.
5. Configure **scheduler** (cron) for automated pushes.
6. After changing `sw.js`, users may need a refresh or SW update cycle.

---

*Last aligned with the codebase structure described above; verify file paths if the project is refactored.*
