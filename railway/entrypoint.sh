#!/usr/bin/env bash
set -e

# Railway entrypoint for pim-vendora
#
# Runs:
#  - Laravel migrations (idempotent)
#  - Seeds a test API key + 2 articles on first boot so the calculator
#    UI can be tested without manual DB setup
#  - Starts the PHP built-in server on $PORT

cd /var/www/html

echo "=== Laravel boot ==="

# Cache config for speed (safe to re-run)
php artisan config:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

# Run migrations — idempotent, fine on every deploy
echo "=== Running migrations ==="
php artisan migrate --force --no-interaction

# Seed test data only once — wrapped in a guard so existing data isn't overwritten
echo "=== Seeding test data (if needed) ==="
php artisan tinker --execute="
if (\App\Models\ApiKey::where('key', 'pim-vendora-dev-key')->doesntExist()) {
    \App\Models\ApiKey::create([
        'key' => 'pim-vendora-dev-key',
        'description' => 'Local dev / Railway smoke test',
    ]);
    echo \"Seeded API key: pim-vendora-dev-key\n\";
}

if (\App\Models\Article::where('article_number', 'TEST-001')->doesntExist()) {
    \App\Models\Article::create([
        'article_number' => 'TEST-001',
        'description' => 'Test Wireless Charger',
        'article_type' => 'FinishedGoodItem',
        'ean' => '7350167970017',
        'cost_price_avg' => 150.00,
        'rek_price_SEK' => 799.00,
        'standard_reseller_margin' => 35.0,
        'minimum_margin' => 20.0,
    ]);
    echo \"Seeded article: TEST-001\n\";
}

if (\App\Models\Article::where('article_number', 'BUN-001')->doesntExist()) {
    \App\Models\Article::create([
        'article_number' => 'BUN-001',
        'description' => 'Test Starter Bundle',
        'article_type' => 'Bundle',
        'standard_reseller_margin' => 35.0,
        'minimum_margin' => 25.0,
    ]);
    echo \"Seeded bundle: BUN-001\n\";
}
" || echo "Seeding skipped (tinker may not be available in prod)"

echo "=== Starting server on port ${PORT:-8080} ==="
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
