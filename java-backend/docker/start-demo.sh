#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
export PORT="${PORT:-10000}"

if [[ "$(id -u)" == 0 ]]; then
  echo 'The disposable demo must run as a non-root user.' >&2
  exit 1
fi
if [[ ! "${PORT:-10000}" =~ ^[0-9]+$ ]] || (( PORT < 1024 || PORT > 65535 )); then
  echo 'PORT must be an unprivileged TCP port.' >&2
  exit 1
fi

# A fresh private directory is used for EVERY start, even docker restart.
# No production connection variables or externally mounted PGDATA are consumed.
DEMO_ROOT=$(mktemp -d /tmp/yanghomestay-demo.XXXXXXXX)
export PGDATA="$DEMO_ROOT/database"
export PGHOST="$DEMO_ROOT"
export PGPORT=55432
JAVA_PID=''
PG_PID=''

cleanup() {
  trap - EXIT TERM INT
  set +e
  if [[ -n "$JAVA_PID" ]] && kill -0 "$JAVA_PID" 2>/dev/null; then
    kill -TERM "$JAVA_PID"
    timeout 12 tail --pid="$JAVA_PID" -f /dev/null
    kill -KILL "$JAVA_PID" 2>/dev/null
    wait "$JAVA_PID" 2>/dev/null
  fi
  if [[ -n "$PG_PID" ]] && kill -0 "$PG_PID" 2>/dev/null; then
    pg_ctl -D "$PGDATA" -m fast -w -t 8 stop
    kill -KILL "$PG_PID" 2>/dev/null
    wait "$PG_PID" 2>/dev/null
  fi
  # Only this process's mktemp directory is disposable.
  rm -rf -- "$DEMO_ROOT"
}
trap cleanup EXIT
trap 'exit 0' TERM INT

initdb --username=postgres --auth-local=trust --auth-host=scram-sha-256 \
  --encoding=UTF8 --locale=C.UTF-8 > /dev/null
postgres -D "$PGDATA" -h 127.0.0.1 -p "$PGPORT" -k "$DEMO_ROOT" \
  -c shared_buffers=32MB -c max_connections=12 -c work_mem=1MB \
  -c maintenance_work_mem=16MB -c max_wal_size=128MB -c min_wal_size=32MB \
  -c temp_file_limit=65536 -c statement_timeout=15000 \
  -c idle_in_transaction_session_timeout=10000 &
PG_PID=$!
ready=false
for ((attempt=0; attempt<300; attempt++)); do
  if ! kill -0 "$PG_PID" 2>/dev/null; then
    echo 'Demo PostgreSQL exited during startup.' >&2
    exit 1
  fi
  if pg_isready --username=postgres --dbname=postgres > /dev/null 2>&1; then
    ready=true
    break
  fi
  sleep 0.1
done
if [[ "$ready" != true ]]; then
  echo 'Demo PostgreSQL did not become ready.' >&2
  exit 1
fi

export POSTGRES_USER=postgres
export POSTGRES_DB=yanghomestay
export YANGHOMESTAY_DB_PASSWORD
YANGHOMESTAY_DB_PASSWORD=$(od -An -N32 -tx1 /dev/urandom | tr -d ' \n')
createdb --username=postgres "$POSTGRES_DB"
# Reuse the same schema/seed order as the local PostgreSQL demo.
YANGHOMESTAY_SQL_DIRECTORY=/app/sql /app/init-demo.sh > /dev/null
psql --username=postgres --dbname="$POSTGRES_DB" --set ON_ERROR_STOP=1 \
  --command "UPDATE public.admins SET status='inactive'; UPDATE public.customers SET status='inactive';" \
  > /dev/null

export YANGHOMESTAY_DB_URL="jdbc:postgresql://127.0.0.1:$PGPORT/$POSTGRES_DB"
export YANGHOMESTAY_DB_USER=yanghomestay
export YANGHOMESTAY_BIND=0.0.0.0
export YANGHOMESTAY_SECURE_COOKIE="${YANGHOMESTAY_SECURE_COOKIE:-true}"
export YANGHOMESTAY_RECEPTION_ENABLED=false
export YANGHOMESTAY_DEMO_EPHEMERAL=true
export SPRING_DATASOURCE_HIKARI_MAXIMUM_POOL_SIZE=4
export SPRING_DATASOURCE_HIKARI_MINIMUM_IDLE=1
export SERVER_TOMCAT_THREADS_MAX=20
export SERVER_TOMCAT_THREADS_MIN_SPARE=2
export SERVER_TOMCAT_MAX_CONNECTIONS=150
export SERVER_SHUTDOWN=graceful
export SPRING_LIFECYCLE_TIMEOUT_PER_SHUTDOWN_PHASE=10s
echo 'Starting disposable YangHomestay demo. Data resets on every server start.'
java -Xms64m -Xmx224m -Xss256k -XX:MaxMetaspaceSize=96m \
  -XX:ReservedCodeCacheSize=32m -XX:+UseSerialGC -XX:ActiveProcessorCount=2 \
  -XX:+ExitOnOutOfMemoryError -jar /app/yanghomestay.jar &
JAVA_PID=$!

# Failure of either process takes the whole workload down for host recovery.
wait -n "$JAVA_PID" "$PG_PID" || true
echo 'A demo process exited; stopping the companion process.' >&2
exit 1
