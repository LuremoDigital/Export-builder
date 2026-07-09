<p align="center">
  <img src="./icon.svg" width="120" alt="Export Builder icon">
</p>

<h1 align="center">Export Builder</h1>

<p align="center">
  Saved export templates for Craft CMS — pick fields, filter precisely, schedule runs, and deliver automatically. Accounting-ready Commerce exports in Pro.
</p>

<p align="center">
  <a href="https://plugins.craftcms.com/data-export-builder"><img src="https://img.shields.io/badge/Craft%20Plugin%20Store-data--export--builder-E5422B.svg" alt="Craft Plugin Store"></a>
  <img src="https://img.shields.io/badge/Craft%20CMS-5.x-E5422B.svg" alt="Craft CMS 5.x">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/editions-Standard%20%7C%20Pro-0EA5E9.svg" alt="Standard and Pro editions">
  <img src="https://img.shields.io/badge/license-Commercial-0F172A.svg" alt="Commercial license">
</p>

---

**Export Builder** turns exports from one-off requests into saved templates your whole team can run from the Control Panel. Craft's native element index export is fine for a quick one-time dump — Export Builder is for the exports you run more than once: month-end order reports for the bookkeeper, content audits, migration extracts, recurring feeds. Save the field selection, column names, and filters once, then run it on demand, schedule it, or let it deliver itself by email or webhook. Small exports run immediately; larger exports run through the Craft queue and stay available for download from run history.

Built for agencies, freelancers, and in-house Craft teams that repeatedly need clean exports without turning every request into bespoke development work.

## Supported Element Types

| Element Type     | Standard | Pro |
| ---------------- | :------: | :-: |
| Entries              |    ✓     |  ✓  |
| Users                |    ✓     |  ✓  |
| Categories           |    ✓     |  ✓  |
| Tags                 |    ✓     |  ✓  |
| Assets               |    ✓     |  ✓  |
| Formie Submissions †  |    —     |  ✓  |
| Wheelform Submissions †|   —     |  ✓  |
| Commerce Orders ‡    |    —     |  ✓  |
| Commerce Products ‡  |    —     |  ✓  |
| Commerce Variants ‡  |    —     |  ✓  |

† Pro edition; available when the [Formie](https://plugins.craftcms.com/formie) or [Wheelform](https://plugins.craftcms.com/wheelform) plugin is installed and enabled.
‡ Requires [Craft Commerce](https://plugins.craftcms.com/commerce) and the Pro edition.

## Supported Output Formats

| Format | Standard | Pro |
| ------ | :------: | :-: |
| CSV    |    ✓     |  ✓  |
| JSON   |    ✓     |  ✓  |
| XLSX   |    —     |  ✓  |
| XML    |    —     |  ✓  |

XML exports (Pro) follow Craft's native XML structure: the element type is the root, each exported element is an `<item>`, and selected field paths become child tags. Values are flattened to readable text the same way CSV flattens them.

## Features

- 📤 **Export the elements you actually use** — entries, users, categories, tags, and assets, plus Commerce orders, products, and variants in Pro.
- 📝 **Export form submissions (Pro)** — [Formie](https://plugins.craftcms.com/formie) and [Wheelform](https://plugins.craftcms.com/wheelform) submissions, filtered by form.
- 🧩 **Pick fields without code** — native attributes, custom fields, relation fields, and practical Matrix sub-field paths, all from one field picker.
- 🔃 **Shape the output** — rename and reorder columns, and choose CSV, JSON, XLSX, or XML.
- 🔍 **Filter precisely** — by section, site, form, status, keyword, relevant element date, relations, and selected field values where supported.
- ⚡ **Commerce accounting preset (Pro)** — export one row per completed order with 19 accountant-ready columns, two-decimal totals, and line-item values joined with ` | `. Order Ops, Catalog Feed, and Inventory Feed presets are also included.
- ♻️ **Reuse everything** — save export templates and run them again on demand.
- 📦 **Move templates between projects** — export a saved template to JSON and import it into another Craft project.
- ⏱️ **Scale safely** — small exports run immediately; larger ones queue and download later from run history.
- 🧹 **Control local retention** — auto-delete completed local export files after 7, 30, or 90 days, or keep them forever.
- 🤖 **Automate (Pro)** — schedule recurring exports and deliver them by email, webhook, or to a Craft asset volume.

## Requirements

- PHP 8.2+ (with the `xmlwriter` extension, enabled by default)
- Craft CMS 5.0+
- Craft queue configured (for larger exports)
- Craft Commerce (optional, for order/product/variant exports)
- Formie or Wheelform (optional, for form submission exports — Pro edition)

## Installation

Install from the **Plugin Store** in the Craft Control Panel (search for *Export Builder*), or with Composer:

```bash
composer require luremo/craft-data-export-builder
php craft plugin/install data-export-builder
```

Then grant the plugin permissions to the right user groups.

## Quick Start

1. Open **Exports** in the Craft Control Panel.
2. Create a new export template.
3. Choose an element type.
4. Add the fields you want to export.
5. Rename and reorder the selected columns.
6. Apply filters if needed.
7. Save the template.
8. Run the export.
9. Download the completed file from run history.

Use **Export Template JSON** to move a saved template to another project. Importing creates a new template with the same fields, filters, and portable settings, while leaving delivery secrets and local runtime state behind.

## Field Support

The field picker includes:

- native element attributes
- common meta values like title, slug, uri, status, and dates
- custom fields
- relation fields
- practical Matrix sub-field paths

Dates are normalized to `Y-m-d H:i:s`. CSV output uses native `fputcsv()` escaping for commas, quotes, and multiline values. Relation values export as comma-separated readable values in CSV, and as arrays where practical in JSON.

## Queue Behavior

- Each template has a queue threshold.
- Exports at or below the threshold run immediately.
- Larger exports create a queued export run.
- Completed runs remain downloadable from the template screen.
- Failed runs store an error message.

## Automation & Delivery (Pro)

Configure automation per export template under **Settings**:

- Scheduled exports are queued by running `php craft data-export-builder/scheduler/run`. When a scheduled run is due, the plugin creates a normal export run for that template.
- **Email delivery** sends the exported file as an attachment.
- **Webhook delivery** posts the export payload and file to the configured endpoint.
- **Remote storage** uploads a copy to a selected Craft asset volume. *Keep local downloadable copy* retains the local run file after upload.
- Failed runs stay in run history and can be retried from the Control Panel.

## Permissions

- `manageDataExports`
- `runDataExports`
- `downloadDataExports`

## Data Handling & Retention

Exports can contain personal data (user records, Commerce order and customer details). Handle the output accordingly:

- Completed export files are written to Craft's storage. Per-template retention can auto-delete local files after 7, 30, or 90 days, or keep them forever.
- Expired local export files are cleaned by `php craft data-export-builder/scheduler/run`.
- Download access is gated by the `downloadDataExports` permission.
- Pro webhook delivery posts the export payload and file to a configured public HTTPS endpoint; redirects and private-network destinations are blocked. When a secret is configured, the signature covers a timestamp, the JSON payload, and the SHA-256 digest of the file. The request also includes a run-scoped idempotency key.
- Pro email delivery sends the exported file as an attachment to the addresses you configure.
- Remote copies are not deleted by local retention cleanup; prune those where they are stored.

## Editions

Export Builder declares native Craft plugin editions:

- **Standard** — general content exports (CSV, JSON).
- **Pro** — Commerce-focused workflows, XLSX, XML, scheduling, and delivery.

| Edition  | Price |
| -------- | ----- |
| Standard | $29   |
| Pro      | $69   |

Set the edition through Craft plugin editions, not environment variables. Craft stores the active edition in project config; change it via `plugins.data-export-builder.edition` when testing edition-gated behavior locally. See [docs/pricing-edition-notes.md](docs/pricing-edition-notes.md) for edition rationale and pricing direction after launch validation.

## Support

- **Bug reports:** [GitHub Issues](https://github.com/LuremoDigital/Export-builder/issues) (please include reproduction steps).
- **Commercial support:** contact Luremo through the [Craft Plugin Store listing](https://plugins.craftcms.com/data-export-builder).
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)

## License

Commercial. See [LICENSE.md](LICENSE.md). Licenses are sold through the [Craft Plugin Store](https://plugins.craftcms.com/data-export-builder).

---


## Screenshots

<p align="center"><img src="docs/img/templates-index.png" alt="Templates index" width="800"></p>
<p align="center"><em>The Exports index — every saved template, ready to run.</em></p>

<p align="center"><img src="docs/img/template-builder.png" alt="Template builder with field picker" width="800"></p>
<p align="center"><em>Build a template: choose an element type, pick fields, rename and reorder columns.</em></p>

<p align="center"><img src="docs/img/run-history.png" alt="Run history" width="800"></p>
<p align="center"><em>Run history with queued, completed, and failed runs.</em></p>

<p align="center"><img src="docs/img/commerce-export.png" alt="Commerce order export" width="800"></p>
<p align="center"><em>A Commerce order export template (Pro).</em></p>

<p align="center">Built by <a href="https://github.com/LuremoDigital">Luremo</a> for the Craft CMS community.</p>
