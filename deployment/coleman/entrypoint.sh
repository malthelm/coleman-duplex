#!/bin/sh
set -eu
if [ ! -f /var/www/html/config/settings.inc.php ]; then
    cp -a /opt/qloapps/. /var/www/html/
fi
# Update only our module; installation state and uploaded files stay persistent.
cp -a /opt/qloapps/modules/colemanstay /var/www/html/modules/
cp -a /opt/qloapps/deployment /var/www/html/
if [ -d /var/www/html/admin-dev ] && [ ! -d /var/www/html/host-admin ]; then
    mv /var/www/html/admin-dev /var/www/html/host-admin
fi
# Installer remains available only to CLI, blocked from HTTP by Apache.
if [ -d /var/www/html/install-dev ]; then
    mv /var/www/html/install-dev /var/www/html/install-cli
fi
chown -R www-data:www-data /var/www/html
exec docker-php-entrypoint "$@"
