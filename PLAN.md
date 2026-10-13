# Lucky Catch Draw — Build Plan

## Stack found / chosen
- Fresh directory. Scaffolded **Laravel 12** (pinned explicitly — `laravel new --react` on this
  machine defaults to the not-yet-requested Laravel 13 starter kit with bleeding-edge tooling
  (laravel/chisel, wayfinder, pao) that isn't what was asked for). Used
  `composer create-project laravel/laravel:^12.0`.
- PHP 8.4 (Herd), MySQL 9.3 (Homebrew, `root`/`password`), Node 24, Composer 2.8.
- Databases created: `lucky_catch_draw` (dev), `lucky_catch_draw_test` (Pest/phpunit runs against
  real MySQL so generated columns, lockForUpdate, and unique-constraint race behavior match prod).
- Added manually (no official starter kit used, since admins are the only authenticated users and
  they log in through Filament — no public register/login React pages are needed):
  - `inertiajs/inertia-laravel` ^2.0, `@inertiajs/react`, `react`, `react-dom`, `typescript`
  - `filament/filament` ^4.0
  - `spatie/laravel-permission` ^6.0
  - `openspout/openspout` ^4.0
  - `canvas-confetti`, `motion` (framer-motion successor)
  - `pestphp/pest` ^3.8 + `pest-plugin-laravel` (phpunit bumped to ^11.5.56 to satisfy pest)
  - Tailwind v4 (already in skeleton via `@tailwindcss/vite`)
- Queue driver: `database`. Session driver: `database`.
- Git repo initialized locally; commit after each stage.

## Schema (adapted from spec, MySQL-specific features kept as specified)
- `users` + `active` boolean. Roles via spatie/permission: `super_admin`, `campaign_manager`,
  `viewer`.
- `campaigns`, `imports`, `import_rows`, `participants`, `draws` (with stored generated column
  `active_sequence` + unique index), `audit_logs` (insert-only model) — exactly as specified in
  the brief.
- All enums implemented as PHP backed enums (`CampaignStatus`, `ImportStatus`, `ImportRowStatus`,
  `DrawStatus`) cast on the Eloquent models.

## Routes
Admin (Filament, `/admin/*`): resources for Campaigns, Draws (winner history), AuditLogs, Users;
custom Import Wizard page; relation managers for Participants/Imports/Draws/Audit on Campaign view.

Public-to-authenticated (web guard, same session as Filament):
- `GET  /draw/{campaign}` — Inertia `Draw/Show`
- `POST /draw/{campaign}` — JSON, runs `DrawService::draw()`, rate-limited 10/min/user
- `GET  /draw/{campaign}/latest` — JSON, latest valid draw (reload recovery)
- `GET  /draws/{draw}/print` — Inertia `Draws/Print`
- `GET  /exports/...` — authorized download routes for Excel exports (participants, winners,
  error reports), never raw public file paths.

## Stage checklist
- [x] Stage 0 — inspect, scaffold, plan
- [ ] Stage 1 — migrations, models, enums, factories, policies, Auditor service
- [ ] Stage 2 — Filament panel branding, resources, import wizard, dashboard widgets
- [ ] Stage 3 — DrawService (server-authoritative selection)
- [ ] Stage 4 — Inertia/React Draw Screen + print report
- [ ] Stage 5 — Excel export safety, auth hardening, exception handling
- [ ] Stage 6 — Pest test suite
- [ ] Stage 7 — seeders, README, full end-to-end walkthrough

Commits land after each stage completes and tests for that stage pass.
