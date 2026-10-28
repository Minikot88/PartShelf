# PartShelf

PartShelf's Owner-approved local MVP covers products, stock, storage locations, simple counting, specified Excel import/export, Barcode/QR/basic labels, online-only PWA, vehicle fitment and a summary dashboard.

## Current status

- P00: DONE — reconciled the Owner's Master Execution Prompt against all 83 requirements.
- P01: DONE — React/Vite frontend, Laravel API and PostgreSQL pass local health checks.
- P02: DONE — domain schema and integrity constraints migrated and verified in local PostgreSQL.
- P03: DONE — Sanctum session login/logout, hashed passwords and backend role checks pass local acceptance.
- P04: DONE — Products, categories and vehicle fitment pass local acceptance.
- P05: DONE — Storage locations and product assignment pass local acceptance.
- P06–P12: DONE — stock, Excel, counts/corrections, labels/PWA, dashboard and isolated backup/restore passed local acceptance; Owner UAT confirmed.
- DP01: local production preparation for the approved Vercel Services/Container Beta + Neon + private R2 architecture. See `docs/DP01_LOCAL_PREPARATION.md` and current evidence in `docs/TEST_RESULTS.md`.
- Local URLs: web <http://127.0.0.1:5175>, API <http://127.0.0.1:8083/api/health>, PostgreSQL `127.0.0.1:5442`.

Start with `.\scripts\start-local.ps1`; it creates ignored local settings, checks port use and starts the stack. See `docs/PROGRESS.md` and `docs/TEST_RESULTS.md` for evidence. Production configuration is prepared locally; automatic Git deployments are disabled and cloud deployment awaits approval.

Provision a user locally with `docker compose exec api php artisan partshelf:user:create`; the command requests a hidden, confirmed password. The default seeder creates no login. Automated tests are isolated to PostgreSQL database `partshelf_test`.
