#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — Stage 2 Pre-Migration Validation Drill (PostgreSQL 17.4)
# Verifies guarded numeric validation, normalized relationship matching,
# detection of all 10 edge cases, and zero SQL operator errors.
# =============================================================================
set -euo pipefail
umask 077

echo "=== STAGE 2 PRE-MIGRATION VALIDATION DRILL (PostgreSQL 17.4) ==="

export PGPASSWORD="${PGPASSWORD:-postgres}"
PG_HOST="${PG_HOST:-127.0.0.1}"
PG_PORT="${PG_PORT:-5432}"
PG_USER="${PG_USER:-postgres}"

TEMP_DB="fm_stage2_pre_mig_test_$$"
DB_CREATED=0

# Ownership-guarded cleanup handler
cleanup() {
    ORIGINAL_EXIT=$?
    if [ "$DB_CREATED" -eq 1 ]; then
        echo "Teardown: dropping verified ephemeral database $TEMP_DB..."
        psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "DROP DATABASE \"$TEMP_DB\";" >/dev/null 2>&1 || true
        echo "Ephemeral database dropped cleanly."
    else
        echo "Ownership guard: DB_CREATED=0, skipping dropdb."
    fi
    exit "$ORIGINAL_EXIT"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# Collision check
DB_EXISTS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -t -A -c "SELECT 1 FROM pg_database WHERE datname = '${TEMP_DB}';")
if [ "$DB_EXISTS" = "1" ]; then
    echo "CRITICAL SAFETY ABORT: Database ${TEMP_DB} already exists! Halting without modifying or dropping." >&2
    exit 1
fi

echo "[Setup] Creating ephemeral database: $TEMP_DB..."
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d postgres -c "CREATE DATABASE \"$TEMP_DB\";" >/dev/null
DB_CREATED=1

echo "[Setup] Provisioning original pre-migration schema..."
# Pre-migration schema: users.id is BIGINT, seller_verifies.seller_id is VARCHAR(191)
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -q << 'SQL'
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL
);

CREATE TABLE seller_verifies (
    id BIGSERIAL PRIMARY KEY,
    seller_id VARCHAR(191),
    status INT DEFAULT 0
);

CREATE TABLE provider_categories (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL,
    category_id BIGINT NOT NULL
);

-- Seed baseline users
INSERT INTO users (id, name) VALUES 
    (1, 'User 1'),
    (7, 'User 7 (Leading zero target)'),
    (456, 'User 456 (Whitespace target)');

-- Seed baseline provider_category
INSERT INTO provider_categories (provider_id, category_id) VALUES (1, 10);
SQL

# Guarded validation SQL function / query
VALIDATE_SELLER_IDS_QUERY="
SELECT count(*)
FROM seller_verifies sv
WHERE sv.seller_id IS NULL
   OR trim(sv.seller_id::text) = ''
   OR trim(sv.seller_id::text) !~ '^[+-]?[0-9]+$'
   OR (CASE 
         WHEN trim(sv.seller_id::text) ~ '^[+-]?[0-9]+$' 
         THEN trim(sv.seller_id::text)::numeric < -9223372036854775808::numeric 
              OR trim(sv.seller_id::text)::numeric > 9223372036854775807::numeric
         ELSE true 
       END);
"

NORMALIZED_ORPHAN_QUERY="
SELECT count(*)
FROM seller_verifies sv
LEFT JOIN users u
  ON u.id = NULLIF(trim(sv.seller_id::text), '')::bigint
WHERE u.id IS NULL;
"

echo "Executing test cases against pre-migration schema..."

# -----------------------------------------------------------------------------
# Case 1: Valid numeric seller ID matching an existing user
# -----------------------------------------------------------------------------
echo -n "Test Case 1: Valid numeric seller ID ('1')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('1');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
ORPHANS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$NORMALIZED_ORPHAN_QUERY")
if [ "$INVALID" -eq 0 ] && [ "$ORPHANS" -eq 0 ]; then
    echo "[PASS] (Invalid: 0, Orphans: 0)"
else
    echo "[FAIL] (Invalid: $INVALID, Orphans: $ORPHANS)" >&2; exit 1
fi

# -----------------------------------------------------------------------------
# Case 2: Valid numeric seller ID with surrounding whitespace
# -----------------------------------------------------------------------------
echo -n "Test Case 2: Valid numeric seller ID with whitespace ('  456  ')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('  456  ');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
ORPHANS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$NORMALIZED_ORPHAN_QUERY")
if [ "$INVALID" -eq 0 ] && [ "$ORPHANS" -eq 0 ]; then
    echo "[PASS] (Invalid: 0, Orphans: 0, matched user 456)"
else
    echo "[FAIL] (Invalid: $INVALID, Orphans: $ORPHANS)" >&2; exit 1
fi

# -----------------------------------------------------------------------------
# Case 3: Valid numeric seller ID with leading zeroes
# -----------------------------------------------------------------------------
echo -n "Test Case 3: Valid numeric seller ID with leading zeroes ('007')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('007');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
ORPHANS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$NORMALIZED_ORPHAN_QUERY")
if [ "$INVALID" -eq 0 ] && [ "$ORPHANS" -eq 0 ]; then
    echo "[PASS] (Invalid: 0, Orphans: 0, matched user 7)"
else
    echo "[FAIL] (Invalid: $INVALID, Orphans: $ORPHANS)" >&2; exit 1
fi

# -----------------------------------------------------------------------------
# Case 4: NULL seller ID
# -----------------------------------------------------------------------------
echo -n "Test Case 4: NULL seller ID... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES (NULL);" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
if [ "$INVALID" -eq 1 ]; then
    echo "[PASS] (Validation gate caught NULL seller_id)"
else
    echo "[FAIL] (Expected 1 invalid, got $INVALID)" >&2; exit 1
fi
# Clean up Case 4 row
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "DELETE FROM seller_verifies WHERE seller_id IS NULL;" >/dev/null

# -----------------------------------------------------------------------------
# Case 5: Empty or whitespace-only seller ID
# -----------------------------------------------------------------------------
echo -n "Test Case 5: Empty or whitespace-only seller ID ('   ')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('   ');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
if [ "$INVALID" -eq 1 ]; then
    echo "[PASS] (Validation gate caught whitespace-only seller_id)"
else
    echo "[FAIL] (Expected 1 invalid, got $INVALID)" >&2; exit 1
fi
# Clean up Case 5 row
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "DELETE FROM seller_verifies WHERE trim(seller_id) = '';" >/dev/null

# -----------------------------------------------------------------------------
# Case 6: Alphabetic or otherwise non-numeric seller ID
# -----------------------------------------------------------------------------
echo -n "Test Case 6: Alphabetic or non-numeric seller ID ('abc-seller')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('abc-seller');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
if [ "$INVALID" -eq 1 ]; then
    echo "[PASS] (Validation gate caught non-numeric seller_id without query crash)"
else
    echo "[FAIL] (Expected 1 invalid, got $INVALID)" >&2; exit 1
fi
# Clean up Case 6 row
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "DELETE FROM seller_verifies WHERE seller_id = 'abc-seller';" >/dev/null

# -----------------------------------------------------------------------------
# Case 7: Numeric value exceeding BIGINT maximum (9223372036854775807)
# -----------------------------------------------------------------------------
echo -n "Test Case 7: Numeric value exceeding BIGINT max ('99999999999999999999999999999999')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('99999999999999999999999999999999');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
if [ "$INVALID" -eq 1 ]; then
    echo "[PASS] (Validation gate caught out-of-range value without overflow crash)"
else
    echo "[FAIL] (Expected 1 invalid, got $INVALID)" >&2; exit 1
fi
# Clean up Case 7 row
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "DELETE FROM seller_verifies WHERE seller_id = '99999999999999999999999999999999';" >/dev/null

# -----------------------------------------------------------------------------
# Case 8: Numeric value below BIGINT minimum (-9223372036854775808)
# -----------------------------------------------------------------------------
echo -n "Test Case 8: Numeric value below BIGINT min ('-99999999999999999999999999999999')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('-99999999999999999999999999999999');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
if [ "$INVALID" -eq 1 ]; then
    echo "[PASS] (Validation gate caught below-range value without underflow crash)"
else
    echo "[FAIL] (Expected 1 invalid, got $INVALID)" >&2; exit 1
fi
# Clean up Case 8 row
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "DELETE FROM seller_verifies WHERE seller_id = '-99999999999999999999999999999999';" >/dev/null

# -----------------------------------------------------------------------------
# Case 9: Valid numeric seller ID with no corresponding user (orphan)
# -----------------------------------------------------------------------------
echo -n "Test Case 9: Valid numeric seller ID with no corresponding user ('99999')... "
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO seller_verifies (seller_id) VALUES ('99999');" >/dev/null
INVALID=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$VALIDATE_SELLER_IDS_QUERY")
ORPHANS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "$NORMALIZED_ORPHAN_QUERY")
if [ "$INVALID" -eq 0 ] && [ "$ORPHANS" -eq 1 ]; then
    echo "[PASS] (Validation gate passed 0 invalid; Orphan check correctly caught 1 orphan)"
else
    echo "[FAIL] (Expected 0 invalid, 1 orphan; got Invalid: $INVALID, Orphans: $ORPHANS)" >&2; exit 1
fi
# Clean up Case 9 row
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "DELETE FROM seller_verifies WHERE seller_id = '99999';" >/dev/null

# -----------------------------------------------------------------------------
# Case 10: Provider-category orphan check using actual column type
# -----------------------------------------------------------------------------
echo -n "Test Case 10: Provider-category orphan check using actual column type... "
PC_TYPE=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "
SELECT data_type FROM information_schema.columns 
WHERE table_name = 'provider_categories' AND column_name = 'provider_id';
")
if [ "$PC_TYPE" != "bigint" ]; then
    echo "[FAIL] (Expected bigint, got $PC_TYPE)" >&2; exit 1
fi

# Baseline check: 0 orphans
BASELINE_PC_ORPHANS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "
SELECT count(*) FROM provider_categories pc LEFT JOIN users u ON u.id = pc.provider_id WHERE u.id IS NULL;
")
if [ "$BASELINE_PC_ORPHANS" -ne 0 ]; then
    echo "[FAIL] (Expected 0 baseline pc orphans, got $BASELINE_PC_ORPHANS)" >&2; exit 1
fi

# Insert orphaned provider_category (user 88888 does not exist)
psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -c "INSERT INTO provider_categories (provider_id, category_id) VALUES (88888, 20);" >/dev/null
DETECTED_PC_ORPHANS=$(psql -U "$PG_USER" -h "$PG_HOST" -p "$PG_PORT" -d "$TEMP_DB" -t -A -c "
SELECT count(*) FROM provider_categories pc LEFT JOIN users u ON u.id = pc.provider_id WHERE u.id IS NULL;
")
if [ "$DETECTED_PC_ORPHANS" -eq 1 ]; then
    echo "[PASS] (Actual type is bigint; direct join detected 1 orphan correctly)"
else
    echo "[FAIL] (Expected 1 detected pc orphan, got $DETECTED_PC_ORPHANS)" >&2; exit 1
fi

echo "=== ALL 10 STAGE 2 PRE-MIGRATION TEST CASES PASSED ==="
exit 0

