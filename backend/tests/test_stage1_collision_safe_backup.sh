#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — Stage 1 Collision-Safe Backup Filenames Test Drill
# Tests mktemp partial-file allocation, collision avoidance against pre-existing
# backups, fail-closed atomic promotion via mv -nT, source non-existence assertion,
# non-empty destination validation, mid-window collision detection, and partial cleanup.
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

echo "[1/5] Pre-creating existing backup representing prior run..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
EXISTING_BACKUP="${TEST_DIR}/funmoments_staging_pre_type_alignment_${TIMESTAMP}_1111111111.dump"
echo "PRE_EXISTING_DUMP_CONTENT_THAT_MUST_NOT_BE_TOUCHED" > "$EXISTING_BACKUP"
chmod 600 "$EXISTING_BACKUP"
ORIGINAL_HASH=$(sha256sum "$EXISTING_BACKUP" | awk '{print $1}')
echo "Pre-existing backup: $EXISTING_BACKUP (SHA-256: $ORIGINAL_HASH)"

echo "[2/5] Executing collision-safe mktemp backup creation and fail-closed promotion..."
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

    # Fail-closed atomic promotion using mv -nT --
    mv -nT -- "$PARTIAL_FILE" "$FINAL_FILE"

    # Immediately verify that source partial file no longer exists
    if [ -e "$PARTIAL_FILE" ]; then
        echo "CRITICAL: Promotion failed! Source $PARTIAL_FILE still exists." >&2
        exit 1
    fi

    # Verify that destination is a regular, non-empty file
    if [ ! -f "$FINAL_FILE" ] || [ ! -s "$FINAL_FILE" ]; then
        echo "CRITICAL: Promotion failed! Destination $FINAL_FILE missing or empty." >&2
        exit 1
    fi

    # Positive confirmation: disarm cleanup trap
    CLEANUP_PARTIAL=0

    echo "Successfully promoted to: $FINAL_FILE"
)

echo "[3/5] Verifying pre-existing backup preservation and distinct naming..."
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

echo "[4/5] Testing Cleanup of Partial File on Early Failure..."
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

echo "[5/5] Testing Mid-Window Destination Collision (Fail-Closed No-Clobber)..."
COLLISION_TEST_DIR="${TEST_DIR}/collision_race"
mkdir -p "$COLLISION_TEST_DIR"
chmod 700 "$COLLISION_TEST_DIR"

COLLISION_TARGET="${COLLISION_TEST_DIR}/pre_existing_target.dump"
echo "EXISTING_VALUABLE_BACKUP_DO_NOT_OVERWRITE" > "$COLLISION_TARGET"
chmod 600 "$COLLISION_TARGET"
TARGET_HASH_BEFORE=$(sha256sum "$COLLISION_TARGET" | awk '{print $1}')

set +e
(
    set -euo pipefail
    BACKUP_DIR="$COLLISION_TEST_DIR"
    # Create partial file
    PARTIAL_FILE=$(mktemp "${BACKUP_DIR}/funmoments_staging_test_XXXXXXXXXX.partial")
    FINAL_FILE="$COLLISION_TARGET" # Simulating race where final file exists before mv

    CLEANUP_PARTIAL=1
    cleanup_stage1() {
        EXIT_CODE=$?
        if [ "$CLEANUP_PARTIAL" -eq 1 ] && [ -n "${PARTIAL_FILE:-}" ] && [ -f "${PARTIAL_FILE:-}" ]; then
            rm -f "$PARTIAL_FILE"
        fi
        exit "$EXIT_CODE"
    }
    trap cleanup_stage1 EXIT

    chmod 600 "$PARTIAL_FILE"
    echo "NEW_UNCOMMITTED_BACKUP_STREAM" > "$PARTIAL_FILE"

    # Attempt fail-closed promotion
    mv -nT -- "$PARTIAL_FILE" "$FINAL_FILE"

    # Assert source partial file still exists (because mv -n skipped the move)
    if [ -e "$PARTIAL_FILE" ]; then
        echo "  [DETECTED] Source $PARTIAL_FILE remains after mv -nT skipped move due to existing destination." >&2
        exit 1
    fi

    # Should not reach here
    if [ ! -f "$FINAL_FILE" ] || [ ! -s "$FINAL_FILE" ]; then
        exit 1
    fi
    CLEANUP_PARTIAL=0
)
RACE_EXIT=$?
set -e

if [ "$RACE_EXIT" -ne 1 ]; then
    echo "CRITICAL: Expected mid-window collision to exit with code 1, got $RACE_EXIT!" >&2
    exit 1
fi
echo "  [PASS] Mid-window collision correctly reported failure (Exit code: 1)."

TARGET_HASH_AFTER=$(sha256sum "$COLLISION_TARGET" | awk '{print $1}')
if [ "$TARGET_HASH_BEFORE" != "$TARGET_HASH_AFTER" ]; then
    echo "CRITICAL FAILURE: Existing backup was overwritten or corrupted!" >&2
    exit 1
fi
echo "  [PASS] Pre-existing backup is 100% untouched and unchanged."

COLLISION_PARTIALS=$(find "$COLLISION_TEST_DIR" -maxdepth 1 -name "*.partial" | wc -l)
if [ "$COLLISION_PARTIALS" -ne 0 ]; then
    echo "CRITICAL FAILURE: Partial file remained after collision failure!" >&2
    exit 1
fi
echo "  [PASS] Partial file removed cleanly by trap (0 lingering partials)."

echo "=== ALL STAGE 1 COLLISION-SAFE BACKUP CHECKS PASSED (5/5) ==="
exit 0
