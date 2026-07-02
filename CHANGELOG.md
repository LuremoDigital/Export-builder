# Changelog

## 1.2.0 - 2026-07-02

### Added
- XML export format (Pro): generic row-based XML output — one row node per exported item, one child element per selected field, with a configurable root and row element name
- inline XML Settings under Output Format with clear, field-level name validation (invalid names are rejected, never silently rewritten), plus a hint explaining how column titles become XML tag names and how duplicate names get `_2`/`_3` suffixes
- XML output streams to disk with flat memory, escapes values safely, strips characters that are illegal in XML 1.0, and verifies writes so a failed or interrupted run never exposes a partial file as a completed export

### Changed
- export format metadata (label, MIME type, file extension, edition gating) now lives in a single registry, so every surface (validation, downloads, delivery, format dropdown) agrees on the supported formats
- unknown export formats now fail with a clear error everywhere instead of silently falling back to CSV
- webhook delivery payloads can now carry `xml` as a format value; integrators validating the format field should allow it
- the plugin now requires the PHP `xmlwriter` extension (enabled by default in PHP)

## 1.1.0 - 2026-06-29

### Added
- advanced filter builder: match custom-field values and require element relations before rows enter the export, with per-type operators (equals, contains, is not empty)
- collapsible field-picker groups with field counts, so large field sets (custom fields, matrix paths) no longer render as one long flat list

### Changed
- the export editor now has a persistent sticky Save bar that stays reachable from anywhere on the page instead of a button buried in the first card
- the Exports index leads with the templates list; the Pro upsell moved below it
- trimmed duplicate "Pro" messaging on the editor and bumped field-group headings for clearer hierarchy

### Fixed
- fixed a field-picker initialisation crash (relation-filter attribute collision) that left the picker unresponsive, so no export columns could be selected on a new template
- removed the unsupported `mimeType` asset filter that could fail an export run
- reset field-group expansion state when the element type changes

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
