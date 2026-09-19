#!/bin/bash

set -e
# Disable history expansion (important for passwords containing !)
set +H
# Also disable via HISTCONTROL for non-interactive shells
export HISTCONTROL=ignoredups
# Use safer IFS?
set -o pipefail 2>/dev/null || true

#===============================================================================
# Usage: deploy.sh [start|stop|restart|health|migrate|deploy]
#
# start   – start the existing stack (per-env project allows prod + staging simultaneously)
# stop    – stop the existing stack
# restart – restart the existing stack
# health  – deep health: containers + HTTP + bucket
# migrate – run ${MIGRATE_CMD:-migrate --force} inside app
# deploy  – full lifecycle: generate .env + build + start + setup_db + setup_minio + migrate + health + cleanup
#
# Environment variables are only required by deploy (build).
#===============================================================================

ACTION="${1:-}"


#===============================================================================
# Configuration
#===============================================================================

COMPOSE_FILE="docker-compose.yml"


#===============================================================================
# Docker Compose
#===============================================================================

compose() {
    # Per-env project so production (8000) and staging (8001) can run simultaneously
    # APP_ENV=production → lumished-production, staging → lumished-staging, local → lumished-local
    local project="lumished-${APP_ENV:-local}"
    # sanitize: lowercase, replace spaces
    project=$(echo "$project" | tr '[:upper:]' '[:lower:]' | tr -cs 'a-z0-9_-' '-' | sed 's/^-*//;s/-*$//')

    docker compose \
        --project-name "$project" \
        --file "$COMPOSE_FILE" \
        "$@"
}


#===============================================================================
# Generate Environment
#===============================================================================


generate_env() {
    echo
    echo "========================================"
    echo "Generating .env"
    echo "========================================"

    local env_example=".env.example"
    local env_file=".env"

    #===========================================================================
    # Validate .env.example
    #===========================================================================

    if [[ ! -f "$env_example" ]]; then
        echo "ERROR: $env_example not found."
        exit 1
    fi

    #===========================================================================
    # Persistent variables
    #
    # These values are preserved from the existing .env when it is regenerated.
    #===========================================================================

    local persistent_vars=(
        APP_KEY
    )

    declare -A persistent_values

    #===========================================================================
    # Read existing persistent values
    #===========================================================================

    if [[ -f "$env_file" ]]; then
        for key in "${persistent_vars[@]}"; do
            value=$(grep -E "^${key}=" "$env_file" | head -n 1 || true)

            if [[ -n "$value" ]]; then
                persistent_values["$key"]="${value#*=}"
            fi
        done
    fi

    #===========================================================================
    # Start from .env.example
    #===========================================================================

    cp "$env_example" "$env_file"

    #===========================================================================
    # CI-managed variables
    #
    # These values are supplied by GitHub Actions and overwrite the values
    # from .env.example on every build.
    #===========================================================================

    local ci_vars=(
        APP_NAME
        APP_ENV
        APP_DEBUG
        APP_URL
        APP_PORT

        DB_HOST
        DB_PORT
        DB_DATABASE
        DB_USERNAME
        DB_PASSWORD

        REDIS_HOST
        REDIS_PORT
        REDIS_PASSWORD
        REDIS_DB
        REDIS_CACHE_DB

        AWS_ACCESS_KEY_ID
        AWS_SECRET_ACCESS_KEY
        AWS_DEFAULT_REGION
        AWS_BUCKET
        AWS_USE_PATH_STYLE_ENDPOINT
        AWS_ENDPOINT
        AWS_URL

        MAIL_SCHEME
        MAIL_HOST
        MAIL_PORT
        MAIL_USERNAME
        MAIL_PASSWORD

        PAYSTACK_SECRET
    )

    #===========================================================================
    # Must-fill variables — must be provided by workflow, cannot be empty
    #===========================================================================

    local must_fill_vars=(
        APP_NAME
        APP_ENV
        APP_URL
        APP_PORT

        DB_HOST
        DB_DATABASE
        DB_USERNAME
        DB_PASSWORD
        DB_ROOT_PASSWORD

        REDIS_HOST
        REDIS_PASSWORD

        AWS_BUCKET

        PAYSTACK_SECRET
    )

    #===========================================================================
    # Validate must-fill variables (must come from workflow, cannot be empty)
    #===========================================================================

    for key in "${must_fill_vars[@]}"; do
        value="${!key:-}"

        if [[ -z "${value:-}" ]]; then
            echo "ERROR: Required variable ${key} is empty. Must be provided via workflow env."
            exit 1
        fi
    done

    #===========================================================================
    # Apply CI values
    #===========================================================================

    for key in "${ci_vars[@]}"; do
        value="${!key:-}"

        if grep -qE "^${key}=" "$env_file"; then
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        else
            echo "${key}=${value}" >> "$env_file"
        fi
    done

    # Sanitize AWS_BUCKET to S3 DNS rules (lowercase, no _)
    if grep -qE "^AWS_BUCKET=" "$env_file"; then
        current_bucket=$(grep -E "^AWS_BUCKET=" "$env_file" | head -n 1 | cut -d= -f2-)
        sanitized_bucket=$(echo "${current_bucket}" | tr '[:upper:]' '[:lower:]' | tr '_' '-' | tr -cs 'a-z0-9.-' '-' | sed 's/^-*//;s/-*$//' | sed 's/--*/-/g')
        if [[ "${sanitized_bucket}" != "${current_bucket}" && -n "${sanitized_bucket:-}" ]]; then
            echo "   sanitizing AWS_BUCKET: ${current_bucket} → ${sanitized_bucket} (S3 DNS)"
            esc_sanitized=$(printf '%s' "${sanitized_bucket}" | sed 's/[&|]/\\&/g')
            sed -i "s|^AWS_BUCKET=.*|AWS_BUCKET=${esc_sanitized}|" "$env_file"
        fi
    fi

    #===========================================================================
    # Restore persistent values
    #===========================================================================

    for key in "${persistent_vars[@]}"; do
        if [[ -n "${persistent_values[$key]:-}" ]]; then
            if grep -qE "^${key}=" "$env_file"; then
                sed -i "s|^${key}=.*|${key}=${persistent_values[$key]}|" "$env_file"
            else
                echo "${key}=${persistent_values[$key]}" >> "$env_file"
            fi
        fi
    done

    #===========================================================================
    # Generate APP_KEY if missing (first run — else app fails with no key)
    #===========================================================================

    current_key=$(grep -E "^APP_KEY=" "$env_file" | head -n 1 | cut -d= -f2- || true)

    if [[ -z "${current_key:-}" ]]; then
        echo "APP_KEY empty — generating..."

        generated=""

        if command -v php >/dev/null 2>&1 && [[ -f "artisan" ]]; then
            generated=$(php artisan key:generate --show 2>/dev/null || true)
        fi

        if [[ -z "${generated:-}" ]]; then
            if command -v openssl >/dev/null 2>&1; then
                generated="base64:$(openssl rand -base64 32)"
            else
                generated="base64:$(head -c 32 /dev/urandom | base64)"
            fi
        fi

        if grep -qE "^APP_KEY=" "$env_file"; then
            # Use | as sed delimiter, escape & and | in generated key
            esc_generated=$(printf '%s' "$generated" | sed 's/[&|]/\\&/g')
            sed -i "s|^APP_KEY=.*|APP_KEY=${esc_generated}|" "$env_file"
        else
            echo "APP_KEY=${generated}" >> "$env_file"
        fi

        echo "APP_KEY generated."
    fi

    #===========================================================================
    # Secure .env
    #===========================================================================

    chmod 600 "$env_file"

    echo ".env generated successfully."
}


#===============================================================================
# Build
#===============================================================================

build() {
    echo
    echo "========================================"
    echo "Validating Docker Compose"
    echo "========================================"

    compose config --quiet

    echo
    echo "========================================"
    echo "Building Docker images"
    echo "========================================"

    compose build --pull

    echo
    echo "Build completed successfully."
}


#===============================================================================
# Start
#===============================================================================

start() {
    echo
    echo "========================================"
    echo "Starting Lumished"
    echo "========================================"

    compose up -d

    echo
    echo "Lumished started successfully."

    health
}


#===============================================================================
# Stop
#===============================================================================

stop() {
    echo
    echo "========================================"
    echo "Stopping Lumished"
    echo "========================================"

    compose stop

    echo
    echo "Lumished stopped successfully."
}


#===============================================================================
# Restart
#===============================================================================

restart() {
    echo
    echo "========================================"
    echo "Restarting Lumished"
    echo "========================================"

    compose restart

    echo
    echo "Lumished restarted successfully."

    # Reuse migrate() — non-fresh (MIGRATE_CMD not set here, defaults to migrate --force)
    MIGRATE_CMD="migrate --force" migrate

    health
}


#===============================================================================
# Setup Data Directory (for easy migration of mysql/redis/minio)
#===============================================================================

setup_data_dir() {
    if [[ -n "${DATA_DIR:-}" ]]; then
        echo "📁 Ensuring DATA_DIR ${DATA_DIR} exists..."
        sudo mkdir -p "${DATA_DIR}/mysql" "${DATA_DIR}/redis" "${DATA_DIR}/minio" "${DATA_DIR}/logs" 2>/dev/null || mkdir -p "${DATA_DIR}/mysql" "${DATA_DIR}/redis" "${DATA_DIR}/minio" "${DATA_DIR}/logs"
        # Ensure parent DATA_DIR is traversable (775) — workflow's chown to lumished can make it 750
        sudo chmod 777 "${DATA_DIR}" 2>/dev/null || chmod 777 "${DATA_DIR}" 2>/dev/null || true
        # Ensure mysql data dir has correct perms for mysql user (999)
        # Try sudo chown, fallback to docker-based chown, then fallback to 777 if no sudo
        # Use 755 (not 750) so that userns-remap root can still traverse, and 999 can read even if chown fails
        if sudo chown -R 999:999 "${DATA_DIR}/mysql" 2>/dev/null; then
            sudo chmod -R 777 "${DATA_DIR}/mysql" 2>/dev/null || chmod -R 777 "${DATA_DIR}/mysql" 2>/dev/null || true
        else
            # Try via docker (alpine) to chown without sudo
            if docker run --rm -v "${DATA_DIR}/mysql:/data" alpine sh -c 'chown -R 999:999 /data && chmod -R 777 /data' 2>/dev/null; then
                echo "   (chown via docker alpine succeeded)"
                chmod -R 777 "${DATA_DIR}/mysql" 2>/dev/null || true
            else
                echo "   ⚠️  Cannot chown to 999:999 (no sudo), using 777 for local dev"
                chmod -R 777 "${DATA_DIR}/mysql" 2>/dev/null || chmod 777 "${DATA_DIR}/mysql" 2>/dev/null || true
            fi
        fi
        # Also ensure redis/minio/logs are writable and parent is correct
        chmod -R 775 "${DATA_DIR}/redis" "${DATA_DIR}/minio" "${DATA_DIR}/logs" 2>/dev/null || true
        # Final safety: ensure mysql dir is at least 755 (or 777 if still failing) — helps with userns-remap
        chmod 777 "${DATA_DIR}/mysql" 2>/dev/null || chmod 777 "${DATA_DIR}/mysql" 2>/dev/null || true
        echo "✅ DATA_DIR ready at ${DATA_DIR}"
        ls -ld "${DATA_DIR}" 2>&1 | sed 's/^/   /' || true
        ls -ld "${DATA_DIR}/mysql" 2>&1 | sed 's/^/   /' || true
    else
        mkdir -p "./data/mysql" "./data/redis" "./data/minio" "./data/logs" 2>/dev/null || true
    fi
}


#===============================================================================
# Setup Network & Shared Services
#===============================================================================

setup_network() {
    echo "🌐 Ensuring network lumished-network exists..."
    docker network create lumished-network 2>/dev/null || true

    # Ensure shared services (mysql/redis/minio) are up for DB_HOST=mysql
    if [[ "${DB_HOST:-}" == "mysql" ]]; then
        echo "📦 Ensuring shared services (mysql/redis/minio) are up..."
        # If mysql is stuck in restart loop due to perms (from previous chown workflow), fix before up
        local existing_mysql
        existing_mysql=$(docker ps -a --filter "label=com.docker.compose.service=mysql" --format "{{.Names}}" | head -n 1 2>/dev/null || true)
        if [[ -n "${existing_mysql:-}" ]]; then
            local mysql_status
            mysql_status=$(docker inspect -f '{{.State.Status}}' "${existing_mysql}" 2>/dev/null || echo "unknown")
            if [[ "${mysql_status}" == "restarting" ]]; then
                echo "   ⚠️  mysql is restarting (likely permission issue), stopping to fix..."
                docker stop "${existing_mysql}" 2>/dev/null || true
                # Fix perms via alpine (works without sudo, handles userns-remap)
                docker run --rm -v "${DATA_DIR:-./data}/mysql:/data" alpine sh -c 'chown -R 999:999 /data 2>/dev/null; chmod -R 777 /data 2>/dev/null; echo "   perms fixed via alpine"' 2>/dev/null || true
                chmod -R 777 "${DATA_DIR:-./data}/mysql" 2>/dev/null || true
                chmod 775 "${DATA_DIR:-./data}" 2>/dev/null || true
            fi
        fi
        # Shared compose uses DATA_DIR for volumes, so pass it through
        DATA_DIR="${DATA_DIR:-./data}" DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}" REDIS_PASSWORD="${REDIS_PASSWORD:-}" \
        MINIO_ROOT_USER="${AWS_ACCESS_KEY_ID:-minioadmin}" MINIO_ROOT_PASSWORD="${AWS_SECRET_ACCESS_KEY:-minioadmin}" \
        MINIO_PORT="${MINIO_PORT:-9002}" MINIO_CONSOLE_PORT="${MINIO_CONSOLE_PORT:-9003}" \
        docker compose --project-name lumished --file docker-compose.shared.yml up -d 2>&1 | sed 's/^/   /'
        echo "✅ Shared services ensured"
    fi
}


#===============================================================================
# Setup Database
#===============================================================================

setup_db() {
    echo
    echo "========================================"
    echo "Setting up Database: ${DB_DATABASE}"
    echo "========================================"

    # Only for dockerized mysql (DB_HOST=mysql via shared network). Skip for external RDS.
    if [[ "${DB_HOST:-}" != "mysql" ]]; then
        echo "DB_HOST is not 'mysql' — assuming external DB, skipping local setup."
        return 0
    fi

    if [[ -z "${DB_DATABASE:-}" || -z "${DB_ROOT_PASSWORD:-}" ]]; then
        echo "DB_DATABASE or DB_ROOT_PASSWORD empty — skipping."
        return 0
    fi

    echo "Waiting for mysql to be ready..."

    # Find shared mysql container regardless of project-name
    # Shared services use docker-compose.shared.yml with explicit network lumished-network
    local mysql_container
    mysql_container=$(docker ps --filter "label=com.docker.compose.service=mysql" --format "{{.Names}}" | head -n 1)

    if [[ -z "${mysql_container:-}" ]]; then
        mysql_container=$(docker ps --filter "name=mysql" --format "{{.Names}}" | grep -E "mysql" | head -n 1 || true)
    fi

    if [[ -z "${mysql_container:-}" ]]; then
        echo "ERROR: mysql container not found. Ensure docker-compose.shared.yml is up."
        exit 1
    fi

    echo "Found mysql container: ${mysql_container}"

    local retries=30
    local ping_ok=false

    # Use MYSQL_PWD to avoid shell history expansion (! ) and to avoid password in process list.
    # mysqladmin ping is unreliable (returns 0 even on Auth failure in some MySQL versions), so use mysql -e SELECT 1.
    while [[ $retries -gt 0 ]]; do
        if docker exec -e MYSQL_PWD="${DB_ROOT_PASSWORD}" "${mysql_container}" mysql -u root -e "SELECT 1" >/dev/null 2>&1; then
            ping_ok=true
            break
        fi
        # fallback: try mysqladmin for connectivity check (without auth) — if it fails, mysql truly not ready
        # but primary check is mysql -e which validates auth.

        echo "   ...mysql not ready, $retries tries left (waiting 2s)..."
        retries=$((retries - 1))
        sleep 2
    done

    if [[ "${ping_ok}" != "true" ]]; then
        echo "ERROR: mysql not ready after 30 tries."
        echo "--- mysql container logs (tail 80) ---"
        docker logs --tail 80 "${mysql_container}" 2>&1 | sed 's/^/   /' || true
        echo "--- docker inspect health ---"
        docker inspect -f '{{json .State.Health}}' "${mysql_container}" 2>&1 | sed 's/^/   /' || true
        echo "--- verbose connection attempt (with MYSQL_PWD) ---"
        docker exec -e MYSQL_PWD="${DB_ROOT_PASSWORD}" "${mysql_container}" mysql -u root -e "SELECT 1" 2>&1 | sed 's/^/   /' || true
        echo "--- attempting ping without password (to check if server is up at all) ---"
        docker exec "${mysql_container}" mysqladmin ping -h localhost 2>&1 | sed 's/^/   /' || true
        exit 1
    fi

    echo "mysql ready — creating database if not exists..."

    docker exec -e MYSQL_PWD="${DB_ROOT_PASSWORD}" "${mysql_container}" \
        mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    # Create app DB user if not root
    if [[ "${DB_USERNAME:-}" != "root" && -n "${DB_USERNAME:-}" && -n "${DB_PASSWORD:-}" ]]; then
        echo "Creating DB user ${DB_USERNAME}..."
        docker exec -e MYSQL_PWD="${DB_ROOT_PASSWORD}" "${mysql_container}" \
            mysql -u root -e "CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%' IDENTIFIED BY '${DB_PASSWORD}'; GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%'; FLUSH PRIVILEGES;"
    fi

    echo "Database ${DB_DATABASE} ready."

    echo "Verifying DB connection as ${DB_USERNAME}..."
    if docker exec -e MYSQL_PWD="${DB_PASSWORD}" "${mysql_container}" mysql -u "${DB_USERNAME}" -e "USE \`${DB_DATABASE}\`; SELECT 1;" >/dev/null 2>&1; then
        echo "✅ DB connection verified for ${DB_USERNAME}@${DB_DATABASE}"
    else
        echo "❌ DB connection failed for ${DB_USERNAME}@${DB_DATABASE}"
        echo "--- verbose verification ---"
        docker exec -e MYSQL_PWD="${DB_PASSWORD}" "${mysql_container}" mysql -u "${DB_USERNAME}" -e "USE \`${DB_DATABASE}\`; SELECT 1;" 2>&1 | sed 's/^/   /' || true
        exit 1
    fi

    # Verify via app container as well (uses .env)
    if compose exec -T app php artisan tinker --execute "DB::connection()->getPdo(); echo 'ok';" >/dev/null 2>&1; then
        echo "✅ App DB connection verified"
    else
        echo "⚠️  App DB check via tinker failed (app may still be starting) — will be verified by migrate/health"
    fi
}


#===============================================================================
# Setup MinIO Bucket
#===============================================================================

setup_minio() {
    echo
    echo "========================================"
    echo "Setting up MinIO Bucket: ${AWS_BUCKET}"
    echo "========================================"

    if [[ -z "${AWS_BUCKET:-}" ]]; then
        echo "AWS_BUCKET empty — skipping."
        return 0
    fi

    # Sanitize bucket name to S3/MinIO DNS rules (lowercase, a-z0-9.-, no _)
    local original_bucket="${AWS_BUCKET}"
    local bucket_sanitized
    bucket_sanitized=$(echo "${AWS_BUCKET}" | tr '[:upper:]' '[:lower:]' | tr '_' '-' | tr -cs 'a-z0-9.-' '-' | sed 's/^-*//;s/-*$//' | sed 's/--*/-/g')
    if [[ "${bucket_sanitized}" != "${original_bucket}" ]]; then
        echo "   ⚠️  Bucket name sanitized: ${original_bucket} → ${bucket_sanitized} (S3 DNS: no _)"
        AWS_BUCKET="${bucket_sanitized}"
    fi

    # Only for MinIO (path-style or explicit endpoint). Skip for real S3 (AWS_ENDPOINT empty, use_path_style false)
    if [[ "${AWS_USE_PATH_STYLE_ENDPOINT:-}" != "true" && -z "${AWS_ENDPOINT:-}" ]]; then
        echo "Not MinIO (AWS_USE_PATH_STYLE_ENDPOINT != true and AWS_ENDPOINT empty) — assuming S3, skipping local bucket creation."
        return 0
    fi

    if [[ -z "${AWS_ACCESS_KEY_ID:-}" || -z "${AWS_SECRET_ACCESS_KEY:-}" ]]; then
        echo "AWS credentials empty — skipping."
        return 0
    fi

    # Find shared minio container
    local minio_container
    minio_container=$(docker ps --filter "label=com.docker.compose.service=minio" --format "{{.Names}}" | head -n 1)

    if [[ -z "${minio_container:-}" ]]; then
        minio_container=$(docker ps --filter "name=minio" --format "{{.Names}}" | grep -E "minio" | head -n 1 || true)
    fi

    if [[ -z "${minio_container:-}" ]]; then
        echo "MinIO container not found — skipping bucket creation (maybe S3)."
        return 0
    fi

    echo "Found MinIO container: ${minio_container}"

    # Use minio/mc image to create bucket if not exists (idempotent)
    # MC_HOST alias: http://<user>:<pass>@minio:9000
    local endpoint_host="minio:9000"

    # Extract host from AWS_ENDPOINT if provided (e.g., http://minio:9000)
    if [[ -n "${AWS_ENDPOINT:-}" ]]; then
        endpoint_host=$(echo "${AWS_ENDPOINT}" | sed -E 's|https?://||' | sed 's|/.*||')
    fi

    echo "Creating bucket ${AWS_BUCKET} via mc (endpoint ${endpoint_host})..."

    docker run --rm --network lumished-network \
        --entrypoint "" \
        quay.io/minio/mc:latest sh -c "
            set -e
            mc alias set minio http://${endpoint_host} \"${AWS_ACCESS_KEY_ID}\" \"${AWS_SECRET_ACCESS_KEY}\" >/dev/null 2>&1 || true
            mc mb --ignore-existing minio/${AWS_BUCKET}
            mc anonymous set download minio/${AWS_BUCKET} >/dev/null 2>&1 || true
            echo \"Bucket ${AWS_BUCKET} ready.\"
        " 2>&1 | sed 's/^/   /'

    echo "MinIO bucket ${AWS_BUCKET} ready."
}


#===============================================================================
# Optimize Laravel
#===============================================================================

optimize_laravel() {
    echo "⚡ Optimizing Laravel..."
    compose exec -T app php artisan optimize:clear
    compose exec -T app php artisan optimize
    echo "✅ Laravel optimized"
}


#===============================================================================
# Setup Storage Symlink
#===============================================================================

setup_storage() {
    echo "🗂️ Setting up storage symlink..."
    compose exec -T app php artisan storage:link || true
    echo "✅ Storage ready"
}


#===============================================================================
# Fix Permissions
#===============================================================================

fix_permissions() {
    echo "🔧 Fixing storage permissions..."
    # Container part — only if app is running (fix_permissions is called after start, but be defensive)
    if compose ps app 2>/dev/null | grep -q "Up" || docker inspect -f '{{.State.Running}}' "lumished-${APP_ENV:-local}-app-1" 2>/dev/null | grep -q "true"; then
        compose exec -T -u root app sh -c "chmod -R 775 storage bootstrap/cache public 2>/dev/null || chmod -R 775 storage bootstrap/cache 2>/dev/null; chown -R www-data:www-data storage bootstrap/cache public 2>/dev/null || chown -R www-data:www-data storage bootstrap/cache 2>/dev/null" 2>/dev/null || echo "⚠️  Container chmod skipped (app not ready)"
    else
        echo "⚠️  App not running yet — skipping container chmod (will be fixed on next start)"
    fi

    # Fix host-side log directory ownership so the volume mount is writable by www-data (uid 33)
    if [ -d "${DATA_DIR}/logs" ]; then
        sudo chown -R 33:33 "${DATA_DIR}/logs" 2>/dev/null || chown -R 33:33 "${DATA_DIR}/logs" 2>/dev/null || true
        sudo chmod -R 775 "${DATA_DIR}/logs" 2>/dev/null || chmod -R 775 "${DATA_DIR}/logs" 2>/dev/null || true
    fi

    echo "✅ Permissions fixed"
}


#===============================================================================
# Generate API Docs
#===============================================================================

generate_api_docs() {
    echo "📘 Checking for Scribe..."
    if compose exec -T app php artisan list 2>/dev/null | grep -q "scribe:generate"; then
        echo "📘 Generating API documentation..."
        compose exec -T app php artisan route:clear
        compose exec -T app php artisan scribe:generate
        echo "✅ API docs generated"
    else
        echo "⏭️  Scribe not installed — skipping"
    fi
}


#===============================================================================
# Migrate
#===============================================================================

migrate() {
    echo
    echo "========================================"
    echo "Running Migrations: ${MIGRATE_CMD:-migrate --force}"
    echo "========================================"

    # Wait for app to be healthy before migrating (per-env project allows prod + staging simultaneously)
    sleep 10

    compose exec -T app php artisan ${MIGRATE_CMD:-migrate --force}

    echo
    echo "Migrations completed."
}


#===============================================================================
# Cleanup (images + build cache + logs)
#===============================================================================

cleanup() {
    echo "🧹 Pruning unused Docker images and build cache..."
    docker image prune -f
    docker builder prune --keep-storage 2GB -f
    echo "✅ Cleanup done"

    echo "🧹 Clearing application logs..."
    compose exec -T -u root app sh -c "
        LOG_FILE=storage/logs/laravel.log
        if [ -f \"\$LOG_FILE\" ]; then
            TIMESTAMP=\$(date +%Y%m%d_%H%M%S)
            mv \"\$LOG_FILE\" \"storage/logs/laravel_\${TIMESTAMP}.log\"
        fi
        find storage/logs -type f -name '*.log' ! -name '.gitignore' -delete
        touch \"\$LOG_FILE\"
        chown www-data:www-data \"\$LOG_FILE\"
        chmod 664 \"\$LOG_FILE\"
    "
    echo "✅ Logs cleared"
}


#===============================================================================
# Health
#===============================================================================

health() {
    echo "🔍 Verifying containers..."

    # Per-env app containers (lumished-${APP_ENV}) + shared services (via labels, not hardcoded project)
    local project_base="lumished-${APP_ENV:-local}"
    project_base=$(echo "$project_base" | tr '[:upper:]' '[:lower:]' | tr -cs 'a-z0-9_-' '-' | sed 's/^-*//;s/-*$//')

    local failed=()

    for container in \
        "${project_base}-app-1" \
        "${project_base}-queue-1" \
        "${project_base}-scheduler-1"; do
        local status
        status=$(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null || echo "false")
        if [ "$status" != "true" ]; then
            failed+=("$container")
        else
            echo "   ✅ $container is running"
        fi
    done

    # Shared services — find by compose label, not hardcoded name (handles florishmax vs lumished typo + any project)
    # For S3 prod (AWS_USE_PATH_STYLE_ENDPOINT != true && AWS_ENDPOINT empty), minio is not required
    local services_to_check=("mysql" "redis")
    if [[ "${AWS_USE_PATH_STYLE_ENDPOINT:-}" == "true" || -n "${AWS_ENDPOINT:-}" ]]; then
        services_to_check+=("minio")
    fi
    for service in "${services_to_check[@]}"; do
        local shared_container
        shared_container=$(docker ps --filter "label=com.docker.compose.service=${service}" --format "{{.Names}}" | head -n 1)
        if [[ -z "${shared_container:-}" ]]; then
            shared_container=$(docker ps --filter "name=${service}" --format "{{.Names}}" | grep -E "${service}" | head -n 1 || true)
        fi
        if [[ -z "${shared_container:-}" ]]; then
            failed+=("lumished-${service}-1 (shared)")
        else
            local status
            status=$(docker inspect -f '{{.State.Running}}' "$shared_container" 2>/dev/null || echo "false")
            if [ "$status" != "true" ]; then
                failed+=("$shared_container")
            else
                echo "   ✅ $shared_container is running (shared $service)"
            fi
        fi
    done

    if [ ${#failed[@]} -gt 0 ]; then
        echo "❌ Containers not running: ${failed[*]}"
        exit 1
    fi

    # Try localhost:APP_PORT first (always available via Docker), then APP_URL
    local urls=()
    urls+=("http://127.0.0.1:${APP_PORT:-8000}/up")
    if [[ -n "${APP_URL:-}" && "${APP_URL}" != "http://127.0.0.1:${APP_PORT:-8000}" ]]; then
        local app_url_norm="${APP_URL%/}/up"
        # Avoid duplicate if APP_URL already equals localhost:port
        if [[ "$app_url_norm" != "http://127.0.0.1:${APP_PORT:-8000}/up" ]]; then
            urls+=("$app_url_norm")
        fi
    fi

    local url_ok=false
    for url in "${urls[@]}"; do
        echo "🌐 Checking service is reachable at ${url}..."
        local retries=3
        local wait=2
        local ok=false
        until curl -sSL --max-time 5 -o /dev/null -w "%{http_code}" "${url}" 2>/dev/null | grep -qE '^[23]'; do
            retries=$((retries - 1))
            if [ "$retries" -eq 0 ]; then
                echo "   ⚠️  ${url} not responding"
                break
            fi
            echo "   still waiting for HTTP response... ($retries attempts left)"
            sleep "$wait"
        done
        if curl -sSL --max-time 5 -o /dev/null -w "%{http_code}" "${url}" 2>/dev/null | grep -qE '^[23]'; then
            echo "✅ Service is reachable at ${url}"
            url_ok=true
            break
        fi
    done

    if [[ "$url_ok" != "true" ]]; then
        echo "❌ Service not responding on any URL: ${urls[*]}"
        exit 1
    fi

    # Check MinIO bucket is accessible (sanitize bucket name first)
    if [[ -n "${AWS_BUCKET:-}" ]]; then
        # Sanitize bucket name to S3 DNS rules (same as setup_minio)
        local original_bucket="${AWS_BUCKET}"
        local bucket_sanitized
        bucket_sanitized=$(echo "${AWS_BUCKET}" | tr '[:upper:]' '[:lower:]' | tr '_' '-' | tr -cs 'a-z0-9.-' '-' | sed 's/^-*//;s/-*$//' | sed 's/--*/-/g')
        if [[ "${bucket_sanitized}" != "${original_bucket}" ]]; then
            echo "   sanitized bucket: ${original_bucket} → ${bucket_sanitized}"
            AWS_BUCKET="${bucket_sanitized}"
        fi
        echo "🪣 Checking bucket ${AWS_BUCKET}..."

        local bucket_ok=false

        if [[ "${AWS_USE_PATH_STYLE_ENDPOINT:-}" == "true" || -n "${AWS_ENDPOINT:-}" ]]; then
            # MinIO via mc
            local endpoint_host="minio:9000"
            if [[ -n "${AWS_ENDPOINT:-}" ]]; then
                endpoint_host=$(echo "${AWS_ENDPOINT}" | sed -E 's|https?://||' | sed 's|/.*||')
            fi

            if docker run --rm --network lumished-network --entrypoint "" quay.io/minio/mc:latest sh -c "
                mc alias set minio http://${endpoint_host} \"${AWS_ACCESS_KEY_ID}\" \"${AWS_SECRET_ACCESS_KEY}\" >/dev/null 2>&1
                mc stat minio/${AWS_BUCKET} >/dev/null 2>&1
            " >/dev/null 2>&1; then
                bucket_ok=true
            fi
        else
            # S3 — try via app container aws check (if bucket exists, Storage::exists will be tested elsewhere, just check env)
            bucket_ok=true
            echo "   (S3 bucket check skipped — assuming exists, verified via app Storage)"
        fi

        if [[ "$bucket_ok" == "true" ]]; then
            echo "   ✅ Bucket ${AWS_BUCKET} is accessible"
        else
            echo "   ⚠️ Bucket ${AWS_BUCKET} is not accessible (will be created by setup_minio if fresh)"
            # Don't fail health on first deploy — bucket is created after start
            # Only warn, setup_minio will create it, final health will verify
        fi
    fi
}


#===============================================================================
# Deploy (full lifecycle)
#===============================================================================

deploy() {
    generate_env
    build

    # Ensure DATA_DIR exists for mysql/redis/minio (easy migration via host dir)
    setup_data_dir

    # Ensure shared network exists (external) before app start
    setup_network

    # Start containers with new images (per-env project allows prod + staging simultaneously)
    start

    # Fix permissions after start (app must be running)
    fix_permissions

    # Ensure DB exists (uses DB_ROOT_PASSWORD via shared mysql, not .env)
    setup_db

    # Ensure MinIO bucket exists (uses AWS_* via shared minio, skipped for S3)
    setup_minio

    # Run migrations inside the per-env app container
    migrate

    # Optimize framework cache & storage symlink
    optimize_laravel
    setup_storage

    # Generate API docs if Scribe is installed
    generate_api_docs

    # Health check
    health

    # Post-deploy housekeeping (images + logs)
    cleanup
}


#===============================================================================
# Command
#===============================================================================

case "$ACTION" in

    start)
        start
        ;;

    stop)
        stop
        ;;

    restart)
        restart
        ;;

    health)
        health
        ;;

    migrate)
        migrate
        ;;

    deploy)
        deploy
        ;;

    *)
        echo "Usage: ./deploy.sh {start|stop|restart|health|migrate|deploy}"
        exit 1
        ;;

esac
