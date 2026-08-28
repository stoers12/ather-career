#!/bin/sh
set -eu

php /var/www/app/scripts/check-production-security.php
exec apache2-foreground
