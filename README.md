# Churchnalytics

A dashboard for monitoring and analyzing church social media growth — subscriber, follower, view, like, and post growth on YouTube, Instagram, TikTok, and Facebook, for both church accounts and personal ministry accounts, all in one place.

Built with Laravel 12, Vite, and Tailwind CSS 4.

## Features

- **Automated monitoring** — data is fetched automatically every week on a configurable schedule (via Apify scrapers), or manually at any time, with a live progress bar and the ability to cancel an in-progress refresh.
- **Analytics & charts** — weekly growth charts (with per-point value labels), filters per church/platform, total reach summaries, and a week-over-week growth score trend per church/person.
- **Growth score** — a composite, percentage-based score (not raw follower counts) so small and large accounts are compared fairly. See the [/about](resources/views/about.blade.php) page in-app for the full formula.
- **Performance comparison** — rank churches by platform, by metric (followers, views, likes, posts), by composite growth score, or compare platforms against each other.
- **Map & presentation mode** — an interactive map of church locations, and a fullscreen presentation view for events/meetings.
- **Account directory** — a searchable, filterable list of every social account, with a clear marker for accounts flagged "manual" (auto-fetch off).
- **Manual data entry** — accounts that can't be scraped automatically (e.g. personal Facebook profiles) can have their weekly stats entered by hand.
- **Data export** — download PDF, Word, or Excel reports for a church, a personal account, a single social account's history, or any comparison/leaderboard view.
- **Queue monitoring** (`/queue`) — track pending jobs, active/completed refresh batches, and failed jobs; cancel a running batch or clear history individually or in bulk.

## Tech stack

- PHP 8.2+ / Laravel 12
- MySQL (or any Laravel-supported database)
- Vite + Tailwind CSS 4
- Laravel queues (`database` driver) for background data-refresh jobs
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf), [phpoffice/phpword](https://github.com/PHPOffice/PHPWord), [phpoffice/phpspreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) for PDF/Word/Excel export
- [Apify](https://apify.com) actors for scraping YouTube/Instagram/TikTok/Facebook public stats

## Requirements

- PHP >= 8.2 with the usual Laravel extensions
- Composer
- Node.js + npm
- A database (MySQL/MariaDB recommended; SQLite works for local dev)
- An [Apify](https://apify.com) account/API token for automated fetching (not required if you only use manual data entry)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at minimum:

- `DB_*` — your database connection
- `APP_TIMEZONE` — defaults to `Asia/Jakarta`; change if your churches are elsewhere
- `APIFY_TOKEN` — required for automated fetching; leave blank to rely on manual data entry only

Then:

```bash
php artisan migrate
npm install
npm run build
```

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
