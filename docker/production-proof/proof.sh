#!/usr/bin/env bash
#
# Orchestrator for the production-topology proof.
#
# Reproduces, end to end and with zero manual steps, the sequence a real
# deployment goes through — including a real outage of the server path:
#
#   down -v + up     deterministic clean volumes (no cross-run state)
#   main             readiness (runner polls the tiers itself), health, the
#                    remote workload through nginx, cross-client check,
#                    leak window, seed for the durability phase
#   DISCONNECT app ←→ sqld (server-net only; the nginx path keeps working)
#   offline-write    the journaled offline flavor under a REAL outage:
#                    writes accrue in the journal, sqld has ZERO of them
#   RECONNECT
#   offline-sync     sync(); the second, independent client reads the rows
#                    back through the server, key+value exact
#   restart app      (php-fpm dies; volume survives — like a pod restart)
#   persist          the local file DB row survives the app restart
#   restart sqld     (the server itself restarts — volume durability)
#   persist-server   the app reaches the restarted server and sees its data
#   report           merge all phase reports into one PROOF JSON
#
# The ONLY docker-side movements here are the restarts and the network
# toggle of the outage window — everything asserted lives inside the
# containers, in realistic production states.
#
# Usage:
#   docker/production-proof/proof.sh              # full proof run
#   docker/production-proof/proof.sh --teardown   # same + `down -v` afterwards
#
# Output: PROOF: {...} JSON on stdout, machine-readable artifacts in
# ./proof-report/ (gitignored). Exit code non-zero if any phase failed.

set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE="docker compose -f $DIR/compose.yaml"
TEARDOWN="${1:-}"
mkdir -p "$DIR/proof-report"

# mode $COMPOSE_RUN flags every phase run with --no-deps: a plain
# `compose run` RECONCILES the running services' network config (re-attach
# app to server-net), which would silently undo the outage window. The
# dependencies (sqld, web) are up from `up -d` above anyway.
COMPOSE_RUN="docker compose -f $DIR/compose.yaml run --rm --no-deps proof"

echo "### proof.sh: clean state (no cross-run volume leakage, no stale reports)"
rm -f "$DIR"/proof-report/report-*.json "$DIR"/proof-report/full-report.json
$COMPOSE down -v --remove-orphans >/dev/null 2>&1 || true

echo "### proof.sh: up (build if needed, start with fresh volumes)"
$COMPOSE up -d --build --quiet-pull >/dev/null

echo "### proof.sh: phase main"
$COMPOSE_RUN main

APP_ID="$($COMPOSE ps -q app)"
NET_NAME="$(docker network ls --filter name=production-proof_server-net --format '{{.Name}}' | head -1)"

echo "### proof.sh: DISCONNECT app from sqld (server-net only, web-net intact)"
docker network disconnect "$NET_NAME" "$APP_ID"
sleep 1

echo "### proof.sh: phase offline-write (genuine outage window)"
$COMPOSE_RUN offline-write

echo "### proof.sh: RECONNECT app to sqld"
docker network connect "$NET_NAME" "$APP_ID"

echo "### proof.sh: phase offline-sync"
$COMPOSE_RUN offline-sync

echo "### proof.sh: restart app (fpm restart; volume must survive)"
$COMPOSE restart app

echo "### proof.sh: phase persist"
$COMPOSE_RUN persist

echo "### proof.sh: restart sqld (server restart; its volume must survive)"
$COMPOSE restart sqld

echo "### proof.sh: phase persist-server"
$COMPOSE_RUN persist-server

echo "### proof.sh: phase report"
$COMPOSE_RUN report

if [ "$TEARDOWN" = "--teardown" ]; then
    echo "### proof.sh: teardown"
    $COMPOSE down -v --remove-orphans
fi
