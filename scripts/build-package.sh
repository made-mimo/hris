#!/usr/bin/env bash
# Builds the production install package (zip) from this working tree.
# Usage: scripts/build-package.sh [output-dir]   (default: current directory's parent project folder)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT}"
STAMP="$(date +%Y%m%d)-$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo nogit)"
NAME="hris-production-$STAMP"
STAGE="$(mktemp -d)/$NAME"
mkdir -p "$STAGE"

echo "==> Building frontend assets"
(cd "$ROOT" && npm run build >/dev/null)

echo "==> Copying application files"
rsync -a \
  --exclude='.git' --exclude='.github' --exclude='.claude' --exclude='.env' --exclude='.env.backup' \
  --exclude='node_modules' --exclude='vendor' --exclude='tests' --exclude='scripts' \
  --exclude='.phpunit.result.cache' --exclude='.phpunit.cache' --exclude='phpunit.xml' \
  --exclude='storage/app/*' --exclude='storage/framework/*' --exclude='storage/logs/*' --exclude='storage/*.key' \
  --exclude='public/storage' --exclude='public/hot' --exclude='public/temp-test' --exclude='public/fonts-manifest.dev.json' \
  --exclude='database/database.sqlite*' --exclude='database/seeders/HrisDemoSeeder.php' \
  --exclude='*.docx' --exclude='PLAN.md' --exclude='AGENTS.md' --exclude='CLAUDE.md' --exclude='README.md' \
  --exclude='boost.json' --exclude='.mcp.json' --exclude='package.json' --exclude='package-lock.json' \
  --exclude='vite.config.js' --exclude='.editorconfig' --exclude='.env.example' --exclude='.npmrc' --exclude='.gitattributes' --exclude='.gitignore' \
  --exclude='bootstrap/cache/*.php' --exclude='*.log' --exclude='livewire_old_wwwdata*' \
  "$ROOT"/ "$STAGE"/

# The demo seeder (known-password accounts) is not shipped; DatabaseSeeder keeps only system reference data.
cat > "$STAGE/database/seeders/DatabaseSeeder.php" <<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** System reference data only. Production installs normally use `php artisan hris:install` instead. */
    public function run(): void
    {
        $this->call(RbacSeeder::class);
        $this->call(WorkflowSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(MasterListSeeder::class);
        $this->call(RenewalTypeSeeder::class);
        (new HelpdeskCategorySeeder)->seedConfidential();
    }
}
PHP

echo "==> Installing production dependencies (no dev packages)"
cp "$ROOT/composer.json" "$ROOT/composer.lock" "$STAGE"/
(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet)
rm -f "$STAGE"/bootstrap/cache/*.php

echo "==> Creating empty runtime directories"
mkdir -p "$STAGE"/storage/app/{public,private} "$STAGE"/storage/framework/{cache/data,sessions,views} "$STAGE"/storage/logs "$STAGE"/bootstrap/cache
for d in storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  touch "$STAGE/$d/.gitkeep"
done
chmod +x "$STAGE/artisan"

echo "==> Zipping"
(cd "$(dirname "$STAGE")" && zip -qr "$OUT/$NAME.zip" "$NAME")
(cd "$OUT" && sha256sum "$NAME.zip" > "$NAME.zip.sha256")
echo "Built $OUT/$NAME.zip"
