web: php -S 0.0.0.0:$PORT -t public server.php
worker: php artisan queue:work --tries=3 --backoff=10 --timeout=60
