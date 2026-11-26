# Disposable Java portfolio demo

This container serves the Java 17 / Spring Boot customer application and starts an
isolated PostgreSQL 17 process on loopback. It is a **demonstration**, not a
production deployment. It does not connect to the PHP site's Supabase database.

Every server start creates a new database and applies SQL `001` through `013` in
order. Existing database files and incoming `YANGHOMESTAY_DB_*` connection settings
are ignored by this entrypoint. Accounts, bookings and favorites entered in the
demo are lost on restart, redeploy or Free service sleep. The customer interface
shows this limitation and asks visitors to use fictional information.

The existing seed is used only inside this fresh database. Seeded staff and
customer accounts are deactivated. Reception is forcibly disabled, even if its
environment variable is accidentally set to `true`. Visitors can register a new
fictional customer to try booking. There is no real payment integration.

## Run locally

From the repository root:

```sh
docker build -f Dockerfile.demo -t yanghomestay:demo .
docker run --rm --memory=512m -p 127.0.0.1:18080:10000 \
  -e YANGHOMESTAY_SECURE_COOKIE=false yanghomestay:demo
```

Open <http://127.0.0.1:18080/customer/>. With the container running,
`python3 java-backend/docker/smoke-demo.py` creates a fictional customer, exercises
YangMatch with future dates, compares the nightly quote with an atomic booking,
and cancels that booking. Run it once per server start: it reuses a fixed fictional
email so a second pass after restart also verifies that the old account was reset.
The script refuses non-local destinations.

## Render settings

Use a separate Free **Web Service** in the existing portfolio workspace:

| Setting | Value |
| --- | --- |
| Source | This repository and the reviewed demo release branch |
| Runtime | Docker |
| Dockerfile path | `./Dockerfile.demo` |
| Build context | Repository root (`.`) |
| Docker command | Leave empty; use the image entrypoint |
| Region | Singapore, matching the other portfolio demos |
| Health check | `/health` (also checks PostgreSQL) |
| Plan | Free |
| Secure cookie | Default `true`; do not apply the local HTTP override |
| External database or disk | None |

The process binds HTTP to `0.0.0.0:$PORT`. PostgreSQL binds only to `127.0.0.1:55432`
and uses a random application password generated at startup. Neither the database
password nor a production secret is baked into the image. Both processes run as
the image's non-root `postgres` user. `tini` handles child reaping; the startup
script forwards termination to Java, then stops PostgreSQL. If either process
exits unexpectedly, the container exits so the host can recover it.

The Java heap is capped at 224 MiB, metaspace at 96 MiB, PostgreSQL shared buffers
at 32 MiB and the JDBC pool at four connections. CI builds the image, runs the API
smoke test under a 512 MiB container limit, verifies loopback-only PostgreSQL and
disabled staff accounts, checks a graceful stop and repeats the flow after restart
to verify that the database resets. This is a small portfolio
workload; it is not a capacity or load-test result.

Render Free instances sleep when idle and have shared workspace usage limits.
The first request can wait for a cold start. See [Render Free limits](https://render.com/docs/free)
and [health checks](https://render.com/docs/health-checks). Use managed PostgreSQL,
separate least-privilege migration/runtime roles, backups and a paid runtime when
building a persistent production deployment.

HelioHost continues serving the PHP application independently. Uploading this
Dockerfile, Java source, SQL or build artifacts to its public web directory does
not deploy this Java service and must be avoided.
