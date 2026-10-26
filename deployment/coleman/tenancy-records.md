# Private rental workflows

The Coleman module now extends each long-term enquiry with a private applicant/resident portal, fuller applications, lease and move-in records, a manual rent ledger, and maintenance requests. Open **Applications → an enquiry → Manage application & rental**. The portal is `/portal`.

## Operation

- Issue a 30-day access code on the application's rental page. Share the reference and code with the intended applicant yourself. Codes are shown once; only SHA-256 hashes are stored. Replacing/revoking a code invalidates existing sessions. Sessions expire after 12 hours. Nothing is emailed.
- Applicants can save drafts, submit contact/household/rental history with optional work and references, and return later. Submitted applications are locked until a host reopens them. No scoring, screening, identity documents or automated eligibility decisions.
- Hosts record agreed lease dates/rent, deposit due/received, signing status/date and keys/inventory/utilities. Unknown terms remain blank. Upload owner-approved PDF versions (8 MB maximum); manual signing status does not execute a signature or reserve calendar dates.
- Add monthly rent charges, record partial payments received elsewhere, download receipts and export CSV. A duplicate active month, overpayment or stale/replayed form is rejected. To correct entries, void payments with reasons, then void the charge if needed and add a replacement. Original entries remain visible. Deposits are separate manual lease fields, not rent payments or deposit-account reconciliation.
- Enable maintenance on the lease page. Residents submit descriptions and an optional photo (JPEG/PNG, 5 MB / 12 MP). Hosts record scheduled/resolved status, resident-visible updates and private repair costs. The portal does not dispatch contractors, authorize spending or send notifications.

## Data and access

Two additive tables store each rental's bounded document and dated actor/action/revision history. The small duplex workflow uses a single locked row and revision for atomic changes across its profile, lease, ledger and maintenance entries; no new production dependency. Records are bounded to 2 MB, with attachments stored separately. Historical payments, void reasons, document versions and maintenance updates are retained. General history shows the latest 30 actions; all actions remain stored. Lease field edits have dated action history, not full prior-value snapshots.

Every record is scoped through the two configured apartment IDs. Host actions use native employee permissions and CSRF; portal mutations use the session-bound rental and current unexpired grant, never a posted application ID. File downloads require an authorized POST with CSRF. Private PDFs are attachment downloads; maintenance images are decoded and re-encoded to JPEG. Files have generated names and live at `/var/www/html/var/coleman-private`, denied direct HTTP access by Apache and `.htaccess`, with directory 0700/files 0600. Site-volume backups include these files.

This remains a private **test-data preview**, not a launch-ready collection system for real tenant screening or legal documents. Actual screening, electronic signing, payment processing, outbound notifications and public launch remain separate integrations/decisions. Existing booking requests, calendars and blank unit settings are preserved.

## Verification and recovery

`test-tenancy.php` runs in the installed PHP/Smarty/MariaDB environment using temporary tables only, including the existing application tests. It covers draft/submit/reopen, lease validation, exact cents, partial payments/corrections, maintenance transitions, expired/revoked grants, native permissions/CSRF, stale updates and all eight real templates.

Pre-deployment rollback: `/home/winona/coleman-duplex/private/backups/tenancy-20261026-01` contains the database, complete site, checksums and retained image tags for deployed revision `01339e59b5077fdf7f2c453cce0abab284112241`. Restore the prior code/image through the existing Dokploy recovery procedure; keep the additive rental tables and private uploads. Do not restore an older database over newer enquiries without reconciling those records.

Live acceptance on 2026-10-26 verified host and resident draft/submit/reopen, lease terms and PDF upload/download, denial of forged cross-rental downloads, partial payments, rejected overpayments/replayed forms, CSV formula protection, receipts, photo re-encoding, repair scheduling/resolution and immediate session revocation. Test rentals CA-00002/CA-00003 are closed, grants revoked, and test charges/payments voided with reasons. Current audit: five existing stay requests, zero orders/date blocks/confirmed units, mail disabled, zero outstanding demo rent, two private attachments at 0600. Visual evidence uses live public portal checks plus sanitized HTML rendered by the authenticated live host/resident endpoints for desktop/mobile review; it does not imply an authenticated host browser session.

Browser form verification caught and fixed the shared submit handler's assumption that every button used `decision`: it now preserves the actual button name (`operation` for rental actions) and leaves download forms reusable. `node deployment/coleman/test-host-forms.cjs` covers the regression without dependencies. A real browser posting the rendered lease form to a local test endpoint confirmed `decision=tenant_action` and `operation=lease_save`; a download kept its button enabled and the page in place. This supplements the live authenticated HTTP workflow checks.
