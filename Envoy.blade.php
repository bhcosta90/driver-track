@setup
    // Application directory on the remote host(s)
    $app_dir = '/var/www/drivertrack.com.br';

    // Git branch to deploy
    $branch = 'main';
@endsetup

@php
    // Load local .env so we can read DEPLOY_SERVERS_WEB when running envoy locally
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable(__DIR__)->load();
    }

    $serversEnv = getenv('DEPLOY_SERVERS_WEB') ?: ($_ENV['DEPLOY_SERVERS_WEB'] ?? null);

    if (blank($serversEnv)) {
        throw new Exception('DEPLOY_SERVERS_WEB is not set in the .env file');
    }

    // Allow comma separated list, trim each entry
    $webServer = array_values(array_filter(array_map('trim', explode(',', (string) $serversEnv))));
@endphp

@servers(['web' => $webServer])

@story('deploy', ['on' => 'web'])
pause-horizon
update-code
node-install-dependencies
php-install-dependencies
php-artisan-optimize
php-artisan-migrate
start-horizon
finish-deploy
@endstory

@story('reset', ['on' => 'web'])
pause-horizon
reset-database
start-horizon
@endstory

@task('pause-horizon')
cd {{ $app_dir }}
echo "🔄 Starting deployment..."

echo "⏸️ Pausing Horizon..."
php artisan horizon:pause

echo "⏳  Waiting for running jobs to finish..."
while php artisan horizon:status | grep -q running; do
  echo "⏳  Still processing jobs... waiting 5s"
  sleep 5
done
@endtask

@task('finish-deploy')
echo "✅ Deployment finished successfully!"
@endtask

@task('reset-database')
cd {{ $app_dir }}
echo "🧹 Resetting database (fresh migrate + seed)..."
php artisan migrate:fresh --seed --force
@endtask

@task('start-horizon')
cd {{ $app_dir }}
echo "♻️ Restarting Horizon..."
php artisan horizon:terminate

echo "▶️ Resuming Horizon..."
php artisan horizon:continue
@endtask

@task('update-code')
cd {{ $app_dir }}
git checkout {{ $branch }} -f
git pull origin {{ $branch }}
@endtask

@task('php-install-dependencies')
cd {{ $app_dir }}

if git diff --name-only HEAD@{1} HEAD | grep -qE 'composer\.lock|composer\.json'; then
composer install --no-ansi --no-dev --no-interaction --no-plugins --no-progress --no-scripts --optimize-autoloader
else
echo "⏭️ composer.lock and composer.json did not change; skipping Composer install."
fi

rm -f bootstrap/cache/{config.php,events.php,packages.php,routes-v7.php,services.php}
@endtask

@task('node-install-dependencies')
cd {{ $app_dir }}

if git diff --name-only HEAD@{1} HEAD | grep -qE 'package-lock\.json|package\.json'; then
npm install
else
echo "⏭️ package-lock.json and package.json did not change; skipping NPM install."
fi
npm run build
@endtask

@task('php-artisan-migrate')
cd {{ $app_dir }}
php artisan migrate --force
@endtask

@task('php-artisan-optimize')
cd {{ $app_dir }}
php artisan optimize
@endtask

@task('remove-log')
cd {{ $app_dir }}
rm -f storage/logs/*.log
@endtask