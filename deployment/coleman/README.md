# 560 Coleman private demo

QloApps fork for two apartments at 560 Coleman Avenue, Highland Park, Los Angeles.
Upstream base: `10251e3591f4e0528a34e1bcf99c30594a93055a` (`develop`). This is a preview based on an unreleased upstream revision; review/pin a suitable stable release before public use.

The `colemanstay` module supplies the website, apartment pages, validated request form and authenticated **Stay requests** inbox. It uses an additive ObjectModel/table and does not modify core QloApps. Short stays and longer enquiries use the same dates form. Submitting a request does not reserve dates, create orders, send email or charge guests. Hosts can separately reserve approved requests in the custom calendar; native QloApps inventory remains separate. Native apartments remain inactive drafts; zero prices and internal capacity/time values are software placeholders, never advertised rates or policies.

## Build and first installation

Build the image for the target architecture from the repository root:

```sh
docker build --platform linux/amd64 -f deployment/coleman/Dockerfile -t coleman-duplex:demo-1 .
```

On Winona, Dokploy owns deployment and rollback. Create a Git-source Compose service for this fork and branch, with Compose path `deployment/coleman/compose.yml`, and supply `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `ADMIN_PASSWORD` and `DEMO_DOMAIN` through Dokploy's private environment. Use unique random secrets. Only port 18090 on loopback is exposed; MariaDB has no host port. Keep Winona resource admission and batch limits for builds/imports. Do not bypass its pressure guard.

Deploy, then run these once inside the web container as `www-data`:

```sh
php deployment/coleman/install.php
php deployment/coleman/seed.php
```

The installer refuses an existing settings file and never clears the database. The seed creates one draft property and two draft apartments; repeated completed runs do not duplicate them. If first installation fails mid-step, inspect the error and preserve the partial database before recovery. Do not blindly reinstall.

Host login: `/host-admin/`, account `host@coleman.example`, password from the private deployment environment. No default password is committed. The branded inbox is at `/host-admin/index.php?controller=AdminColemanRequests`. Hosts with edit permission can approve, decline, or move a request back to pending. Decisions record the employee, timestamp and revision; stale submissions cannot overwrite a newer decision. Approval is for follow-up only. Use **Reserve dates** and its confirmation checkbox to allocate approved nights. **Release dates** frees the nights and returns the request to approved follow-up. The Availability tab supports separate apartment calendars and manual blocks. Allocation uses a shared apartment row lock and a transaction to prevent overlapping reservations and manual blocks; checkout is exclusive. Messages/payments are not sent. Rental details stay blank until entered, and private until explicitly marked owner-confirmed. Confirmed minimum stays and guest limits apply to requests and reservations. Use the status filters to find reviewed requests. Advanced QloApps settings remain available from the footer.

## VPS migration

1. Build this same revision and image for the VPS architecture.
2. Back up the database with `mariadb-dump --single-transaction` and archive the `site` volume, including generated settings and uploads. Store backups encrypted/private: they contain credentials and enquiry contact details.
3. Create the Compose stack on the VPS with fresh deployment secrets and restore both volumes. Do **not** rerun the installer against restored data. Preserve or deliberately update the database credentials in QloApps' settings using the normal configuration path.
4. Put an HTTPS reverse proxy in front of the loopback web port. Forward the real Host and `X-Forwarded-Proto: https`. Update QloApps shop domain/SSL domain and base URL to the new hostname, clear application caches, and test redirects, login and request persistence before switching DNS.
5. Keep the old instance and backup until restore tests pass. Avoid running two writable copies during cutover.

Before public booking, confirm permission to use the listing photos and confirm apartment details, rates, availability, terms and applicable rental requirements. Add reviewed notifications and abuse controls for public traffic. This demo's session-based throttle is intended for private testing, not a public anti-spam service.

## Updates and rollback

Keep upstream changes separate from this module. Persistent application files are initialized once; entrypoint updates only the custom module/deployment helpers on restart. A QloApps core upgrade needs its normal migration path, not merely an image swap. Before any release preserve database + site volumes and the Dokploy Compose/environment definition. Roll back through Dokploy to the previous image, restoring corresponding data only when a schema change requires it. Never delete volumes to redeploy.

## Visual direction and asset

Classic rental-listing layout: white background, dark sans-serif typography, coral request buttons, rounded apartment photos and a bordered request panel. Apartment comparison comes first; no unconfirmed ratings, amenities, rates or availability. The compact trip bar focuses the real date and guest fields. Desktop target 1440×1000, phone target 390×844. Native form controls, visible focus, semantic labels, keyboard skip link, server validation and no-JavaScript submission.

The site now uses two real rental-listing photographs: `560-lower-kitchen.jpg` for Apartment One (560, lower) and `562-upper-kitchen.jpg` for Apartment Two (562, upper). Source pages explicitly distinguish the units; each detail page links its source. See `modules/colemanstay/views/img/PHOTO-SOURCES.md` for original URLs and asset provenance. The Docker build downloads and verifies these two images; they are excluded from the public Git repository. These third-party photographs are not covered by the code’s open-source license. The original concept asset remains in the repository for history but is no longer rendered.

The fork retains upstream license notices. Custom additions use OSL-3.0. A visible source link points to this fork.

## Ownership and future platform integrations

This is a GitHub fork of `Qloapps/QloApps` at https://github.com/malthelm/coleman-duplex. The deployed custom work is on `codex/coleman-demo`; `upstream` tracks QloApps. Keep custom features in `colemanstay` and preserve upstream license notices.

No paid QloApps channel-manager subscription is required for this direct-request workflow. Future integrations can be implemented in this fork. Start with platform-supported iCal import/export for blocked dates, then consider direct APIs where access is available. Calendar feeds do not provide instant synchronization, messaging, pricing or payments. No external platform sync is implemented yet; each platform's access terms and charges still apply independently.

Run the focused review regression check inside an installed web container:

```sh
php deployment/coleman/test-request-review.php
php deployment/coleman/test-calendar.php
php deployment/coleman/test-messages.php
```

It shadows the request table with a connection-local temporary table and checks persistence, review transitions, stale submissions, CSRF, permissions, escaping and template rendering without changing live requests.


The calendar check uses connection-local temporary tables for rental settings, allocations and requests. It covers adjacent dates, overlap rejection, stale submissions, reservation/release transitions, confirmed stay limits, permissions and real Smarty templates. No test prices or policies are written to live apartment settings.

**Calendar rollback:** rolling back to code before the calendar feature would stop enforcing its blocks and would not understand reserved requests. Prefer a forward fix. If rollback is necessary, first stop guest writes and preserve the current database/site; restore a matched backup only after reconciling any requests received since that backup. Never overwrite new guest data blindly.

See [launch-status.md](launch-status.md) for the five-stage goal and owner decisions.


## Guest-message drafts

Reviewed requests have **Draft reply** (approved/declined) or **Draft confirmation** (reserved). The dedicated editor preserves host-written subjects and plain-text bodies, with a separate draft for each decision. Saving does not send anything. **Download saved email draft** exports a Unicode `.eml` attachment marked unsent, addressed to the request's guest. Email-app support for opening `.eml` as an editable draft varies; delivery is never claimed by this site.

If the request or another host's draft changes, stale saves/downloads are rejected. Reopened/reapproved requests retain earlier wording but require review and another save before export. Local unsaved edits disable the download button; without JavaScript, download always uses the saved wording. Drafts and exports require the native host login, edit permission and CSRF token. Current defaults explicitly describe a private demo; confirmation drafts do not invent terms, prices or a rental agreement.

The message regression check uses temporary request/draft tables and verifies persistence, stale conflicts, status changes, CSRF/permissions, plain-text escaping and MIME round-trip. Mail delivery remains disabled. Before an actual guest email is sent, obtain explicit authorization for its recipient and content through the chosen sending workflow.


## Verified recovery

An isolated restore and code rollback/return rehearsal passed on Winona on 2026-10-26. See [restore-runbook.md](restore-runbook.md) for evidence, exact scope, private artifact locations and repeatable helper commands. The live site was not rolled back. Off-host transfer and VPS migration remain pending.
