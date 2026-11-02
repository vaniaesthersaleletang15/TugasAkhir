#!/usr/bin/env sh

set -eu

DOMAIN="${1:?Domain deployment wajib diberikan}"
ACME_EMAIL="${2:?Email ACME wajib diberikan}"
DEPLOY_PATH="${DEPLOY_PATH:-/opt/tugasakhir}"
ENV_FILE="$DEPLOY_PATH/.env"
CREDENTIAL_FILE="/root/prima-initial-credentials.txt"

if [ "$(id -u)" -ne 0 ]; then
  echo "Skrip ini harus dijalankan sebagai root." >&2
  exit 1
fi

if [ ! -f "$DEPLOY_PATH/compose.yaml" ] || [ ! -f "$DEPLOY_PATH/compose.production.yaml" ]; then
  echo "File Docker Compose tidak ditemukan di $DEPLOY_PATH." >&2
  exit 1
fi

if [ -e "$ENV_FILE" ]; then
  echo "$ENV_FILE sudah ada; bootstrap dibatalkan agar secret tidak tertimpa." >&2
  exit 1
fi

umask 077

random_hex() {
  openssl rand -hex "$1"
}

APP_KEY="base64:$(openssl rand -base64 32)"
SIGNATURE_SECRET="$(random_hex 32)"
POSTGRES_PASSWORD="$(random_hex 24)"
ADMIN_PASSWORD="Admin-$(random_hex 12)"
MANAGER_PASSWORD="ITM-$(random_hex 12)"
REGIONAL_PASSWORD="Regional-$(random_hex 12)"

cat > "$ENV_FILE" <<EOF
PRIMA_DOMAIN=$DOMAIN
ACME_EMAIL=$ACME_EMAIL
PRIMA_REGISTRY=ghcr.io/vaniaesthersaleletang15
PRIMA_IMAGE_TAG=latest

APP_ENV=production
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_PORT=8092
APP_KEY=$APP_KEY
PRIMA_SIGNATURE_SECRET=$SIGNATURE_SECRET

POSTGRES_DB=prima
POSTGRES_USER=prima
POSTGRES_PASSWORD=$POSTGRES_PASSWORD

PRIMA_ADMIN_USERNAME=admin
PRIMA_ADMIN_PASSWORD=$ADMIN_PASSWORD
PRIMA_ADMIN_EMAIL=$ACME_EMAIL
PRIMA_MANAGER_USERNAME=manager
PRIMA_MANAGER_NAME=Integrated Terminal Manager
PRIMA_MANAGER_PASSWORD=$MANAGER_PASSWORD
PRIMA_MANAGER_EMAIL=manager@$DOMAIN
PRIMA_REGIONAL_MANAGER_USERNAME=regional_manager
PRIMA_REGIONAL_MANAGER_NAME=Regional Manager Supply & Distribution
PRIMA_REGIONAL_MANAGER_PASSWORD=$REGIONAL_PASSWORD
PRIMA_REGIONAL_MANAGER_EMAIL=regional.manager@$DOMAIN

# Key pengujian resmi Cloudflare Turnstile. Ganti sebelum produksi final.
CAPTCHA_ENABLED=true
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
TURNSTILE_EXPECTED_ACTION=login
TURNSTILE_ALLOWED_HOSTNAMES=$DOMAIN
EOF

cat > "$CREDENTIAL_FILE" <<EOF
URL=https://$DOMAIN

SPV HSSE / Administrator Sistem
username=admin
password=$ADMIN_PASSWORD

Integrated Terminal Manager
username=manager
password=$MANAGER_PASSWORD

Regional Manager
username=regional_manager
password=$REGIONAL_PASSWORD

CATATAN: CAPTCHA masih memakai key pengujian. Ganti konfigurasi Turnstile sebelum produksi final.
EOF

chmod 600 "$ENV_FILE" "$CREDENTIAL_FILE"

echo "Konfigurasi dibuat di $ENV_FILE."
echo "Kredensial awal disimpan lokal di VPS pada $CREDENTIAL_FILE."
