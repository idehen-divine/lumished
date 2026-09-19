#!/bin/bash

set -e

#===============================================================================
#  Usage: deploy.sh [fresh|start|stop|restart]   (default: fresh)
#
#  fresh    – full deploy: system prep, image rebuild, DB wipe + reseed, docs
#  start    – bring the stack up with existing image and data (no reinstall)
#  stop     – stop the application stack (shared infra stays up)
#  restart  – stop then start
#
#  All variables are passed as environment variables from the CI pipeline.
#
#  APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL
#  DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_ROOT_PASSWORD
#  REDIS_HOST, REDIS_PASSWORD, REDIS_PORT, REDIS_DB, REDIS_CACHE_DB
#  MAIL_SCHEME, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
#  AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET, AWS_ENDPOINT
#  MINIO_ROOT_USER, MINIO_ROOT_PASSWORD, MINIO_ENDPOINT
#  PAYSTACK_SECRET, SQUAD_API_KEY
#  DEPLOY_DIR, SHARED_DIR, MIGRATE_CMD, DATA_DIR
#===============================================================================

cd "$DEPLOY_DIR"

APP_EXEC="docker compose exec -T app"
SHARED_COMPOSE="docker compose -p shelfie-shared -f $SHARED_DIR/docker-compose.shared.yml"

CADDY_HTTP_PORT=80
CADDY_HTTPS_PORT=443


#===============================================================================
#                           APPLICATION FUNCTIONS
#===============================================================================

# Runs apt-get update and upgrade to ensure the server OS is up to date
# before any deployment steps are executed.
# Ensures the ports Caddy needs are free before starting containers.
# For each blocked port, attempts to stop the process gracefully via systemctl
# (for known services like nginx/apache2) or kills it directly by PID as a fallback.
check_ports() {
    echo "🔌 Checking required ports..."
    for port in "$CADDY_HTTP_PORT" "$CADDY_HTTPS_PORT"; do
        if ! sudo ss -tlnH "sport = :$port" | grep -q .; then
            echo "   ✅ Port $port is free"
            continue
        fi

        local pid proc_name
        pid=$(sudo ss -tlnpH "sport = :$port" | grep -oP 'pid=\K[0-9]+' | head -1)
        proc_name=$(cat /proc/$pid/comm 2>/dev/null || echo "unknown")

        # docker-proxy means Caddy (shared stack) already owns this port — leave it alone
        if [ "$proc_name" = "docker-proxy" ]; then
            echo "   ✅ Port $port held by Caddy (docker-proxy) — shared stack will reload it"
            continue
        fi

        echo "   ⚠️  Port $port is in use by $proc_name (PID $pid) — attempting to free it..."

        if systemctl is-active --quiet "$proc_name" 2>/dev/null; then
            sudo systemctl daemon-reload 2>/dev/null || true
            sudo systemctl stop "$proc_name" || true
        elif [ -n "$pid" ]; then
            sudo kill "$pid" || true
        fi

        if sudo ss -tlnH "sport = :$port" | grep -q .; then
            echo "❌ Could not free port $port — please stop $proc_name manually"
            exit 1
        fi

        echo "   ✅ Port $port freed"
    done
    echo "✅ Required ports are free"
}


system_upgrade() {
    if [ "$APP_ENV" = "local" ]; then
        echo "⏭️ Local env detected — skipping system upgrade"
        return
    fi
    echo "🔄 Upgrading system packages..."
    sudo apt-get update -qq && sudo apt-get upgrade -y -qq
    echo "✅ System upgraded"
}


# Checks whether Docker and the Docker Compose plugin are installed.
# If either is missing, installs them automatically using the official
# Docker install script and apt package manager respectively.
# After adding the user to the docker group, re-executes the script under
# sg docker so the group is active in the current session without a logout.
setup_docker() {
    echo "🐳 Checking Docker installation..."

    if ! command -v docker &>/dev/null; then
        echo "⬇️ Docker not found — installing..."
        curl -fsSL https://get.docker.com | sudo sh
        sudo systemctl enable docker
        sudo systemctl start docker
        sudo usermod -aG docker "$USER"
        echo "✅ Docker installed"
    else
        echo "✅ Docker already installed ($(docker --version))"
    fi

    if ! docker compose version &>/dev/null; then
        echo "⬇️ Docker Compose plugin not found — installing..."
        sudo apt-get install -y -qq docker-compose-plugin
        echo "✅ Docker Compose installed"
    else
        echo "✅ Docker Compose already installed ($(docker compose version))"
    fi

    if ! groups | grep -q docker; then
        echo "🔁 Re-executing script under docker group (group added this session)..."
        exec sg docker "$0"
    fi
}


# Validates that all required environment variables have been supplied by
# the CI pipeline. Collects every missing variable before failing so the
# full list is visible in a single error message rather than one at a time.
validate_env() {
    echo "🧩 Validating environment variables..."
    local missing=()
    for var in \
        APP_NAME APP_ENV APP_KEY APP_URL \
        DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_ROOT_PASSWORD \
        REDIS_HOST REDIS_PORT REDIS_DB REDIS_CACHE_DB \
        AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_BUCKET AWS_ENDPOINT \
        AWS_USE_PATH_STYLE_ENDPOINT \
        MINIO_ROOT_USER MINIO_ROOT_PASSWORD \
        PAYSTACK_SECRET SQUAD_API_KEY; do
        if [ -z "${!var}" ]; then
            missing+=("$var")
        fi
    done
    if [ ${#missing[@]} -gt 0 ]; then
        echo "❌ Missing required variables: ${missing[*]}"
        exit 1
    fi
    echo "✅ Environment validated"
}



verify_compose_file() {
    echo "⚙️ Verifying docker-compose.yml..."
    if [ ! -f "$DEPLOY_DIR/docker-compose.yml" ]; then
        echo "❌ docker-compose.yml not found at $DEPLOY_DIR"
        exit 1
    fi
    echo "✅ docker-compose.yml present — env vars resolved from pipeline"
}


# Generates the application .env file by first copying .env.example as a clean
# base, then using sed to replace every placeholder value with the real secrets
# injected by the CI pipeline. This ensures no stale values survive between deploys.
setup_env_file() {
    echo "📝 Writing .env file..."
    local env_file="$DEPLOY_DIR/.env"
    local example_file="$DEPLOY_DIR/.env.example"

    if [ ! -f "$example_file" ]; then
        echo "❌ .env.example not found at $example_file"
        exit 1
    fi

    cp "$example_file" "$env_file"

    export APP_DOMAIN=$(echo "$APP_URL" | sed 's|https\?://||' | cut -d'/' -f1)
    CDN_SCHEME=$(echo "$APP_URL" | grep -q "^https" && echo "https" || echo "http")
    export MINIO_URL="${CDN_SCHEME}://cdn.${APP_DOMAIN}"
    export AWS_URL="${MINIO_URL}/${AWS_BUCKET}"

    e() { echo "$1" | sed 's|[&]|\\&|g'; }

    sed -i \
        -e "s|^APP_NAME=.*|APP_NAME=\"$(e "$APP_NAME")\"|" \
        -e "s|^APP_ENV=.*|APP_ENV=$(e "$APP_ENV")|" \
        -e "s|^APP_KEY=.*|APP_KEY=$(e "$APP_KEY")|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=$(e "$APP_DEBUG")|" \
        -e "s|^APP_URL=.*|APP_URL=$(e "$APP_URL")|" \
        -e "s|^DB_HOST=.*|DB_HOST=$(e "$DB_HOST")|" \
        -e "s|^DB_PORT=.*|DB_PORT=$(e "$DB_PORT")|" \
        -e "s|^DB_DATABASE=.*|DB_DATABASE=$(e "$DB_DATABASE")|" \
        -e "s|^DB_USERNAME=.*|DB_USERNAME=$(e "$DB_USERNAME")|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(e "$DB_PASSWORD")|" \
        -e "s|^REDIS_HOST=.*|REDIS_HOST=$(e "$REDIS_HOST")|" \
        -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$(e "$REDIS_PASSWORD")|" \
        -e "s|^REDIS_PORT=.*|REDIS_PORT=$(e "$REDIS_PORT")|" \
        -e "s|^REDIS_DB=.*|REDIS_DB=$(e "$REDIS_DB")|" \
        -e "s|^REDIS_CACHE_DB=.*|REDIS_CACHE_DB=$(e "$REDIS_CACHE_DB")|" \
        -e "s|^MAIL_MAILER=.*|MAIL_MAILER=smtp|" \
        -e "s|^MAIL_SCHEME=.*|MAIL_SCHEME=$(e "$MAIL_SCHEME")|" \
        -e "s|^MAIL_HOST=.*|MAIL_HOST=$(e "$MAIL_HOST")|" \
        -e "s|^MAIL_PORT=.*|MAIL_PORT=$(e "$MAIL_PORT")|" \
        -e "s|^MAIL_USERNAME=.*|MAIL_USERNAME=$(e "$MAIL_USERNAME")|" \
        -e "s|^MAIL_PASSWORD=.*|MAIL_PASSWORD=$(e "$MAIL_PASSWORD")|" \
        -e "s|^AWS_ACCESS_KEY_ID=.*|AWS_ACCESS_KEY_ID=$(e "$AWS_ACCESS_KEY_ID")|" \
        -e "s|^AWS_SECRET_ACCESS_KEY=.*|AWS_SECRET_ACCESS_KEY=$(e "$AWS_SECRET_ACCESS_KEY")|" \
        -e "s|^AWS_DEFAULT_REGION=.*|AWS_DEFAULT_REGION=$(e "$AWS_DEFAULT_REGION")|" \
        -e "s|^AWS_BUCKET=.*|AWS_BUCKET=$(e "$AWS_BUCKET")|" \
        -e "s|^AWS_ENDPOINT=.*|AWS_ENDPOINT=$(e "$AWS_ENDPOINT")|" \
        -e "s|^AWS_USE_PATH_STYLE_ENDPOINT=.*|AWS_USE_PATH_STYLE_ENDPOINT=$(e "$AWS_USE_PATH_STYLE_ENDPOINT")|" \
        -e "s|^AWS_URL=.*|AWS_URL=$(e "$AWS_URL")|" \
        -e "s|^PAYSTACK_SECRET=.*|PAYSTACK_SECRET=$(e "$PAYSTACK_SECRET")|" \
        -e "s|^SQUAD_API_KEY=.*|SQUAD_API_KEY=$(e "$SQUAD_API_KEY")|" \
        "$env_file"

    echo "✅ .env file written"
}


# Writes the docker-compose.override.yml used only in local dev to mount the
# working directory into the containers. Generated fresh on every start so no
# stale overrides survive between runs.
setup_local_override() {
    if [ "$APP_ENV" = "local" ]; then
        echo "🔧 Local env detected — generating volume mount override..."
        cat > "$DEPLOY_DIR/docker-compose.override.yml" <<EOF
services:
  app:
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
      - ./storage/logs:/var/www/html/storage/logs
  queue:
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
      - ./storage/logs:/var/www/html/storage/logs
  scheduler:
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
      - ./storage/logs:/var/www/html/storage/logs
EOF
    fi
}

remove_local_override() {
    if [ "$APP_ENV" = "local" ] && [ -f "$DEPLOY_DIR/docker-compose.override.yml" ]; then
        rm "$DEPLOY_DIR/docker-compose.override.yml"
    fi
}


# Brings up shared infrastructure (MySQL, Redis, MinIO, Caddy), optionally
# builds the application image, then starts the environment stack (app, queue,
# scheduler, websocket). Pass "build" to force an image rebuild — plain starts
# only pull up whatever image already exists, so not every deploy reinstalls.
start_containers() {
    echo "🛑 Stopping existing environment stack..."
    docker compose down --remove-orphans || true
    echo "✅ Old containers stopped"

    echo "🔗 Ensuring shared services are running..."
    $SHARED_COMPOSE up -d --remove-orphans --force-recreate
    echo "✅ Shared services running"

    if [ "$1" = "build" ]; then
        echo "📦 Building Docker image..."
        docker compose build app
        echo "✅ Image built"
    else
        echo "⏭️ Skipping image build (use deploy:fresh to rebuild)"
    fi

    setup_local_override

    echo "🚀 Starting environment stack..."
    docker compose up -d --remove-orphans
    echo "✅ Containers started"

    remove_local_override
}


# Creates the MinIO bucket if it does not exist and sets it to public so
# files are readable without auth headers. Uses the mc client bundled in
# the MinIO image via the shared compose stack.
setup_minio_bucket() {
    echo "🪣 Setting up MinIO bucket..."
    $SHARED_COMPOSE exec -T minio sh -c "
        mc alias set local http://localhost:9000 ${MINIO_ROOT_USER} ${MINIO_ROOT_PASSWORD} --quiet &&
        mc mb --ignore-existing local/${AWS_BUCKET} &&
        mc anonymous set public local/${AWS_BUCKET}
    "
    echo "✅ MinIO bucket '${AWS_BUCKET}' ready"
}


# Waits until the MySQL container reports healthy before proceeding.
# On a cold first boot MySQL can take 20-30 seconds to initialize — without
# this wait, setup_application_database would run against an unready socket.
wait_for_database() {
    echo "⏳ Waiting for MySQL to be ready..."
    local retries=30
    local wait=3

    until $SHARED_COMPOSE exec -T mysql mysqladmin ping -uroot -p"${DB_ROOT_PASSWORD}" --silent &>/dev/null; do
        retries=$((retries - 1))
        if [ "$retries" -eq 0 ]; then
            echo "❌ MySQL did not become ready in time"
            exit 1
        fi
        echo "   still waiting... ($retries attempts left)"
        sleep "$wait"
    done
    echo "✅ MySQL is ready"
}


# Creates the application database and user in MySQL if they do not already
# exist. Runs against the shared MySQL container using the root credentials.
setup_application_database() {
    echo "🗄️ Setting up application database..."

    $SHARED_COMPOSE exec -T mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" <<DBEOF
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%'
  IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%';
FLUSH PRIVILEGES;
DBEOF
    echo "✅ Database and user ready"
}


# Verifies that Laravel can successfully connect to the database by running
# db:show. Fails the deployment early if the connection cannot be established.
verify_database_connection() {
    echo "🔍 Verifying database connection..."
    # $APP_EXEC php artisan package:discover --ansi
    $APP_EXEC php artisan db:show > /dev/null || {
        echo "❌ Laravel cannot connect to database"
        exit 1
    }
    echo "✅ Database connection OK"
}


# Runs the Artisan migration command supplied by the pipeline via MIGRATE_CMD.
# Skipped entirely when MIGRATE_CMD is not set (e.g. for hotfix deploys).
run_database_migrations() {
    if [ -n "$MIGRATE_CMD" ]; then
        echo "🗄️ Running migrations: $MIGRATE_CMD"
        $APP_EXEC php artisan $MIGRATE_CMD
        echo "✅ Migrations completed"
    else
        echo "⏭️ Migrations skipped"
    fi
}

# Runs only incremental migrations for non-destructive starts. Never wipes
# or reseeds the database the way deploy:fresh does.
run_incremental_migrations() {
    echo "🗄️ Running incremental migrations (non-destructive)..."
    $APP_EXEC php artisan migrate --force
    echo "✅ Migrations completed"
}


# Clears all cached config, routes, and views then re-caches them so the
# running containers serve fresh configuration after every deploy.
optimize_laravel() {
    echo "⚡ Optimizing Laravel..."
    $APP_EXEC php artisan optimize:clear
    $APP_EXEC php artisan optimize
    echo "✅ Laravel optimized"
}


# Creates the public storage symlink required for user-uploaded file access.
# Uses || true so the step is skipped silently if the symlink already exists.
setup_storage() {
    echo "🗂️ Setting up storage symlink..."
    $APP_EXEC php artisan storage:link || true
    echo "✅ Storage ready"
}


# Sets the correct ownership and permissions on the storage and bootstrap/cache
# directories so PHP-FPM (www-data) can write to them without errors.
# Runs explicitly as root inside the container to avoid permission denied errors
# when the default exec user is non-root.
fix_permissions() {
    echo "🔧 Fixing storage permissions..."
    docker compose exec -T -u root app chmod -R 775 storage bootstrap/cache public
    docker compose exec -T -u root app chown -R www-data:www-data storage bootstrap/cache public

    # Fix host-side log directory ownership so the volume mount is writable by www-data (uid 33)
    if [ -d "${DATA_DIR}/logs" ]; then
        sudo chown -R 33:33 "${DATA_DIR}/logs"
        sudo chmod -R 775 "${DATA_DIR}/logs"
    fi

    # For local env, laravel-dynamic-helpers writes IDE helper files to the project root on every boot.
    # Pre-create them so www-data can write without needing ownership of the whole mounted directory.
    if [ "$APP_ENV" = "local" ]; then
        for f in _ide_helper.php .phpstorm.meta.php; do
            touch "$DEPLOY_DIR/$f" 2>/dev/null || true
            sudo chown 33:33 "$DEPLOY_DIR/$f" 2>/dev/null || true
            sudo chmod 664 "$DEPLOY_DIR/$f" 2>/dev/null || true
        done
    fi
    echo "✅ Permissions fixed"
}


# Generates Scribe API documentation if the scribe:generate command is
# available. Skipped silently when Scribe is not installed in the project.
generate_api_docs() {
    if $APP_EXEC php artisan list | grep -q "scribe:generate"; then
        echo "📘 Generating API documentation..."
        $APP_EXEC php artisan scribe:generate || true
        echo "✅ API docs generated"
    else
        echo "⏭️ Scribe not installed — skipping"
    fi
}


# Archives the current laravel.log with a timestamp then removes all old log
# files so the new deployment starts with a clean log slate.
# Verifies that every container in both the environment stack and the shared
# stack is running, then performs an HTTP health check against the app URL
# to confirm the service is publicly reachable before declaring success.
verify_deployment() {
    echo "🔍 Verifying containers..."

    local failed=()

    for container in \
        "${APP_ENV}-app" \
        "${APP_ENV}-queue" \
        "${APP_ENV}-scheduler" \
        shelfie-mysql \
        shelfie-redis \
        shelfie-minio \
        shelfie-caddy; do
        local status
        status=$(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null || echo "false")
        if [ "$status" != "true" ]; then
            failed+=("$container")
        else
            echo "   ✅ $container is running"
        fi
    done

    if [ ${#failed[@]} -gt 0 ]; then
        echo "❌ Containers not running: ${failed[*]}"
        exit 1
    fi

    echo "🌐 Checking service is reachable at ${APP_URL}..."
    local retries=10
    local wait=5

    until curl -sSL --max-time 5 -o /dev/null -w "%{http_code}" "${APP_URL}" 2>/dev/null | grep -qE '^[23]'; do
        retries=$((retries - 1))
        if [ "$retries" -eq 0 ]; then
            echo "❌ Service at ${APP_URL} is not responding"
            exit 1
        fi
        echo "   still waiting for HTTP response... ($retries attempts left)"
        sleep "$wait"
    done

    echo "✅ Service is reachable at ${APP_URL}"
}


# Signals Caddy to reload its configuration without downtime so the updated
# Caddyfile takes effect immediately after the new containers are running.
reload_caddy() {
    echo "🔁 Reloading Caddy..."
    $SHARED_COMPOSE exec -T caddy caddy reload --config /etc/caddy/Caddyfile || true
    echo "✅ Caddy reloaded"
}


 


#===============================================================================
#                           DEPLOYMENT PHASES
#===============================================================================

# Full deploy: system prep, image rebuild, database wipe + reseed, docs,
# logging rotation. This is the only path that reinstalls everything.
deploy_fresh() {
    system_upgrade
    setup_docker
    validate_env
    check_ports
    verify_compose_file
    setup_env_file
    start_containers build
    setup_minio_bucket
    wait_for_database
    setup_application_database
    verify_database_connection
    run_database_migrations
    optimize_laravel
    setup_storage
    fix_permissions
    generate_api_docs
    clear_application_logs
    reload_caddy
    verify_deployment
    cleanup
}

# Brings the stack up with the existing image and data. No rebuild, no DB wipe.
deploy_start() {
    validate_env
    verify_compose_file
    setup_env_file
    check_ports
    start_containers
    setup_minio_bucket
    wait_for_database
    setup_application_database
    verify_database_connection
    run_incremental_migrations
    optimize_laravel
    setup_storage
    fix_permissions
    generate_api_docs
    reload_caddy
    verify_deployment
}

# Stops the application stack. Shared infrastructure (MySQL, Redis, MinIO,
# Caddy) is left running so subsequent starts are fast and lossless.
deploy_stop() {
    echo "🛑 Stopping application stack..."
    docker compose down --remove-orphans || true
    echo "✅ Application stack stopped"
}

deploy_restart() {
    deploy_stop
    deploy_start
}


#===============================================================================
#                           MAIN EXECUTION
#===============================================================================

COMMAND="${1:-fresh}"

case "$COMMAND" in
    start)
        deploy_start
        ;;
    stop)
        deploy_stop
        ;;
    restart)
        deploy_restart
        ;;
    fresh)
        deploy_fresh
        ;;
    *)
        echo "ℹ️  Unknown command '$COMMAND' — defaulting to fresh"
        deploy_fresh
        ;;
esac

echo "🎉 Deployment successful!"
