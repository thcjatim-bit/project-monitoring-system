#!/usr/bin/env bash

set -euo pipefail

readonly testing_database="project_monitoring_system_testing"
readonly testing_container="pms-dev-postgres-testing"
readonly app_role="pms_app"
readonly migrator_role="pms_migrator"

if [[ "${APP_ENV:-testing}" == "production" ]]; then
    echo "Refusing to bootstrap testing database with APP_ENV=production." >&2
    exit 1
fi

if hostname | grep -Eiq '(^|[-.])pms-prod($|[-.])'; then
    echo "Refusing to bootstrap testing database on a production host." >&2
    exit 1
fi

if [[ "${PMS_TESTING_DATABASE:-$testing_database}" != "$testing_database" ]]; then
    echo "Refusing: testing database target is not explicitly approved." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required to locate the dedicated testing PostgreSQL service." >&2
    exit 1
fi

if [[ "$(docker inspect --format '{{.Name}}' "$testing_container" 2>/dev/null || true)" != "/$testing_container" ]]; then
    echo "Refusing: dedicated testing PostgreSQL container was not found." >&2
    exit 1
fi

if [[ "$(docker inspect --format '{{.Config.Image}}' "$testing_container")" != postgres:* ]]; then
    echo "Refusing: target container is not a PostgreSQL image." >&2
    exit 1
fi

if [[ "$(docker inspect --format '{{.State.Status}}' "$testing_container")" != "running" ]]; then
    echo "Dedicated testing PostgreSQL container is not running." >&2
    exit 1
fi

readonly database_host="$(docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$testing_container")"
if [[ -z "$database_host" ]]; then
    echo "Refusing: testing PostgreSQL container has no inspectable network address." >&2
    exit 1
fi

readonly app_password="${PMS_APP_PASSWORD:-$(openssl rand -hex 32)}"
readonly migrator_password="${PMS_MIGRATOR_PASSWORD:-$(openssl rand -hex 32)}"

if [[ ! "$app_password" =~ ^[A-Za-z0-9]+$ || ! "$migrator_password" =~ ^[A-Za-z0-9]+$ ]]; then
    echo "Refusing: supplied role credentials contain unsafe characters." >&2
    exit 1
fi

run_admin_sql() {
    printf '%s\n' "$1" | docker exec -i "$testing_container" sh -c 'psql -AtF "|" -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d postgres'
}

run_app_sql() {
    printf '%s\n' "$1" | docker exec -i -e "PGPASSWORD=$app_password" "$testing_container" psql -AtF '|' -v ON_ERROR_STOP=1 -U "$app_role" -d "$testing_database"
}

echo "Preparing dedicated PostgreSQL testing database: $testing_database"

run_admin_sql "
    SELECT pg_terminate_backend(pid)
    FROM pg_stat_activity
    WHERE datname = '$testing_database' AND pid <> pg_backend_pid();
    DROP DATABASE IF EXISTS $testing_database;
    DO \$bootstrap\$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$app_role') THEN
            CREATE ROLE $app_role LOGIN PASSWORD '$app_password';
        ELSE
            ALTER ROLE $app_role LOGIN PASSWORD '$app_password';
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$migrator_role') THEN
            CREATE ROLE $migrator_role LOGIN PASSWORD '$migrator_password';
        ELSE
            ALTER ROLE $migrator_role LOGIN PASSWORD '$migrator_password';
        END IF;
    END
    \$bootstrap\$;
    ALTER ROLE $app_role NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
    ALTER ROLE $migrator_role NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
    CREATE DATABASE $testing_database OWNER $migrator_role;
    REVOKE ALL ON DATABASE $testing_database FROM PUBLIC;
    GRANT CONNECT ON DATABASE $testing_database TO $app_role, $migrator_role;
"

umask 077
cat > .env.testing <<EOF
APP_ENV=testing
DB_CONNECTION=pgsql
DB_HOST=$database_host
DB_PORT=5432
DB_DATABASE=$testing_database
DB_USERNAME=$app_role
DB_PASSWORD=$app_password
DB_MIGRATOR_HOST=$database_host
DB_MIGRATOR_PORT=5432
DB_MIGRATOR_DATABASE=$testing_database
DB_MIGRATOR_USERNAME=$migrator_role
DB_MIGRATOR_PASSWORD=$migrator_password
DB_SSLMODE=prefer
EOF

APP_ENV=testing php artisan config:clear >/dev/null
APP_ENV=testing php artisan migrate:fresh --database=migrator --seed --force

run_admin_sql "
    SELECT rolname, rolsuper, rolbypassrls, rolcreatedb, rolcreaterole, rolreplication, rolcanlogin
    FROM pg_roles WHERE rolname IN ('$app_role', '$migrator_role') ORDER BY rolname;
"

identity="$(run_app_sql "SELECT concat_ws('|', current_database(), current_user);")"
if ! grep -q "$testing_database|$app_role" <<<"$identity"; then
    echo "Refusing: application connection did not reach the approved database and role." >&2
    exit 1
fi

role_fidelity="$(run_admin_sql "
    SELECT rolsuper, rolbypassrls, rolcreatedb, rolcreaterole, rolreplication, rolcanlogin
    FROM pg_roles WHERE rolname = '$app_role';
")"
if ! grep -q '^f|f|f|f|f|t$' <<<"$role_fidelity"; then
    echo "Refusing: pms_app role attributes are not restricted as required." >&2
    exit 1
fi

rls_fidelity="$(run_app_sql "
    SELECT c.relname, c.relrowsecurity, c.relforcerowsecurity
    FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND c.relname IN ('projects', 'warehouses', 'material_stoks', 'material_transaksis')
    ORDER BY c.relname;
")"
if [[ "$(grep -c '|t|t' <<<"$rls_fidelity")" -ne 4 ]]; then
    echo "Refusing: required testing tables do not all have forced RLS." >&2
    exit 1
fi

policy_fidelity="$(run_app_sql "
    SELECT tablename || '|' || policyname
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename IN ('projects', 'warehouses', 'material_stoks', 'material_transaksis')
    ORDER BY tablename;
")"
for expected_policy in \
    'material_stoks|warehouse_stock_tenant_isolation' \
    'material_transaksis|material_transaction_tenant_isolation' \
    'projects|tenant_isolation' \
    'warehouses|tenant_isolation'; do
    if ! grep -qx "$expected_policy" <<<"$policy_fidelity"; then
        echo "Refusing: expected RLS policy is missing: $expected_policy" >&2
        exit 1
    fi
done

echo "Testing database rebuilt and verified for pms_app; credentials remain in ignored .env.testing."
