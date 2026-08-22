#!/usr/bin/env bash

# Assert that the infrastructure files installed on this host are
# byte-identical to the copies versioned in this repository.
#
# The files covered here are executed by root (/usr/local/sbin) or by systemd
# (/etc/systemd/system). That makes this script an ASSERTION and never an
# installer: it reports drift, it never resolves it. Copying a file from a
# checkout into a system directory is an action a human takes deliberately --
# if a deploy could do it, any commit could change what root executes.
# See docs/runbook/infra-tools.md.
#
# Usage:
#   scripts/verify-infra-tools.sh
#
# Environment overrides (for tests):
#   PMS_INFRA_PAIRS   newline-separated "versioned_dir|installed_dir" entries,
#                     replacing the defaults below

set -uo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly repo_root="$script_dir/.."

readonly default_pairs="$repo_root/deploy/sbin|/usr/local/sbin
$repo_root/deploy/systemd|/etc/systemd/system"

readonly pairs="${PMS_INFRA_PAIRS:-$default_pairs}"

failures=0
checked=0

echo "Infrastructure drift check"
echo

while IFS='|' read -r versioned_dir installed_dir; do
    [[ -n "$versioned_dir" ]] || continue

    echo "  $versioned_dir -> $installed_dir"

    if [[ ! -d "$versioned_dir" ]]; then
        echo "Refusing: versioned directory was not found: $versioned_dir" >&2
        exit 1
    fi

    for versioned in "$versioned_dir"/*; do
        [[ -f "$versioned" ]] || continue

        name="$(basename "$versioned")"
        installed="$installed_dir/$name"
        checked=$((checked + 1))

        if [[ ! -f "$installed" ]]; then
            printf '    %-28s MISSING     not installed at %s\n' "$name" "$installed"
            failures=$((failures + 1))
            continue
        fi

        if [[ ! -r "$installed" ]]; then
            printf '    %-28s UNREADABLE  cannot compare %s\n' "$name" "$installed"
            failures=$((failures + 1))
            continue
        fi

        # Byte equality is the only equality that counts: the installed file is
        # what root or systemd actually reads, trailing whitespace included.
        if cmp -s "$versioned" "$installed"; then
            printf '    %-28s OK\n' "$name"
        else
            printf '    %-28s DRIFT       installed copy differs from the versioned one\n' "$name"
            failures=$((failures + 1))
        fi
    done

    echo
done <<< "$pairs"

if [[ "$checked" -eq 0 ]]; then
    echo "Refusing: no versioned files found to compare" >&2
    exit 1
fi

if [[ "$failures" -gt 0 ]]; then
    echo "VERDICT: FAIL - $failures of $checked file(s) drifted from the repository." >&2
    echo "Reinstall deliberately, as root. For example:" >&2
    echo "  sudo install -o root -g root -m 0644 <versioned> <installed>" >&2
    echo "systemd units additionally need: sudo systemctl daemon-reload" >&2
    exit 1
fi

echo "VERDICT: PASS - all $checked installed file(s) match the repository."
