#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — Fingerprint Canonicalization & End-to-End Regression Test Drill
# Verifies Stage 4 pre-migration and Stage 6 post-migration canonical BIGINT
# text serialization across '007', ' 456 ', '+0008', asserts identical digests,
# proves mutation detection on non-key field changes, and enforces fail-closed
# behavior on invalid inputs against PostgreSQL 17.4.
# =============================================================================
set -euo pipefail
umask 077

echo "=== FINGERPRINT CANONICALIZATION REGRESSION DRILL (PostgreSQL 17.4) ==="

export PGPASSWORD="${PGPASSWORD:-postgres}"
PG_HOST="${PG_HOST:-127.0.0.1}"
PG_PORT="${PG_PORT:-5432}"
PG_USER="${PG_USER:-postgres}"

TEMP_DB="fm_canon_drill_$$"
DRILL_DIR="./secure_local_backups/canon_drill_$$"
mkdir -p "$DRILL_DIR"
chmod 700 "$DRILL_DIR"

cleanup() {
    echo "Teardown: dropping ephemeral database $TEMP_DB and cleaning up..."
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE IF EXISTS \"$TEMP_DB\";" >/dev/null 2>&1 || true
    rm -rf "$DRILL_DIR"
    echo "Teardown complete."
}
trap cleanup EXIT INT TERM

echo "[1/6] Creating ephemeral database: $TEMP_DB..."
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$TEMP_DB\";" >/dev/null

echo "[2/6] Provisioning schema with legacy VARCHAR(191) seller_id..."
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q << 'SQL'
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL
);

CREATE TABLE seller_verifies (
    id BIGSERIAL PRIMARY KEY,
    seller_id VARCHAR(191),
    status INT DEFAULT 0,
    cr_number VARCHAR(191),
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT '2026-11-05 12:00:00'
);

INSERT INTO users (id, name) VALUES
    (7, 'User Seven'),
    (456, 'User Four-Fifty-Six'),
    (8, 'User Eight');

-- Insert the required test fixtures with raw string variations
INSERT INTO seller_verifies (id, seller_id, status, cr_number) VALUES
    (1, '007', 0, 'CR_007'),
    (2, '  456  ', 1, 'CR_456'),
    (3, '+0008', 0, 'CR_008'),
    (4, '7', 0, 'CR_ORIGINAL');
SQL

echo "[3/6] Capturing Stage 4 pre-migration digests..."
STAGE4_QUERY="
SELECT sv.id || '|' || encode(sha256(convert_to(
    (to_jsonb(sv) || jsonb_build_object(
        'seller_id',
        CASE
            WHEN sv.seller_id IS NULL THEN NULL
            ELSE trim(sv.seller_id::text)::bigint::text
        END
    ))::text,
    'UTF8'
)), 'hex')
FROM seller_verifies sv
ORDER BY sv.id;
"
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -q -c "$STAGE4_QUERY" > "${DRILL_DIR}/pre_digests.txt"

echo "Pre-migration digests captured:"
cat "${DRILL_DIR}/pre_digests.txt"

echo "[4/6] Verifying fail-closed behavior on non-numeric and out-of-range inputs..."
# Advance sequence to prevent PK collisions
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q -c "SELECT setval('seller_verifies_id_seq', (SELECT max(id) FROM seller_verifies));"

# Verify that non-numeric seller_id throws an error and does NOT silently produce NULL
(
    set -e
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q -c "
        INSERT INTO seller_verifies (id, seller_id, status, cr_number) VALUES (99, 'not_a_number', 0, 'CR_ERR');
    "
    # Stage 4 query must raise exception on 'not_a_number'
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$STAGE4_QUERY" 2>/dev/null
) && {
    echo "CRITICAL: Stage 4 query failed to fail closed on non-numeric string!" >&2
    exit 1
} || {
    echo "  [PASS] Non-numeric value cleanly halted Stage 4 query (fail-closed)."
}
# Clean up error fixture
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q -c "DELETE FROM seller_verifies WHERE id = 99;"

# Verify out-of-range seller_id throws an error
(
    set -e
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q -c "
        INSERT INTO seller_verifies (id, seller_id, status, cr_number) VALUES (99, '99999999999999999999999999999999', 0, 'CR_ERR');
    "
    psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$STAGE4_QUERY" 2>/dev/null
) && {
    echo "CRITICAL: Stage 4 query failed to fail closed on out-of-range numeric value!" >&2
    exit 1
} || {
    echo "  [PASS] Out-of-range numeric value cleanly halted Stage 4 query (fail-closed)."
}
# Clean up error fixture
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q -c "DELETE FROM seller_verifies WHERE id = 99;"

echo "[5/6] Executing targeted migration DDL & non-key mutation..."
# Execute the exact migration DDL
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q << 'SQL'
SET lock_timeout = '5s';
ALTER TABLE seller_verifies 
ALTER COLUMN seller_id TYPE bigint 
USING (NULLIF(trim(seller_id::text), '')::bigint);
SQL

# Mutate non-key field on record 4 to prove mutation detection
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q -c "
UPDATE seller_verifies SET cr_number = 'CR_MUTATED_VALUE' WHERE id = 4;
"

echo "[6/6] Capturing Stage 6 post-migration digests and asserting parity..."
STAGE6_QUERY="
SELECT sv.id || '|' || encode(sha256(convert_to(
    (to_jsonb(sv) || jsonb_build_object(
        'seller_id',
        CASE
            WHEN sv.seller_id IS NULL THEN NULL
            ELSE sv.seller_id::text
        END
    ))::text,
    'UTF8'
)), 'hex')
FROM seller_verifies sv
ORDER BY sv.id;
"
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -q -c "$STAGE6_QUERY" > "${DRILL_DIR}/post_digests.txt"

echo "Post-migration digests captured:"
cat "${DRILL_DIR}/post_digests.txt"

# Assert parity for records 1, 2, 3
PRE_1=$(grep '^1|' "${DRILL_DIR}/pre_digests.txt")
POST_1=$(grep '^1|' "${DRILL_DIR}/post_digests.txt")
if [ "$PRE_1" = "$POST_1" ]; then
    echo "[PASS] Record 1 ('007' -> 7): Digests identical."
else
    echo "[FAIL] Record 1 mismatch: Pre: $PRE_1 vs Post: $POST_1" >&2
    exit 1
fi

PRE_2=$(grep '^2|' "${DRILL_DIR}/pre_digests.txt")
POST_2=$(grep '^2|' "${DRILL_DIR}/post_digests.txt")
if [ "$PRE_2" = "$POST_2" ]; then
    echo "[PASS] Record 2 (' 456 ' -> 456): Digests identical."
else
    echo "[FAIL] Record 2 mismatch: Pre: $PRE_2 vs Post: $POST_2" >&2
    exit 1
fi

PRE_3=$(grep '^3|' "${DRILL_DIR}/pre_digests.txt")
POST_3=$(grep '^3|' "${DRILL_DIR}/post_digests.txt")
if [ "$PRE_3" = "$POST_3" ]; then
    echo "[PASS] Record 3 ('+0008' -> 8): Digests identical."
else
    echo "[FAIL] Record 3 mismatch: Pre: $PRE_3 vs Post: $POST_3" >&2
    exit 1
fi

# Assert mutation detection for record 4
PRE_4=$(grep '^4|' "${DRILL_DIR}/pre_digests.txt")
POST_4=$(grep '^4|' "${DRILL_DIR}/post_digests.txt")
if [ "$PRE_4" != "$POST_4" ]; then
    echo "[PASS] Record 4 (cr_number mutated): Digest changed as expected (proves genuine change detection)."
else
    echo "[FAIL] Record 4 digest did NOT change despite mutation!" >&2
    exit 1
fi

echo "=== ALL FINGERPRINT CANONICALIZATION CHECKS PASSED ==="
exit 0
