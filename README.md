# Hopenalytics

A dashboard for monitoring and analyzing church social media growth — subscriber, follower, view, like, and post growth on YouTube, Instagram, TikTok, Facebook, and X, for church accounts, personal ministry accounts, standalone institution accounts (schools, publishing houses, etc.), and the Union/Conference organizational levels themselves, all in one place. Access is scoped by organizational level (Union → Conference → Church, plus standalone Institutions), so each admin only sees and manages their own region.

Built with Laravel 12, Vite, and Tailwind CSS 4.

## Features

- **Automated monitoring** — data is fetched automatically every week on a configurable schedule (via Apify scrapers, or YouTube's official API), or manually at any time (single account or a bulk "Refresh All" batch), with a live progress bar and the ability to cancel an in-progress refresh. A newly-added account doesn't fetch immediately — it waits for the next scheduled run or a manual refresh, so typos get caught before they burn an API call. Facebook accounts also sample their 10 most recent posts (count/likes/shares) alongside the follower count.
- **Hashtag tracking** — admins define any number of hashtags to watch across Instagram, TikTok, and YouTube; matched posts are fetched on the same weekly schedule, or on demand via a manual "Scan Ulang Sekarang" rescan (e.g. to monitor a live hashtag launch), and summarized on the dashboard, with a "Perbandingan Hastag" comparison tab (its own Uni/Daerah region filter, export, and a growth chart that switches from per-date to a gap-filled per-hour view for a chosen monitoring window) alongside the platform/metric comparisons on every Analitik & Grafik scope.
- **Platform visibility toggle** — a superadmin-only Settings card to turn any platform on/off app-wide. A disabled platform disappears from every chart, list, form, and export, and its weekly fetch pauses — no data is deleted, so re-enabling it restores everything instantly.
- **Analytics & charts** — weekly growth charts (with per-point value labels), filters per church/platform/category, total reach summaries, and a week-over-week growth score trend per account, across separate Gereja / Institusi / Personal / Organisasi (Uni/Daerah) tabs. Each social account's card also shows a red/amber/green performance border based on recent posting activity.
- **Growth score** — a composite, percentage-based score (not raw follower counts) so small and large accounts are compared fairly. See the [/about](resources/views/about.blade.php) page in-app for the full formula.
- **Performance comparison** — rank churches, institutions, personal accounts, or Uni/Daerah's own organizational accounts by platform, by metric (followers, views, likes, posts), by composite growth score, or compare platforms against each other. Every church, institution, personal, and Uni/Daerah account has its own read-only history page reachable from any of these views; a superadmin can additionally edit or permanently delete individual historical data points from a dedicated management page per account.
- **Map & presentation mode** — an interactive map of church, personal, and institution locations (with per-type and combined layers), and a fullscreen presentation view for events/meetings (light/dark, auto-refreshing, with a live leaderboard, and each entity's Union/Conference shown beneath its name).
- **Account directory** — a searchable, filterable list of every social account (church, institution, personal, and Uni/Daerah), with a clear marker for accounts flagged "manual" (auto-fetch off), and its own PDF/Word/Excel export. Adding an account warns on likely duplicate handles/entity names before saving.
- **Bulk import** — create or update many Gereja, Personal, or Institusi accounts at once from a spreadsheet template: name, city/country, Uni/Daerah, and — for Gereja/Institusi — their social media accounts too, all in one file, scoped to the uploader's own admin region. Any newly-filled city/country is geocoded automatically in the background afterward.
- **Manual data entry** — accounts that can't be scraped automatically (e.g. personal Facebook profiles) can have their weekly stats entered by hand, at any time, alongside the automatic fetch option.
- **Account health** — dedicated pages listing every account that failed its last auto-fetch attempt ("Akun Bermasalah") and every account currently set to automatic with its last-fetched status ("Akun Otomatis"), both reachable from Kelola Akun.
- **Data export** — download PDF, Word, or Excel reports for a church, an institution, a personal account, a Uni/Daerah (organisasi) account, a single social account's history, the account directory, tracked hashtags, or any comparison/leaderboard view.
- **Audit Log** (`/admin/audit-log`, superadmin-only) — two tabs: an action log (who created/edited/deleted what) and a login history log (who logged in, from where, and for how long).
- **Bilingual** — the whole app (including the public login/register pages) is available in Indonesian and English, switchable from a language icon on every page.
- **Queue monitoring** (`/queue`) — track pending jobs, active/completed refresh batches, and failed jobs (with plain-language error messages); cancel a running batch or clear history individually or in bulk.

### Accounts & access control

- **Self-registration** — anyone can sign up; email is verified with a one-time code before the account is usable. A short "Lengkapi Profil" step lets a new member report their Uni/Daerah/Gereja, or skip it and fill it in later from Profil Saya. A member with no admin role sees Analitik & Grafik and the main dashboard scoped no wider than that self-reported Uni/Daerah — with a notice pointing them to Profil Saya if they haven't reported one at all.
- **Role-based hierarchy** — Uni → Daerah (Conference) → Gereja (Church) form a strict delegation chain, plus standalone Institusi outside that chain. Each level has an **Admin** (manage) and read-only **Pimpinan** role; a Superadmin/Admin Nasional can also bootstrap any level directly. Admins can only promote members into the level directly below their own, scoped to their own region.
- **Kelola Pengguna** (`/admin/users`) — assign/revoke roles, release a member's reported Uni/Daerah/Gereja without touching their role (e.g. to clean up a bad self-report), resend a pending OTP, deactivate or permanently delete an account.
- **Kelola Akun** (`/admin/organization`) — one page, five tabs (Uni / Daerah / Gereja / Institusi / Personal), to manage the org units themselves (create, edit, deactivate, or delete — delete is blocked while a unit still has dependents) and tracked personal accounts (people whose social media is monitored), independently of the account directory, which only handles adding social media handles. Which tabs a visitor sees depends on their role/region — e.g. admin_gereja only ever sees the Personal tab.
- **"X Saya" shortcuts** — Gereja Saya / Uni Saya / Daerah Saya / Institusi Saya in the account menu jump straight to managing your own org unit's accounts, for the levels whose own tab is otherwise hidden from them in Kelola Akun.
- **Account security** — rate-limited login/OTP/password-reset (brute-force protection), no user-enumeration on "forgot password", and a current-password check before changing your password or email.

## Tech stack

- PHP 8.2+ / Laravel 12
- MySQL (or any Laravel-supported database)
- Vite + Tailwind CSS 4
- Laravel queues (`database` driver) for background data-refresh jobs
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf), [phpoffice/phpword](https://github.com/PHPOffice/PHPWord), [phpoffice/phpspreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) for PDF/Word/Excel export
- [Apify](https://apify.com) actors for scraping Instagram/TikTok/Facebook/X public stats; YouTube uses its own official Data API v3 instead (free, but needs its own API key)

## Requirements

- PHP >= 8.2 with the usual Laravel extensions
- Composer
- Node.js + npm
- A database (MySQL/MariaDB recommended; SQLite works for local dev)
- A mail transport for OTP emails (registration, password reset, email change) — defaults to logging to `storage/logs/laravel.log` in local dev if left unconfigured
- An [Apify](https://apify.com) account/API token and a YouTube Data API v3 key for automated fetching (not required if you only use manual data entry) — either can be set via `.env` or, once the app is running, from the Settings page instead

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at minimum:

- `APP_NAME` — defaults to `Laravel`; set to `Hopenalytics`
- `DB_*` — your database connection
- `APP_TIMEZONE` — defaults to `Asia/Jakarta`; change if your churches are elsewhere
- `MAIL_*` — required for members to actually receive OTP codes; leave as the default `log` mailer to read codes from `storage/logs/laravel.log` during local dev instead
- `APIFY_TOKEN` / `YOUTUBE_API_KEY` — required for automated fetching; leave blank to rely on manual data entry only. Both can also be set later from the in-app Settings page (superadmin/admin nasional) instead of `.env`, without a redeploy

Then:

```bash
php artisan migrate
npm install
npm run build
```

Bootstrap the first Superadmin account (bypasses OTP verification, since no one else can promote them yet):

```bash
php artisan make:superadmin
```

Every other admin/pimpinan role is assigned afterward through Kelola Pengguna, once a member has self-registered.

## Running locally

Start everything (web server, queue worker, log tailing, and Vite) at once:

```bash
composer run dev
```

Or run each piece separately:

```bash
php artisan serve                                # web server — http://127.0.0.1:8000
php artisan queue:listen --tries=1 --timeout=0   # required — processes data-refresh jobs
npm run dev                                       # Vite dev server (asset hot reload)
```

The queue worker is **required** even in local development — the "Fetch Latest Data" button and the weekly schedule both dispatch background jobs that only run while a worker is listening. Without it, refreshes will appear to start but never complete (check `/queue` to see pending/stuck jobs).

## Scheduled tasks

Weekly auto-fetch is configured in `routes/console.php` and controlled by the day/time set on the [Settings](resources/views/settings/edit.blade.php) page. For production, make sure Laravel's scheduler is running:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Testing

```bash
composer run test
```
