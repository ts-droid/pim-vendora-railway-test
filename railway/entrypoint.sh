#!/usr/bin/env bash
set -e

# Railway entrypoint for pim-vendora
#
# Boot flow:
#  1. Detect whether the DB has been pre-populated (e.g. via mysqldump import).
#     If articles.id > 0 exists → skip migrations entirely, skip seed, go to serve.
#     Otherwise → run migrations + seed our test fixtures.
#  2. Start the Laravel dev server on $PORT.
#
# Rationale: Anton may import a schema+data dump from the production Vendora
# DB into this Railway MySQL. Running our migrations on top of that would fail
# if the schemas have drifted. The articles-count check is a cheap sentinel
# for "the DB is already real".

cd /var/www/html

echo "=== Laravel boot ==="

# ──────────────────────────────────────────────────────────────────────────
# SAFETY: block all outbound syncs to production systems.
# This instance is a test replica and must never hit VismaNet,
# MailerLite, Vendora Admin, etc. with real data.
# ──────────────────────────────────────────────────────────────────────────
echo "=== Safety: blocking outbound syncs ==="
# Force APP_ENV away from production so scheduled jobs that guard on
# App::environment('production') never fire. Railway scheduler isn't
# started by us, but this is defense-in-depth.
export APP_ENV=testing

# Hard-coded kill switch for Article::saved/::updated/::created hooks that
# chain into DispatchArticleUpdate → UpdateArticleJob → VismaNet/WGR/etc.
# Checked directly in app/Models/Article.php, so this survives even if
# the DB-level wgr_is_active flag is flipped by an imported dump.
export DISABLE_OUTBOUND_SYNCS=1

# Force the sync kill switch off at the DB level, overriding anything
# imported from a production dump. Silent no-op if configs table does
# not exist yet (first boot on empty DB).
php -r "
    try {
        \$pdo = new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        \$exists = \$pdo->query(\"SHOW TABLES LIKE 'configs'\")->rowCount() > 0;
        if (\$exists) {
            \$pdo->exec(\"REPLACE INTO configs (\`key\`, value, created_at, updated_at) VALUES ('wgr_is_active', '0', NOW(), NOW())\");
            echo \"  - wgr_is_active forced to 0\n\";
        } else {
            echo \"  - configs table not present yet (empty DB)\n\";
        }
    } catch (\\Throwable \$e) {
        echo \"  - sync kill switch setup skipped: \" . \$e->getMessage() . \"\n\";
    }
"

# Cache config for speed (safe to re-run)
php artisan config:clear
php artisan config:cache
php artisan route:cache || true
# Skip view:cache — Laravel Pulse package ships views without being
# publishable without DB, so caching blows up before migrate has run.
# Views cache lazily on first request instead, which is fine for
# a test instance.

# Detect existing data
HAS_DATA=$(
    php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            \$tableExists = \$pdo->query(\"SHOW TABLES LIKE 'articles'\")->rowCount() > 0;
            if (!\$tableExists) { echo 'no'; exit; }
            \$count = (int) \$pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
            echo \$count > 0 ? 'yes' : 'no';
        } catch (\\Throwable \$e) {
            echo 'no';
        }
    "
)

if [ "$HAS_DATA" = "yes" ]; then
    echo "=== DB already has articles — skipping migrations + seed ==="
    echo "    (import from mysqldump detected)"

    # Only seed the API key if missing, so /pricing URLs work
    php artisan tinker --execute="
        if (\App\Models\ApiKey::where('key', 'pim-vendora-dev-key')->doesntExist()) {
            \App\Models\ApiKey::create([
                'key' => 'pim-vendora-dev-key',
                'description' => 'Railway smoke test',
            ]);
            echo \"Added api_key: pim-vendora-dev-key\n\";
        }
    " 2>/dev/null || echo "Note: could not add api_key via tinker (not critical)"
else
    echo "=== Empty DB — running migrations ==="
    php artisan migrate --force --no-interaction

    echo "=== Seeding test fixtures ==="
    php artisan tinker --execute="
        if (\App\Models\ApiKey::where('key', 'pim-vendora-dev-key')->doesntExist()) {
            \App\Models\ApiKey::create([
                'key' => 'pim-vendora-dev-key',
                'description' => 'Railway smoke test',
            ]);
            echo \"Seeded api_key\n\";
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
            echo \"Seeded TEST-001\n\";
        }

        if (\App\Models\Article::where('article_number', 'BUN-001')->doesntExist()) {
            \App\Models\Article::create([
                'article_number' => 'BUN-001',
                'description' => 'Test Starter Bundle',
                'article_type' => 'Bundle',
                'standard_reseller_margin' => 35.0,
                'minimum_margin' => 25.0,
            ]);
            echo \"Seeded BUN-001\n\";
        }
    " || echo "Seeding skipped"
fi

echo "=== Starting server on port ${PORT:-8080} ==="
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
