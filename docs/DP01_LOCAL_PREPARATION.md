# DP01 — local production preparation

Owner approved Vercel Services/Container Beta + Neon + private R2 and local DP01 work on 2026-10-28, plus the first Git commit/push to `Minikot88/PartShelf`. Cloud provisioning and all deployments still require explicit approval.

## What changed

- `vercel.json` defines static Vite and a PHP container service. API/Sanctum routing precedes the SPA route and preserves prefixes. Automatic Git deployments are disabled with `git.deploymentEnabled=false` until approval.
- `backend/Dockerfile.production` builds PHP 8.4 with production-only Composer dependencies. It has no source bind mounts, migration/startup seed, generated APP_KEY or embedded runtime credentials. `PORT` controls the Apache listener.
- `backend/.env.production.example` lists blank credentials and intended shared DB/session/cache/TLS settings. Preserve APP_KEY across releases. Production requests fail closed when core security/storage/session settings are incomplete.
- R2 disk uses the S3 adapter with checksum compatibility options. Production images use authenticated same-origin redirects to short-lived signed GETs; the bucket remains private.
- Object mode uses a 15-minute upload intent bound to user/purpose/size/SHA256 and a short signed PUT. The server reads bounded bytes, verifies checksum and true image type/XLSX structure, then consumes the intent under a DB row lock. Validated image bytes go into a distinct final key so a replayed PUT cannot mutate the published image. Invalid attempts do not write products.
- XLSX import keeps row validation, preview and explicit one-use confirmation. A shared cache lock serializes confirmation and unauthorized callers cannot consume another user's token. Archive expansion/entry limits bound XLSX parsing.
- Exports return a small JSON download descriptor for an R2 object instead of returning workbook bytes through the function. Multipart/public-local mode remains the default for existing local use.
- `partshelf:files:cleanup` removes expired staging/exports and unreferenced image candidates after a 24-hour grace period. The ledger is retained for images still referenced by products, so later replacements can be cleaned. Storage failure preserves the ledger for retry. Provision a controlled cleanup schedule only after cloud approval.
- New migration `0001_01_09_000000_create_file_transfers_table.php` is exercised in the isolated test DB. Apply to a future cloud target only via an approved release job, never during build/startup.

## Run local checks

Existing local stack remains loopback 5175/8083/5442. Start it using the existing local guide; the source defaults to multipart/public storage.

```powershell
docker compose exec -T -e APP_ENV=testing api php artisan test --compact
cd frontend
npm ci
npm run lint
npm run build
```

Run the S3-compatible integration test on the project network, without publishing emulator host ports:

```powershell
docker compose -f compose.yaml -f compose.dp01.yaml up -d s3
docker compose exec -T -e APP_ENV=testing -e S3_TEST_ENDPOINT=http://s3:9000 api php artisan test --compact tests/Integration/S3TransportIntegrationTest.php
docker compose -f compose.yaml -f compose.dp01.yaml rm -sf s3
```

RustFS is a pinned local emulator image with tmpfs storage and explicit public test credentials. It is not a production service or an R2 substitute. The test refuses other endpoint hosts and uses only `partshelf_test`.

Build/smoke the production image locally with unexposed test-only `prod-api` settings:

```powershell
docker build -f backend/Dockerfile.production -t partshelf-api:dp01 backend
docker compose -f compose.yaml -f compose.dp01.yaml up -d --no-deps prod-api
docker compose exec -T api curl --fail http://prod-api:8080/api/health
docker compose -f compose.yaml -f compose.dp01.yaml rm -sf prod-api
```

The smoke container uses the isolated test DB and a clearly labelled test APP_KEY. It never calls the placeholder R2 endpoint. It verifies the image and request configuration, not cloud TLS or R2 availability.

For Playwright, check host port 5176 is free, start `npm run dev -- --host 127.0.0.1 --port 5176 --strictPort` in `frontend`, then run `npm run test:e2e` in another shell. The tests use API/storage mocks; backend real-byte tests are separate. Install Chromium into `frontend/node_modules/.cache/ms-playwright` with `PLAYWRIGHT_BROWSERS_PATH` set to that directory if needed. Stop the temporary Vite server afterward.

## Git/release safeguards

The repository is public. `.gitignore` excludes private tooling, actual environment files, uploaded data, database dumps, dependencies and generated browser artifacts. Only blank environment examples and labelled local test credentials are intended source. Before committing, run `scripts/check-staged-secrets.ps1` and `git diff --cached --check`; do not print secret values to review them. Source push is authorized; enabling Git deployment, creating cloud resources or deploying is not.

## Remaining cloud gates

DP01 local checks cannot prove Vercel schema/build acceptance, actual ingress proxy addresses/HTTPS cookies, R2 CORS/checksum compatibility, Neon PDO/pooling/concurrency or hosted backup/restore. These require explicitly approved DP02/DP03 targets, regions, budget, private credentials and live staging evidence. Exact-origin bucket CORS/lifecycle and production TLS must be configured and tested there. No cloud readiness PASS is implied by local tests.
