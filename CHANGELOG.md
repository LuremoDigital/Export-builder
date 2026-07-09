# Changelog

## Unreleased

## 1.4.3 — 2026-07-09

### Added

- Per-run template snapshots and stable delivery keys for queued exports and retries
- Webhook signatures, idempotency headers, and public-destination validation

### Changed

- Plugin display name unified to "Export Builder" across the control panel, queue jobs, and documentation
- Repository links updated to the renamed GitHub repository
- Scheduler claiming is safer when multiple scheduler processes run concurrently
- Commerce accounting exports remain aligned with the 19-column v1 schema

### Fixed

- Spreadsheet exports now neutralize formula-like cell values
- Webhook redirects and other non-success responses now fail delivery explicitly

## Unreleased

## 1.4.2 — 2026-07-07

### Added

- Refund amount, date, reference, and currency columns in the Commerce accounting export preset
- Field paths can now filter related objects by `type` and `status` (e.g. `transactions.refund.success.paymentAmount`)

### Changed

- Commerce order exports use the native `withLineItems`/`withTransactions` eager loading when available

## 1.4.1 — 2026-07-07

### Added

- Saved export templates can now run from Craft's native element index Export button

## 1.4.0 — 2026-07-07

### Added

- Template configuration import and export for moving saved templates between projects
- Export file retention cleanup for completed local export files

### Fixed

- Export direction icon alignment

## 1.3.1 — 2026-07-05

### Changed

- XML exports now match Craft's native XML structure

### Removed

- Custom XML root and row naming settings

## 1.3.0 — 2026-07-04

### Added

- Commerce accounting preset with 19 verified order columns (Pro)
- Commerce integration tests for CSV output, empty results, settings, and warnings

### Changed

- Order date filters now use `dateOrdered`

### Fixed

- Preset replacement now asks for confirmation when fields are already selected

## 1.2.0 — 2026-07-02

### Added

- XML exports with configurable root and row names (Pro)
- XML name validation and column-title previews

### Changed

- Centralized export format metadata and validation
- Unknown formats now fail instead of falling back to CSV
- Added `xml` as a webhook format
- Added the PHP `xmlwriter` requirement
- Empty runs now show "Completed, no matching rows"
- Updated Save Template button styling

### Fixed

- Email attachments now keep the export filename

## 1.1.0 — 2026-06-29

### Added

- Custom-field and relation filters
- Collapsible field groups with counts

### Changed

- Added a sticky Save bar to the export editor
- Moved the Pro upsell below the template list
- Reduced duplicate Pro labels

### Fixed

- Fixed field picker initialization for new templates
- Removed the unsupported asset `mimeType` filter
- Reset field groups when the element type changes

## 1.0.1 — 2026-06-29

- Switched to native Craft plugin editions
- Prepared the Plugin Store copy and commercial license
- Added edition and request-normalization tests
- Used the local PHPUnit binary in Composer scripts
- Corrected package URLs and metadata
- Added the Craft commercial license
- Made migrations work with MySQL, MariaDB, and PostgreSQL
- Declared `phpoffice/phpspreadsheet` for XLSX exports
- Removed duplicate migrations
- Documented data retention and manual QA
- Added CI for PHP 8.2–8.4

## 1.0.0 — 2026-03-16

- First release of Data builder
- CSV and JSON export templates
- Native, custom, relation, and Matrix fields
- Queued runs, download history, and permissions
