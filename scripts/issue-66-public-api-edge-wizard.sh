#!/usr/bin/env bash
#
# PMS issue #66 handoff wizard.
#
# This wizard only walks a Platform/Network Owner through human-only work. It
# never edits DNS, MikroTik, Nginx, Certbot, secrets, or the application.

set -euo pipefail

TOTAL_STAGES=5
stage_index=0

if [[ -t 1 ]] && command -v tput >/dev/null 2>&1 && [[ "$(tput colors 2>/dev/null || echo 0)" -ge 8 ]]; then
    bold=$(tput bold)
    dim=$(tput dim)
    reset=$(tput sgr0)
    blue=$(tput setaf 4)
    green=$(tput setaf 2)
    yellow=$(tput setaf 3)
else
    bold=""
    dim=""
    reset=""
    blue=""
    green=""
    yellow=""
fi

clear_screen() {
    [[ -t 1 ]] || return 0
    if command -v tput >/dev/null 2>&1; then
        tput clear
    fi
}

pause_for_operator() {
    printf '  %s%s%s ' "$dim" "${1:-Press Enter to continue}" "$reset"
    read -r _ || true
}

confirm() {
    local reply=""
    printf '  %s? %s [y/N] ' "$yellow" "$1"
    read -r reply || true
    [[ "$reply" =~ ^[Yy] ]]
}

say() { printf '  %s\n' "$1"; }
step() { printf '  %s*%s %s\n' "$blue" "$reset" "$1"; }
note() { printf '  %s%s%s\n' "$dim" "$1" "$reset"; }
warn() { printf '  %sWARNING: %s%s\n' "$yellow" "$1" "$reset"; }

stage() {
    clear_screen
    stage_index=$((stage_index + 1))
    printf '\n%s%sStage %s/%s: %s%s\n' \
        "$bold" "$blue" "$stage_index" "$TOTAL_STAGES" "$1" "$reset"
}

banner() {
    clear_screen
    printf '\n%s%sPMS #66 public API edge handoff%s\n' "$bold" "$blue" "$reset"
    printf '%s%d manual stages%s\n\n' "$dim" "$TOTAL_STAGES" "$reset"
    say "This wizard records no credentials and performs no root-level changes."
    say "Use only the approved DNS, network, and infrastructure authority."
    pause_for_operator "Ready to begin?"
}

finish() {
    clear_screen
    printf '\n%s%sHandoff stages completed%s\n' "$bold" "$green" "$reset"
    note "Return sanitized evidence to issue #66; do not paste API keys, private keys, or raw logs."
    printf '\n'
}

banner

stage "Verify prerequisites and shared-IP forwarding"
say "In the approved MikroTik/firewall authority, inventory first and preserve the shared public IPv4 services."
step "Confirm issue #52 backend routes and its valid-key canary prerequisites are ready before changing DNS or the edge."
step "Read the current dst-NAT and filter tables; compare them with the issue-66 shared-public-IP runbook, then record the live ownership matrix."
step "Verify the active PMS mappings for deploythc.web.id remain TCP 80 -> 192.168.150.100:80 and TCP 443 -> 192.168.150.100:443; do not recreate, renumber, or replace them."
step "Do not remove, disable, migrate, repurpose, or renumber any existing non-PMS dst-NAT rule, including approved mappings for WAHA, PPPoE, ONT monitoring, Spawnlog, OpenClaw, NMS, 9router, or SSH administration."
step "Preserve the working WAN source-IP bypass for PMS TCP 80/443 and do not change the generic masquerade rule."
step "Confirm that no WAN dst-NAT or public firewall rule exists for TCP 5432 or TCP 5433."
step "Do not allocate an auxiliary public API port; api.deploythc.web.id will share the existing Nginx TCP 80/443 listeners after #52 is ready."
if ! confirm "Has the PMS 80/443 mapping, shared-IP exception inventory, source-IP bypass, and 5432/5433 WAN-negative state been verified without changing existing team services?"; then
    warn "Stop here. Do not continue until the network boundary is confirmed."
    exit 1
fi

stage "Publish API DNS"
say "In the approved public DNS control plane, create the API record only after the prerequisite gate passes."
step "Publish A api.deploythc.web.id to the approved public edge address."
step "Publish AAAA only when the complete IPv6 path is available and tested; otherwise leave AAAA absent."
step "Wait for authoritative/public resolvers to return the intended record and record the timestamp."
pause_for_operator "DNS is published and externally resolvable?"

stage "Install the Nginx API virtual host"
say "Use the approved root-level infrastructure path; do not edit production files ad hoc."
step "Proceed only after issue #52 supplies the API routes and the approved canary plan; this stage does not implement API resources."
step "Add server_name api.deploythc.web.id on Nginx, with HTTP-01 webroot on port 80 and an HTTP-to-HTTPS redirect."
step "Serve HTTPS on 443 and keep the Laravel/PHP-FPM upstream private."
step "Overwrite client-supplied X-Forwarded-* values, allow only the approved API host, and use path-only redacted access logs."
step "Run the approved Nginx validation and reload procedure without printing configuration secrets."
pause_for_operator "The approved infrastructure authority reports the API virtual host active and Nginx healthy?"

stage "Issue and verify the API certificate"
say "Use the existing Certbot HTTP-01/webroot ownership and renewal path."
step "Issue the certificate for api.deploythc.web.id without copying certificate or private-key contents into chat, Git, or logs."
step "Run the approved renewal dry-run, verify the reload hook, and confirm expiry monitoring at 30 days."
step "Record only certificate subject, issuer, expiry, dry-run result, and validation timestamps."
pause_for_operator "Certificate issuance, dry-run, reload, and expiry monitoring are confirmed?"

stage "Collect public API evidence"
say "Coordinate the valid-key canary with issue #52; never enter or paste the key into this wizard."
step "Verify HTTP redirect, HTTPS certificate, approved-host behavior, forged forwarded headers, generic 401, and rate-limit 429 with Retry-After."
step "Verify the path-only access log and application audit metadata contain no query strings, Authorization value, request body, or raw secret."
step "Verify public /up is minimal and unauthenticated, then run the separate valid-key read-only canary."
step "Report PMS/API public ports separately from operator-approved shared-IP team ports; do not claim that 103.149.15.22 exposes only TCP 80/443."
step "Attach sanitized evidence for DNS, TLS, edge checks, /up, canary, and WAN-negative/LAN-positive PostgreSQL tests to issue #66."
pause_for_operator "All evidence is sanitized and attached to issue #66?"

finish
