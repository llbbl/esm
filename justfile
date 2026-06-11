# enum-state-machine — task runner
# Run `just` with no args to list available recipes.

# PHP toolchain recipes (mise + Homebrew): `just php setup`, `just php latest`,
# `just php bump`, `just php floor`, `just php version`.
mod php 'php.justfile'

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
