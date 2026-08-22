#!/usr/bin/env bash

# Offline tests for scripts/verify-infra-tools.sh.
#
# Each case builds a synthetic pair of directories -- a "repo" holding the
# versioned tools and an "sbin" holding the installed ones -- and asserts the
# checker's exit status. Nothing touches /usr/local/sbin or a real server.

set -uo pipefail

readonly checker="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify-infra-tools.sh"

if [[ ! -x "$checker" ]]; then
    echo "Checker script is missing or not executable: $checker" >&2
    exit 1
fi

tests_run=0
tests_failed=0

# Builds a repo/sbin pair. $1 is the versioned content; $2 the installed
# content, or the literal "absent" to leave the tool uninstalled.
make_fixture() {
    local dir versioned installed
    versioned="$1"
    installed="$2"

    dir="$(mktemp -d)"
    mkdir -p "$dir/repo" "$dir/sbin"
    printf '%s\n' "$versioned" > "$dir/repo/pms-deploy"

    if [[ "$installed" != "absent" ]]; then
        printf '%s\n' "$installed" > "$dir/sbin/pms-deploy"
    fi

    printf '%s' "$dir"
}

expect_status() {
    local description expected_status fixture actual output
    description="$1"
    expected_status="$2"
    fixture="$3"

    tests_run=$((tests_run + 1))

    output="$(PMS_INFRA_PAIRS="$fixture/repo|$fixture/sbin" \
        "$checker" 2>&1)"
    actual=$?

    if [[ "$actual" -eq "$expected_status" ]]; then
        echo "ok - $description"
    else
        tests_failed=$((tests_failed + 1))
        echo "FAIL - $description (expected exit $expected_status, got $actual)"
        sed 's/^/        /' <<<"$output"
    fi

    rm -rf "$fixture"
}

expect_status 'identical installed copy passes' 0 \
    "$(make_fixture '#!/bin/sh
echo same' '#!/bin/sh
echo same')"

expect_status 'drifted installed copy is refused' 1 \
    "$(make_fixture '#!/bin/sh
echo versioned' '#!/bin/sh
echo installed')"

expect_status 'uninstalled tool is refused' 1 \
    "$(make_fixture '#!/bin/sh
echo versioned' absent)"

# A trailing-newline difference is still drift: the installed file is executed
# verbatim by root, so byte equality is the only equality that counts.
whitespace_fixture="$(make_fixture 'x' 'x')"
printf '\n' >> "$whitespace_fixture/sbin/pms-deploy"
expect_status 'trailing-whitespace drift is refused' 1 "$whitespace_fixture"

multi_fixture="$(make_fixture 'x' 'x')"
mkdir -p "$multi_fixture/repo2" "$multi_fixture/sbin2"
printf 'unit
' > "$multi_fixture/repo2/pms-queue.service"
printf 'drifted
' > "$multi_fixture/sbin2/pms-queue.service"
tests_run=$((tests_run + 1))
multi_output="$(PMS_INFRA_PAIRS="$multi_fixture/repo|$multi_fixture/sbin
$multi_fixture/repo2|$multi_fixture/sbin2" "$checker" 2>&1)"
multi_status=$?
if [[ "$multi_status" -eq 1 ]] && grep -q 'OK' <<<"$multi_output" && grep -q 'DRIFT' <<<"$multi_output"; then
    echo "ok - drift in the second directory pair fails the whole run"
else
    tests_failed=$((tests_failed + 1))
    echo "FAIL - drift in the second directory pair fails the whole run (exit $multi_status)"
    sed 's/^/        /' <<<"$multi_output"
fi
rm -rf "$multi_fixture"

empty_fixture="$(mktemp -d)"
mkdir -p "$empty_fixture/repo" "$empty_fixture/sbin"
expect_status 'empty versioned directory is refused' 1 "$empty_fixture"

missing_fixture="$(mktemp -d)"
rm -rf "$missing_fixture"
expect_status 'missing versioned directory is refused' 1 "$missing_fixture"

echo
echo "$((tests_run - tests_failed))/$tests_run checks passed"
[[ "$tests_failed" -eq 0 ]]
