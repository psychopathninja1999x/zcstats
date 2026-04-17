# Chavacano language in ZCStats

## How the “Chavacano” option works

The dashboard does **not** download Chavacano text from the internet, a translation API, or a language pack. All copy for that option is **static PHP translation entries** in:

- `lang/cbk/zcstats.php`

Laravel’s `__('zcstats.*')` helper loads the active locale. When the user picks **CBK** in the header, the session stores `cbk` and `App\Http\Middleware\SetLocale` sets `App::setLocale('cbk')`, so those strings are used.

## Locale code `cbk`

- **`cbk`** is the [ISO 639-3](https://iso639-3.sil.org/code/cbk) code for **Chavacano** (the family of Spanish-lexified contact languages in the Philippines).
- The UI label **“Chavacano (Zamboanga)”** (`lang_cbk_title` in `lang/*/zcstats.php`) indicates the project is aiming at **Zamboanga City** usage, not a generic “Philippine Chavacano” standard.

## Where it is wired in code

| Piece | Role |
|--------|------|
| `App\Http\Middleware\SetLocale::SUPPORTED` | Allowed locales: `en`, `tl`, `cbk` |
| `GET /locale/{locale}` (`LocaleController`) | Saves chosen locale in session |
| Dashboard header links | `route('locale.switch', 'cbk')` |
| Translations | Only `lang/cbk/zcstats.php` for this app’s custom strings (no other `lang/cbk/*` files in the repo) |

## Where the wording itself came from

The phrases in `lang/cbk/zcstats.php` are **authored and maintained in the repository** (Spanish-influenced Chavacano-style wording suited to a civic/status dashboard). They are **not**:

- scraped from a single official government glossary in this codebase, or  
- produced by an automated pipeline checked into the app.

For **accuracy and community acceptance**, new or updated strings should be **reviewed by Zamboanga Chavacano speakers** (schools, media, or local language advocates) before treating them as normative.

## Adding or changing strings

1. Add or edit keys in `lang/en/zcstats.php` (source of key names).
2. Mirror the same keys in `lang/tl/zcstats.php` and `lang/cbk/zcstats.php`.
3. Keep `lang/cbk/zcstats.php` consistent with the tone and vocabulary already used there.

## Related files

- English: `lang/en/zcstats.php`
- Filipino: `lang/tl/zcstats.php`
- Chavacano (CBK): `lang/cbk/zcstats.php`
