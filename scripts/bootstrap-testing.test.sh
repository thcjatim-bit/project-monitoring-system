#!/usr/bin/env bash

# Offline tests for the provider seam in scripts/bootstrap-testing.sh.
#
# Skrip yang diuji menjatuhkan database, jadi yang berharga diuji di sini adalah
# penolakannya -- bukan jalur suksesnya. Setiap kasus di bawah berhenti sebelum
# koneksi pertama ke PostgreSQL, sehingga tes ini aman dijalankan di mana pun
# dan tidak pernah menyentuh data siapa pun.
#
# Jalur docker tidak diuji di sini: ia butuh daemon Docker yang hidup, dan itu
# persis lingkungan yang jalur native ada untuk menggantikan.

set -uo pipefail

readonly script="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/bootstrap-testing.sh"

if [[ ! -f "$script" ]]; then
    echo "Bootstrap script is missing: $script" >&2
    exit 1
fi

tests_run=0
tests_failed=0

# Menjalankan skrip dengan environment yang bersih dari variabel PMS_*, supaya
# kasus tidak saling mewarisi keadaan, lalu menagih exit status dan potongan
# pesan yang menjelaskan penolakannya.
expect_refusal() {
    local name="$1" expected_message="$2"
    shift 2

    tests_run=$((tests_run + 1))

    local output status
    output="$(env -u PMS_TESTING_PROVIDER -u PMS_TESTING_HOST -u PMS_TESTING_PORT \
        -u PMS_SUPERUSER -u PMS_SUPERUSER_PASSWORD -u PMS_PSQL -u PMS_TESTING_DATABASE \
        "$@" bash "$script" 2>&1)"
    status=$?

    if [[ $status -eq 0 ]]; then
        echo "FAIL: $name -- script exited 0 where a refusal was required" >&2
        tests_failed=$((tests_failed + 1))
        return
    fi

    if ! grep -qF "$expected_message" <<<"$output"; then
        echo "FAIL: $name -- expected message not found: $expected_message" >&2
        echo "  got: $output" >&2
        tests_failed=$((tests_failed + 1))
        return
    fi

    echo "ok: $name"
}

# Provider yang salah ketik tidak boleh diam-diam jatuh ke salah satu jalur.
expect_refusal 'provider tak dikenal ditolak' \
    "unknown PMS_TESTING_PROVIDER" \
    PMS_TESTING_PROVIDER=postgres

# Pagar terpenting jalur native: database di server jauh mustahil dijatuhkan.
expect_refusal 'host non-loopback ditolak' \
    "only targets a loopback PostgreSQL server" \
    PMS_TESTING_PROVIDER=native PMS_TESTING_HOST=db.internal PMS_SUPERUSER_PASSWORD=irrelevant

expect_refusal 'host loopback yang menyamar tetap ditolak' \
    "only targets a loopback PostgreSQL server" \
    PMS_TESTING_PROVIDER=native PMS_TESTING_HOST=127.0.0.1.evil.example PMS_SUPERUSER_PASSWORD=irrelevant

expect_refusal 'port bukan angka ditolak' \
    "port is not numeric" \
    PMS_TESTING_PROVIDER=native PMS_TESTING_PORT='5432; DROP DATABASE'

# Tanpa ini skrip bisa menyambung lewat autentikasi trust tanpa pernah menyebut
# kredensial -- persis kondisi yang membuat sasaran mudah tertukar.
expect_refusal 'password superuser wajib disebut eksplisit' \
    "PMS_SUPERUSER_PASSWORD is required" \
    PMS_TESTING_PROVIDER=native

expect_refusal 'psql yang tidak ada dilaporkan, bukan dibiarkan gagal belakangan' \
    "psql was not found" \
    PMS_TESTING_PROVIDER=native PMS_SUPERUSER_PASSWORD=irrelevant PMS_PSQL=/nonexistent/psql

# Pagar lama yang tetap berlaku untuk kedua provider.
expect_refusal 'database sasaran di luar yang disetujui ditolak' \
    "testing database target is not explicitly approved" \
    PMS_TESTING_PROVIDER=native PMS_TESTING_DATABASE=project_monitoring_system

expect_refusal 'APP_ENV production ditolak' \
    "Refusing to bootstrap testing database with APP_ENV=production" \
    APP_ENV=production PMS_TESTING_PROVIDER=native

echo
echo "bootstrap-testing.sh: $((tests_run - tests_failed))/$tests_run passed"
[[ $tests_failed -eq 0 ]]
