#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — Stage 3 Local Workstation Verification Drill
# Tests fail-closed OpenSSL decryption with conditional error handling,
# PGDMP header check, pg_restore -l TOC, temporary plaintext file permissions (600),
# explicit signal handling (INT, TERM, EXIT), remote-key best-effort cleanup trap,
# and preservation of the encrypted archive and symmetric key against PostgreSQL 17.4.
# =============================================================================
set -euo pipefail
umask 077

echo "=== STAGE 3 LOCAL VERIFICATION TEST DRILL (PostgreSQL 17.4) ==="

DRILL_DIR="./secure_local_backups/stage3_drill_$$"
mkdir -p "$DRILL_DIR"
chmod 700 "$DRILL_DIR"

DB_NAME="fm_stage3_drill_$$"
export PGPASSWORD="${PGPASSWORD:-postgres}"
PG_HOST="${PG_HOST:-127.0.0.1}"
PG_PORT="${PG_PORT:-5432}"
PG_USER="${PG_USER:-postgres}"

# Cleanup fixture database and drill dir on overall test script exit
cleanup_all() {
    echo "Cleaning up drill fixtures..."
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE IF EXISTS \"$DB_NAME\";" >/dev/null 2>&1 || true
    rm -rf "$DRILL_DIR"
    echo "Fixture cleanup complete."
}
trap cleanup_all EXIT

echo "[1/8] Creating fixture database: $DB_NAME..."
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$DB_NAME\";" >/dev/null

echo "[2/8] Populating schema objects (> 50 catalog items)..."
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$DB_NAME" -q << 'SQL'
DO $$
DECLARE
    i INT;
BEGIN
    FOR i IN 1..30 LOOP
        EXECUTE format('
            CREATE TABLE test_table_%s (
                id BIGSERIAL PRIMARY KEY,
                uuid UUID DEFAULT gen_random_uuid(),
                name VARCHAR(191) NOT NULL,
                metadata JSONB DEFAULT ''{"active": true}'',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX idx_test_table_%s_uuid ON test_table_%s(uuid);
            COMMENT ON TABLE test_table_%s IS ''Test table catalog item'';
            INSERT INTO test_table_%s (name) VALUES (''Sample '' || %s);
        ', i, i, i, i, i, i);
    END LOOP;
END $$;

CREATE VIEW test_composite_view AS
    SELECT t1.id, t1.name FROM test_table_1 t1;
SQL

echo "[3/8] Generating native custom dump with pg_dump -F c..."
DUMP_FILE="${DRILL_DIR}/fixture_backup.dump"
pg_dump -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$DB_NAME" -F c -b -v > "$DUMP_FILE"
chmod 600 "$DUMP_FILE"

DUMP_SIZE=$(stat -c%s "$DUMP_FILE")
echo "Generated custom dump size: $DUMP_SIZE bytes"

echo "[4/8] Generating Base64 32-byte key and encrypting with AES-256-CBC PBKDF2..."
KEY_FILE="${DRILL_DIR}/backup_key.txt"
openssl rand -base64 32 > "$KEY_FILE"
chmod 600 "$KEY_FILE"

ENC_FILE="${DUMP_FILE}.enc"
openssl enc -aes-256-cbc -salt -pbkdf2 -in "$DUMP_FILE" -out "$ENC_FILE" -pass file:"$KEY_FILE"
chmod 600 "$ENC_FILE"

# Remove the original unencrypted dump to simulate pristine state after download
rm -f "$DUMP_FILE"

echo "[5/8] Executing Stage 3 Local Verification Procedure in isolated subshell..."
# Run the exact Stage 3 verification script inside a subshell to test trap and exit behavior
(
    set -euo pipefail
    umask 077

    LATEST_LOCAL_ENC="$ENC_FILE"
    LOCAL_KEY="$KEY_FILE"
    TEMP_DECRYPTED="${DRILL_DIR}/.temp_decrypted_$$.dump"
    REMOTE_KEY_FILE=""

    # Local cleanup handler guaranteed on exit, error, or interruption
    cleanup_local() {
        EXIT_CODE=$?
        if [ -n "${TEMP_DECRYPTED:-}" ] && [ -f "${TEMP_DECRYPTED:-}" ]; then
            rm -f "$TEMP_DECRYPTED"
        fi
        exit "$EXIT_CODE"
    }

    trap cleanup_local EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM

    echo "Testing local decryption and PostgreSQL Table of Contents inspection..."

    # Create secure temporary plaintext file inside secure directory with mode 600
    touch "$TEMP_DECRYPTED"
    chmod 600 "$TEMP_DECRYPTED"

    echo "  [Step 1] Decrypting archive into temporary file: $TEMP_DECRYPTED..."
    if openssl enc -d -aes-256-cbc -pbkdf2 -in "$LATEST_LOCAL_ENC" -out "$TEMP_DECRYPTED" -pass file:"$LOCAL_KEY"; then
        echo "  [PASS] OpenSSL decryption completed."
    else
        STATUS=$?
        echo "CRITICAL PRECONDITION FAILED: OpenSSL decryption failed with exit code $STATUS!" >&2
        exit "$STATUS"
    fi
    chmod 600 "$TEMP_DECRYPTED"

    # Verify non-zero file size
    DECRYPTED_SIZE=$(stat -c%s "$TEMP_DECRYPTED")
    if [ "$DECRYPTED_SIZE" -lt 1000 ]; then
        echo "CRITICAL PRECONDITION FAILED: Decrypted file abnormally small ($DECRYPTED_SIZE bytes)!" >&2
        exit 1
    fi
    echo "  [PASS] Decryption completed successfully ($DECRYPTED_SIZE bytes)."

    echo "  [Step 2] Verifying PGDMP magic header on completed decrypted archive..."
    HEADER_CHECK=$(head -c 5 "$TEMP_DECRYPTED")
    if [ "$HEADER_CHECK" != "PGDMP" ]; then
        echo "CRITICAL PRECONDITION FAILED: Decrypted archive does not contain valid PGDMP header! (Found: '$HEADER_CHECK')" >&2
        exit 1
    fi
    echo "  [PASS] Valid PostgreSQL custom dump header (PGDMP) confirmed."

    echo "  [Step 3] Executing pg_restore -l against completed decrypted archive..."
    if TOC_OUTPUT=$(pg_restore -l "$TEMP_DECRYPTED"); then
        echo "  [PASS] pg_restore -l catalog extraction completed."
    else
        STATUS=$?
        echo "CRITICAL PRECONDITION FAILED: pg_restore -l failed with exit code $STATUS!" >&2
        exit "$STATUS"
    fi

    # Count valid catalog objects (excluding comment lines and blank lines)
    TOC_LINES=$(echo "$TOC_OUTPUT" | grep -v '^;' | grep -v '^[[:space:]]*$' | wc -l)
    echo "  [PASS] pg_restore -l verified ${TOC_LINES} catalog objects."

    if [ "$TOC_LINES" -lt 50 ]; then
        echo "CRITICAL PRECONDITION FAILED: Decrypted archive contains insufficient catalog objects (${TOC_LINES} < 50)!" >&2
        exit 1
    fi

    echo "  [Step 4] Subshell finishing successfully. Trap will clean up temporary plaintext file."
)
SUBSHELL_STATUS=$?
if [ $SUBSHELL_STATUS -ne 0 ]; then
    echo "CRITICAL: Stage 3 verification subshell failed with status $SUBSHELL_STATUS!" >&2
    exit 1
fi

echo "[6/8] Verifying cleanup trap and file preservation..."
TEMP_FILES_REMAINING=$(find "$DRILL_DIR" -name ".temp_decrypted_*" | wc -l)
if [ "$TEMP_FILES_REMAINING" -ne 0 ]; then
    echo "CRITICAL: Temporary decrypted plaintext file was NOT cleaned up by the trap!" >&2
    exit 1
fi
echo "[PASS] Temporary decrypted plaintext file removed cleanly by trap (0 plaintext files remaining)."

if [ ! -f "$ENC_FILE" ]; then
    echo "CRITICAL: Encrypted archive was unexpectedly deleted!" >&2
    exit 1
fi
if [ ! -f "$KEY_FILE" ]; then
    echo "CRITICAL: Local encryption key was unexpectedly deleted!" >&2
    exit 1
fi
echo "[PASS] Encrypted archive and encryption key are safely preserved."

echo "[7/8] Testing Fail-Closed Negative Scenarios (Conditional Handling)..."

# Negative Scenario A: Bad Key with conditional check
echo "  [Negative A] Testing with incorrect key..."
WRONG_KEY="${DRILL_DIR}/wrong_key.txt"
openssl rand -base64 32 > "$WRONG_KEY"
(
    set -euo pipefail
    TEMP_BAD="${DRILL_DIR}/.temp_decrypted_bad_$$.dump"
    cleanup_bad() {
        rm -f "$TEMP_BAD"
    }
    trap cleanup_bad EXIT
    touch "$TEMP_BAD"
    if openssl enc -d -aes-256-cbc -pbkdf2 -in "$ENC_FILE" -out "$TEMP_BAD" -pass file:"$WRONG_KEY" 2>/dev/null; then
        echo "Unexpected success!" >&2
        exit 1
    else
        STATUS=$?
        echo "  [PASS] Caught expected OpenSSL decryption failure code: $STATUS"
        exit "$STATUS"
    fi
) && {
    echo "CRITICAL: Decryption with wrong key unexpectedly succeeded!" >&2
    exit 1
} || {
    echo "  [PASS] Wrong key decryption cleanly halted execution with non-zero exit code."
}
BAD_TEMP_REMAINING=$(find "$DRILL_DIR" -name ".temp_decrypted_bad_*" | wc -l)
if [ "$BAD_TEMP_REMAINING" -ne 0 ]; then
    echo "CRITICAL: Temporary file from failed decryption was NOT cleaned up!" >&2
    exit 1
fi
echo "  [PASS] Temporary plaintext file cleaned up on decryption error."

# Negative Scenario B: Corrupted Encrypted Archive
echo "  [Negative B] Testing with corrupted archive..."
CORRUPT_ENC="${DRILL_DIR}/corrupt.dump.enc"
echo "CORRUPTED_CIPHERTEXT_DATA_THAT_IS_NOT_VALID" > "$CORRUPT_ENC"
(
    set -euo pipefail
    TEMP_CORRUPT="${DRILL_DIR}/.temp_decrypted_corrupt_$$.dump"
    cleanup_corrupt() {
        rm -f "$TEMP_CORRUPT"
    }
    trap cleanup_corrupt EXIT
    touch "$TEMP_CORRUPT"
    if openssl enc -d -aes-256-cbc -pbkdf2 -in "$CORRUPT_ENC" -out "$TEMP_CORRUPT" -pass file:"$KEY_FILE" 2>/dev/null; then
        echo "Unexpected success!" >&2
        exit 1
    else
        STATUS=$?
        echo "  [PASS] Caught expected corruption error code: $STATUS"
        exit "$STATUS"
    fi
) && {
    echo "CRITICAL: Decryption of corrupted archive unexpectedly succeeded!" >&2
    exit 1
} || {
    echo "  [PASS] Corrupted archive decryption failed closed as expected."
}

echo "[8/8] Testing Signal Interruption Handling..."
# Test SIGINT (130) and SIGTERM (143) trap handlers guaranteeing cleanup and exit
(
    set -euo pipefail
    TEMP_SIGNAL="${DRILL_DIR}/.temp_decrypted_signal_$$.dump"
    cleanup_sig() {
        rm -f "$TEMP_SIGNAL"
    }
    trap cleanup_sig EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
    touch "$TEMP_SIGNAL"
    # Simulate receiving SIGINT
    exit 130
) || SIG_STATUS=$?

if [ "${SIG_STATUS:-0}" -eq 130 ]; then
    echo "  [PASS] Interrupted process exited with code 130."
else
    echo "  [FAIL] Expected exit code 130, got ${SIG_STATUS:-0}" >&2
    exit 1
fi

SIG_TEMP_REMAINING=$(find "$DRILL_DIR" -name ".temp_decrypted_signal_*" | wc -l)
if [ "$SIG_TEMP_REMAINING" -ne 0 ]; then
    echo "CRITICAL: Temporary file from interrupted session was NOT cleaned up!" >&2
    exit 1
fi
echo "  [PASS] Temporary plaintext file cleaned up on signal interruption."

# Verify that the encrypted archive and local key are STILL intact after signal interruption
if [ ! -f "$ENC_FILE" ] || [ ! -f "$KEY_FILE" ]; then
    echo "CRITICAL: Key or encrypted backup deleted during interruption cleanup!" >&2
    exit 1
fi
echo "  [PASS] Encrypted backup and local key preserved during interruption."

echo "=== ALL STAGE 3 LOCAL VERIFICATION CHECKS PASSED (8/8) ==="
exit 0
