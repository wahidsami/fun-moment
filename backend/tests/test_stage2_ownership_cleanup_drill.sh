#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — Stage 2 Ownership-Guarded & Verified Cleanup Drill
# Tests all 5 required scenarios:
# 1. Normal successful creation and verified cleanup.
# 2. Restore failure followed by verified cleanup (preserving original exit code).
# 3. Existing-name collision with a pre-created sentinel database.
# 4. Simulated cleanup failure (reporting failure & returning non-zero on success).
# 5. Confirm sentinel is never modified or dropped across all tests.
# =============================================================================
set -euo pipefail
umask 077

echo "=== STAGE 2 OWNERSHIP-GUARDED & VERIFIED CLEANUP DRILL (PostgreSQL 17.4) ==="

export PGPASSWORD="${PGPASSWORD:-postgres}"
PG_HOST="${PG_HOST:-127.0.0.1}"
PG_PORT="${PG_PORT:-5432}"
PG_USER="${PG_USER:-postgres}"

SENTINEL_DB="fm_sentinel_drill_$$"

# Cleanup sentinel on script exit
cleanup_fixture() {
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE IF EXISTS \"$SENTINEL_DB\";" >/dev/null 2>&1 || true
}
trap cleanup_fixture EXIT

echo "[Setup] Creating pre-existing sentinel database: $SENTINEL_DB..."
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$SENTINEL_DB\";" >/dev/null
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$SENTINEL_DB" -c "CREATE TABLE sentinel_marker (id int, note text); INSERT INTO sentinel_marker VALUES (42, 'DO_NOT_TOUCH');" >/dev/null

# -----------------------------------------------------------------------------
# Test 1: Normal Successful Creation and Verified Cleanup
# -----------------------------------------------------------------------------
echo "[Test 1/5] Testing Normal Successful Creation and Verified Cleanup..."
TEST1_DB="fm_stage2_test1_$$"
set +e
(
    set -euo pipefail
    TARGET_DB="$TEST1_DB"
    DB_CREATED=0
    IN_CLEANUP=0

    cleanup_stage2() {
        ORIGINAL_EXIT=$?
        if [ "$IN_CLEANUP" -eq 1 ]; then
            exit "$ORIGINAL_EXIT"
        fi
        IN_CLEANUP=1
        set +e

        CLEANUP_FAILED=0
        if [ "${DB_CREATED:-0}" -eq 1 ] && [ -n "${TARGET_DB:-}" ]; then
            echo "Attempting deletion of verified ephemeral database created by this execution: $TARGET_DB..."
            if psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE \"$TARGET_DB\";" >/dev/null 2>&1; then
                DROPDB_STATUS=0
            else
                DROPDB_STATUS=$?
            fi

            DB_STILL_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TARGET_DB}';" 2>/dev/null || echo "0")
            if [ "$DROPDB_STATUS" -eq 0 ] && [ "$DB_STILL_EXISTS" != "1" ]; then
                echo "Ephemeral database $TARGET_DB dropped cleanly (independent verification: confirmed absent)."
            else
                echo "CRITICAL CLEANUP FAILURE: Failed to drop ephemeral database $TARGET_DB! (dropdb status: $DROPDB_STATUS, still_exists: $DB_STILL_EXISTS)" >&2
                CLEANUP_FAILED=1
            fi
        else
            echo "Ownership guard: DB_CREATED=0, skipping dropdb."
        fi

        if [ "$ORIGINAL_EXIT" -ne 0 ]; then
            exit "$ORIGINAL_EXIT"
        elif [ "$CLEANUP_FAILED" -ne 0 ]; then
            exit 1
        else
            exit 0
        fi
    }
    trap cleanup_stage2 EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM

    # Collision check
    DB_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TARGET_DB}';")
    if [ "$DB_EXISTS" = "1" ]; then
        echo "Database already exists!" >&2
        exit 1
    fi

    # Create DB
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$TARGET_DB\";" >/dev/null
    DB_CREATED=1
    echo "  [PASS] Ephemeral DB created and ownership registered (DB_CREATED=1)."

    # Simulate restore and query
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TARGET_DB" -c "CREATE TABLE users (id bigint); INSERT INTO users VALUES (1);" >/dev/null
    exit 0
)
TEST1_EXIT=$?
set -e

if [ "$TEST1_EXIT" -ne 0 ]; then
    echo "CRITICAL: Test 1 failed with exit code $TEST1_EXIT!" >&2
    exit 1
fi

TEST1_CHECK=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TEST1_DB}';")
if [ "$TEST1_CHECK" = "1" ]; then
    echo "CRITICAL: Test 1 database still exists!" >&2
    exit 1
fi
echo "  [PASS] Test 1: Normal success and clean absence verified (Exit code 0)."

# -----------------------------------------------------------------------------
# Test 2: Restore Failure Followed by Verified Cleanup
# -----------------------------------------------------------------------------
echo "[Test 2/5] Testing Restore Failure Followed by Verified Cleanup..."
TEST2_DB="fm_stage2_test2_$$"
set +e
(
    set -euo pipefail
    TARGET_DB="$TEST2_DB"
    DB_CREATED=0
    IN_CLEANUP=0

    cleanup_stage2() {
        ORIGINAL_EXIT=$?
        if [ "$IN_CLEANUP" -eq 1 ]; then
            exit "$ORIGINAL_EXIT"
        fi
        IN_CLEANUP=1
        set +e

        CLEANUP_FAILED=0
        if [ "${DB_CREATED:-0}" -eq 1 ] && [ -n "${TARGET_DB:-}" ]; then
            echo "Attempting deletion of verified ephemeral database created by this execution: $TARGET_DB..."
            if psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE \"$TARGET_DB\";" >/dev/null 2>&1; then
                DROPDB_STATUS=0
            else
                DROPDB_STATUS=$?
            fi

            DB_STILL_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TARGET_DB}';" 2>/dev/null || echo "0")
            if [ "$DROPDB_STATUS" -eq 0 ] && [ "$DB_STILL_EXISTS" != "1" ]; then
                echo "Ephemeral database $TARGET_DB dropped cleanly (independent verification: confirmed absent)."
            else
                echo "CRITICAL CLEANUP FAILURE: Failed to drop ephemeral database $TARGET_DB!" >&2
                CLEANUP_FAILED=1
            fi
        fi

        if [ "$ORIGINAL_EXIT" -ne 0 ]; then
            exit "$ORIGINAL_EXIT"
        elif [ "$CLEANUP_FAILED" -ne 0 ]; then
            exit 1
        else
            exit 0
        fi
    }
    trap cleanup_stage2 EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM

    # Create DB
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$TARGET_DB\";" >/dev/null
    DB_CREATED=1

    # Simulate restore failure (non-zero exit code 2)
    echo "Simulating restore failure..." >&2
    exit 2
)
TEST2_EXIT=$?
set -e

if [ "$TEST2_EXIT" -ne 2 ]; then
    echo "CRITICAL: Test 2 did not preserve original exit code 2 (got $TEST2_EXIT)!" >&2
    exit 1
fi

TEST2_CHECK=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TEST2_DB}';")
if [ "$TEST2_CHECK" = "1" ]; then
    echo "CRITICAL: Test 2 database was not dropped on error!" >&2
    exit 1
fi
echo "  [PASS] Test 2: Restore failure exit code preserved ($TEST2_EXIT) and database cleanly dropped."

# -----------------------------------------------------------------------------
# Test 3: Existing-Name Collision with Pre-Created Sentinel Database
# -----------------------------------------------------------------------------
echo "[Test 3/5] Testing Existing-Name Collision with Sentinel Database..."
set +e
(
    set -euo pipefail
    TARGET_DB="$SENTINEL_DB"
    DB_CREATED=0
    IN_CLEANUP=0

    cleanup_stage2() {
        ORIGINAL_EXIT=$?
        if [ "$IN_CLEANUP" -eq 1 ]; then
            exit "$ORIGINAL_EXIT"
        fi
        IN_CLEANUP=1
        set +e

        if [ "${DB_CREATED:-0}" -eq 1 ] && [ -n "${TARGET_DB:-}" ]; then
            psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE \"$TARGET_DB\";" >/dev/null 2>&1 || true
        else
            echo "Ownership guard: DB_CREATED=0, skipping drop of $TARGET_DB."
        fi
        exit "$ORIGINAL_EXIT"
    }
    trap cleanup_stage2 EXIT

    # Collision Check
    DB_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TARGET_DB}';")
    if [ "$DB_EXISTS" = "1" ]; then
        echo "  [ABORT] Database ${TARGET_DB} already exists! Halting immediately without modifying or dropping." >&2
        exit 1
    fi

    # Should never reach here
    DB_CREATED=1
)
TEST3_EXIT=$?
set -e

if [ "$TEST3_EXIT" -ne 1 ]; then
    echo "CRITICAL: Test 3 collision did not exit with code 1 (got $TEST3_EXIT)!" >&2
    exit 1
fi
echo "  [PASS] Test 3: Collision aborted with code 1, DB_CREATED remained 0, drop was skipped."

# -----------------------------------------------------------------------------
# Test 4: Simulated Cleanup Failure
# -----------------------------------------------------------------------------
echo "[Test 4/5] Testing Simulated Cleanup Failure..."
TEST4_DB="fm_stage2_test4_$$"
# Pre-create test 4 database so we can test what happens if dropdb fails
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$TEST4_DB\";" >/dev/null

set +e
(
    set -euo pipefail
    TARGET_DB="$TEST4_DB"
    DB_CREATED=1  # Positively created
    IN_CLEANUP=0

    cleanup_stage2() {
        ORIGINAL_EXIT=$?
        if [ "$IN_CLEANUP" -eq 1 ]; then
            exit "$ORIGINAL_EXIT"
        fi
        IN_CLEANUP=1
        set +e

        CLEANUP_FAILED=0
        if [ "${DB_CREATED:-0}" -eq 1 ] && [ -n "${TARGET_DB:-}" ]; then
            echo "Attempting deletion of verified ephemeral database created by this execution: $TARGET_DB..."
            
            # SIMULATE DROPDB FAILURE: attempt to drop with invalid user
            if psql -U non_existent_user -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE \"$TARGET_DB\";" 2>/dev/null; then
                DROPDB_STATUS=0
            else
                DROPDB_STATUS=$?
            fi

            DB_STILL_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TARGET_DB}';" 2>/dev/null || echo "0")
            if [ "$DROPDB_STATUS" -eq 0 ] && [ "$DB_STILL_EXISTS" != "1" ]; then
                echo "Ephemeral database $TARGET_DB dropped cleanly."
            else
                echo "  [DETECTED] CRITICAL CLEANUP FAILURE: Failed to drop ephemeral database $TARGET_DB! (dropdb status: $DROPDB_STATUS, still_exists: $DB_STILL_EXISTS)" >&2
                CLEANUP_FAILED=1
            fi
        fi

        # If original succeeded (0) but cleanup failed, MUST return non-zero (1)
        if [ "$ORIGINAL_EXIT" -ne 0 ]; then
            exit "$ORIGINAL_EXIT"
        elif [ "$CLEANUP_FAILED" -ne 0 ]; then
            exit 1
        else
            exit 0
        fi
    }
    trap cleanup_stage2 EXIT

    echo "Executing restore drill successfully..."
    # Execution itself succeeds (0)
    exit 0
)
TEST4_EXIT=$?
set -e

if [ "$TEST4_EXIT" -ne 1 ]; then
    echo "CRITICAL: Test 4 cleanup failure did NOT return non-zero exit code (got $TEST4_EXIT)!" >&2
    exit 1
fi
echo "  [PASS] Test 4: Cleanup failure correctly overrode exit status 0 to non-zero exit code 1."

# Clean up test 4 database
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE \"$TEST4_DB\";" >/dev/null

# -----------------------------------------------------------------------------
# Test 5: Confirm Sentinel Database is NEVER Modified or Dropped
# -----------------------------------------------------------------------------
echo "[Test 5/5] Confirming Sentinel Database Integrity..."
SENTINEL_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${SENTINEL_DB}';")
if [ "$SENTINEL_EXISTS" != "1" ]; then
    echo "CRITICAL SAFETY VIOLATION: Sentinel database was deleted!" >&2
    exit 1
fi

SENTINEL_NOTE=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$SENTINEL_DB" -t -A -c "SELECT note FROM sentinel_marker WHERE id = 42;")
if [ "$SENTINEL_NOTE" != "DO_NOT_TOUCH" ]; then
    echo "CRITICAL SAFETY VIOLATION: Sentinel database data was corrupted!" >&2
    exit 1
fi
echo "  [PASS] Sentinel database positively preserved and unmodified across all tests."

echo "=== ALL 5 STAGE 2 OWNERSHIP & VERIFIED CLEANUP TESTS PASSED ==="
exit 0
