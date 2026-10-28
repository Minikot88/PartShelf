#!/bin/sh
set -eu
case "${PORT:-80}" in ''|*[!0-9]*) echo 'PORT must be numeric' >&2; exit 1;; esac
if [ "${PORT:-80}" -ne 80 ]; then
  sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi
# Schema migration, seeding and APP_KEY generation belong to explicit release jobs.
exec "$@"
