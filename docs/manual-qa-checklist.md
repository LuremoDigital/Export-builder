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

- Run an XML export and confirm it matches Craft's native structure: the element-type root contains one `<item>` per result, with selected field paths as child tags.
- Confirm an invalid XML field path uses Craft's `<item>` fallback.
- Export values containing `&`, `<`, `>`, quotes, multiline text, and emoji; confirm the file stays parseable and values round-trip.
- Run an empty XML export (filters matching zero rows) and confirm a valid root-only document downloads.
- Confirm the download uses the `.xml` extension and the `application/xml` MIME type, and that email/webhook/volume delivery carries the `.xml` file name.
- Confirm Standard edition cannot select or run XML, and the format instructions mention XML as Pro.
- Force a failure mid-run (for example an unwritable export directory) and confirm the run is marked failed with no partial XML file exposed in run history.

## Scheduling (Pro)

- Configure a scheduled export on a template.
- Run `php craft data-export-builder/scheduler/run` and confirm a normal export run is created when due.
- Confirm no run is created before the schedule is due.
- Run two scheduler processes for the same due slot and confirm exactly one export run is queued.

## Retention Cleanup

- Set export file retention to 7 days, age a completed run beyond 7 days, run `php craft data-export-builder/scheduler/run`, and confirm the local file is deleted and no longer downloadable.
- Set export file retention to never and confirm cleanup leaves completed files in place.
- Force a CSV, JSON, XLSX, and delivery failure and confirm no partial local export file remains.

## Delivery (Pro)

- Email: configure email delivery, run an export, and confirm the file arrives as an attachment.
- Webhook: configure a webhook endpoint, run an export, and confirm the payload and file are posted.
- Webhook: confirm HTTP, localhost, private-IP, credentialed, and redirecting URLs are rejected; confirm a signed request contains timestamp, signature, and idempotency headers.
- Volume: configure a Craft asset volume, run an export, and confirm a copy is uploaded.
- `Keep local downloadable copy`: confirm the local run file is retained after remote upload when enabled.

## Failure Behavior

- Point webhook/email delivery at an invalid endpoint/address and confirm the run is marked failed with a stored error message, and is retryable from the CP.
- Confirm a failed run does not delete or corrupt a previously completed run's file.
- Export a value beginning with `=`, `+`, `-`, or `@` to CSV and XLSX and confirm the spreadsheet treats it as text.

## Edition Downgrade

- On a Pro site with Pro-only templates (Commerce/scheduled/delivery), switch the edition to Standard.
- Confirm Pro-only runs are blocked at runtime and the UI clearly indicates the feature requires Pro, with no fatal error and no data loss to the saved template.
