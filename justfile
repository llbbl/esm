# enum-state-machine — task runner
# Run `just` with no args to list available recipes.

# PHP toolchain recipes (mise + Homebrew): `just php setup`, `just php latest`,
# `just php bump`, `just php floor`, `just php version`.
mod php 'php.justfile'

# Legacy-PHP test matrix via Docker (8.1–8.3): `just legacy all`, `just legacy run 8.2`.
mod legacy 'legacy.justfile'

# Run every PHP command through the mise-pinned interpreter.
php_bin := "mise exec -- php"

# Show available recipes.
default:
    @just --list

# Install Composer dependencies.
install:
    composer install

# Update Composer dependencies.
update:
    composer update

# Run the test suite (Pest).
test *ARGS:
    {{php_bin}} vendor/bin/pest {{ARGS}}

# Run tests with coverage (requires Xdebug or PCOV).
coverage:
    {{php_bin}} vendor/bin/pest --coverage

# Static analysis at PHPStan max.
stan:
    {{php_bin}} vendor/bin/phpstan analyse

# Full quality gate: static analysis + tests. Mirrors the review gate.
qa: stan test

# ============================================================================
# Release
#
# Packagist publishes from git tags — composer.json has NO `version` field, so
# a release is driven by a `vX.Y.Z` tag. The Release workflow cuts patch/minor
# automatically on push to main and requires an explicit major dispatch.
# ============================================================================

# Show the latest released version (most recent git tag).
released:
    @git describe --tags --abbrev=0 2>/dev/null || echo "none yet"

# Dispatch the Release workflow on main for the requested bump level.
[private]
_release level:
    gh workflow run release.yml --ref main -f bump={{level}}

# Request a patch release (x.y.Z+1).
release-patch:
    @just _release patch

# Request a minor release (x.Y+1.0).
release-minor:
    @just _release minor

# Request a major release (X+1.0.0).
[positional-arguments]
release-major tag:
    gh workflow run release.yml --ref main -f bump=major -f major_tag="$1"
