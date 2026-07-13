# Asset Manager

A self-hosted personal finance and investment tracking tool built with vanilla PHP — no framework, no bloat. Tracks bank/cash/mobile-banking accounts and investments (FDR, land lease, pond lease) with full transaction history and reporting.

## Features

- **Role-based auth** — Admin and Viewer roles with session-based access control
- **Account management** — CRUD for bank, cash, and mobile banking accounts with deposit transactions
- **Investment lifecycle** — add → renew (profit credited, principal retained) → withdraw (principal returned)
- **Auto transaction ledger** — every money movement is logged automatically for a full audit trail
- **Dashboard** — at-a-glance stats with a Chart.js doughnut breakdown by category
- **Reports** — monthly trend charts and an investment maturity calendar
- **Transactions** — paginated, filterable history
- **User management** — admin-only user administration
- **AI insights** — on-demand financial suggestions generated from account/investment/transaction summaries via the Claude API, saved per user

## Tech Stack

- PHP (PDO, prepared statements)
- MySQL
- Bootstrap 5
- Chart.js
- Vanilla JS (AJAX, JSON endpoints under `/ajax`)
- [Anthropic PHP SDK](https://github.com/anthropics/anthropic-sdk-php) for AI insights (optional feature)

## Architecture

Pages render server-side PHP and talk to `/ajax/*.php` endpoints for mutations. Each AJAX endpoint accepts a POST JSON body and returns a consistent `{ success, message, data }` envelope. Data needed on page load is embedded as `application/json` script tags rather than re-fetched, keeping the app fast with zero client-side framework overhead.

```
├── ajax/            # JSON API endpoints (POST in, {success,message,data} out)
├── assets/          # CSS/JS
├── includes/        # shared layout partials
├── config.php       # DB + site config (reads from env, falls back to local defaults)
├── db.php           # PDO connection singleton
├── functions.php    # shared helpers
├── install.php      # one-time DB installer (creates tables + first admin)
├── auth.php / login.php / logout.php
├── dashboard.php / accounts.php / categories.php / investments.php
├── transactions.php / reports.php / maintenance.php / users.php
```

## Setup

1. Create a MySQL database.
2. Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` as environment variables (or edit the defaults in `config.php`).
3. Visit `install.php` once to create the schema and your first admin account, then delete or rename it.
4. Optional — for AI insights: run `composer install`, set the `ANTHROPIC_API_KEY` environment variable. The feature no-ops with a clear message if either is missing.

## Screenshots

_Dashboard, reports, and investment views — add screenshots here._
