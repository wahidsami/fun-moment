#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — Stage 1 Collision-Safe Backup Filenames Test Drill
# Tests mktemp partial-file allocation, collision avoidance against pre-existing
# backups, atomic promotion via mv -n, non-clobber assertions, and partial cleanup.
# =============================================================================
set -euo pipefail
umask 077

echo "=== STAGE 1 COLLISION-SAFE BACKUP TEST DRILL (PostgreSQL 17.4) ==="

TEST_DIR="./secure_local_backups/stage1_test_$$"
mkdir -p "$TEST_DIR"
chmod 700 "$TEST_DIR"

cleanup() {
    rm -rf "$TEST_DIR"
}
trap cleanup EXIT

echo "[1/4] Pre-creating existing backup representing prior run..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
EXISTING_BACKUP="${TEST_DIR}/funmoments_staging_pre_type_alignment_${TIMESTAMP}_1111111111.dump"
echo "PRE_EXISTING_DUMP_CONTENT_THAT_MUST_NOT_BE_TOUCHED" > "$EXISTING_BACKUP"
chmod 600 "$EXISTING_BACKUP"
ORIGINAL_HASH=$(sha256sum "$EXISTING_BACKUP" | awk '{print $1}')
echo "Pre-existing backup: $EXISTING_BACKUP (SHA-256: $ORIGINAL_HASH)"

echo "[2/4] Executing collision-safe mktemp backup creation and atomic promotion..."
(
    set -euo pipefail
    BACKUP_DIR="$TEST_DIR"
    TS=$(date +%Y%m%d_%H%M%S)

    # Collision-safe partial file creation
    PARTIAL_FILE=$(mktemp "${BACKUP_DIR}/funmoments_staging_pre_type_alignment_${TS}_XXXXXXXXXX.partial")
    FINAL_FILE="${PARTIAL_FILE%.partial}.dump"

    # Destination existence check
    if [ -e "$FINAL_FILE" ]; then
        echo "CRITICAL: Final destination $FINAL_FILE already exists!" >&2
        rm -f "$PARTIAL_FILE"
        exit 1
    fi

    CLEANUP_PARTIAL=1
    cleanup_stage1() {
        EXIT_CODE=$?
        if [ "$CLEANUP_PARTIAL" -eq 1 ] && [ -n "${PARTIAL_FILE:-}" ] && [ -f "${PARTIAL_FILE:-}" ]; then
            echo "Cleaning up incomplete partial: $PARTIAL_FILE..."
            rm -f "$PARTIAL_FILE"
        fi
        exit "$EXIT_CODE"
    }
    trap cleanup_stage1 EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM

    chmod 600 "$PARTIAL_FILE"

    # Simulate writing backup stream with valid PGDMP header and > 1000 bytes
    printf "PGDMP%s\n" "$(head -c 2000 /dev/urandom | base64)" > "$PARTIAL_FILE"

    FILE_SIZE=$(stat -c%s "$PARTIAL_FILE")
    if [ "$FILE_SIZE" -lt 1000 ]; then
        echo "File too small!" >&2
        exit 1
    fi

    HEADER=$(head -c 5 "$PARTIAL_FILE")
    if [ "$HEADER" != "PGDMP" ]; then
        echo "Header mismatch!" >&2
        exit 1
    fi

    # Atomic promotion
    mv -n "$PARTIAL_FILE" "$FINAL_FILE"
    CLEANUP_PARTIAL=0
    chmod 600 "$FINAL_FILE"

    echo "Successfully promoted to: $FINAL_FILE"
)

echo "[3/4] Verifying pre-existing backup preservation and distinct naming..."
# 1. Verify pre-existing backup still exists with exact original checksum
CURRENT_HASH=$(sha256sum "$EXISTING_BACKUP" | awk '{print $1}')
if [ "$CURRENT_HASH" != "$ORIGINAL_HASH" ]; then
    echo "CRITICAL FAILURE: Pre-existing backup was modified!" >&2
    exit 1
fi
echo "  [PASS] Pre-existing backup is 100% unchanged (SHA-256 match)."

# 2. Verify exactly 2 .dump files exist in the directory (pre-existing + new)
DUMP_COUNT=$(find "$TEST_DIR" -maxdepth 1 -name "*.dump" | wc -l)
if [ "$DUMP_COUNT" -ne 2 ]; then
    echo "CRITICAL FAILURE: Expected 2 .dump files, found $DUMP_COUNT!" >&2
    exit 1
fi
echo "  [PASS] Exactly 2 distinct backup files present."

# 3. Verify zero .partial files remain
PARTIAL_COUNT=$(find "$TEST_DIR" -maxdepth 1 -name "*.partial" | wc -l)
if [ "$PARTIAL_COUNT" -ne 0 ]; then
    echo "CRITICAL FAILURE: Incomplete .partial files remaining!" >&2
    exit 1
fi
echo "  [PASS] Zero lingering .partial files (atomic promotion verified)."

echo "[4/4] Testing Cleanup of Partial File on Failure..."
set +e
(
    set -euo pipefail
    BACKUP_DIR="$TEST_DIR"
    TS=$(date +%Y%m%d_%H%M%S)
    PARTIAL_FILE=$(mktemp "${BACKUP_DIR}/funmoments_staging_pre_type_alignment_${TS}_XXXXXXXXXX.partial")
    FINAL_FILE="${PARTIAL_FILE%.partial}.dump"

    CLEANUP_PARTIAL=1
    cleanup_stage1() {
        EXIT_CODE=$?
        if [ "$CLEANUP_PARTIAL" -eq 1 ] && [ -n "${PARTIAL_FILE:-}" ] && [ -f "${PARTIAL_FILE:-}" ]; then
            rm -f "$PARTIAL_FILE"
        fi
        exit "$EXIT_CODE"
    }
    trap cleanup_stage1 EXIT

    # Simulate error before promotion
    echo "Incomplete data" > "$PARTIAL_FILE"
    exit 1
)
FAIL_EXIT=$?
set -e

if [ "$FAIL_EXIT" -ne 1 ]; then
    echo "CRITICAL: Expected fail exit code 1, got $FAIL_EXIT!" >&2
    exit 1
fi

PARTIAL_COUNT_AFTER_FAIL=$(find "$TEST_DIR" -maxdepth 1 -name "*.partial" | wc -l)
if [ "$PARTIAL_COUNT_AFTER_FAIL" -ne 0 ]; then
    echo "CRITICAL FAILURE: Partial file was NOT cleaned up on failure!" >&2
    exit 1
fi
echo "  [PASS] Failed partial file cleaned up by trap (0 lingering partials)."

echo "=== ALL STAGE 1 COLLISION-SAFE BACKUP CHECKS PASSED (4/4) ==="
exit 0

