# PartShelf — Project Progress

> **Updated:** 2026-10-28 (Asia/Bangkok)  
> **Project status:** `P12_DONE / DP01_LOCAL_DONE`  
> **MVP:** products, stock, storage locations and simple counting  
> **Environment:** Docker Compose, local-only

## Phase Status

| Phase | Work | Status | Evidence / Notes |
|---|---|---|---|
| P00 | Scope, 83-item register reconciliation, plan and tracking | `DONE` | Revised all 83 rows to Owner Master Prompt: 28 MVP, 5 partial, 5 foundation, 2 limits, 43 future. Scope, phase plan, Future backlog and evidence align. |
| P01 | Local application bootstrap with Docker Compose | `DONE` | All services healthy; direct and proxied API health report PostgreSQL `ok`; frontend build/lint and Laravel feature test pass. Ports: web 5175, API 8083, DB 5442, loopback only. |
| P02 | Schema and migrations | `DONE` | Fresh PostgreSQL migration verified; SKU and location path uniqueness, foreign keys, and non-negative stock/count constraints pass. Evidence in `TEST_RESULTS.md`. |
| P03 | Minimal login and permissions | `DONE` | Sanctum session login/logout, password hashing, Owner/Manager/Staff guard and secure local user provisioning verified. Full suite 20/57, frontend build/lint, P03 Pint, dependency audit and local HTTP session flow pass; independent protected review completed. Evidence in `TEST_RESULTS.md`. |
| P04 | Products, categories and basic vehicle fitment | `DONE` | Authenticated catalog API/UI, case-insensitive unique SKU/category names, two-level categories, fitments, image storage, search and audit. Full suite 31/107, frontend build/lint, migration and API health pass. |
| P05 | Storage locations | `DONE` | Authenticated CRUD/search; case-insensitive unique Zone/Shelf/Level/Bin; one location per product; audit on changes. Full suite 36/132; moving product leaves balance unchanged. |
| P06 | Opening stock, receiving and balance | `DONE` | 5 focused tests / 34 assertions; full suite 41 /166; migration Ran after empty-table inspection; Pint, lint/build and API/proxy health pass. |
| P07 | Excel product/count import/export | `DONE` | Single-sheet template; additive preview/confirm import with row validation/duplicates and one-use user-bound token; product export; count-result export; Owner/Manager UI. 6 focused tests / 37 assertions; full suite 47 /203 at P07 closure; Pint, lint/build, audit and local health pass. P09 extends count export with attempt history. |
| P08 | Simple count flow | `DONE` | Immediate/location count, locked reference snapshot and timestamp, SKU/name search and scanner input, not-counted/count-zero/count statuses, unknown SKU feedback, and no balance mutation. 5 focused tests / 36 assertions; full suite 52 /239; Pint, lint/build, audit, and health pass. |
| P09 | Variance, recount, correction and audit integrity | `DONE` | Each attempt gets a movement-ID watermark and separate variance; Owner/Manager corrections require reason, use idempotency, preserve later movements, prevent negative balances and audit the result. 4 focused tests; full suite 56 /277; migration Ran; Pint/lint/build/audit/health pass. |
| P10 | Barcode/QR, basic labels and online-only PWA | `DONE` | Stable Product-ID internal code resolves after SKU edits; USB/typed input and optional native camera path; Code128/QR labels with A4 print layout; online-only manifest/worker. Focused API 2/15, frontend lint/build, manifest/icon/network-only static checks, npm audit and local API/web health pass. P11 headless E2E covers QR/print media/fallback/install-prompt logic; physical hardware/browser eligibility remain Owner device checks. |
| P11 | Summary dashboard, integration and browser E2E | `DONE` | Dashboard API/UI, role-filtered variances, full API suite, lint/build, and headless browser flow pass. Mocked E2E covers login, count without stock change, stable code, QR/Code128 label, print media, unsupported-camera fallback and install prompt. |
| P12 | Local backup/restore and Owner acceptance | `DONE` | Local pg_dump/pg_restore scripts and usage/UAT guide implemented; isolated restore drill passed; Owner confirmed the LOCAL_GUIDE checklist passed in chat on 2026-10-28. Production remains excluded. |

## Completed

- P00 requirement reconciliation and Scope/Plan approval.
- P01 React + Vite + TypeScript + Tailwind frontend scaffold.
- P01 Laravel 13 API scaffold, `/api/health` endpoint and feature test.
- PostgreSQL 17 Docker service with readiness check.
- Local-only Compose ports and `scripts/start-local.ps1` for random local DB password creation, port checks, image build and startup.
- Verified frontend-to-API proxy and API-to-PostgreSQL query.
- P02 categories, storage locations, products, balances, movements, count records/attempts and audit log schema with PostgreSQL constraints.

## Runtime

- Web: `http://127.0.0.1:5175`
- API: `http://127.0.0.1:8083/api/health`
- PostgreSQL: `127.0.0.1:5442`
- Compose project: `partshelf-local`
- Docker bridge subnet: `10.250.250.0/24`; checked against existing Docker networks and specific host routes.
- `docker compose down` stops this stack and preserves its database volume. Do not use `down -v` for routine stopping.

## Notes and limitations

- Existing unrelated Docker stack already used `127.0.0.1:8080`; it was left untouched.
- Docker's automatic address pools were exhausted. P01 uses a separately verified subnet; no existing network was changed.
- Git was initialized on `main` with the Owner-authorized origin `https://github.com/Minikot88/PartShelf.git`; actual secrets/data/dependencies and private tooling are excluded. Exact publication revision is recorded in the completion report/checkpoint.
- P00–P12 local functionality is complete. DP01 adds local production preparation; cloud readiness remains unverified.

## Next Action

P00–P12 complete for the authorized local/test MVP. Continue routine use with `LOCAL_GUIDE.md`; production deployment remains outside the approved scope.

## Production planning — 2026-10-28

`DP01_LOCAL_DONE`: Owner approved Vercel Services/Container Beta + Neon + private R2 and local implementation/testing plus first Git commit/push. Production config/image, object transport and cleanup, tests and protected review passed locally. See `DP01_LOCAL_PREPARATION.md` and `TEST_RESULTS.md`. Automatic Git deployments remain disabled. Next action: Owner approves DP02 provisioning targets/region/budget and later DP03 staging deployment; neither is authorized by DP01. No cloud resource, secret, DNS, migration or deployment was created/changed.

## Update Log

| Date | Actor | Change | Evidence |
|---|---|---|---|
| 2026-10-28 | Codex | Completed P01 local Docker bootstrap after port and subnet preflight. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Reconciled the 83 written requirements; Owner approved the four-domain Scope/Plan. | `REQUIREMENTS_REGISTER.md`, `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Owner authorized P02; verified local ports 5175/8083/5442 have no host listeners, confirmed existing Compose ports, and inspected empty database before schema work. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Owner authorized P03; verified existing PartShelf listeners and healthy Docker Compose services before auth work. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed and independently reviewed P03; isolated test database and removed default demo account seeding. | `TEST_RESULTS.md`, `DECISIONS.md` |
| 2026-10-28 | Codex | Owner supplied Master Execution Prompt authorizing P00–P12 locally; P00 reopened to reconcile the explicitly expanded feature scope, existing P01–P03 evidence preserved. | `REQUIREMENTS_REGISTER.md`, `MVP_SCOPE.md`, `IMPLEMENTATION_PLAN.md` |
| 2026-10-28 | Codex | Reconciled the Owner Master Prompt against all 83 register rows and began P04 after closing P00 scope revision. | `TEST_RESULTS.md`, `DECISIONS.md` |
| 2026-10-28 | Codex | Completed P04 and began P05 under Owner's continuous authorization; main local catalog remains empty after verification. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P05 and began P06 under Owner's continuous authorization; moving a product preserves its balance. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P06 opening/receipt/balance and began P07 under Owner's continuous local/test authorization. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P07 Excel template/import/export and began P08 simple count flow under continuous authorization. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P08 simple counting and began P09 variance/recount/correction. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P09 movement-aware variance, recount and authorized correction; began P10. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P10 stable product codes, basic Code128/QR labels and online-only PWA; began P11. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Completed P11 role-aware dashboard, full test suite and headless browser workflow; began P12. | `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Implemented local backup/restore scripts and guide; isolated recovery drill and final suite pass. | `LOCAL_GUIDE.md`, `TEST_RESULTS.md` |
| 2026-10-28 | Owner | Confirmed P12 Owner UAT checklist passed; P12 closed. | Owner chat confirmation; `LOCAL_GUIDE.md`, `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Prepared production plan from PartShelf source, LabSphere read-only reference and current provider docs; execution awaits approval. | `PRODUCTION_DEPLOYMENT_PLAN.md`, `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Owner-approved DP01 local production image/transport/session tests and protected review passed; cloud phases remain approval-gated. | `DP01_LOCAL_PREPARATION.md`, `TEST_RESULTS.md`, `assurance/DP01_REVIEW_2.json` |
