#!/usr/bin/env bash
# File: Runs the reproducible local quality gate used before a commit or release.
# Functions: sequential PHP, JavaScript, build, audit, and repository validation commands.
# State: process exit status only; exact command locations are indexed in docs/code-index.md.

set -euo pipefail

composer install --no-interaction --prefer-dist
composer audit --locked
vendor/bin/pint --test
php artisan test
npm ci
npm audit --audit-level=high
npm test
npm run build
php tools/validate_repo.php --check
