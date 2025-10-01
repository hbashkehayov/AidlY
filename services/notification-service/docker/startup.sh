#!/bin/sh
# Start supervisor which manages nginx and php-fpm
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor.conf -n