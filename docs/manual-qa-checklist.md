# Manual QA Checklist

## Core Template Flow

- Install the plugin on Craft CMS 5 and confirm the `Exports` CP nav item appears.
- Create a new export template for entries.
- Add native fields, custom fields, and at least one relation field.
- Rename columns and reorder them.
- Save the template and confirm it persists correctly after reload.

## Immediate Export Flow

- Set the queue threshold above the expected row count.
- Run the export.
- Confirm the run is marked `completed`.
- Download the file.
- Verify column order, labels, values, and escaping.

## Queue Flow

- Set the queue threshold below the expected row count.
- Run the export.
- Confirm the run is marked `queued`, then `running`, then `completed`.
- Verify the file remains downloadable after completion.

## Filters

- Entries: limit by section and confirm only matching entries export.
- Multi-site elements: limit by site and confirm the correct site content exports.
- Use created-from and created-to dates and verify the output range.

## Data Shapes

- Export relation fields and confirm CSV values are human-readable.
- Export JSON and confirm relation values become arrays.
- Export multiline text and quotes and confirm CSV remains valid.
- Export Matrix content using a defined nested field path and confirm the output is readable.

## Permissions

- Confirm users without `manageDataExports` cannot access the CP UI.
- Confirm users without `runDataExports` cannot trigger runs.
- Confirm users without `downloadDataExports` cannot download completed files.

## Commerce

- Install Craft Commerce.
- Confirm Commerce orders, products, and variants appear only when Commerce is installed and Pro edition is enabled.
- Run an order export and verify key operational fields.

## Clean-Boot (no optional plugins installed)

- On a Craft 5 install with NO Commerce, Formie, or Wheel Form installed, confirm the plugin installs, the `Exports` nav appears, and standard exports run without errors.
- Confirm no fatal errors from the optional-plugin class references in `CapabilityHelper`.
- Install each optional plugin (Commerce, Formie, Wheel Form) one at a time and confirm its element types appear only when both installed and (for Commerce) Pro is active.

## Database Engines

- Run install + the index/constraint migration on MySQL/MariaDB and confirm it completes.
- Run install + the index/constraint migration on PostgreSQL and confirm it completes (cross-DB introspection in `m260318_133301`).
- Re-run the migration path (uninstall/reinstall) on both engines and confirm idempotency guards prevent duplicate-index/foreign-key errors.

## XLSX Output (Pro)

- Run an export with XLSX format and confirm the file opens cleanly in Excel and LibreOffice.
- Verify column headers, ordering, value formatting, and date normalization match the CSV output.
- Confirm Standard edition cannot select or run XLSX.

## XML Output (Pro)

- Select XML as the output format and confirm the `XML Settings` subsection appears directly under Output Format, and disappears when switching to another format.
- Confirm the selected-fields panel shows the XML tag-name hint only while XML is selected.
- Save with the default root/row names (`export`/`row`), run the export, and confirm the file parses as well-formed XML with the expected structure (root -> row -> field elements).
- Save with custom root/row names and confirm the generated file uses them.
- Enter invalid names (`123root`, `xmlData`, a name with spaces, an empty name) and confirm each shows a clear field-level error, keeps the typed value, and blocks the save. Confirm the same feedback appears inline while typing.
- Confirm column titles map to generated tag names ("Order Number" -> `<order_number>`) and duplicate titles get `_2`/`_3` suffixes.
- Export values containing `&`, `<`, `>`, quotes, multiline text, and emoji; confirm the file stays parseable and values round-trip.
- Switch a template XML -> CSV -> XML and confirm the configured root/row names are preserved.
- Run an empty XML export (filters matching zero rows) and confirm a valid root-only document downloads.
- Confirm the download uses the `.xml` extension and the `application/xml` MIME type, and that email/webhook/volume delivery carries the `.xml` file name.
- Confirm Standard edition cannot select or run XML, and the format instructions mention XML as Pro.
- Force a failure mid-run (for example an unwritable export directory) and confirm the run is marked failed with no partial XML file exposed in run history.

## Scheduling (Pro)

- Configure a scheduled export on a template.
- Run `php craft data-export-builder/scheduler/run` and confirm a normal export run is created when due.
- Confirm no run is created before the schedule is due.

## Delivery (Pro)

- Email: configure email delivery, run an export, and confirm the file arrives as an attachment.
- Webhook: configure a webhook endpoint, run an export, and confirm the payload and file are posted.
- Volume: configure a Craft asset volume, run an export, and confirm a copy is uploaded.
- `Keep local downloadable copy`: confirm the local run file is retained after remote upload when enabled.

## Failure Behavior

- Point webhook/email delivery at an invalid endpoint/address and confirm the run is marked failed with a stored error message, and is retryable from the CP.
- Confirm a failed run does not delete or corrupt a previously completed run's file.

## Edition Downgrade

- On a Pro site with Pro-only templates (Commerce/scheduled/delivery), switch the edition to Standard.
- Confirm Pro-only runs are blocked at runtime and the UI clearly indicates the feature requires Pro, with no fatal error and no data loss to the saved template.
