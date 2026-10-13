# Lucky Catch Draw

A lucky-draw campaign system: admins upload an Excel sheet of participants, review and
confirm the list, then run a theatrical big-screen animated draw. The winner is always
selected on the server inside a locked database transaction — the browser only ever
reveals what the server already decided.

**Stack**: Laravel 12 (PHP 8.3+), MySQL 8, Filament 4 (admin panel), Inertia.js + React +
TypeScript + Tailwind (Draw Screen & print report), Pest (tests), `openspout` (streaming
Excel I/O), `spatie/laravel-permission` (roles), database queue driver.

## Local setup

Prerequisites: PHP 8.3+, Composer, Node 20+, MySQL 8.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials plus the seeded super-admin account:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lucky_catch_draw
DB_USERNAME=root
DB_PASSWORD=

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me
```

Create the database, then migrate and seed:

```bash
mysql -u root -p -e "CREATE DATABASE lucky_catch_draw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
```

Seeding creates the three roles (`super_admin`, `campaign_manager`, `viewer`), one
super-admin user from `ADMIN_EMAIL`/`ADMIN_PASSWORD`, and a demo campaign ("Khmer New
Year Lucky Draw") imported through the real import pipeline — 150 participants mixing
English and Khmer names across Facebook/YouTube/TikTok profile URLs, including 6
intentional near-duplicates to show off normalization-based dedup.

Build frontend assets and start the app:

```bash
npm run build      # or `npm run dev` while developing
php artisan serve
```

Visit `http://localhost:8000/admin` and log in with your `ADMIN_EMAIL` /
`ADMIN_PASSWORD`. The public draw screen for a campaign lives at `/draw/{campaign}` and
is reachable from the campaign's "Open Draw Screen" action once it's confirmed Ready.

### Queue worker

Imports (validation + commit) and any other background work run through the `database`
queue driver. A worker must be running for uploads to actually process:

```bash
php artisan queue:work --tries=3
```

During local development, `npm run dev` from the stock Laravel setup or running
`php artisan queue:listen` alongside `php artisan serve` is enough. In production, run
the worker under Supervisor (see below) so it restarts automatically.

### Running tests

```bash
./vendor/bin/pest
```

Tests run against a **real MySQL** database (`lucky_catch_draw_test` by default — see
`phpunit.xml`), not SQLite, because the schema relies on MySQL-specific features
(generated columns, `lockForUpdate()` row locking) that need to be verified against the
real engine. Create the test database once:

```bash
mysql -u root -p -e "CREATE DATABASE lucky_catch_draw_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Deploying to an Ubuntu VPS (Nginx + PHP-FPM + Supervisor)

These steps assume Ubuntu 22.04/24.04, a non-root deploy user, and that MySQL is
already provisioned (locally or managed).

### 1. System packages

```bash
sudo apt update
sudo apt install -y nginx mysql-client supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Deploy the code

```bash
sudo mkdir -p /var/www/lucky-catch-draw
sudo chown $USER:www-data /var/www/lucky-catch-draw
git clone <your-repo-url> /var/www/lucky-catch-draw
cd /var/www/lucky-catch-draw

composer install --no-dev --optimize-autoloader
npm ci
npm run build

cp .env.example .env
php artisan key:generate
# edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain,
# DB_*, ADMIN_EMAIL, ADMIN_PASSWORD, SESSION_DOMAIN, MAIL_*

php artisan migrate --force
php artisan db:seed --force   # first deploy only, or run specific seeders
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. PHP-FPM + Nginx

Use the distro's default PHP-FPM pool (`/etc/php/8.3/fpm/pool.d/www.conf`) or create a
dedicated pool for this app. Nginx server block
(`/etc/nginx/sites-available/lucky-catch-draw`):

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/lucky-catch-draw/public;

    index index.php;
    client_max_body_size 15m; # Excel uploads are capped at 10MB app-side

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/lucky-catch-draw /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Put this behind TLS with `certbot --nginx` (Let's Encrypt) in the usual way.

### 4. Supervisor for `php artisan queue:work`

`/etc/supervisor/conf.d/lucky-catch-draw-worker.conf`:

```ini
[program:lucky-catch-draw-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lucky-catch-draw/artisan queue:work --sleep=1 --tries=3 --max-time=3600
directory=/var/www/lucky-catch-draw
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/lucky-catch-draw/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start lucky-catch-draw-worker:*
```

`--max-time=3600` makes each worker process recycle hourly (picking up new deployed
code without a manual restart); Supervisor immediately respawns it.

### 5. Scheduler

Laravel's scheduler needs a single cron entry no matter how many scheduled tasks exist:

```bash
crontab -e -u www-data
```

```cron
* * * * * cd /var/www/lucky-catch-draw && php artisan schedule:run >> /dev/null 2>&1
```

The app doesn't ship any scheduled jobs out of the box, but this keeps the scheduler
ready for anything added later (e.g. pruning old audit logs or failed jobs) without a
further deploy step.

### 6. Redeploys

```bash
cd /var/www/lucky-catch-draw
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart lucky-catch-draw-worker:*
```

## Project structure highlights

- `app/Services/Draw/DrawService.php` — the single, server-authoritative place a winner
  is ever chosen (`random_int()` inside a locked transaction). No UI code imports this
  except through the controller.
- `app/Services/Import/` — URL normalization, column mapping, file signature checks.
- `app/Jobs/Import/` — queued streaming validation (`ValidateImportJob`) and the
  all-or-nothing commit (`CommitImportJob`).
- `app/Services/Excel/` — every spreadsheet export/import route through `ExcelSafe` to
  neutralize formula-injection payloads.
- `app/Filament/` — the admin panel (campaigns, import wizard, winner history, audit
  log, users).
- `resources/js/Pages/Draw/Show.tsx` + `resources/js/hooks/useDrawController.ts` — the
  theatrical draw screen; `resources/js/Pages/Draws/Print.tsx` — the printable report.

## Known limitations

- The draw screen's reel animation uses a fixed sample of up to 200 eligible
  participants for the spinning filler cards; very large campaigns (thousands of
  participants) won't show every name in the reel, but the actual winner is always the
  one the server selected from the full eligible pool.
- Sounds (tick/spin/win) are generated procedurally via the Web Audio API rather than
  shipped as audio files, so they're simple tones rather than produced sound effects.
- There's no built-in CDN/object storage integration; uploaded files and campaign images
  live on local disk. For a multi-server deployment, point the `local` and `public`
  disks at a shared filesystem or S3-compatible store.
- No automated email notifications (e.g. "you won") are sent — the admin shares results
  manually via the printable report or by sharing the campaign's draw history.
# lucky-catch-draw
