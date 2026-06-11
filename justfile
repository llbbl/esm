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
# a release is just a `vX.Y.Z` tag (no bump commit). CI (.github/release.yml)
# cuts patch/minor automatically on push to main; MAJOR is manual — use
# `just release-major` here, or run the Release workflow with bump=major.
# ============================================================================

# Show the latest released version (most recent git tag).
released:
    @git describe --tags --abbrev=0 2>/dev/null || echo "none yet"

# Compute the next vX.Y.Z for LEVEL (patch|minor|major) from the latest tag.
[private]
_next level:
    #!/usr/bin/env bash
    set -euo pipefail
    cur="$(git describe --tags --abbrev=0 2>/dev/null || echo v0.0.0)"
    IFS='.' read -r MA MI PA <<<"${cur#v}"
    case "{{level}}" in
      patch) PA=$((PA + 1)) ;;
      minor) MI=$((MI + 1)); PA=0 ;;
      major) MA=$((MA + 1)); MI=0; PA=0 ;;
      *) echo "unknown level: {{level}} (want patch|minor|major)" >&2; exit 1 ;;
    esac
    printf 'v%s.%s.%s\n' "$MA" "$MI" "$PA"

# Run QA, create an annotated tag for LEVEL, then push it (Packagist auto-syncs).
[private]
_release level:
    #!/usr/bin/env bash
    set -euo pipefail
    next="$(just _next {{level}})"
    cur="$(git describe --tags --abbrev=0 2>/dev/null || echo none)"
    echo "Release: ${cur} → ${next}"
    just qa
    git tag -a "$next" -m "Release $next"
    git push origin "$next"
    echo "✓ pushed $next"

# Cut a patch release (x.y.Z+1).
release-patch:
    @just _release patch

# Cut a minor release (x.Y+1.0).
release-minor:
    @just _release minor

# Cut a MAJOR release (X+1.0.0) — the manual path CI never takes automatically.
release-major:
    @just _release major
