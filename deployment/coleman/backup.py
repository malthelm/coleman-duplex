#!/usr/bin/env python3
"""Create a private database/site backup with exact image IDs and checksums."""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import subprocess


def run(args, **kwargs):
    """Run one command without exposing Docker environment values."""
    return subprocess.run(args, check=True, **kwargs)


def checksum(path):
    """Hash an artifact incrementally."""
    digest = hashlib.sha256()
    with path.open('rb') as source:
        for block in iter(lambda: source.read(1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def main():
    """Refuse existing output paths and preserve both runtime volumes logically."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--revision', required=True, help='Verified deployed Git revision')
    parser.add_argument('--web', default='coleman-demo-bs68yo-web-1')
    parser.add_argument('--database', default='coleman-demo-bs68yo-db-1')
    args = parser.parse_args()
    os.umask(0o077)
    if not args.output.is_absolute() or args.output.exists():
        parser.error('Output must be a new absolute directory under a private parent')
    args.output.mkdir(mode=0o700)
    manifest = {'created_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'revision': args.revision, 'images': {}, 'files': {}}
    for role, container in [('web', args.web), ('database', args.database)]:
        manifest['images'][role] = run(['docker', 'inspect', '--format', '{{.Image}}', container], capture_output=True, text=True).stdout.strip()
    # --skip-comments avoids timestamps so a restored dump can be compared byte for byte.
    commands = {
        'database.sql': ['docker', 'exec', args.database, 'sh', '-c', 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-dump --single-transaction --skip-comments --order-by-primary -ucoleman coleman'],
        'site.tgz': ['docker', 'exec', args.web, 'tar', '-czf', '-', '-C', '/var/www/html', '.'],
    }
    for name, command in commands.items():
        path = args.output / name
        with path.open('wb') as target:
            run(command, stdout=target)
        manifest['files'][name] = {'bytes': path.stat().st_size, 'sha256': checksum(path)}
    # File identity is checked before starting the restored application (which clears caches).
    listing = run(['docker', 'exec', args.web, 'sh', '-c', 'cd /var/www/html && find . -type f -not -path "./cache/*" -not -path "./log/*" -not -path "./logs/*" -print0 | sort -z | xargs -0 sha256sum'], capture_output=True).stdout
    (args.output / 'site-files.sha256').write_bytes(listing)
    manifest['files']['site-files.sha256'] = {'bytes': len(listing), 'sha256': checksum(args.output / 'site-files.sha256')}
    # Preserve immutable image tags locally so a subsequent application build cannot replace them.
    label = args.output.name.lower()
    for role, image in manifest['images'].items():
        tag = 'coleman-backup-' + role + ':' + label
        run(['docker', 'image', 'tag', image, tag])
        manifest.setdefault('image_tags', {})[role] = tag
    (args.output / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('Backup complete:', args.output)
    print('Revision:', args.revision, 'Artifacts:', len(manifest['files']))


if __name__ == '__main__':
    main()
