<?php
/** One-time native installer, using environment secrets rather than shell arguments. */
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__, 2);
if (file_exists($root.'/config/settings.inc.php')) {
    fwrite(STDERR, "Settings already exist; refusing to reinstall.\n"); exit(1);
}
foreach (array('DB_PASSWORD', 'ADMIN_PASSWORD', 'DEMO_DOMAIN') as $key) {
    if (!getenv($key)) { throw new RuntimeException('Missing '.$key); }
}
$argv = array('index_cli.php', '--step=database,theme', '--domain='.getenv('DEMO_DOMAIN'),
    '--db_server=db', '--db_user=coleman', '--db_password='.getenv('DB_PASSWORD'),
    '--db_name=coleman', '--db_clear=0', '--prefix=qlo_', '--name=560 Coleman',
    '--country=us', '--timezone=America/Los_Angeles', '--email=host@coleman.example',
    '--password='.getenv('ADMIN_PASSWORD'), '--firstname=Coleman', '--lastname=Host',
    '--newsletter=0', '--ssl=1');
$argc = count($argv);
require $root.'/install-cli/index_cli.php';
