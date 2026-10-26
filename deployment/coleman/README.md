# 560 Coleman private demo

QloApps fork for two apartments at 560 Coleman Avenue, Highland Park, Los Angeles.
Upstream base: `10251e3591f4e0528a34e1bcf99c30594a93055a` (`develop`). This is a preview based on an unreleased upstream revision; review/pin a suitable stable release before public use.

The `colemanstay` module supplies the website, apartment pages, validated request form and authenticated **Stay requests** inbox. It uses an additive ObjectModel/table and does not modify core QloApps. Short stays and longer enquiries use the same dates form. Requests do not reserve inventory, create orders, send email or charge guests. Native apartments remain inactive drafts; zero prices and internal capacity/time values are software placeholders, never advertised rates or policies.

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

Host login: `/host-admin/`, account `host@coleman.example`, password from the private deployment environment. No default password is committed. The inbox is read-only; any future approval/payment workflow needs a separate implementation.

## VPS migration

1. Build this same revision and image for the VPS architecture.
2. Back up the database with `mariadb-dump --single-transaction` and archive the `site` volume, including generated settings and uploads. Store backups encrypted/private: they contain credentials and enquiry contact details.
3. Create the Compose stack on the VPS with fresh deployment secrets and restore both volumes. Do **not** rerun the installer against restored data. Preserve or deliberately update the database credentials in QloApps' settings using the normal configuration path.
4. Put an HTTPS reverse proxy in front of the loopback web port. Forward the real Host and `X-Forwarded-Proto: https`. Update QloApps shop domain/SSL domain and base URL to the new hostname, clear application caches, and test redirects, login and request persistence before switching DNS.
5. Keep the old instance and backup until restore tests pass. Avoid running two writable copies during cutover.

Before public booking, replace concept imagery with actual property photos and confirm apartment details, rates, availability, terms and applicable rental requirements. Add reviewed notifications and abuse controls for public traffic. This demo's session-based throttle is intended for private testing, not a public anti-spam service.

## Updates and rollback

Keep upstream changes separate from this module. Persistent application files are initialized once; entrypoint updates only the custom module/deployment helpers on restart. A QloApps core upgrade needs its normal migration path, not merely an image swap. Before any release preserve database + site volumes and the Dokploy Compose/environment definition. Roll back through Dokploy to the previous image, restoring corresponding data only when a schema change requires it. Never delete volumes to redeploy.

## Visual direction and asset

Warm cream, olive, generous space and editorial serif typography; no hotel booking widgets or unconfirmed amenity claims. Desktop target 1440×1000, phone target 390×844. Native form controls, visible focus, semantic labels, keyboard skip link, server validation and no-JavaScript submission.

`interior.png` is an AI-generated fictional interior, not a photograph of either apartment. Every display labels it as a concept. Original generation prompt: “Landscape 3:2 concept image for a private demo apartment rental website. A warm understated Los Angeles bungalow living room with off-white plaster walls, oak flooring, a linen sofa, walnut chair, a single leafy plant, paperback books, and sunlight through wood framed windows. Quiet lived-in editorial architecture photography, restrained natural olive cream and terracotta palette, no luxury mansion, no people, no text, no watermark. Illustrative fictional interior, not a representation of a real address.”

The fork retains upstream license notices. Custom additions use OSL-3.0. A visible source link points to this fork.
