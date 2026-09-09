# This project is intended to function with PHP 8.2 and PostgreSQL 15

Personally, I prefer phpPgAdmin over any other PostgreSQL UI tools due to its similarity to phpMyAdmin, which brings a sense of familiarity. Unfortunately, it appears that the developers behind this great tool have abandoned the project. Moreover, I am reluctant to run PHP 7 on my PC solely to support this tool. Therefore, my objective is to ensure full compatibility with PHP 8.2. I'm open to your PRs, so lets make this phpPgAdmin great again :)

## to run using php embedded HTTP server:
`php -S localhost:8800`

---

## Fork Enhancements — PHP 8 + PostgreSQL 16

This fork extends the PHP 8.2 compatibility work with full PostgreSQL 16 support, focusing on declarative partition visibility and additional PHP 8 modernization.

### PostgreSQL 16 Partition Support

phpPgAdmin previously only showed child partition tables (e.g. `orders_2024_01`) and hid partitioned parent tables entirely. This fork makes all partition tables visible and navigable:

- **Flat table list** — parent and child partition tables appear together; child partitions are prefixed with `↳` in the table list
- **Partition detail panel** — partitioned parent table pages show partition method (RANGE/LIST/HASH), key, and a linked list of child partitions with bounds and row estimates
- **Parent back-link** — child partition table pages show a link back to the parent table
- **"not analyzed" row counts** — PostgreSQL 14+ uses `-1` as a sentinel for tables that have never been analyzed; displays as `not analyzed` instead of a confusing `-1`

### Legacy Table Inheritance Support

For databases using pre-declarative trigger-based inheritance (`INHERITS`):

- **Table list** — legacy inheritance children are prefixed with `↪` (distinct from `↳` for native partitions)
- **Inherited Tables panel** — parent table properties pages show a linked list of child tables
- **Inherits from back-link** — child table properties pages show links back to all parent tables (supports multiple-parent inheritance)

### PHP 8 Compatibility Fixes

- Removed all `magic_quotes_gpc`/`magic_quotes_runtime`/`magic_quotes_sybase` dead code (removed in PHP 5.4/8.0)
- Removed `htmlspecialchars_decode` polyfill (built-in since PHP 5.1)
- Fixed logic error in foreign key dimension parsing (`strlen($s - 1)` → `strlen($s) - 1`)
- Replaced deprecated `var` property declarations with explicit `public` visibility (76 occurrences across 25 class files)
- Replaced `sizeof()` alias with `count()` (27 occurrences)
- Fixed PHP 8 by-reference warning in `insertRow()` — replaced `array_map('fieldClean', $fields)` with `fieldArrayClean()`
- Updated minimum PHP version requirement to 8.0
- Added PG14/15/16 routing in `Connection.php` and new `Postgres14.php`/`Postgres15.php` subclasses
- Replaced deprecated `pg_escape_string()` calls (no connection argument) with `pg_escape_string($conn, ...)`

### UI Modernization

- **New "Modern" theme** (`themes/modern/`) — a lighter, Postgres-brand-blue palette replacing the classic tan/brown look. System typography (native OS font stack), compact data tables, and CSS custom properties for one-line palette tweaks. Set as the default in `conf/config.inc.php-dist`; the classic `default`, `cappuccino`, `bootstrap`, and `gotar` themes remain available.
- **Frame-navigation loading indicator** — a small CSS spinner appears in the browser (nav) frame whenever a link click triggers frame navigation, and hides when the detail frame's `onload` fires (with a 15s safety timeout). No image assets, no jQuery — pure CSS keyframe. Implemented as a shim script in `index.php` passed through `printHeader()`; no changes to `browser.php` or per-page templates.

### Versioning

This fork uses SemVer build-metadata (`+dj.N`) so it never collides with an upstream release number. Current version: **`7.13.0+dj.1`**. The `+dj.N` suffix increments per fork iteration and resets to `+dj.1` on rebase onto a newer upstream. This version string surfaces in the UI header, browser title, and in PostgreSQL's `pg_stat_activity.application_name` (via `PGAPPNAME`).