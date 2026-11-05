#!/usr/bin/env bash
#
# Run on the server over SSH, from the project root: bash deploy.sh
# Assets are committed in public/build, because the host has no Node. Set
# BUILD_ASSETS=1 on a server that has Node (a VPS) to rebuild them here.
#
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer)}"
BRANCH="${BRANCH:-main}"

# composer.json turns off Composer's runtime check, so the version is enforced here.
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);' || {
    echo "PHP 8.4.1 or newer is required. Set PHP_BIN, for example /opt/alt/php84/usr/bin/php." >&2
    exit 1
}

[ -f .env ] || { echo ".env is missing. Copy .env.example and fill it in first." >&2; exit 1; }
grep -q '^APP_DEBUG=false' .env || { echo "APP_DEBUG must be false in production." >&2; exit 1; }
grep -q '^APP_KEY=.\+' .env || { echo "APP_KEY is empty. Run: artisan key:generate" >&2; exit 1; }
grep -q '^MAIL_FROM_ADDRESS=.\+' .env || echo "Warning: MAIL_FROM_ADDRESS is empty, so emails will fail unless it is set in Super Admin, Integrations." >&2

echo "==> Maintenance mode on"
"$PHP_BIN" artisan down --retry=30 || true
# Comes back up even if a later step fails, so a bad deploy cannot leave the site dark.
trap '"$PHP_BIN" artisan up || true' EXIT

echo "==> Fetching ${BRANCH}"
git fetch --prune origin
git reset --hard "origin/${BRANCH}"

echo "==> Installing PHP dependencies"
# Run through PHP_BIN, because the command-line default can be an older PHP than the website uses.
"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [ "${BUILD_ASSETS:-0}" = "1" ]; then
    echo "==> Building assets"
    npm ci --ignore-scripts
    npm run build
fi

echo "==> Migrating"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan cureplus:sync-rbac
"$PHP_BIN" artisan cureplus:seed-defaults

echo "==> Caching"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

echo "==> Restarting workers"
"$PHP_BIN" artisan queue:restart

echo "==> Done"
