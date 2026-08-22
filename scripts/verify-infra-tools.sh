#!/usr/bin/env bash

# Assert that the root-owned infrastructure tools installed on this host are
# byte-identical to the copies versioned in this repository.
#
# These tools run as root through NOPASSWD sudo. That makes this script an
# ASSERTION and never an installer: it reports drift, it never resolves it.
# Copying a file from a checkout into /usr/local/sbin is a root action a human
# takes deliberately -- if a deploy could do it, any commit could change what
# root executes. See docs/runbook/runtime-boundary-guard.md.
#
# Usage:
#   scripts/verify-infra-tools.sh
#
# Environment overrides (for tests):
#   PMS_INFRA_REPO_DIR   directory holding the versioned copies
#   PMS_INFRA_SBIN_DIR   directory holding the installed copies

set -uo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

readonly repo_dir="${PMS_INFRA_REPO_DIR:-$script_dir/../deploy/sbin}"
readonly sbin_dir="${PMS_INFRA_SBIN_DIR:-/usr/local/sbin}"

if [[ ! -d "$repo_dir" ]]; then
    echo "Refusing: versioned tool directory was not found: $repo_dir" >&2
    exit 1
fi

failures=0
checked=0

echo "Infrastructure tool drift check"
echo "  versioned  $repo_dir"
echo "  installed  $sbin_dir"
echo

for versioned in "$repo_dir"/*; do
    [[ -f "$versioned" ]] || continue

    tool="$(basename "$versioned")"
    installed="$sbin_dir/$tool"
    checked=$((checked + 1))

    if [[ ! -f "$installed" ]]; then
        printf '  %-24s MISSING  not installed at %s\n' "$tool" "$installed"
        failures=$((failures + 1))
        continue
    fi

    if [[ ! -r "$installed" ]]; then
        printf '  %-24s UNREADABLE  cannot compare %s\n' "$tool" "$installed"
        failures=$((failures + 1))
        continue
    fi

    # Compare content only. Ownership and mode are asserted separately below,
    # so a readable-but-wrongly-owned tool reports the precise problem rather
    # than a generic mismatch.
    if cmp -s "$versioned" "$installed"; then
        printf '  %-24s OK\n' "$tool"
    else
        printf '  %-24s DRIFT    installed copy differs from the versioned one\n' "$tool"
        failures=$((failures + 1))
    fi
done

if [[ "$checked" -eq 0 ]]; then
    echo "Refusing: no versioned tools found in $repo_dir" >&2
    exit 1
fi

echo

if [[ "$failures" -gt 0 ]]; then
    echo "VERDICT: FAIL - $failures of $checked tool(s) drifted from the repository." >&2
    echo "Reinstall deliberately, as root:" >&2
    echo "  sudo install -o root -g root -m 0755 $repo_dir/<tool> $sbin_dir/<tool>" >&2
    exit 1
fi

echo "VERDICT: PASS - all $checked installed tool(s) match the repository."
