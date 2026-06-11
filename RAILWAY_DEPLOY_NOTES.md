# Railway Deploy Notes

Project ini disiapkan untuk Railway + Supabase PostgreSQL.

## Railway Variables minimal

Set variables berikut di Railway > Service > Variables:

- APP_NAME=Studlent
- APP_ENV=production
- APP_KEY=base64:... hasil `php artisan key:generate --show`
- APP_DEBUG=false
- APP_URL=https://domain-railway-kamu.up.railway.app
- LOG_CHANNEL=stderr
- LOG_STDERR_FORMATTER=Monolog\\Formatter\\JsonFormatter
- DB_CONNECTION=pgsql
- DB_HOST=host Supabase pooler
- DB_PORT=6543
- DB_DATABASE=postgres
- DB_USERNAME=postgres.project_ref
- DB_PASSWORD=password database Supabase
- DB_SCHEMA=public
- DB_SSLMODE=require
- SESSION_DRIVER=file
- CACHE_STORE=file
- QUEUE_CONNECTION=sync
- MIDTRANS_SERVER_KEY=...
- MIDTRANS_CLIENT_KEY=...
- MIDTRANS_IS_PRODUCTION=false atau true sesuai mode Midtrans

Jangan jalankan migration kalau tabel sudah dibuat manual di Supabase.
