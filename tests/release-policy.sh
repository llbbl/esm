#!/usr/bin/env bash
set -euo pipefail

project=$(cd "$(dirname "$0")/.." && pwd)
policy="$project/.github/scripts/release-version.sh"
sandbox=$(mktemp -d)
trap 'rm -rf "$sandbox"' EXIT
export GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null
git init -q --template= "$sandbox/repo"
cd "$sandbox/repo"
git config user.name 'Release Test'
git config user.email 'release-test@example.invalid'

commit() {
  git commit -q --allow-empty "$@"
}

expect_next() {
  local expected=$1 actual
  shift
  actual=$(bash "$policy" next "$@")
  if [[ "$actual" != "$expected" ]]; then
    printf 'next %s: expected <%s>, got <%s>\n' "$*" "$expected" "$actual" >&2
    exit 1
  fi
}

expect_failure() {
  local reason=$1 result
  shift
  if result=$(bash "$policy" "$@" 2>&1) || [[ "$result" != *"$reason"* ]]; then
    printf '%s: expected failure <%s>, got <%s>\n' "$*" "$reason" "$result" >&2
    exit 1
  fi
}

validate() {
  bash "$policy" validate-tag "$@"
}

expect_resumed() {
  local expected=$1 run_id=$2 actual
  actual=$(bash "$policy" resume-tag "$run_id")
  if [[ "$actual" != "$expected" ]]; then
    printf 'resume-tag %s: expected <%s>, got <%s>\n' "$run_id" "$expected" "$actual" >&2
    exit 1
  fi
}

commit -m 'chore: initialize'
expect_next v0.0.1 push auto
validate push auto '' v0.0.1

git tag -a v0.1.0 -m 'Release v0.1.0'
expect_next '' push auto
expect_next '' workflow_dispatch auto
commit -m 'fix: clarify BREAKING CHANGE documentation'
expect_next v0.1.1 push auto
expect_next v0.1.1 push major
validate push auto '' v0.1.1
expect_failure 'requires a manual Release run' validate-tag push auto '' v1.0.0
expect_failure 'requires a manual Release run' validate-tag push major v1.0.0 v1.0.0
expect_failure 'requires a manual Release run' validate-tag workflow_dispatch auto '' v1.0.0
expect_failure 'requires a manual Release run' validate-tag workflow_dispatch minor '' v1.0.0
expect_failure 'Invalid stable release tag' validate-tag push auto '' v00.1.1
expect_failure 'Invalid stable release tag' validate-tag push auto '' v0.1.1-rc.1
expect_failure 'already exists' validate-tag push auto '' v0.1.0

commit -m 'feat(parser): add a reader'
git tag v9.0.0-rc.1
git tag build
expect_next v0.2.0 push auto
expect_next v0.1.1 workflow_dispatch patch
validate push auto '' v0.2.0

commit -m 'fix!: replace the parser'
expect_next '' push auto
expect_next '' workflow_dispatch auto
expect_next '' workflow_dispatch minor
expect_failure 'must confirm v1.0.0' next workflow_dispatch major ''
expect_failure 'must confirm v1.0.0' next workflow_dispatch major v2.0.0
expect_next v1.0.0 workflow_dispatch major v1.0.0
validate workflow_dispatch major v1.0.0 v1.0.0
expect_failure 'must match the confirmed tag' validate-tag workflow_dispatch major v1.0.0 v1.0.1
expect_failure 'must match the confirmed tag' validate-tag workflow_dispatch major v1.0.0 v2.0.0
git tag -a v1.0.0 -m 'Release v1.0.0' -m 'Release-Run-ID: 101'
expect_resumed v1.0.0 101
expect_resumed '' 10
expect_failure 'must confirm v2.0.0' next workflow_dispatch major v1.0.0

commit -m 'feat: change the API' -m 'BREAKING CHANGE: the method now returns a string'
expect_resumed '' 101
expect_next '' push auto
expect_next '' workflow_dispatch patch
expect_next v2.0.0 workflow_dispatch major v2.0.0
validate workflow_dispatch major v2.0.0 v2.0.0
git tag v2.0.0

commit -m 'feat(api)!: rename the method'
expect_next '' push auto
git tag v2.0.1

commit -m 'fix: change the input' -m 'BREAKING-CHANGE: the old input is unsupported'
expect_next '' push auto
git tag v2.0.2

commit -m 'fix: correct an error message' -m 'Release-Run-ID: 200'
expect_next v2.0.3 push auto
validate push auto '' v2.0.3
expect_failure 'requires a manual Release run' validate-tag push auto '' v1.9.0
expect_failure 'requires a manual Release run' validate-tag push auto '' v3.0.0
git tag v2.0.3
expect_resumed '' 200
git tag -a v2.0.4 -m 'Release v2.0.4' -m 'Release-Run-ID: 201'
expect_next '' push auto
expect_resumed v2.0.4 201

git tag v2.0.18446744073709551616
commit -m 'fix: correct a typo'
expect_failure 'Version component is too large to increment' next push auto
git tag v2.18446744073709551616.0
commit -m 'feat: add a check'
expect_failure 'Version component is too large to increment' next push auto
git tag v18446744073709551616.0.0
expect_failure 'Version component is too large to increment' next workflow_dispatch major v1.0.0

printf 'Release policy tests passed.\n'
