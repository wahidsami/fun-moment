#!/usr/bin/env bash
# =============================================================================
# FUN MOMENT — PROJECT 2 MEDIA PERSISTENCE & CATEGORY RECOVERY SCRIPT
# Server: 141.140.0.90 (Coolify Host)
# Target Environment: Project 2 (fun-moment-backend-staging / funmoments_staging)
# Production Guard: Project 1 (fun-moment-backend / funmoments) is STRICTLY READ-ONLY
# =============================================================================
set -euo pipefail

MODE="${1:-}"

if [[ -z "$MODE" || ("$MODE" != "--preflight" && "$MODE" != "--backup-p1-media" && "$MODE" != "--restore-staging-media" && "$MODE" != "--verify" && "$MODE" != "--all") ]]; then
    echo "Usage: $0 {--preflight | --backup-p1-media | --restore-staging-media | --verify | --all}"
    echo ""
    echo "Modes:"
    echo "  --preflight              Read-only inspection of containers, mounts, and current state."
    echo "  --backup-p1-media        Extract and archive complete Project 1 media uploads (P1 read-only)."
    echo "  --restore-staging-media  Sync media files into P2 persistent volume and associate DB records."
    echo "  --verify                 Execute HTTP probes, hash checks, and API contract assertions."
    echo "  --all                    Execute backup, restore, and verify sequentially."
    exit 1
fi

echo "========================================================================="
echo "FUN MOMENT — PROJECT 2 MEDIA PERSISTENCE & CATEGORY RECOVERY"
echo "========================================================================="
echo "Timestamp : $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "Host      : $(hostname) ($(uname -s -r -m))"
echo "Mode      : ${MODE}"
echo "========================================================================="

# -----------------------------------------------------------------------------
# 1. RIGOROUS DUAL BACKEND CONTAINER DISCOVERY
# -----------------------------------------------------------------------------
echo ""
echo "[1/6] Scanning running containers for Coolify Traefik domains..."

P1_DOMAIN="bkfunmoments.unifinitylab.com"
P2_DOMAIN="staging-api.funmoments.unifinitylab.com"

P1_BACKENDS=()
P2_BACKENDS=()

for CID in $(docker ps -q); do
    LABELS=$(docker inspect "$CID" --format '{{range $k, $v := .Config.Labels}}{{$k}}={{$v}}{{"\n"}}{{end}}')
    
    if echo "$LABELS" | grep -Fq "$P1_DOMAIN"; then
        P1_BACKENDS+=("$CID")
    fi
    
    if echo "$LABELS" | grep -Fq "$P2_DOMAIN"; then
        P2_BACKENDS+=("$CID")
    fi
done

if [ "${#P1_BACKENDS[@]}" -ne 1 ]; then
    echo "FATAL: Expected exactly 1 Project 1 backend container (${P1_DOMAIN}), found ${#P1_BACKENDS[@]}!" >&2
    exit 101
fi
P1_BACKEND_ID="${P1_BACKENDS[0]}"
P1_BACKEND_NAME=$(docker inspect "$P1_BACKEND_ID" --format '{{.Name}}' | sed 's|^/||')
echo "  [OK] Project 1 Backend (Production - PROTECTED): ${P1_BACKEND_NAME} (${P1_BACKEND_ID})"

if [ "${#P2_BACKENDS[@]}" -ne 1 ]; then
    echo "FATAL: Expected exactly 1 Project 2 backend container (${P2_DOMAIN}), found ${#P2_BACKENDS[@]}!" >&2
    exit 102
fi
P2_BACKEND_ID="${P2_BACKENDS[0]}"
P2_BACKEND_NAME=$(docker inspect "$P2_BACKEND_ID" --format '{{.Name}}' | sed 's|^/||')
echo "  [OK] Project 2 Backend (Staging - TARGET):     ${P2_BACKEND_NAME} (${P2_BACKEND_ID})"

# Container Disparity Assertion
if [ "$P1_BACKEND_ID" = "$P2_BACKEND_ID" ]; then
    echo "FATAL CRITICAL SAFETY HAZARD: Project 1 and Project 2 resolve to the exact same container ID!" >&2
    exit 103
fi

# -----------------------------------------------------------------------------
# 2. RUNTIME DATABASE CONFIGURATION & ENVIRONMENT ASSERTIONS
# -----------------------------------------------------------------------------
echo ""
echo "[2/6] Extracting runtime database targets and asserting environment isolation..."

P1_CONFIG=$(docker exec "$P1_BACKEND_ID" php artisan tinker --execute="
  \$c = config('database.connections.' . config('database.default'));
  echo 'DRIVER=' . (\$c['driver'] ?? '') . PHP_EOL;
  echo 'HOST=' . (\$c['host'] ?? '') . PHP_EOL;
  echo 'PORT=' . (\$c['port'] ?? 5432) . PHP_EOL;
  echo 'DB=' . (\$c['database'] ?? '') . PHP_EOL;
  echo 'USER=' . (\$c['username'] ?? '') . PHP_EOL;
")

P1_DB_NAME=$(echo "$P1_CONFIG" | grep '^DB=' | cut -d'=' -f2 | tr -d '\r\n ')
P1_DB_USER=$(echo "$P1_CONFIG" | grep '^USER=' | cut -d'=' -f2 | tr -d '\r\n ')
P1_DB_HOST=$(echo "$P1_CONFIG" | grep '^HOST=' | cut -d'=' -f2 | tr -d '\r\n ')

P2_CONFIG=$(docker exec "$P2_BACKEND_ID" php artisan tinker --execute="
  \$c = config('database.connections.' . config('database.default'));
  echo 'DRIVER=' . (\$c['driver'] ?? '') . PHP_EOL;
  echo 'HOST=' . (\$c['host'] ?? '') . PHP_EOL;
  echo 'PORT=' . (\$c['port'] ?? 5432) . PHP_EOL;
  echo 'DB=' . (\$c['database'] ?? '') . PHP_EOL;
  echo 'USER=' . (\$c['username'] ?? '') . PHP_EOL;
")

P2_DB_NAME=$(echo "$P2_CONFIG" | grep '^DB=' | cut -d'=' -f2 | tr -d '\r\n ')
P2_DB_USER=$(echo "$P2_CONFIG" | grep '^USER=' | cut -d'=' -f2 | tr -d '\r\n ')
P2_DB_HOST=$(echo "$P2_CONFIG" | grep '^HOST=' | cut -d'=' -f2 | tr -d '\r\n ')

echo "  Project 1 Runtime Target: DB=${P1_DB_NAME}, Host=${P1_DB_HOST}, User=${P1_DB_USER}"
echo "  Project 2 Runtime Target: DB=${P2_DB_NAME}, Host=${P2_DB_HOST}, User=${P2_DB_USER}"

# Strict Invariant Assertions
if [ "$P1_DB_NAME" != "funmoments" ]; then
    echo "FATAL: Project 1 database name must be strictly 'funmoments', found '${P1_DB_NAME}'!" >&2
    exit 104
fi

if [ "$P2_DB_NAME" != "funmoments_staging" ]; then
    echo "FATAL: Project 2 database name must be strictly 'funmoments_staging', found '${P2_DB_NAME}'!" >&2
    exit 105
fi

if [ "$P1_DB_NAME" = "$P2_DB_NAME" ]; then
    echo "FATAL: Source and Target database names are identical (${P1_DB_NAME})!" >&2
    exit 106
fi

# -----------------------------------------------------------------------------
# 3. STORAGE MOUNT AUDIT & PERSISTENCE VERIFICATION
# -----------------------------------------------------------------------------
echo ""
echo "[3/6] Auditing container persistent storage mounts..."

# Invariant check: music-private nested persistent volume must be active
P2_MUSIC_PRIVATE_MOUNT=$(docker inspect "$P2_BACKEND_ID" --format '{{range .Mounts}}{{if eq .Destination "/app/storage/app/music_private"}}{{.Name}} (Source: {{.Source}}){{end}}{{end}}')
P2_UPLOADS_MOUNT=$(docker inspect "$P2_BACKEND_ID" --format '{{range .Mounts}}{{if eq .Destination "/app/public/assets/uploads"}}{{.Name}} (Source: {{.Source}}){{end}}{{end}}')

echo "  Project 2 music-private mount (/app/storage/app/music_private) : ${P2_MUSIC_PRIVATE_MOUNT:-NOT MOUNTED}"
echo "  Project 2 uploads mount       (/app/public/assets/uploads)      : ${P2_UPLOADS_MOUNT:-NOT MOUNTED}"

if [[ -z "$P2_MUSIC_PRIVATE_MOUNT" ]]; then
    echo "FATAL: music_private persistent volume mount (/app/storage/app/music_private) was not found on Project 2!" >&2
    echo "Existing music private storage must be preserved and cannot be missing or detached." >&2
    exit 107
fi
echo "  [OK] music_private persistent volume verified: ${P2_MUSIC_PRIVATE_MOUNT}"

# -----------------------------------------------------------------------------
# 4. PREFLIGHT EXECUTION (READ-ONLY)
# -----------------------------------------------------------------------------
if [[ "$MODE" == "--preflight" ]]; then
    echo ""
    echo "[PREFLIGHT] Inspecting media availability on Project 1 (Source)..."
    
    EXPECTED_FILES=(
        "dj-world1791019173.png"
        "food-hospitality-mobile1791026107.png"
        "sound-lighting-mobile1791026141.png"
        "event-setup-equipment-mobile1791026170.png"
        "decor-event-styling-mobile1791026203.png"
        "photography-video-mobile1791026232.png"
        "entertainment-activities-mobile1791026260.png"
        "vip-bodyguard-security-duo1792672760.png"
    )

    P1_UPLOADS_PATH="/app/public/assets/uploads/media-uploader"
    P1_MISSING=0

    for FILE in "${EXPECTED_FILES[@]}"; do
        if docker exec "$P1_BACKEND_ID" test -f "${P1_UPLOADS_PATH}/${FILE}"; then
            SIZE=$(docker exec "$P1_BACKEND_ID" stat -c%s "${P1_UPLOADS_PATH}/${FILE}" | tr -d '\r\n')
            echo "  [OK] P1 File Exists: ${FILE} (${SIZE} bytes)"
        else
            echo "  [FAIL] Missing on P1: ${FILE}" >&2
            P1_MISSING=$((P1_MISSING + 1))
        fi
    done

    if [ "$P1_MISSING" -gt 0 ]; then
        echo "FATAL: ${P1_MISSING} expected media file(s) are missing on Project 1!" >&2
        exit 110
    fi

    echo ""
    echo "[PREFLIGHT] Checking Project 2 categories and media status..."
    docker exec "$P2_BACKEND_ID" php artisan tinker --execute="
        \$cats = \App\Category::select('id', 'slug', 'name', 'mobile_icon', 'image')->orderBy('sort_order')->get();
        foreach (\$cats as \$c) {
            echo 'Cat #' . \$c->id . ' [' . \$c->slug . '] mobile_icon=' . (\$c->mobile_icon ?? 'NULL') . ' image=' . (\$c->image ?? 'NULL') . PHP_EOL;
        }
    "

    if [[ -z "$P2_UPLOADS_MOUNT" ]]; then
        echo ""
        echo "========================================================================="
        echo "PREFLIGHT NOTICE: /app/public/assets/uploads is NOT YET MOUNTED on Project 2."
        echo "Before running --restore-staging-media, add Persistent Storage in Coolify:"
        echo "  Type        : Volume Mount"
        echo "  Name        : funmoments-uploads-staging"
        echo "  Destination : /app/public/assets/uploads"
        echo "========================================================================="
    else
        echo ""
        echo "[OK] Persistent mount verified for Project 2 uploads: ${P2_UPLOADS_MOUNT}"
    fi

    echo ""
    echo "========================================================================="
    echo "PREFLIGHT AUDIT COMPLETE — ZERO MUTATIONS PERFORMED"
    echo "========================================================================="
    exit 0
fi

# -----------------------------------------------------------------------------
# 5. BACKUP PROJECT 1 MEDIA (READ-ONLY ON P1)
# -----------------------------------------------------------------------------
BACKUP_BASE_DIR="/var/backups/funmoments/media"

if [[ "$MODE" == "--backup-p1-media" || "$MODE" == "--all" ]]; then
    echo ""
    echo "[4/6] Creating non-destructive backup of Project 1 uploads tree..."
    mkdir -p "$BACKUP_BASE_DIR"

    TIMESTAMP=$(date -u +"%Y%m%d_%H%M%S")
    BACKUP_DIR="${BACKUP_BASE_DIR}/p1_uploads_${TIMESTAMP}"
    mkdir -p "$BACKUP_DIR"

    echo "  Extracting /app/public/assets/uploads from Project 1 container (${P1_BACKEND_ID})..."
    docker cp "${P1_BACKEND_ID}:/app/public/assets/uploads" "${BACKUP_DIR}/"

    echo "  Creating compressed tarball..."
    TARBALL="${BACKUP_BASE_DIR}/funmoments_p1_uploads_${TIMESTAMP}.tar.gz"
    tar -czf "$TARBALL" -C "$BACKUP_DIR" uploads

    echo "  Generating SHA-256 checksums..."
    (cd "$BACKUP_DIR/uploads/media-uploader" && sha256sum * > "${BACKUP_DIR}/SHA256SUMS.txt") 2>/dev/null || true

    BACKUP_SIZE=$(du -sh "$TARBALL" | awk '{print $1}')
    echo "  [OK] Project 1 uploads backup created: ${TARBALL} (${BACKUP_SIZE})"
    echo "  [OK] Extracted tree location: ${BACKUP_DIR}/uploads"

    if [[ "$MODE" == "--backup-p1-media" ]]; then
        echo ""
        echo "Project 1 media backup completed successfully. Project 1 remained strictly read-only."
        exit 0
    fi
fi

# -----------------------------------------------------------------------------
# 6. RESTORE MEDIA TO PROJECT 2 & ASSOCIATE DATABASE
# -----------------------------------------------------------------------------
if [[ "$MODE" == "--restore-staging-media" || "$MODE" == "--all" ]]; then
    echo ""
    echo "[5/6] Restoring media into Project 2 persistent uploads volume..."

    # Ensure persistent mount is present on /app/public/assets/uploads
    if [[ -z "$P2_UPLOADS_MOUNT" ]]; then
        echo "FATAL: Refusing to restore media without an active persistent mount on Project 2!" >&2
        echo "Configure Volume Mount '/app/public/assets/uploads' in Coolify before running restore." >&2
        exit 120
    fi

    # Strict Volume Invariant: verify that /app/storage/app/music_private (music-private-staging) remains mounted and untouched
    P2_MUSIC_PRIVATE_CHECK=$(docker inspect "$P2_BACKEND_ID" --format '{{range .Mounts}}{{if eq .Destination "/app/storage/app/music_private"}}{{.Name}}{{end}}{{end}}')
    if [[ -z "$P2_MUSIC_PRIVATE_CHECK" ]]; then
        echo "FATAL CRITICAL SAFETY HAZARD: music_private volume mount (/app/storage/app/music_private) is missing or detached on Project 2!" >&2
        echo "Refusing to proceed: Existing music-private storage must remain mounted and untouched." >&2
        exit 122
    fi
    echo "  [OK] music_private persistent volume mount verified: ${P2_MUSIC_PRIVATE_CHECK}"

    # Step A: Restore Project 2 pre-mount baseline files if pre-mount archive exists
    P2_PRE_TAR=$(ls -t "${BACKUP_BASE_DIR}"/funmoments_p2_uploads_pre_mount_*.tar.gz 2>/dev/null | head -n 1 || true)
    if [[ -n "$P2_PRE_TAR" && -f "$P2_PRE_TAR" ]]; then
        echo "  Found Project 2 pre-mount archive: ${P2_PRE_TAR}"
        P2_RESTORE_TMP="${BACKUP_BASE_DIR}/p2_pre_mount_extracted"
        mkdir -p "$P2_RESTORE_TMP"
        tar -xzf "$P2_PRE_TAR" -C "$P2_RESTORE_TMP"
        if [ -d "$P2_RESTORE_TMP/uploads" ]; then
            echo "  Preserving Project 2 baseline uploads into persistent volume..."
            docker cp "${P2_RESTORE_TMP}/uploads/." "${P2_BACKEND_ID}:/app/public/assets/uploads/"
        fi
        rm -rf "$P2_RESTORE_TMP"
    fi

    # Step B: Locate or extract Project 1 uploads backup
    P1_SOURCE_MEDIA_DIR=""
    LATEST_BACKUP_DIR=$(ls -td "${BACKUP_BASE_DIR}"/p1_uploads_* 2>/dev/null | head -n 1 || true)
    if [[ -n "$LATEST_BACKUP_DIR" && -d "$LATEST_BACKUP_DIR/uploads/media-uploader" ]]; then
        P1_SOURCE_MEDIA_DIR="${LATEST_BACKUP_DIR}/uploads/media-uploader"
    else
        LATEST_P1_TAR=$(ls -t "${BACKUP_BASE_DIR}"/funmoments_p1_uploads_*.tar.gz 2>/dev/null | head -n 1 || true)
        if [[ -n "$LATEST_P1_TAR" && -f "$LATEST_P1_TAR" ]]; then
            echo "  Extracting Project 1 archive ${LATEST_P1_TAR}..."
            P1_EXTRACT_TMP="${BACKUP_BASE_DIR}/p1_uploads_extracted"
            mkdir -p "$P1_EXTRACT_TMP"
            tar -xzf "$LATEST_P1_TAR" -C "$P1_EXTRACT_TMP"
            P1_SOURCE_MEDIA_DIR="${P1_EXTRACT_TMP}/uploads/media-uploader"
        fi
    fi

    if [[ -z "$P1_SOURCE_MEDIA_DIR" || ! -d "$P1_SOURCE_MEDIA_DIR" ]]; then
        echo "FATAL: No valid Project 1 uploads backup directory or tarball found in ${BACKUP_BASE_DIR}!" >&2
        exit 121
    fi
    echo "  Using Project 1 media source: ${P1_SOURCE_MEDIA_DIR}"

    # Step C: Selective copy of ONLY the 8 approved category files and their scaled variants
    APPROVED_CATEGORY_FILES=(
        # Category 1: Music & DJ
        "dj-world1791019173.png"
        "grid-dj-world1791019173.png"
        "large-dj-world1791019173.png"
        "semi-large-dj-world1791019173.png"
        "thumb-dj-world1791019173.png"

        # Category 2: Food & Hospitality
        "food-hospitality-mobile1791026107.png"
        "grid-food-hospitality-mobile1791026107.png"
        "large-food-hospitality-mobile1791026107.png"
        "semi-large-food-hospitality-mobile1791026107.png"
        "thumb-food-hospitality-mobile1791026107.png"

        # Category 3: Sound & Lighting
        "sound-lighting-mobile1791026141.png"
        "grid-sound-lighting-mobile1791026141.png"
        "large-sound-lighting-mobile1791026141.png"
        "semi-large-sound-lighting-mobile1791026141.png"
        "thumb-sound-lighting-mobile1791026141.png"

        # Category 4: Event Setup & Equipment
        "event-setup-equipment-mobile1791026170.png"
        "grid-event-setup-equipment-mobile1791026170.png"
        "large-event-setup-equipment-mobile1791026170.png"
        "semi-large-event-setup-equipment-mobile1791026170.png"
        "thumb-event-setup-equipment-mobile1791026170.png"

        # Category 5: Decor & Event Styling
        "decor-event-styling-mobile1791026203.png"
        "grid-decor-event-styling-mobile1791026203.png"
        "large-decor-event-styling-mobile1791026203.png"
        "semi-large-decor-event-styling-mobile1791026203.png"
        "thumb-decor-event-styling-mobile1791026203.png"

        # Category 6: Photography & Video
        "photography-video-mobile1791026232.png"
        "grid-photography-video-mobile1791026232.png"
        "large-photography-video-mobile1791026232.png"
        "semi-large-photography-video-mobile1791026232.png"
        "thumb-photography-video-mobile1791026232.png"

        # Category 7: Entertainment Activities
        "entertainment-activities-mobile1791026260.png"
        "grid-entertainment-activities-mobile1791026260.png"
        "large-entertainment-activities-mobile1791026260.png"
        "semi-large-entertainment-activities-mobile1791026260.png"
        "thumb-entertainment-activities-mobile1791026260.png"

        # Category 8: Body Guards
        "vip-bodyguard-security-duo1792672760.png"
        "grid-vip-bodyguard-security-duo1792672760.png"
        "large-vip-bodyguard-security-duo1792672760.png"
        "semi-large-vip-bodyguard-security-duo1792672760.png"
        "thumb-vip-bodyguard-security-duo1792672760.png"
    )

    CATEGORY_RESTORE_TMP="${BACKUP_BASE_DIR}/category_media_restore_staging"
    mkdir -p "${CATEGORY_RESTORE_TMP}"

    COPIED_COUNT=0
    for CFILE in "${APPROVED_CATEGORY_FILES[@]}"; do
        if [ -f "${P1_SOURCE_MEDIA_DIR}/${CFILE}" ]; then
            cp "${P1_SOURCE_MEDIA_DIR}/${CFILE}" "${CATEGORY_RESTORE_TMP}/"
            COPIED_COUNT=$((COPIED_COUNT + 1))
        fi
    done

    echo "  Staged ${COPIED_COUNT} approved category media files (8 base images + variants) for restore."

    # Ensure target directory structure exists in P2 container
    docker exec "$P2_BACKEND_ID" mkdir -p /app/public/assets/uploads/media-uploader

    echo "  Copying scoped category media into Project 2 (/app/public/assets/uploads/media-uploader/)..."
    docker cp "${CATEGORY_RESTORE_TMP}/." "${P2_BACKEND_ID}:/app/public/assets/uploads/media-uploader/"

    # Clean up temporary staging dir
    rm -rf "${CATEGORY_RESTORE_TMP}"

    # Clean up temporary extract dir if used
    if [ -d "${BACKUP_BASE_DIR}/p1_uploads_extracted" ]; then
        rm -rf "${BACKUP_BASE_DIR}/p1_uploads_extracted"
    fi

    # Set standard, safe file and folder permissions (0755 dirs, 0644 files) without broad wildcards
    echo "  Applying standard file permissions..."
    docker exec "$P2_BACKEND_ID" find /app/public/assets/uploads -type d -exec chmod 0755 {} +
    docker exec "$P2_BACKEND_ID" find /app/public/assets/uploads -type f -exec chmod 0644 {} +

    echo "  Executing idempotent database restoration command inside Project 2..."
    docker exec "$P2_BACKEND_ID" php artisan categories:restore-media

    echo "  [OK] Media files restored and database associations established in Project 2."
fi

# -----------------------------------------------------------------------------
# 7. POST-RESTORATION VERIFICATION & HTTP CHECKS
# -----------------------------------------------------------------------------
if [[ "$MODE" == "--verify" || "$MODE" == "--all" ]]; then
    echo ""
    echo "[6/6] Executing comprehensive post-restoration verification suite..."

    EXPECTED_HASHES=(
        "dj-world1791019173.png:723e6a0bc8b994fd20c68ef2cd6daa37c8340ec3401bb501021a694bcd216daa"
        "food-hospitality-mobile1791026107.png:054767b244c92dc401e694e4d08f35eecb27ce9acab2f26206c404ebcd215a23"
        "sound-lighting-mobile1791026141.png:b4cb2dff485e2eafb7b94dac0bbc81d84ea48d51cb2cc1c2dd1eaf3cb8350f6e"
        "event-setup-equipment-mobile1791026170.png:2a456ec1ee6246706ec8f09da07f359349b01b2afa4d45cc1d67457ccc5750cb"
        "decor-event-styling-mobile1791026203.png:9de1796ac03fe311e879c921f539e1dfce96c44c72a8bea7e7d9d6566ae67566"
        "photography-video-mobile1791026232.png:fc2bb9232ab3193537b51278f92a3061f0db140cfb657f17a4136c26f354f91b"
        "entertainment-activities-mobile1791026260.png:7ac17b0fd6202f42d65c87e93618c2afcc703816792c6630de62fa585325d94d"
        "vip-bodyguard-security-duo1792672760.png:33779cc16e46533430a79e349b622b81d8213e55805f357dfc11673dd5eb7cae"
    )

    echo ""
    echo "--- 1. Cryptographic SHA-256 Integrity Checks on Project 2 Disk ---"
    P2_UPLOADS_PATH="/app/public/assets/uploads/media-uploader"
    HASH_ERRORS=0

    for ENTRY in "${EXPECTED_HASHES[@]}"; do
        FILE="${ENTRY%%:*}"
        EXP_HASH="${ENTRY##*:}"

        ACT_HASH=$(docker exec "$P2_BACKEND_ID" sha256sum "${P2_UPLOADS_PATH}/${FILE}" 2>/dev/null | awk '{print $1}' || echo "MISSING")
        if [ "$ACT_HASH" = "$EXP_HASH" ]; then
            echo "  [PASS] Hash matches: ${FILE}"
        else
            echo "  [FAIL] Hash mismatch: ${FILE} (expected ${EXP_HASH}, got ${ACT_HASH})" >&2
            HASH_ERRORS=$((HASH_ERRORS + 1))
        fi
    done

    if [ "$HASH_ERRORS" -gt 0 ]; then
        echo "FATAL: ${HASH_ERRORS} file(s) failed integrity check!" >&2
        exit 130
    fi

    echo ""
    echo "--- 2. Public Static Asset HTTP Probes ---"
    HTTP_ERRORS=0

    for ENTRY in "${EXPECTED_HASHES[@]}"; do
        FILE="${ENTRY%%:*}"
        URL="https://${P2_DOMAIN}/assets/uploads/media-uploader/${FILE}"

        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$URL" || echo "000")
        CONTENT_TYPE=$(curl -s -I "$URL" | grep -i '^Content-Type:' | tr -d '\r\n' | awk '{print $2}' || echo "unknown")

        if [[ "$HTTP_STATUS" == "200" && "$CONTENT_TYPE" =~ image/ ]]; then
            echo "  [PASS] ${URL} -> ${HTTP_STATUS} (${CONTENT_TYPE})"
        else
            echo "  [FAIL] ${URL} -> ${HTTP_STATUS} (${CONTENT_TYPE}) - Expected 200 image/*" >&2
            HTTP_ERRORS=$((HTTP_ERRORS + 1))
        fi
    done

    if [ "$HTTP_ERRORS" -gt 0 ]; then
        echo "FATAL: ${HTTP_ERRORS} static media probe(s) failed!" >&2
        exit 131
    fi

    echo ""
    echo "--- 3. Category API Contract Verification ---"
    CAT_JSON=$(curl -s "https://${P2_DOMAIN}/api/v1/category")
    NULL_COUNT=$(echo "$CAT_JSON" | grep -o '"mobile_icon":null' | wc -l || true)
    VALID_URLS=$(echo "$CAT_JSON" | grep -o '"mobile_icon":"https:' | wc -l || true)

    echo "  Categories returned with valid mobile_icon URL: ${VALID_URLS}/8"
    if [ "$NULL_COUNT" -gt 0 ] || [ "$VALID_URLS" -ne 8 ]; then
        echo "  [FAIL] API contract failed: found ${NULL_COUNT} null mobile_icon values (expected 0 nulls, 8 valid URLs)!" >&2
        echo "  Payload: ${CAT_JSON}" >&2
        exit 132
    fi
    echo "  [PASS] API /api/v1/category successfully returned all 8 categories with valid mobile_icon URLs."

    echo ""
    echo "========================================================================="
    echo "ALL VERIFICATION CHECKS PASSED (SHA-256 MATCH, HTTP 200 IMAGE, API 8/8 OK)"
    echo "========================================================================="
fi

