# Changelog

## Unreleased

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
