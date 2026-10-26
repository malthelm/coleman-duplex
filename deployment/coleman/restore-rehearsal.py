#!/usr/bin/env python3
"""Run a private, port-free Winona restore rehearsal through Dokploy; never target live volumes."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import urllib.request

PRIVATE = Path('/home/winona/coleman-duplex/private')
API = 'https://winona.taila338b3.ts.net:3007/api/'


def command(args, **kwargs):
    """Capture command errors rather than printing private database or environment contents."""
    result = subprocess.run(args, stderr=subprocess.PIPE, **kwargs)
    if result.returncode:
        raise RuntimeError('Command failed: ' + args[0] + ' (exit ' + str(result.returncode) + ')')
    return result


def api(procedure, payload):
    """Call the existing deployment owner with its private server-side API key."""
    key = Path('/home/winona/.hermes/secrets/dokploy-full-access-api-key.txt').read_text().strip()
    request = urllib.request.Request(API + procedure, data=json.dumps(payload).encode(), headers={'x-api-key': key, 'Content-Type': 'application/json'})
    with urllib.request.urlopen(request, timeout=60) as response:
        return json.load(response)


def digest(path):
    """Hash a backup artifact without loading it all into memory."""
    h = hashlib.sha256()
    with path.open('rb') as source:
        for block in iter(lambda: source.read(1024 * 1024), b''):
            h.update(block)
    return h.hexdigest()


def compose(work, images):
    """Define only an internal network, independent volumes, and bounded resources."""
    limits = {'cpus': 0.5, 'cpuset': '4-15', 'mem_limit': '512m', 'pids_limit': 128, 'restart': 'no', 'pull_policy': 'never'}
    return json.dumps({'services': {
        'db': dict(limits, image=images['database'], environment={'MARIADB_DATABASE': 'coleman', 'MARIADB_USER': 'coleman', 'MARIADB_PASSWORD': '${DB_PASSWORD:?required}', 'MARIADB_ROOT_PASSWORD': '${DB_ROOT_PASSWORD:?required}'}, volumes=['database:/var/lib/mysql'], healthcheck={'test': ['CMD', 'healthcheck.sh', '--connect', '--innodb_initialized'], 'interval': '5s', 'timeout': '5s', 'retries': 20}),
        'web': dict(limits, image=images['web'], environment={'DB_PASSWORD': '${DB_PASSWORD:?required}', 'ADMIN_PASSWORD': '${ADMIN_PASSWORD:?required}', 'DEMO_DOMAIN': '${DEMO_DOMAIN:?required}'},
            entrypoint=['sh', '-c', 'while [ ! -e /restore/ready ]; do sleep 2; done; exec /usr/local/bin/coleman-entrypoint apache2-foreground'], command=[],
            volumes=['site:/var/www/html', str(work / 'control') + ':/restore:ro'], depends_on={'db': {'condition': 'service_healthy'}},
            healthcheck={'test': ['CMD', 'curl', '-fsS', 'http://127.0.0.1/modules/colemanstay/views/css/stay.css'], 'interval': '5s', 'timeout': '3s', 'retries': 60, 'start_period': '5m'})},
        'volumes': {'site': {}, 'database': {}}, 'networks': {'default': {'internal': True}}}, indent=2)


def inspect(container):
    """Read container state internally; only selected non-secret fields are reported."""
    return json.loads(command(['docker', 'inspect', container], stdout=subprocess.PIPE).stdout)[0]


def main():
    """Perform one explicit phase, retaining state and artifacts for recovery on failure."""
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('action', choices=['create', 'status', 'restore', 'rollback', 'forward', 'stop'])
    p.add_argument('--work', required=True, type=Path)
    p.add_argument('--backup', type=Path)
    p.add_argument('--previous-image', default='coleman-duplex:verified-before-final')
    args = p.parse_args()
    os.umask(0o077)
    work = args.work.resolve()
    if not work.is_relative_to(PRIVATE / 'restores'):
        p.error('Rehearsal work must be under the private restores directory')
    state_path = work / 'state.json'
    if args.action == 'create':
        if work.exists() or not args.backup:
            p.error('Create requires a new work directory and an existing backup')
        backup = args.backup.resolve()
        manifest = json.loads((backup / 'manifest.json').read_text())
        for name, info in manifest['files'].items():
            if digest(backup / name) != info['sha256']:
                raise RuntimeError('Backup artifact checksum mismatch: ' + name)
        previous = command(['docker', 'image', 'inspect', '--format', '{{.Id}}', args.previous_image], stdout=subprocess.PIPE, text=True).stdout.strip()
        work.mkdir(parents=True, mode=0o700); (work / 'control').mkdir(mode=0o700)
        live = json.loads((PRIVATE / 'dokploy.json').read_text())
        definition = compose(work, manifest['images'])
        result = api('compose.create', {'name': 'Coleman restore rehearsal', 'description': 'Isolated backup validation; no published ports, internal network, independent volumes.', 'environmentId': live['environmentId'], 'composeType': 'docker-compose', 'appName': 'coleman-restore', 'sourceType': 'raw', 'composeFile': definition})
        state = {k: result[k] for k in ['composeId', 'appName']}
        state.update({'backup': str(backup), 'images': manifest['images'], 'previous_image': previous, 'phase': 'waiting_for_restore'})
        state_path.write_text(json.dumps(state, indent=2) + '\n')
        api('compose.update', {'composeId': state['composeId'], 'composeFile': definition, 'env': (PRIVATE / 'demo.env').read_text()})
        api('compose.deploy', {'composeId': state['composeId'], 'title': 'Isolated restore rehearsal'})
        print('Created isolated rehearsal:', state['appName'])
        return
    state = json.loads(state_path.read_text())
    live = json.loads((PRIVATE / 'dokploy.json').read_text())
    if state['composeId'] == live['composeId'] or not state['appName'].startswith('coleman-restore-'):
        raise RuntimeError('Refusing to target a non-rehearsal deployment')
    web, db = state['appName'] + '-web-1', state['appName'] + '-db-1'
    if args.action == 'status':
        for name in [web, db]:
            data = inspect(name)
            networks = data['NetworkSettings']['Networks']
            network_data = [json.loads(command(['docker', 'network', 'inspect', n], stdout=subprocess.PIPE).stdout)[0] for n in networks]
            print(json.dumps({'name': name, 'running': data['State']['Running'], 'health': data['State'].get('Health', {}).get('Status'), 'image': data['Image'], 'published_ports': data['HostConfig']['PortBindings'], 'internal_networks': all(n['Internal'] for n in network_data)}))
        return
    if args.action == 'restore':
        if state['phase'] != 'waiting_for_restore' or (work / 'control/ready').exists():
            raise RuntimeError('This rehearsal has already been restored; refusing to overwrite it')
        for name in [web, db]:
            data = inspect(name)
            if not data['State']['Running'] or data['HostConfig']['PortBindings']:
                raise RuntimeError('Rehearsal container not ready or has published ports')
            for network in data['NetworkSettings']['Networks']:
                info = json.loads(command(['docker', 'network', 'inspect', network], stdout=subprocess.PIPE).stdout)[0]
                if not info['Internal']:
                    raise RuntimeError('Rehearsal must use only an internal network')
        count = command(['docker', 'exec', db, 'sh', '-c', 'MYSQL_PWD="$MARIADB_PASSWORD" mariadb -N -ucoleman coleman -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()"'], stdout=subprocess.PIPE, text=True).stdout.strip()
        if count != '0':
            raise RuntimeError('Restore database is not empty; preserve and inspect it before recovery')
        backup = Path(state['backup']); manifest = json.loads((backup / 'manifest.json').read_text())
        for name, info in manifest['files'].items():
            if digest(backup / name) != info['sha256']:
                raise RuntimeError('Backup checksum mismatch before restore')
        with (backup / 'database.sql').open('rb') as source:
            command(['docker', 'exec', '-i', db, 'sh', '-c', 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb -ucoleman coleman'], stdin=source, stdout=subprocess.DEVNULL)
        with (backup / 'site.tgz').open('rb') as source:
            command(['docker', 'exec', '-i', web, 'tar', '-xzpf', '-', '-C', '/var/www/html'], stdin=source, stdout=subprocess.DEVNULL)
        with (work / 'restored-database.sql').open('wb') as target:
            command(['docker', 'exec', db, 'sh', '-c', 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-dump --single-transaction --skip-comments --order-by-primary -ucoleman coleman'], stdout=target)
        if digest(work / 'restored-database.sql') != manifest['files']['database.sql']['sha256']:
            raise RuntimeError('Restored database dump differs; private artifacts retained for inspection')
        with (backup / 'site-files.sha256').open('rb') as source:
            command(['docker', 'exec', '-i', '-w', '/var/www/html', web, 'sha256sum', '--quiet', '-c', '-'], stdin=source, stdout=subprocess.DEVNULL)
        state['database_identical'] = True; state['site_files_identical'] = True; state['phase'] = 'restored'
        state_path.write_text(json.dumps(state, indent=2) + '\n')
        (work / 'control/ready').touch(mode=0o600)
        print('Restored database matches byte for byte; non-cache site files match. Application released to start.')
        return
    if args.action in ['rollback', 'forward']:
        if not (work / 'control/ready').exists():
            raise RuntimeError('Restore must be verified before switching application versions')
        images = dict(state['images'])
        if args.action == 'rollback': images['web'] = state['previous_image']
        api('compose.update', {'composeId': state['composeId'], 'composeFile': compose(work, images)})
        api('compose.deploy', {'composeId': state['composeId'], 'title': 'Restore rehearsal ' + args.action})
        print('Requested rehearsal version switch:', args.action)
        return
    api('compose.stop', {'composeId': state['composeId']})
    print('Stop requested for rehearsal only; volumes and backup retained.')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Rehearsal stopped:', type(error).__name__, str(error) if isinstance(error, RuntimeError) else '')
        raise SystemExit(1)
