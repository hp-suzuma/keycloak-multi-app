#!/usr/bin/env bash
set -euo pipefail

upsert_env() {
  local key="$1"
  local value="${2-}"
  touch .env

  if grep -q "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${value}#" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

for key in \
  APP_NAME APP_ENV APP_DEBUG APP_URL APP_LOCALE APP_FALLBACK_LOCALE APP_FAKER_LOCALE APP_KEY \
  LOG_CHANNEL LOG_LEVEL SESSION_DRIVER SESSION_LIFETIME SESSION_DOMAIN SESSION_SECURE_COOKIE SESSION_SAME_SITE \
  CACHE_STORE QUEUE_CONNECTION FILESYSTEM_DISK DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
  TRUSTED_PROXIES FORCE_HTTPS KEYCLOAK_ISSUER KEYCLOAK_INTERNAL_BASE_URL KEYCLOAK_CLIENT_ID KEYCLOAK_CLIENT_SECRET \
  BACKEND_URL GLOBAL_LOGIN_URL CORS_ALLOWED_ORIGINS APP_ROLE MANAGEMENT_API_USER MANAGEMENT_API_PASSWORD
do
  upsert_env "$key" "${!key:-}"
done

php artisan config:clear
php artisan route:clear

exec php-fpm -F
