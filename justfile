# enum-state-machine — task runner
# Run `just` with no args to list available recipes.

# Use the mise-pinned PHP for every recipe.
php := "mise exec -- php"

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
    {{php}} vendor/bin/pest {{ARGS}}

# Run tests with coverage (requires Xdebug or PCOV).
coverage:
    {{php}} vendor/bin/pest --coverage

# Static analysis at PHPStan max.
stan:
    {{php}} vendor/bin/phpstan analyse

# Full quality gate: static analysis + tests. Mirrors the review gate.
qa: stan test

# Print the active PHP version.
php-version:
    {{php}} --version
