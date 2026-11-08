#!/bin/sh
# Entrypoint for the local-sqld fallback container (used only when the
# official image cannot be pulled; see README). Binds HTTP on all interfaces
# so the PHP app container can reach it, and keeps the database in the
# mounted volume.
set -e

DB_PATH="${SQLD_DB_PATH:-/var/lib/sqld/proof.db}"
HTTP_ADDR="${SQLD_HTTP_LISTEN_ADDR:-0.0.0.0:8080}"

echo "sqld fallback: db=$DB_PATH http=$HTTP_ADDR"
exec /usr/local/bin/sqld -d "$DB_PATH" --http-listen-addr "$HTTP_ADDR"
