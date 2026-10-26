# Private backup, restore and rollback

## Verified rehearsal — 2026-10-26

An isolated Dokploy stack restored the `f067c9d` private-demo snapshot, switched to the previous application image (`cca0d06`), and switched back to `f067c9d`. The live Coleman deployment was left running.

Evidence obtained:

- Restored MariaDB dump matched the backup **byte for byte** before application startup, using identical dump options and the same database image.
- All regular site files outside cache/log directories matched their SHA-256 manifest before startup. The complete site archive also includes those transient directories, generated settings and uploads.
- Homepage, both apartment pages, anonymous host protection, native host credentials, signed-in request inbox, availability and rental settings passed HTTP checks in the restored copy. The current release's message screen also passed.
- A new synthetic request was submitted through the restored guest form, returned a receipt and appeared in its host inbox.
- The previous image passed the guest/host HTTP checks. Returning to the current image restored the message screen. Request, calendar, rental-setting and message-draft fingerprints were identical before rollback and after both switches, preserving the post-backup test request.
- Both rehearsal containers had no published ports and used only an internal Docker network. Both were stopped through Dokploy after verification; their volumes were retained.
- The live stack remained healthy with five pending synthetic requests, no active date blocks, two blank/unconfirmed rental settings, zero orders and email disabled.

Private Winona evidence and artifacts:

- Backup: `/home/winona/coleman-duplex/private/backups/restore-rehearsal-20261026/`
- Rehearsal state/fingerprints/report: `/home/winona/coleman-duplex/private/restores/20261026/`
- Stopped Dokploy application: `coleman-restore-qga8gj`

This proves recovery on the existing Winona host and rollback between these two compatible application versions. It does not prove an off-host disaster recovery, a new VPS architecture, DNS/TLS cutover, email delivery or a rollback to pre-calendar code. Images currently remain on Winona under immutable backup tags; copy/export them and transfer private backup artifacts through an authorized secure path before relying on recovery elsewhere. The private deployment environment remains a separate prerequisite; it is not printed or included in the public repository.

## Create a backup

Use `backup.py` under `winona-batch-run`, supplying a **new** output directory beneath the private backup parent and the verified deployed revision. The helper refuses an existing output directory. It captures a consistent transactional database dump, the site archive, a non-cache file manifest, checksums and actual running image IDs, and pins those images locally. The site and database must not be undergoing an upgrade or file-upload changes during the snapshot; quiesce writes for a real cutover.

```sh
winona-batch-run python3 deployment/coleman/backup.py \
  --output /home/winona/coleman-duplex/private/backups/NEW_BACKUP_ID \
  --revision VERIFIED_DEPLOYED_REVISION
```

Replace the uppercase placeholders. Verify resource admission first and retain the private environment/secret-manager access separately. Backup files contain sensitive application data and must stay private. This helper does not deploy anything.

## Rehearse restoration

`restore-rehearsal.py` is deliberately scoped to Winona's existing private Dokploy project, credentials and resource policy. Use a new work directory beneath `/home/winona/coleman-duplex/private/restores/` for each rehearsal. `create` verifies backup checksums, registers a separate Dokploy stack with exact local image IDs and independent volumes, and starts the web container waiting for restore verification. It never points at live volumes.

```sh
winona-batch-run python3 deployment/coleman/restore-rehearsal.py create \
  --work /home/winona/coleman-duplex/private/restores/NEW_REHEARSAL_ID \
  --backup /home/winona/coleman-duplex/private/backups/BACKUP_ID
python3 deployment/coleman/restore-rehearsal.py status \
  --work /home/winona/coleman-duplex/private/restores/NEW_REHEARSAL_ID
winona-batch-run python3 deployment/coleman/restore-rehearsal.py restore \
  --work /home/winona/coleman-duplex/private/restores/NEW_REHEARSAL_ID
```

Wait for the named database to be healthy and both containers to be running before `restore`. Restoration refuses a non-empty database, a completed restore, published ports or a non-internal network. It imports into the new database, extracts the archive into the new site volume, compares the database dump and file manifest, then releases the copied application to start. A failed import leaves the partial copy intact for inspection; stop that rehearsal before making another, rather than clearing or reinstalling it.

Use the returned application name to identify its containers. Copy `test-restored-http.php` and `test-restored-state.php` into that work directory's `control/` folder (private permissions). Run them inside the rehearsal web container via `/restore/`. They refuse execution without the rehearsal marker. The HTTP test routes every request to container localhost, simulates the normal trusted-proxy headers/cookies, and keeps the application authentication and Secure-cookie settings intact. It does not validate the public TLS path.

- Set `COLEMAN_RESTORE_TEST=1` for HTTP checks.
- Set `COLEMAN_EXPECT_MESSAGES=1` when testing the current message-capable release.
- Set `COLEMAN_TEST_GUEST_CREATE=1` only once against this five-request snapshot to test saving its next synthetic request. Its expected receipt is intentionally tied to this fixture snapshot; update the assertion for a different snapshot.
- Save the state check's non-secret counts/fingerprints before and after version switches; they must match.

## Rehearse code rollback, then stop

The `rollback` action switches only the rehearsal web image to the previous image captured at creation; `forward` returns to the backed-up current image. Both use Dokploy and keep the copied database/site volumes. Inspect the exact running image and its health after each deployment, then run HTTP and state checks. Do not treat an accepted deployment request as completion.

```sh
winona-batch-run python3 deployment/coleman/restore-rehearsal.py rollback --work /home/winona/coleman-duplex/private/restores/NEW_REHEARSAL_ID
winona-batch-run python3 deployment/coleman/restore-rehearsal.py forward --work /home/winona/coleman-duplex/private/restores/NEW_REHEARSAL_ID
python3 deployment/coleman/restore-rehearsal.py stop --work /home/winona/coleman-duplex/private/restores/NEW_REHEARSAL_ID
```

Run those phases separately, verifying each before the next. `stop` uses the [Dokploy Compose API](https://docs.dokploy.com/docs/api/compose); confirm both rehearsal containers are no longer running. Keep the backup and stopped rehearsal volumes until no longer needed. They contain a copy of private data and must not be published or reused as an active second booking site.

For an actual live rollback, first identify the exact source/image and data compatibility. A code-only rollback from the message feature to the calendar release retains draft data but hides that feature. Rolling back before the calendar feature would stop enforcing date blocks and cannot safely interpret reserved requests. Prefer a forward fix; otherwise stop guest writes, preserve current data and reconcile requests received after the backup before any matched data restore. Never blindly restore an older database over new guest requests.
