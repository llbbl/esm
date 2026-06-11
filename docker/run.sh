#!/usr/bin/env bash
# Resolve dependencies and run the suite on the container's PHP version, fully
# isolated from the host. The repo is mounted read-only at /src; we copy the
# sources out (minus vendor/.git) so the per-version `composer update` and its
# rewritten composer.lock/vendor stay INSIDE the container and never touch the
# host's 8.4-resolved lock.
set -euo pipefail

ver="$(php -r 'echo PHP_VERSION;')"
echo "──────── PHP ${ver} ────────"

work=/app
tar -C /src --exclude=./vendor --exclude=./.git --exclude=./node_modules -cf - . \
  | tar -C "$work" -xf -
cd "$work"

# Pest 3 requires PHP >= 8.2; below that we can only syntax-check the floor.
if php -r 'exit(PHP_VERSION_ID < 80200 ? 0 : 1);'; then
  echo "PHP < 8.2 — Pest 3 unavailable; syntax-linting src/ instead."
  find src -name '*.php' -print0 | xargs -0 -n1 php -l
  echo "✓ src/ parses on PHP ${ver}"
else
  composer update --no-interaction --no-progress --prefer-dist
  vendor/bin/pest
fi
