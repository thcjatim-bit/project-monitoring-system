#!/usr/bin/env bash

# Fail-closed runtime boundary guard for pms-dev and pms-prod.
#
# Implements the guard contract decided in issue #63 and the evidence required
# by issue #65: assert that the cached Laravel configuration a runtime is about
# to serve was built from the environment it claims, and that no identifier
# belonging to the other environment leaked into it.
#
# The guard is an assertion, not a repair: it never edits configuration, never
# rebuilds a cache, and never prints a secret value. Any mismatch, missing
# input, or unknown profile refuses instead of defaulting to success, so it is
# safe to call as a deployment preflight and post-deploy gate.
#
# Usage:
#   scripts/verify-runtime-boundary.sh <production|testing>
#
# Environment overrides (for tests and non-default checkouts):
#   PMS_BOUNDARY_APP_DIR       checkout to inspect
#   PMS_BOUNDARY_SKIP_RUNTIME  set to 1 to check the cache only, skipping the
#                              live database identity and service checks

# Deliberately without -e: the guard reports every failed assertion before it
# refuses, so it accumulates a failure count instead of aborting on the first
# mismatch. Hard errors (missing checkout, unreadable cache) still refuse
# immediately via refuse(), and the final exit status is driven by that count.
set -uo pipefail

readonly app_dir="${PMS_BOUNDARY_APP_DIR:-/var/www/project-monitoring-system}"
readonly skip_runtime="${PMS_BOUNDARY_SKIP_RUNTIME:-0}"
readonly environment="${1:-}"

refuse() {
    echo "Refusing: $1" >&2
    exit 1
}

# Allowlisted identity per environment. The guard compares against these exact
# values; it never infers the expected identity from the runtime it is auditing.
case "$environment" in
    production)
        readonly expected_env="production"
        readonly expected_debug="false"
        readonly expected_url="https://deploythc.web.id"
        readonly expected_connection="pgsql"
        readonly expected_db="project_monitoring_system_prod"
        readonly expected_host="127.0.0.1"
        readonly expected_port="5433"
        readonly expected_user="pms_prod_app"
        # Identifiers that must never appear in a production runtime.
        readonly forbidden_identifiers=(
            "project_monitoring_system_testing"
            "172.19.0.2"
            "pms_app"
            "pms_migrator"
        )
        readonly expected_units=("pms-queue.service" "pms-schedule.timer" "nginx" "php8.3-fpm")
        ;;
    testing)
        readonly expected_env="testing"
        readonly expected_debug="any"
        readonly expected_url="any"
        readonly expected_connection="pgsql"
        readonly expected_db="project_monitoring_system_testing"
        readonly expected_host="any"
        readonly expected_port="5432"
        readonly expected_user="pms_app"
        # Identifiers that must never appear in a testing runtime.
        readonly forbidden_identifiers=(
            "project_monitoring_system_prod"
            "pms_prod_app"
            "pms_prod_migrator"
            "deploythc.web.id"
        )
        readonly expected_units=()
        ;;
    "")
        echo "Usage: $(basename "$0") <production|testing>" >&2
        exit 64
        ;;
    *)
        echo "Unknown environment profile: $environment" >&2
        echo "Usage: $(basename "$0") <production|testing>" >&2
        exit 64
        ;;
esac

[[ -d "$app_dir" ]] || refuse "checkout directory was not found: $app_dir"
[[ -f "$app_dir/artisan" ]] || refuse "checkout does not look like a Laravel application: $app_dir"

readonly config_cache="$app_dir/bootstrap/cache/config.php"

# Config CACHED is itself part of the contract: an uncached production runtime
# reads .env on every request and has no artifact to audit.
[[ -f "$config_cache" ]] || refuse "configuration is not cached: $config_cache is missing"

command -v php >/dev/null 2>&1 || refuse "php is required to read the cached configuration"

# Extract only the identity fields, so a password is never carried into the
# shell or into the evidence output.
#
# The connection inspected is database.default, not a hardcoded "pgsql": the
# live check below resolves the default connection too, so pinning this side to
# pgsql would let a runtime whose DB_CONNECTION points elsewhere pass on the
# strength of a connection it never opens.
#
# Assigned plainly rather than via readonly: declaration builtins report their
# own status, not the command substitution's, which would make the failure
# branch below unreachable.
identity="$(php -r '
$path = $argv[1];
$config = require $path;
if (! is_array($config)) {
    fwrite(STDERR, "cached configuration did not evaluate to an array\n");
    exit(1);
}
$default = $config["database"]["default"] ?? "";
$connection = $config["database"]["connections"][$default] ?? [];
$fields = [
    "env" => $config["app"]["env"] ?? "",
    "debug" => ($config["app"]["debug"] ?? false) ? "true" : "false",
    "url" => $config["app"]["url"] ?? "",
    "connection" => $default,
    "db" => $connection["database"] ?? "",
    "host" => $connection["host"] ?? "",
    "port" => (string) ($connection["port"] ?? ""),
    "user" => $connection["username"] ?? "",
];
foreach ($fields as $key => $value) {
    printf("%s=%s\n", $key, $value);
}
' "$config_cache" 2>/dev/null)"

if [[ "$?" -ne 0 ]]; then
    refuse "cached configuration could not be read as PHP"
fi

readonly identity

[[ -n "$identity" ]] || refuse "cached configuration yielded no identity fields"

read_field() {
    local key="$1"
    sed -n "s/^${key}=//p" <<<"$identity"
}

readonly actual_env="$(read_field env)"
readonly actual_debug="$(read_field debug)"
readonly actual_url="$(read_field url)"
readonly actual_connection="$(read_field connection)"
readonly actual_db="$(read_field db)"
readonly actual_host="$(read_field host)"
readonly actual_port="$(read_field port)"
readonly actual_user="$(read_field user)"

failures=0

check_field() {
    local label expected actual
    label="$1"
    expected="$2"
    actual="$3"

    if [[ "$expected" == "any" ]]; then
        printf '  %-14s %-6s %s\n' "$label" "INFO" "$actual"
        return 0
    fi

    if [[ "$expected" == "$actual" ]]; then
        printf '  %-14s %-6s %s\n' "$label" "OK" "$actual"
    else
        printf '  %-14s %-6s %s (expected %s)\n' "$label" "FAIL" "$actual" "$expected"
        failures=$((failures + 1))
    fi
}

echo "Runtime boundary check: $environment"
echo "  checkout       $app_dir"

# The exact SHA and a clean worktree are part of the evidence contract, so an
# uninspectable checkout is a failure rather than a skipped section. Detection
# goes through rev-parse instead of testing for a .git directory, because .git
# is a file in worktree and submodule checkouts and absent in export deploys --
# all of which would otherwise drop these assertions and still report PASS.
if ! command -v git >/dev/null 2>&1; then
    printf '  %-14s %-6s %s\n' "commit" "FAIL" "git is unavailable; exact SHA is not verifiable"
    failures=$((failures + 1))
elif ! git -C "$app_dir" rev-parse --git-dir >/dev/null 2>&1; then
    printf '  %-14s %-6s %s\n' "commit" "FAIL" "checkout is not a git repository; exact SHA is not verifiable"
    failures=$((failures + 1))
else
    sha="$(git -C "$app_dir" rev-parse HEAD 2>/dev/null)"
    if [[ -z "$sha" ]]; then
        printf '  %-14s %-6s %s\n' "commit" "FAIL" "HEAD could not be resolved"
        failures=$((failures + 1))
    else
        echo "  commit         $sha"
    fi

    # Distinguish "clean" from "could not look": git refuses outright on a
    # checkout it considers dubiously owned, and an empty result there would
    # otherwise be reported as a clean worktree.
    dirty="$(git -C "$app_dir" status --porcelain 2>/dev/null)"
    if [[ "$?" -ne 0 ]]; then
        printf '  %-14s %-6s %s\n' "worktree" "FAIL" "worktree state was not inspectable"
        failures=$((failures + 1))
    elif [[ -n "$dirty" ]]; then
        printf '  %-14s %-6s %s\n' "worktree" "FAIL" "checkout contains uncommitted changes"
        failures=$((failures + 1))
    else
        printf '  %-14s %-6s %s\n' "worktree" "OK" "clean"
    fi
fi

echo "  config cache   CACHED ($(date -r "$config_cache" '+%Y-%m-%dT%H:%M:%S%z' 2>/dev/null || echo 'mtime unavailable'))"
echo "Cached configuration identity:"

check_field "app.env" "$expected_env" "$actual_env"
check_field "app.debug" "$expected_debug" "$actual_debug"
check_field "app.url" "$expected_url" "$actual_url"
check_field "db.default" "$expected_connection" "$actual_connection"
check_field "db.database" "$expected_db" "$actual_db"
check_field "db.host" "$expected_host" "$actual_host"
check_field "db.port" "$expected_port" "$actual_port"
check_field "db.username" "$expected_user" "$actual_user"

# Cross-environment leak check. This deliberately inspects only the identity
# fields above rather than the whole cached array: unrelated framework defaults
# (app.frontend_url, the unused beanstalkd queue host) legitimately mention
# localhost and would make a whole-blob scan permanently red.
echo "Cross-environment identifiers:"
for identifier in "${forbidden_identifiers[@]}"; do
    leaked=""
    for value in "$actual_env" "$actual_url" "$actual_db" "$actual_host" "$actual_user"; do
        if [[ "$value" == *"$identifier"* ]]; then
            leaked="yes"
            break
        fi
    done

    if [[ -n "$leaked" ]]; then
        printf '  %-34s %s\n' "$identifier" "PRESENT (FAIL)"
        failures=$((failures + 1))
    else
        printf '  %-34s %s\n' "$identifier" "absent"
    fi
done

if [[ "$skip_runtime" != "1" ]]; then
    echo "Live runtime identity:"

    # Confirm the served configuration actually reaches the expected database
    # and role, not merely that the cached file claims it does. Laravel reports
    # the resolved connection under platform.config, which excludes the
    # password, so no secret is read into the shell.
    live="$(cd "$app_dir" && php artisan db:show --json 2>/dev/null \
        | php -r '
$raw = stream_get_contents(STDIN);
$data = json_decode($raw, true);
$config = $data["platform"]["config"] ?? null;
if (! is_array($config)) {
    exit(1);
}
printf(
    "%s|%s|%s|%s\n",
    $config["database"] ?? "",
    $config["username"] ?? "",
    $config["host"] ?? "",
    (string) ($config["port"] ?? "")
);
' 2>/dev/null || true)"

    if [[ -z "$live" ]]; then
        printf '  %-14s %-6s %s\n' "live.database" "FAIL" "live database identity was not inspectable"
        failures=$((failures + 1))
    else
        IFS='|' read -r live_db live_user live_host live_port <<<"$live"
        check_field "live.database" "$expected_db" "$live_db"
        check_field "live.username" "$expected_user" "$live_user"
        check_field "live.host" "$expected_host" "$live_host"
        check_field "live.port" "$expected_port" "$live_port"
    fi

    for unit in ${expected_units[@]+"${expected_units[@]}"}; do
        state="$(systemctl is-active "$unit" 2>/dev/null || true)"
        if [[ "$state" == "active" ]]; then
            printf '  %-14s %-6s %s\n' "$unit" "OK" "active"
        else
            printf '  %-14s %-6s %s\n' "$unit" "FAIL" "${state:-unknown}"
            failures=$((failures + 1))
        fi
    done
fi

echo
if [[ "$failures" -ne 0 ]]; then
    refuse "$failures runtime boundary assertion(s) failed for $environment"
fi

echo "VERDICT: PASS - $environment runtime matches its approved boundary."
