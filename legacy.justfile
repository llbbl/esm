# Legacy-PHP test matrix via Docker (GitHub CI covers the supported 8.4/8.5).
# Imported by the root justfile as `mod legacy`. Invoke as `just legacy <recipe>`.

# List legacy recipes.
default:
    @just --list legacy

# Run the suite on every legacy PHP version (8.1 lints; 8.2/8.3 full Pest).
all:
    docker compose run --rm php81
    docker compose run --rm php82
    docker compose run --rm php83

# Run one version, e.g. `just legacy run 8.2`.
run VERSION:
    docker compose run --rm php{{ replace(VERSION, ".", "") }}

# Rebuild the legacy images (e.g. after changing docker/Dockerfile).
build:
    docker compose build
