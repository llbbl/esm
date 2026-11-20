#!/usr/bin/env bash
set -euo pipefail

stable_tag='^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$'
command=$1

fail() {
  printf '%s\n' "$1" >&2
  exit 1
}

if [[ "$command" == resume-tag ]]; then
  run_id=$2
  tags=$(git tag --points-at HEAD)
  while IFS= read -r tag; do
    [[ "$tag" =~ $stable_tag ]] || continue
    annotation=$(git for-each-ref --format='%(objecttype)%0a%(contents:body)' "refs/tags/$tag")
    if [[ "${annotation%%$'\n'*}" == tag ]] && grep -Fxq "Release-Run-ID: $run_id" <<< "$annotation"; then
      printf '%s\n' "$tag"
      exit 0
    fi
  done <<< "$tags"
  exit 0
fi

event=$2
requested=${3:-auto}
major_tag=${4-}

latest_tag=v0.0.0
range=(HEAD)
tags=$(git tag --merged HEAD --sort=-version:refname)
while IFS= read -r tag; do
  if [[ "$tag" =~ $stable_tag ]]; then
    latest_tag=$tag
    range=("refs/tags/$tag..HEAD")
    break
  fi
done <<< "$tags"
IFS=. read -r major minor patch <<< "${latest_tag#v}"

manual_major() {
  [[ "$event" == workflow_dispatch && "$requested" == major ]]
}

increment() {
  local component=$1
  [[ ${#component} -lt 19 ]] || fail "Version component is too large to increment: $component"
  printf '%s\n' "$((component + 1))"
}

confirmed_major_tag() {
  local next_major expected
  next_major=$(increment "$major")
  expected="v$next_major.0.0"
  [[ "$major_tag" == "$expected" ]] ||
    fail "A manual major release from $latest_tag must confirm $expected."
  printf '%s\n' "$expected"
}

case "$command" in
  next)
    if manual_major; then
      confirmed_major_tag
      exit 0
    fi

    subjects=$(git log --format=%s "${range[@]}")
    bodies=$(git log --format=%b "${range[@]}")
    if grep -qE '^[[:alpha:]][[:alnum:]_-]*(\([^)]*\))?!:|^BREAKING[ -]CHANGE:' <<< "$subjects" ||
       grep -qE '^BREAKING[ -]CHANGE:' <<< "$bodies"; then
      printf '::notice title=Major release pending::Run Release on main with bump=major and confirm the next major tag for breaking changes since %s.\n' "$latest_tag" >&2
      exit 0
    fi

    level=auto
    if [[ "$event" == workflow_dispatch ]]; then
      level=$requested
    fi
    if [[ "$level" == auto ]]; then
      if [[ -z "$subjects" ]]; then
        exit 0
      fi
      if grep -qE '^feat(\([^)]*\))?:' <<< "$subjects"; then
        level=minor
      else
        level='patch'
      fi
    fi

    case "$level" in
      minor)
        next_minor=$(increment "$minor")
        printf 'v%s.%s.0\n' "$major" "$next_minor"
        ;;
      patch)
        next_patch=$(increment "$patch")
        printf 'v%s.%s.%s\n' "$major" "$minor" "$next_patch"
        ;;
      *) fail "Unknown release bump: $level" ;;
    esac
    ;;
  validate-tag)
    candidate=$5
    [[ "$candidate" =~ $stable_tag ]] || fail "Invalid stable release tag: $candidate"
    candidate_major=${BASH_REMATCH[1]}
    if manual_major; then
      expected=$(confirmed_major_tag)
      [[ "$candidate" == "$expected" ]] || fail "The release tag must match the confirmed tag $expected."
    else
      [[ "$candidate_major" == "$major" ]] ||
        fail "Changing the major version from $latest_tag requires a manual Release run with bump=major."
    fi
    if git show-ref --verify --quiet "refs/tags/$candidate"; then
      fail "Release tag already exists: $candidate"
    fi
    ;;
  *) fail "Unknown release command: $command" ;;
esac
