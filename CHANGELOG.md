# Changelog

## 1.0.1 - 2026-06-29

- switched edition gating from environment variables to native Craft plugin editions
- refreshed README, plugin store copy, and commercial license text for paid release readiness
- improved test coverage for plugin editions and template request normalization
- updated Composer test script to use the project-local PHPUnit binary
- corrected package identity URLs and removed the deprecated Composer version field for Plugin Store release
- adopted the official Craft commercial license
- made the index/constraint migration database-agnostic so it installs on both MySQL/MariaDB and PostgreSQL
- declared phpoffice/phpspreadsheet explicitly for the Pro XLSX feature
- removed a stray duplicate root migrations directory
- documented data handling and retention, expanded the manual QA checklist, and added CI for PHP 8.2 to 8.4

## 1.0.0 - 2026-03-16

- initial commercial-ready V1 scaffold for Data Export Builder
- Craft CMS 5 plugin bootstrap, migrations, records, models, services, controllers, queue job, and CP UI
- reusable export templates with CSV and JSON support
- field discovery for native attributes, custom fields, relations, and practical Matrix paths
- queued export runs with download history and permission gates
- commercial README, plugin store copy draft, pricing notes, and manual QA checklist
