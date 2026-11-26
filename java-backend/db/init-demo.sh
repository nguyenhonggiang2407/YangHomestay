#!/usr/bin/env bash
(
  set -euo pipefail

  psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    --set ON_ERROR_STOP=1 --set "app_password=$YANGHOMESTAY_DB_PASSWORD" <<'SQL'
CREATE ROLE anon NOLOGIN;
CREATE ROLE authenticated NOLOGIN;
CREATE ROLE service_role NOLOGIN;
CREATE ROLE yanghomestay LOGIN PASSWORD :'app_password';
ALTER SCHEMA public OWNER TO yanghomestay;
SQL

  for migration in /yanghomestay-sql/[0-9][0-9][0-9]_*.sql; do
    psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --set ON_ERROR_STOP=1 \
      --command 'SET ROLE yanghomestay' --file "$migration"
  done
)
