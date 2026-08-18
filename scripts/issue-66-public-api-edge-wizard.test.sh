#!/usr/bin/env bash
set -euo pipefail

script="${1:-scripts/issue-66-public-api-edge-wizard.sh}"

test -f "$script"
grep -Fq 'Do not remove, disable, migrate, repurpose, or renumber any existing non-PMS dst-NAT rule' "$script"
grep -Fq 'Preserve the working WAN source-IP bypass for PMS TCP 80/443' "$script"
grep -Fq 'TCP 5432 or TCP 5433' "$script"
grep -Fq 'Do not allocate an auxiliary public API port' "$script"
grep -Fq 'do not claim that 103.149.15.22 exposes only TCP 80/443' "$script"

if grep -Fq '80/443-only ingress boundary' "$script"; then
    echo 'FAIL: wizard still contains the superseded global 80/443-only boundary' >&2
    exit 1
fi

if grep -Fq 'have their owners remove' "$script"; then
    echo 'FAIL: wizard still asks operators to remove unrelated services' >&2
    exit 1
fi

echo 'Issue #66 wizard policy checks passed.'
