# PHP toolchain (mise + Homebrew). Imported by the root justfile as `mod php`.
# Invoke as: `just php <recipe>` — e.g. `just php setup`, `just php latest`.

php := "mise exec -- php"

# List php recipes.
default:
    @just --list php

# One-time toolchain bootstrap (macOS): install the Homebrew C libraries the
# PHP build links against, then compile the pinned PHP (mise.toml) against
# openssl@3.
#
# NOTE: mise's php plugin compiles from source and (as of now) hardcodes the
# EOL `openssl@1.1`, so the build ships PHP with no working https/TLS wrapper.
# This points the build at `openssl@3` via `brew --prefix`. Overriding
# PHP_CONFIGURE_OPTIONS also replaces the plugin's os_based lib block, so we
# re-supply openssl + Homebrew libiconv (macOS's stock libiconv fails configure).
# One-time bootstrap: Homebrew libs + build the pinned PHP against openssl@3.
setup:
    #!/usr/bin/env bash
    set -euo pipefail
    brew install gd icu4c libzip oniguruma libxml2 libiconv
    ssl="$(brew --prefix openssl@3)"
    iconv="$(brew --prefix libiconv)"
    export PHP_CONFIGURE_OPTIONS="--with-openssl=${ssl} --with-iconv=${iconv}"
    export PKG_CONFIG_PATH="${ssl}/lib/pkgconfig:$(brew --prefix icu4c)/lib/pkgconfig:$(brew --prefix libzip)/lib/pkgconfig:$(brew --prefix gd)/lib/pkgconfig:$(brew --prefix oniguruma)/lib/pkgconfig:$(brew --prefix libxml2)/lib/pkgconfig:${PKG_CONFIG_PATH:-}"
    mise install
    echo "── verifying build ──"
    mise exec -- php -v
    # Capture into a var + here-string: piping `php -m` into `grep -q` makes grep
    # close the pipe early, php -m takes SIGPIPE, and `pipefail` then reports a
    # false failure even when openssl IS present.
    mods="$(mise exec -- php -m)"
    if grep -iqx 'openssl' <<<"$mods"; then
      echo "✓ openssl extension present"
    else
      echo "✗ openssl extension MISSING — build did not link openssl@3"; exit 1
    fi

# Print the active PHP version.
version:
    {{php}} --version

# Filters out the plugin's non-semver RC tags (e.g. 8.4.22RC1).
# Show the latest stable (non-RC) PHP mise can build, vs the current pin.
latest:
    #!/usr/bin/env bash
    set -euo pipefail
    pin="$(grep -E '^php[[:space:]]*=' mise.toml | sed -E 's/.*"([^"]+)".*/\1/')"
    minor="$(printf '%s' "$pin" | grep -oE '^[0-9]+\.[0-9]+')"
    stable="$(mise ls-remote php | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$')"
    latest_line="$(printf '%s\n' "$stable" | grep -E "^${minor}\." | sort -V | tail -1)"
    latest_all="$(printf '%s\n' "$stable" | sort -V | tail -1)"
    printf 'pinned (mise.toml):    %s\n' "$pin"
    printf 'latest stable %s.x:    %s\n' "$minor" "$latest_line"
    printf 'latest stable overall: %s\n' "$latest_all"
    if [ "$pin" = "$latest_line" ]; then
      printf '\n✓ pin is the latest %s.x stable\n' "$minor"
    else
      printf '\n→ newer %s.x stable: %s — run: just php bump\n' "$minor" "$latest_line"
    fi

# Accepts an explicit VERSION (e.g. `just php bump 8.5.7`); refuses RC tags.
# Pin mise.toml to the latest stable in the current minor line (or to VERSION).
bump VERSION="":
    #!/usr/bin/env bash
    set -euo pipefail
    pin="$(grep -E '^php[[:space:]]*=' mise.toml | sed -E 's/.*"([^"]+)".*/\1/')"
    target="{{VERSION}}"
    if [ -z "$target" ]; then
      minor="$(printf '%s' "$pin" | grep -oE '^[0-9]+\.[0-9]+')"
      target="$(mise ls-remote php | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' | grep -E "^${minor}\." | sort -V | tail -1)"
    fi
    if ! printf '%s' "$target" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
      echo "refusing to pin '$target' — not a stable X.Y.Z version"; exit 1
    fi
    if ! mise ls-remote php | grep -Fxq "$target"; then
      echo "version $target not found in 'mise ls-remote php'"; exit 1
    fi
    if [ "$target" = "$pin" ]; then
      printf '✓ already pinned to the latest stable: %s\n' "$pin"; exit 0
    fi
    sed -i '' -E "s/^(php[[:space:]]*=[[:space:]]*).*/\1\"${target}\"/" mise.toml
    printf 'mise.toml: php %s → %s\n' "$pin" "$target"
    printf 'next: just php setup   # build the new version\n'

# Per endoflife.date's `support` date; stays within the current major; no RC.
# Show the oldest php.net ACTIVE-support PHP — the library's compatibility floor.
floor:
    #!/usr/bin/env bash
    set -euo pipefail
    command -v jq >/dev/null || { echo "needs jq: brew install jq"; exit 1; }
    pin="$(grep -E '^php[[:space:]]*=' mise.toml | sed -E 's/.*"([^"]+)".*/\1/')"
    major="$(printf '%s' "$pin" | cut -d. -f1)"
    today="$(date +%F)"
    row="$(curl -fsSL https://endoflife.date/api/php.json \
      | jq -r --arg today "$today" --arg major "$major" '
          map(select((.support|type=="string") and (.support > $today)))
          | map(select(.cycle|startswith($major + ".")))
          | sort_by(.cycle|split(".")|map(tonumber))
          | (.[0] // empty) | "\(.cycle)\t\(.support)\t\(.latest)"')"
    [ -n "$row" ] || { echo "could not determine an actively-supported $major.x branch"; exit 1; }
    minor="$(printf '%s' "$row" | cut -f1)"
    sup="$(printf '%s' "$row" | cut -f2)"
    patch="$(printf '%s' "$row" | cut -f3)"
    printf 'current pin:                  %s\n' "$pin"
    printf 'oldest active-support %s.x:    %s   (active support until %s)\n' "$major" "$minor" "$sup"
    printf 'latest stable patch:          %s\n' "$patch"
    if mise ls-remote php | grep -Fxq "$patch"; then
      printf '\napply (active-support floor): just php bump %s\n' "$patch"
    else
      printf '\n(note: %s not yet in `mise ls-remote php`)\n' "$patch"
    fi
