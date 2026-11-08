#!/usr/bin/env bash
#
# Per-PHP-version compatibility matrix runner for the libsql_php extension.
#
# For every requested PHP version this script:
#   1. builds the Rust extension against that version's headers in an isolated
#      target directory (ext-php-rs bakes PHP-version-specific bindings, so a
#      single `cargo build` is NOT a multi-version proof),
#   2. runs tests/e2e/e2e.php under that exact runtime,
#   3. prints a summary table and exits non-zero if any version failed.
#
# Usage:
#   tests/e2e/run-matrix.sh                    # default matrix: 8.1–8.5
#   tests/e2e/run-matrix.sh 8.4                # single version
#   tests/e2e/run-matrix.sh 8.2 8.4            # arbitrary subset
#
# Requirements on the host:
#   - nightly Rust toolchain (cargo on PATH)
#   - for each version: `php<v>` and `php-config<v>` (e.g. Debian/Ubuntu:
#     php8.1-cli + php8.1-dev from the ondrej PPA)
#   - an existing unit/feature test stack (vendor/) is OPTIONAL: if
#     `vendor/bin/pest` exists for the given runtime it is run too; when the
#     installed Pest release does not support a runtime, that step is skipped
#     with a note (the E2E suite itself has zero dependencies).
#
# This script never touches the network. The E2E suite's own only "remote" is
# the guaranteed-dead port 127.0.0.1:9 (offline simulation).

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
E2E="$SCRIPT_DIR/e2e.php"

# Default matrix = the versions documented in README ("Supported Versions").
VERSIONS="${*:-8.1 8.2 8.3 8.4 8.5}"

RESULTS=()
declare -a SUMMARY_ROWS=()
FAILED_TOTAL=0

for v in $VERSIONS; do
    php_bin="/usr/bin/php$v"
    php_cfg="/usr/bin/php-config$v"

    if ! [ -x "$php_bin" ] || ! [ -x "$php_cfg" ]; then
        echo "### php$v  SKIP-NO-TOOLCHAIN ($php_bin or $php_cfg missing)"
        SUMMARY_ROWS+=("php$v | SKIP | toolchain missing")
        FAILED_TOTAL=$((FAILED_TOTAL + 1))
        continue
    fi

    echo "### php$v — build (isolated target dir: target/php$v)"
    if ! ( cd "$REPO_ROOT" \
        && CARGO_TARGET_DIR="target/php$v" \
           PHP="$php_bin" PHP_CONFIG="$php_cfg" \
           cargo build --quiet ); then
        echo "### php$v  FAIL-build"
        SUMMARY_ROWS+=("php$v | BUILD-FAILED")
        FAILED_TOTAL=$((FAILED_TOTAL + 1))
        continue
    fi

    echo "### php$v — run E2E suite"
    E2E_OUT="$(
        "$php_bin" -n \
            -d extension_dir="$REPO_ROOT/target/php$v/debug" \
            -d extension=liblibsql_php.so \
            "$E2E" 2>&1
    )"
    E2E_EXIT=$?
    echo "$E2E_OUT"
    REPORT_JSON="$(printf '%s\n' "$E2E_OUT" | sed -n 's/^E2E-REPORT: //p' | tail -1)"

    if [ "$E2E_EXIT" -eq 0 ] && [ -n "$REPORT_JSON" ]; then
        OK_CNT="$(printf '%s' "$REPORT_JSON" | sed -n 's/.*"ok":\([0-9]*\).*/\1/p')"
        FAIL_CNT="$(printf '%s' "$REPORT_JSON" | sed -n 's/.*"fail":\([0-9]*\).*/\1/p')"
        DURATION="$(printf '%s' "$REPORT_JSON" | sed -n 's/.*"duration_seconds":"\{0,1\}\([0-9.]*\).*/\1/p')"
        if [ "${FAIL_CNT:-1}" -ne 0 ]; then
            echo "### php$v  FAIL-e2e"
            SUMMARY_ROWS+=("php$v | FAIL | ok=$OK_CNT fail=$FAIL_CNT ${DURATION}s")
            FAILED_TOTAL=$((FAILED_TOTAL + 1))
        else
            echo "### php$v  PASS (ok=$OK_CNT, ${DURATION}s)"
            RESULTS+=("$REPORT_JSON")
            SUMMARY_ROWS+=("php$v | PASS | ok=$OK_CNT fail=0 ${DURATION}s")
        fi
    else
        echo "### php$v  FAIL-e2e (exit=$E2E_EXIT)"
        SUMMARY_ROWS+=("php$v | FAIL | exit=$E2E_EXIT")
        FAILED_TOTAL=$((FAILED_TOTAL + 1))
    fi

    # Optional: per-version Pest smoke (vendor stack) — best effort, never
    # proves the wrong binary.
    if [ -f "$REPO_ROOT/vendor/bin/pest" ]; then
        SYS_LOADED="$("$php_bin" -m 2>/dev/null | grep -ci libsql || true)"
        if [ "$SYS_LOADED" -gt 0 ]; then
            echo "    (note: the system ini already loads libsql_php - skipping the vendor Pest smoke, so we never prove a binary other than the freshly built one)"
        elif ! "$php_bin" "$REPO_ROOT/vendor/bin/pest" --version >/dev/null 2>&1; then
            echo "    (note: vendor Pest does not run under php$v — skipped)"
        else
            echo "### php$v — run Pest smoke"
            PEST_LOG="${TMPDIR:-/tmp}/turso-e2e-pest-php$v.log"
            ( cd "$REPO_ROOT" && NO_COLOR=1 "$php_bin" \
                -d extension="$REPO_ROOT/target/php$v/debug/liblibsql_php.so" \
                vendor/bin/pest ) > "$PEST_LOG" 2>&1
            PEST_EXIT=$?
            tail -4 "$PEST_LOG"
            if [ "$PEST_EXIT" -ne 0 ]; then
                SUMMARY_ROWS+=("php$v | FAIL-pest (full log: $PEST_LOG)")
                FAILED_TOTAL=$((FAILED_TOTAL + 1))
            fi
        fi
    fi

    echo
done

echo "================ E2E MATRIX SUMMARY ================"
printf '%s\n' "${SUMMARY_ROWS[@]}"
echo "===================================================="

# Aggregate reports (machine-readable, e.g. for CI artifacts or docs).
for r in "${RESULTS[@]}"; do
    printf 'MATRIX-REPORT: %s\n' "$r"
done

exit "$FAILED_TOTAL"
