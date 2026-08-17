#!/usr/bin/env bash

# Offline tests for scripts/verify-runtime-boundary.sh.
#
# These exercise the fail-closed cached-config assertions without touching a
# real server: each case builds a synthetic bootstrap/cache/config.php fixture
# and asserts the guard's exit status. Runtime checks (live database identity,
# systemd units) are skipped via PMS_BOUNDARY_SKIP_RUNTIME.

set -uo pipefail

readonly guard="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify-runtime-boundary.sh"

if [[ ! -x "$guard" ]]; then
    echo "Guard script is missing or not executable: $guard" >&2
    exit 1
fi

tests_run=0
tests_failed=0

# Builds a throwaway app directory containing only what the guard reads.
make_fixture() {
    local dir app_env app_debug app_url db_name db_host db_port db_user
    dir="$(mktemp -d)"
    app_env="$1"
    app_debug="$2"
    app_url="$3"
    db_name="$4"
    db_host="$5"
    db_port="$6"
    db_user="$7"

    mkdir -p "$dir/bootstrap/cache"
    touch "$dir/artisan"

    cat > "$dir/bootstrap/cache/config.php" <<EOF
<?php return [
    'app' => [
        'env' => '$app_env',
        'debug' => $app_debug,
        'url' => '$app_url',
        'frontend_url' => 'http://localhost:3000',
    ],
    'queue' => [
        'default' => 'database',
        'connections' => [
            'beanstalkd' => ['host' => 'localhost'],
        ],
    ],
    'database' => [
        'connections' => [
            'pgsql' => [
                'database' => '$db_name',
                'host' => '$db_host',
                'port' => '$db_port',
                'username' => '$db_user',
                'password' => 'not-a-real-secret',
            ],
        ],
    ],
];
EOF

    printf '%s' "$dir"
}

# Runs the guard against a fixture and asserts the expected exit status.
expect_status() {
    local description expected_status environment fixture actual output
    description="$1"
    expected_status="$2"
    environment="$3"
    fixture="$4"

    tests_run=$((tests_run + 1))

    output="$(PMS_BOUNDARY_APP_DIR="$fixture" \
        PMS_BOUNDARY_SKIP_RUNTIME=1 \
        "$guard" "$environment" 2>&1)"
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

# Asserts the guard never leaks a secret value it has read.
expect_no_secret_in_output() {
    local fixture output
    fixture="$1"

    tests_run=$((tests_run + 1))

    output="$(PMS_BOUNDARY_APP_DIR="$fixture" \
        PMS_BOUNDARY_SKIP_RUNTIME=1 \
        "$guard" production 2>&1)"

    if grep -q 'not-a-real-secret' <<<"$output"; then
        tests_failed=$((tests_failed + 1))
        echo "FAIL - guard output must never contain a password value"
    else
        echo "ok - guard output contains no password value"
    fi

    rm -rf "$fixture"
}

readonly prod_env='production'
readonly prod_url='https://deploythc.web.id'
readonly prod_db='project_monitoring_system_prod'
readonly prod_host='127.0.0.1'
readonly prod_port='5433'
readonly prod_user='pms_prod_app'

# A correctly built production cache passes, including the benign framework
# defaults (app.frontend_url, beanstalkd host) that mention localhost.
expect_status 'production cache built from production env passes' 0 production \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

# The exact incident from #65: a testing-built cache published to production.
expect_status 'testing-built cache is refused for production' 1 production \
    "$(make_fixture testing false 'http://localhost' project_monitoring_system_testing 172.19.0.2 5432 pms_app)"

# Each identity field must be independently fail-closed.
expect_status 'wrong app.env is refused' 1 production \
    "$(make_fixture local false "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

expect_status 'debug enabled in production is refused' 1 production \
    "$(make_fixture "$prod_env" true "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

expect_status 'wrong app.url is refused' 1 production \
    "$(make_fixture "$prod_env" false 'http://localhost' "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

expect_status 'testing database name is refused for production' 1 production \
    "$(make_fixture "$prod_env" false "$prod_url" project_monitoring_system_testing "$prod_host" "$prod_port" "$prod_user")"

expect_status 'testing database host is refused for production' 1 production \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" 172.19.0.2 "$prod_port" "$prod_user")"

expect_status 'wrong database port is refused for production' 1 production \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" 5432 "$prod_user")"

expect_status 'testing runtime role is refused for production' 1 production \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" "$prod_port" pms_app)"

# The testing profile has its own allowlist and must not accept production.
expect_status 'testing cache passes the testing profile' 0 testing \
    "$(make_fixture testing true 'http://localhost' project_monitoring_system_testing 172.19.0.2 5432 pms_app)"

expect_status 'production cache is refused for the testing profile' 1 testing \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

expect_no_secret_in_output \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

# Fail-closed on missing or unknown inputs rather than defaulting to pass.
missing_cache_fixture="$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"
rm -f "$missing_cache_fixture/bootstrap/cache/config.php"
expect_status 'uncached config is refused for production' 1 production "$missing_cache_fixture"

expect_status 'unknown environment profile is refused' 64 staging \
    "$(make_fixture "$prod_env" false "$prod_url" "$prod_db" "$prod_host" "$prod_port" "$prod_user")"

missing_dir_fixture="$(mktemp -d)"
rm -rf "$missing_dir_fixture"
expect_status 'missing checkout is refused' 1 production "$missing_dir_fixture"

echo
echo "$((tests_run - tests_failed))/$tests_run checks passed"
[[ "$tests_failed" -eq 0 ]]
