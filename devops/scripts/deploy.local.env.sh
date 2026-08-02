#!/bin/bash
# Local test environment variables for deploy.sh
# DO NOT COMMIT — add to .gitignore if not already covered

export DEPLOY_DIR="/home/l0n3ly/Documents/dev/Lawal_Res/SHELFIE"
export SHARED_DIR="/home/l0n3ly/Documents/dev/Lawal_Res/SHELFIE"
export DATA_DIR="/home/l0n3ly/Documents/dev/Lawal_Res/data"
export MIGRATE_CMD="migrate:fresh --seed"

# App
export APP_NAME="shelfie"
export APP_ENV="local"
export APP_KEY="base64:C9Qk+LOcQ6bqYAFz0BxpCvyIPyUN1WcsbtV46Q6pnJs="
export APP_DEBUG="true"
export APP_URL="http://shelfie.test"

# Database
export DB_HOST="shelfie-mysql"
export DB_PORT="3306"
export DB_DATABASE="shelfie_local"
export DB_USERNAME="shelfie"
export DB_PASSWORD="secret"
export DB_ROOT_PASSWORD="root"

# Redis
export REDIS_HOST="shelfie-redis"
export REDIS_PASSWORD="null"
export REDIS_PORT="6379"
export REDIS_DB="0"
export REDIS_CACHE_DB="1"

# Mail
export MAIL_SCHEME="null"
export MAIL_HOST="127.0.0.1"
export MAIL_PORT="2525"
export MAIL_USERNAME="null"
export MAIL_PASSWORD="null"

# AWS / MinIO
export AWS_ACCESS_KEY_ID="minioadmin"
export AWS_SECRET_ACCESS_KEY="minioadmin"
export AWS_DEFAULT_REGION="us-east-1"
export AWS_BUCKET="shelfie-local"
export AWS_ENDPOINT="http://shelfie-minio:9000"
export AWS_USE_PATH_STYLE_ENDPOINT="true"
export MINIO_ROOT_USER="minioadmin"
export MINIO_ROOT_PASSWORD="minioadmin"

# Payments
export PAYSTACK_SECRET="sk_test_REPLACE_ME"
export SQUAD_API_KEY="REPLACE_ME"
