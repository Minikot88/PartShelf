# PartShelf — Test Results

## Inventory integrity audit — 2026-10-29

Docker setup follow-up PASS (configuration/readback): Owner approved relocation; actual disk D:\DockerData\DockerDesktopWSL,old C data VHDX absent; after full WSL restart docker info4CPUs/6216564736 bytes RAM/json-file driver,swap1048576KiB,Kubernetes disabled. Config readback6GB/4CPU/1GB swap,GC2GB target,logs10m×3; images/containers/volumes/cache all0. No under-load rotation/eviction tests or inventory behavioral tests in this setup task. See `DOCKER_LOCAL_SETUP.md` and `evidence/DOCKER_LOCAL_SETUP.json`. This supersedes the CLI-unavailable observation; no new inventory regression PASS is claimed.

**Overall: NOT_VERIFIED.** No inventory API or database writes were issued in this audit. Existing test results below are historical evidence; they were not re-run here.

| Check | Result | Evidence / limitation |
|---|---|---|
| Source/schema review | PASS — static only | `StockController`, `StockCountController`, core migrations, correction/idempotency migrations, and four related Feature test files inspected. Writes are grouped in transactions; product/balance/count rows use locks; PostgreSQL stores `decimal(14,3)` and checks inventory/count quantities non-negative. Static inspection does not establish runtime behavior. |
| Existing normal/error test coverage | PASS — historical only | Prior reports record coverage for opening, receipt, same-key replay and payload conflict, count without stock mutation, zero count, movement-aware variance/recount, Owner correction/replay, negative correction, and PostgreSQL negative-balance constraint. See `INVENTORY_INTEGRITY_AUDIT.md` for exact test names. |
| Concurrent sessions and duplicate races | NOT_VERIFIED | No fresh multi-connection PostgreSQL run. |
| Transaction rollback / injected failure | NOT_VERIFIED | No fresh fault-injection test proving balance, movement and audit rollback atomically. |
| Owner/Manager correction and decimal boundaries/unit conversion | PASS — historical role tests; boundaries NOT_VERIFIED | Existing tests include Owner correction/replay and Manager correction success, plus Staff denial. No decimal-boundary matrix was found. No unit-conversion facility was found in the source inspected. |
| Isolated Test DB execution | BLOCKED | `backend/phpunit.xml` pins tests to `partshelf_test`, but `docker`/`php` are unavailable in this shell. Loopback ports `5442`, `5443`, `8083`, `8084`, `5175`, `5177` were already accepting connections, so no tests were sent to Local/Demo. |
| Data preservation | PASS — scope | No API writes, database commands, reset, Local/Demo/Staging mutation, Production action, or cloud action was performed. |

No application source fix was made because static review did not establish a concrete defect. Do not treat historical suite results as a fresh PASS for this audit. Next: run focused inventory and full regression only after restoring access to the isolated PostgreSQL Test DB and confirming `current_database() = 'partshelf_test'`; add and run the missing concurrency, rollback and decimal-boundary tests. Full item-by-item results: `INVENTORY_INTEGRITY_AUDIT.md`.

**Historical baseline run date:** 2026-10-28 (Asia/Bangkok)  
**Status:** P00–P12 acceptance verified; Owner confirmed the P12 UAT checklist passed on 2026-10-28.  
**Runtime:** Docker Desktop 29.8.1; Compose 5.5.1; Node 24.18.0 / npm 11.16.0; Laravel 13.11.0 skeleton with framework 13.39.0; PHP 8.4 Apache; PostgreSQL 17 Alpine.

## P00 — requirements and scope

### Owner Master Execution Prompt revision (2026-10-28)

| Check | Result | Evidence |
|---|---|---|
| Owner authorization through P12 | PASS | Attached `Pasted text.txt` explicitly authorizes continuous local/test development through P12; no production deployment. |
| Existing completed phase evidence | PASS | P01–P03 remain verified; no completed phase is being repeated. P00 is reopened only to reconcile the updated feature boundary. |
| Revised 83-row register mapping | PASS | PowerShell parsed 83 rows, 83 unique IDs, min 1/max 83. Classification counts: MVP 28, MVP-PARTIAL 5, FOUNDATION 5, SCOPE-LIMIT 2, FUTURE 43 (total 83). |

| Check | Result | Evidence |
|---|---|---|
| All written requirements covered | PASS | 83 register rows; 83 unique IDs covering 1–83 exactly once; counts 28 MVP, 5 MVP-PARTIAL, 5 FOUNDATION, 2 SCOPE-LIMIT, 43 FUTURE. |
| Scope/plan alignment | PASS | Scope, plan and Future Backlog now align with explicit Excel, Barcode/QR/basic labels, online-only PWA, fitment and summary dashboard features; full reports, offline mode, bulk editing and custom label design remain deferred. |
| Owner approval | PASS | Attached Master Execution Prompt explicitly authorizes local/test development through P12; `DECISIONS.md` records the scope revision and production exclusion. |
| Source fidelity to original conversation | NOT_TESTED | The written register was reconciled; its transcription against the original conversation cannot be independently verified. |

## P01 — local Docker bootstrap

| Acceptance check | Result | Evidence |
|---|---|---|
| Port preflight before binding | PASS | `Get-NetTCPConnection` showed 5175, 8083 and 5442 free; Docker published-port scan showed the unrelated stack on 8080 only. Final mappings are bound to `127.0.0.1`. |
| Docker Compose configuration and build | PASS | `scripts/start-local.ps1` ran `docker compose config --quiet` and `docker compose up -d --build`; frontend and API images built successfully. |
| PostgreSQL available | PASS | `docker compose exec -T db pg_isready -U partshelf -d partshelf` returned `/var/run/postgresql:5432 - accepting connections`. |
| API health and DB connectivity | PASS | `GET http://127.0.0.1:8083/api/health` returned `{"status":"ok","database":"ok"}`. |
| Frontend serves locally | PASS | `GET http://127.0.0.1:5175` returned the PartShelf HTML. |
| Frontend reaches API | PASS | `GET http://127.0.0.1:5175/api/health` (Vite proxy) returned `{"status":"ok","database":"ok"}`. |
| Container health and actual ports | PASS | `docker compose ps`: api, db and frontend all `Up (healthy)`; mappings 8083→80, 5442→5432, 5175→5173 on loopback. |
| Laravel feature test | PASS | Test-first: endpoint test initially failed with 404 before route implementation; after implementation `docker compose exec -T api php artisan test --filter=HealthTest` passed 1 test / 3 assertions. |
| Frontend build and lint | PASS | `npm run build` completed with Vite 8.3.5; `npm run lint` exited 0. |
| Dependency audit | PASS | Tailwind install reported 0 vulnerabilities across 46 audited packages. |
| Local secret handling | PASS with Git limit | Random DB password generated only in root `.env`; Laravel app key remains in `backend/.env`. Both paths are ignored and backend `.env` is excluded from the image build context. No secret-pattern matches in authored source. This workspace has no `.git`, so no Git index/commit scan was possible; no repository was initialized. |
| Startup helper syntax | PASS | PowerShell parser reported no errors for `scripts/start-local.ps1`; script itself successfully generated `.env`, checked ports and started Compose. |
| Network collision handling | PASS after correction | Initial Compose attempt failed because Docker's automatic address pools were exhausted. Existing Docker subnets and host routes were inspected read-only; `10.250.250.0/24` had no overlap. Compose now creates only `partshelf-local_default` on that subnet; unrelated networks were not modified. |
| Production deployment | NOT_RUN | Local-only authorization; no external deployment attempted. |

**PASS — P00 revised scope reconciliation met.** Owner's latest Master Prompt was reconciled against all 83 written rows; the exact row coverage and class counts were verified. Source fidelity to the original conversation remains `NOT_TESTED`.

## P02 — schema and migrations

| Check | Result | Evidence |
|---|---|---|
| Owner approval and scope | PASS | Owner authorized P02 on 2026-10-28; phase scope is database schema/migrations from `IMPLEMENTATION_PLAN.md`. |
| Port preflight | PASS | `Get-NetTCPConnection` showed no host listeners on 5175, 8083 or 5442 before verification; current Compose stack retains the previously selected loopback mappings. |
| Baseline database inspection | PASS | Before schema work, `php artisan migrate:status` reported no migrations table and PostgreSQL listed no public tables. Existing local database contained no application data. |
| Baseline tests | PASS | `docker compose exec -T api php artisan test --compact`: 3 tests, 5 assertions passed. |
| TDD red phase | PASS | `PartshelfSchemaTest` failed as expected before the migration because `categories` and dependent tables did not exist. |
| Fresh PostgreSQL migration | PASS | `RefreshDatabase` ran a fresh migration; `php artisan migrate:status` shows the three Laravel base migrations and `0001_01_03_000000_create_partshelf_domain_tables` all `Ran`. |
| P02 domain schema | PASS | PostgreSQL tables exist for categories, storage locations, products, inventory balances, stock movements, stock counts/items/attempts and audit logs; existing Laravel users table is reused. |
| SKU and location path uniqueness | PASS | `PartshelfSchemaTest`: duplicate product SKU and duplicate Zone/Shelf/Level/Bin path each rejected by PostgreSQL. Catalog confirms `products_sku_unique` and `storage_locations_path_unique`. |
| Foreign keys | PASS | `PartshelfSchemaTest` confirms unknown product reference is rejected; PostgreSQL catalog lists FKs for products, balances, movements, count records/attempts and audit users. |
| Non-negative stock/count constraints | PASS | `PartshelfSchemaTest` confirms negative balance rejection; PostgreSQL catalog confirms checks on balances, minimum stock, count reference and counted quantities; zero remains valid for physical counts. |
| Full Laravel suite | PASS | `docker compose exec -T api php artisan test --compact`: 8 tests, 18 assertions passed. |
| Live health and containers | PASS | API returns `{"status":"ok","database":"ok"}`; api/db/frontend all `Up (healthy)` on existing loopback mappings. |

## P01 result

**PASS — P01 acceptance met.** React/Vite/TypeScript/Tailwind, Laravel API and PostgreSQL start locally with Docker Compose; API and frontend proxy pass health checks; ports do not collide; no production deployment occurred.

**PASS — P02 acceptance met.** Fresh PostgreSQL migrations create the approved domain schema; uniqueness, foreign keys and quantity checks pass the feature tests and PostgreSQL catalog inspection. P03 was completed and P00 scope has since been revised under the latest Owner prompt.

## P03 — minimal login and permissions

| Check | Result | Evidence |
|---|---|---|
| Owner approval and scope | PASS | Owner authorized P03 on 2026-10-28; scope is minimal login/logout, password hashing and backend role checks from `IMPLEMENTATION_PLAN.md`. |
| Port and local stack preflight | PASS | Existing PartShelf listeners are on loopback 5175, 8083 and 5442; `docker compose ps` shows api/db/frontend healthy. No new service or port binding is planned. |
| Authentication architecture | PASS | First-party SPA uses Sanctum session cookies and CSRF protection; no external identity provider, registration or bearer-token flow. |
| Test-first baseline | PASS | Focused auth/authorization tests failed before implementation because role, routes and session behavior were absent; implementation made the suite pass. |
| Test database isolation | PASS | PHPUnit tests force pgsql/`partshelf_test`; `Tests\\TestCase::createApplication()` resolves and fails closed unless the actual Laravel connection is pgsql/`partshelf_test`, before `RefreshDatabase`. Ordinary `APP_ENV=testing php artisan test --compact` passed; PostgreSQL confirmed `partshelf` retained its 18 public tables while test DB separately held 18 tables. |
| Password hashing and role integrity | PASS | 20-test full suite verifies hashed password, rejected invalid database role, explicit CLI enum role assignment and role is excluded from mass assignment. PostgreSQL role constraint limits values to owner/manager/staff. |
| Login/logout and session behavior | PASS | Feature tests verify case-normalized login, safe user response, persistent authenticated session, generic invalid-credential response, bcrypt 72-byte guard, logout invalidation and anonymous 401. Live same-origin HTTP probe through Vite completed login/session/logout (204), then `/api/user` returned 401. |
| Backend authorization | PASS | Owner and Manager reach the correction authorization stub (501 because P09 mutation is not implemented); Staff receives 403. Login is limited to five attempts per minute per IP. |
| Secure user provisioning | PASS | `partshelf:user:create` prompts for hidden confirmed password, validates 12+ characters/mixed case/numbers and 72-byte limit, hashes password, sets role explicitly and writes audit data without password. Default `DatabaseSeeder` no longer creates a demo login; regression test confirms zero users after default seeding. |
| Frontend build and lint | PASS | `npm run build` (TypeScript + Vite 8.3.5) and `npm run lint` (oxlint) both exit 0. |
| PHP format and dependency audit | PASS | Pint targeted at all 12 changed P03 PHP files: PASS; `composer audit --locked`: no security vulnerability advisories. |
| API health and service state | PASS | Direct API and Vite-proxied `/api/health` both return status/database `ok`; api/db/frontend are healthy, loopback mappings remain 8083/5442/5175. |
| Independent protected review | PASS | Vera read-only review identified and led to removal of seeded default account, test DB isolation and fail-closed resolved connection guard; final recheck found no other material P03 finding. |
| Scope boundary | PASS | Correction endpoint only enforces role guard and returns 501 for Owner/Manager until P09; no correction business logic, registration, external identity or out-of-scope feature was added. |

**PASS — P03 acceptance met.** Minimal login/logout, session security, password hashing, backend role checks and secure local user provisioning are implemented and verified.

## P04 — products, categories and fitment

| Check | Result | Evidence |
|---|---|---|
| Owner authorization | PASS | Attached Master Execution Prompt authorizes continuous P00–P12 local/test development. |
| P04 scope | PASS | Products, two-level categories and basic make/model/year/engine fitment only; optional product image. Excel, location management and scanner/label mapping remain in later phases. |
| Port preflight | PASS | Existing PartShelf stack is healthy on loopback 5175/8083/5442; no new port binding planned. Workspace has no Git repository, confirmed by `git status`; no pre-existing files were overwritten. |
| TDD red baseline | PASS | `ProductCatalogTest`: 6 expected failures / 6 assertions before routes and controllers were implemented (404 for missing endpoints). |
| Product/category API, permissions and validation | PASS | `ProductCatalogTest`: 11 passed / 50 assertions; auth required, Owner/Manager write guard, Staff read-only, case-insensitive duplicate SKU and category rejected, category depth capped at 2, validation rejects bad year/negative minimum. |
| Product/fitment CRUD and search | PASS | Test creates and updates product without changing ID, syncs multiple vehicle fitments, and searches SKU/name/part number/make/model. Product and category edits write audit logs. |
| Image storage | PASS | Test uploads a valid PNG to fake public disk and verifies file exists; invalid SVG rejected. Local `storage:link` created for actual local image serving. Browser-level image rendering remains for P11 E2E. |
| Migration and local data safety | PASS | Before migration, products/categories were 0 and no fitment table existed. Additive migration `0001_01_05_000000_add_product_catalog_details` is `Ran`; case-insensitive unique indexes and `product_fitments` uniqueness exist; main products/categories/fitments remain 0. |
| Full backend suite | PASS | `APP_ENV=testing php artisan test --compact`: 31 tests / 107 assertions. Tests use fail-closed isolated `partshelf_test`. |
| PHP format | PASS | `vendor/bin/pint --test` over 9 changed P04 PHP files. |
| Frontend checks | PASS | `npm run build` passed TypeScript/Vite 8.3.5; `npm run lint` (oxlint) exited 0. |
| Dependency audit | PASS | `composer audit --locked`: no security advisories. |
| Local runtime | PASS | Direct/proxied health both status/database `ok`; web returned HTTP 200; all Compose services healthy on loopback ports 5175/8083/5442. |

**PASS — P04 acceptance met.** Product/category management, vehicle fitment and optional single image are usable through authenticated API/UI with role checks; browser E2E remains scheduled for P11. No demo rows were seeded into the local application database.

## P05 — storage locations

| Check | Result | Evidence |
|---|---|---|
| Owner authorization and scope | PASS | Attached Master Execution Prompt authorizes continuous P00–P12 local/test work. |
| Port preflight | PASS | Existing healthy PartShelf services use loopback 5175/8083/5442; no new binding planned. |
| TDD red baseline | PASS | `StorageLocationTest`: 5 expected failures / 5 assertions while `/api/locations` and assignment route were absent. |
| Location CRUD/search/uniqueness | PASS | `StorageLocationTest`: 5 passed / 25 assertions; Owner/Manager create/update; path search returns `Zone-Shelf-Level-Bin`; lower/upper case duplicate is rejected. |
| Product assignment and balance integrity | PASS | Test moves product between locations, verifies audit row and confirms inventory balance remains 12.500; GET location products returns assigned item. |
| Role enforcement | PASS | Staff can read location catalog but receives 403 for create/update. |
| Migration and local data | PASS | Main `products` and `storage_locations` each had 0 rows and no case-insensitive duplicate paths before migration; `0001_01_06_000000_add_case_insensitive_location_path_index` is `Ran`. No demo data inserted. |
| Full backend suite | PASS | `APP_ENV=testing php artisan test --compact`: 36 tests / 132 assertions. |
| PHP format and frontend | PASS | Pint targeted at 8 changed PHP files; `npm run build` and `npm run lint` pass. |
| Local runtime | PASS | Direct/proxy health return status/database `ok`; web HTTP 200; all Docker services healthy on loopback 5175/8083/5442. |

**PASS — P05 acceptance met.** Location CRUD/search, product assignment and audit are verified; balance is unchanged by location movement.

## P06 — opening stock, receiving and balance

| Check | Result | Evidence |
|---|---|---|
| Test-first baseline | PASS | Before implementation the five P06 tests failed because endpoints were absent (404); after implementation all 5 focused tests passed / 34 assertions. |
| Authorization and validation | PASS | Feature tests verify unauthenticated access is denied, Staff cannot write, receipt quantities must be positive, and zero opening is accepted. |
| Ledger, balance and statuses | PASS | Opening 20 + receipt 5 yields 25; movement history contains both rows; zero opening is recorded once and balance status is `out`; balances expose `out`/`low`/`ok`. |
| Idempotency and transaction | PASS | Same UUID and payload replays without duplicate movement; same UUID with a different payload returns 409. Product row lock, balance row lock, ledger and audit write share one database transaction. |
| Local migration preflight | PASS | Before migration P06 was pending; local `stock_movements=0`, `inventory_balances=0`, `zero_movements=0`. Additive migration applied; migration status reports P06 `[4] Ran`. |
| Candidate suite | PASS | `APP_ENV=testing php artisan test --compact`: 41 passed / 166 assertions. Test DB is isolated as `partshelf_test`. |
| Style, frontend and dependencies | PASS | Pint on changed PHP controller/routes passes; `npm run lint` clean; `npm run build` passes; `composer audit` reports no advisories. Full Pint repository scan reports 4 pre-existing issues in unrelated test files, not changed by this phase. |
| Local runtime | PASS | API and Vite proxy `/api/health` both returned `{"status":"ok","database":"ok"}`; web returned HTTP 200; Compose services healthy on existing loopback ports 5175/8083/5442. No new port bound. |
| UI scope | PASS | Stock balance and movement panels with opening/receipt forms are implemented and TypeScript/lint/build pass; authenticated browser workflow verification remains scheduled in P11. |

**PASS — P06 acceptance met.** Owner-authorized local stock operations are implemented and verified. Browser end-to-end evidence remains scheduled for P11.

## P07 — Excel product/count import and export

| Check | Result | Evidence |
|---|---|---|
| Dependency selection | PASS | PhpSpreadsheet was not installable because the local PHP image has no GD extension. OpenSpout v5.13.0 was installed instead; required ZIP/XML reader/writer extensions are present. Docker image and ports were unchanged. |
| Test-first baseline | PASS | Initial focused `ExcelProductWorkflowTest` run failed because routes/features were missing; final focused tests passed 6 / 37 assertions. |
| Workbook template | PASS | Download is a valid XLSX workbook with exactly one worksheet and the six template headers; verified by reopening the generated file with OpenSpout. |
| Import preview/validation | PASS | Preview reports existing SKU, within-file duplicate and malformed row errors; confirms preview does not write product rows. Only validated rows are eligible to import. |
| Import confirmation and permissions | PASS | Owner/Manager can confirm once with a user-bound 15-minute token; import creates product/category and audit row transactionally; replayed/expired token is rejected. Staff cannot import or export. |
| Product export | PASS | XLSX download reopens and contains exported SKU data. |
| Count-results export | PASS | XLSX includes separate rows per attempt with reference/variance and later correction fields; initial fixture with attempts 8 then 9 keeps both rows, values 8.000/-2.000 and 9.000/-1.000. P09 adds watermarks to variance computation. |
| Candidate suite | PASS | Full isolated Laravel suite: 47 passed / 203 assertions. P07 changed-file Pint passes; frontend lint/build pass; composer audit found no advisories. |
| Local runtime | PASS | API and Vite proxy health both `ok`; web HTTP 200; existing loopback services healthy on 5175/8083/5442. No port was added or rebound. |
| Browser workflow | NOT_RUN | Authenticated interactive browser acceptance remains scheduled for P11. |

**PASS — P07 acceptance met.** Workbook template, import preview/confirmation, product export and count-results export are implemented and verified. Authenticated browser workflow is included in P11 integration acceptance.

## P08 — simple count flow

| Check | Result | Evidence |
|---|---|---|
| Test-first baseline | PASS | Initial 5 focused tests failed because count routes were missing; after implementation 5 passed / 36 assertions. |
| Start count and scope | PASS | Staff can create an immediate count over all products or filter to one storage location; snapshot references and count items are created transactionally. Empty products are not silently counted as zero. |
| Snapshot and statuses | PASS | Every item preserves reference quantity/time. Detail distinguishes `not_counted`, `counted_zero`, and `counted`; missing SKU returns `not_found`. |
| Save and permissions | PASS | Authenticated roles can record actual quantities including zero; negative and duplicate initial entries are rejected; attempt and audit row are recorded. |
| Balance integrity | PASS | Feature test verifies counting zero against reference 4 leaves inventory balance at 4. |
| Candidate suite | PASS | Full isolated suite: 52 passed / 239 assertions. Changed-file Pint passes; frontend lint/build pass; composer audit has no advisories. |
| Local runtime | PASS | API/proxy health `ok`; web HTTP 200; existing local ports unchanged. |
| Browser integration | NOT_RUN | Interactive authenticated workflow remains scheduled in P11. |

**PASS — P08 acceptance met.** Count sessions and initial count entries work without changing stock. Recount and variance handling are assigned to P09.

## P09 — variance, recount, correction and audit integrity

| Check | Result | Evidence |
|---|---|---|
| Test-first baseline | PASS | Initial 3 focused P09 tests failed because recount/correction routes were absent; final correction suite passes 4 tests. |
| Movement-consistent variance | PASS | Timestamp precision caused same-second movements to be omitted in the first implementation. P09 now records product movement-ID watermarks under the product lock at count start and every attempt. Test confirms movement during count is included, while movement after an attempt remains outside its variance. |
| Recount history | PASS | Recounts create attempt 2+ rows with individual counted quantity, watermark, reference-at-attempt and variance; history does not overwrite or sum attempts. Count-results XLSX exports every attempt separately. |
| Correction safety | PASS | Owner/Manager correction applies the latest attempt variance to current balance, preserving movements after count. Requires a reason, creates a correction movement when delta is non-zero, records an audit/correction row, and replays same idempotency key safely. |
| Roles and data integrity | PASS | Staff correction denied; negative resulting balance returns 409 with no mutation; a counted product without a prior balance can be initialized; zero-delta correction is retained without an invalid zero stock movement. |
| Migration | PASS | Before migration local products/counts/items/attempts were all 0. Additive migration `0001_01_08_000000_create_stock_count_corrections_table` applied as `[5] Ran`; post-check correction/count tables remain empty. |
| Candidate suite | PASS | Full isolated Laravel suite: 56 passed / 277 assertions. Changed PHP Pint passes; frontend lint/build pass; composer audit reports no advisories. |
| Local runtime | PASS | API/proxy health `ok`; web HTTP 200; ports remain loopback 5175/8083/5442. |
| Browser workflow | NOT_RUN | Authenticated interactive review remains scheduled in P11. |

**PASS — P09 acceptance met.** Movement-aware variance, separate recounts and audited Owner/Manager corrections are verified. No production action occurred.

## P10 — Barcode/QR, basic labels and online-only PWA

| Check | Result | Evidence |
|---|---|---|
| Stable internal product code | PASS | `ProductInternalCodeTest`: 2 tests / 15 assertions; code resolves to Product ID after SKU edit and count lookup accepts the internal code. |
| Label implementation | PASS | Product UI renders Code128 and QR from the stable internal code and selected products into a basic A4 print sheet; USB wedge and typed code resolve through the same endpoint. |
| Camera fallback | PASS (implementation) | Native `BarcodeDetector` handles QR/Code128 when available; unsupported/denied cases explain USB/typed fallback. Browser hardware acceptance is scheduled in P11. |
| PWA manifest and icons | PASS | JSON parsed; install fields `start_url=/`, `scope=/`, `display=standalone` verified; PNG headers/dimensions are 192x192 and 512x512. |
| Online-only worker | PASS | Static check confirms fetch uses network with `cache: no-store`; no Cache Storage writes or offline fallback found. |
| Frontend quality | PASS | `npm run lint` (no warnings) and `npm run build` pass. `npm audit --omit=dev` reports 0 vulnerabilities. |
| Local runtime | PASS | Frontend HTTP 200 and direct API health reports database `ok`; no new port/service was started. |
| Candidate backend full suite / Docker | PASS (verified in P11 run) | P11 full candidate suite includes all P10 tests: 59 passed / 306 assertions with explicit `APP_ENV=testing`. |
| Browser install/print/camera | NOT_RUN | Device/browser interaction is part of P11 integration E2E; static build does not prove browser prompt or camera hardware behavior. |

**PASS — P10 implementation and focused acceptance met.** Browser install/camera/print behavior was further exercised in the P11 headless browser suite; physical camera hardware and actual printer output remain environment-specific limitations.

## P11 — role-aware summary dashboard and integration

| Check | Result | Evidence |
|---|---|---|
| Test-first endpoint | PASS | New `DashboardSummaryTest` first failed because `/api/dashboard` returned 404; after implementation it passes 1 test / 14 assertions. |
| Summary metrics | PASS | Test covers product count, summed on-hand quantity (including zero), low-stock count and out-of-stock count. |
| Recent counts and variance | PASS | Recent count item count is returned; variance uses the same reference/attempt movement watermarks and produces the expected `-2.000` in fixture. |
| Role filtering | PASS | Owner/Manager response includes recent variance details; Staff receives summary/recent counts but `can_view_variances=false` and an empty variance list. Unauthenticated access is denied. |
| Full candidate backend suite | PASS | `docker compose exec -T -e APP_ENV=testing api php artisan test --compact`: 59 passed / 306 assertions. Explicit test environment avoids local CSRF middleware configuration. |
| PHP style and dependencies | PASS | Pint passes on changed DashboardController, API routes and test; Composer audit reports no advisories. Whole-repository Pint still reports 2 pre-existing unrelated test-file style issues. |
| Frontend quality | PASS | `npm run lint` has no warnings and `npm run build` succeeds after DashboardPanel integration. |
| Existing runtime | PASS | Docker Compose services healthy; frontend HTTP 200 and API health/database `ok`; bindings remain loopback 5175/8083/5442. |
| Headless browser E2E | PASS | Playwright/Chromium headless, one scenario: login, role dashboard, count save while preserving stock, stable internal code lookup, unsupported-camera fallback, generated QR canvas/Code128 SVG, print-media label visibility, and install prompt response. All APIs were mocked; no live catalog data changed. |
| Test server/ports | PASS | Checked 5176 free before starting temporary host-local Vite with strict port; server stopped after E2E and listener released. Existing Compose 5175/8083/5442 unchanged. |

**PASS — P11 acceptance met.** Dashboard API/UI, role filtering, backend suite and headless browser workflow passed. Camera hardware, actual browser installation eligibility and physical printer output are not emulated by this E2E.

## P12 — local backup/restore and Owner UAT

| Check | Result | Evidence |
|---|---|---|
| Backup/restore scripts | PASS | `scripts/backup-local-db.ps1` creates PostgreSQL custom-format archives for allowlisted local DBs; `scripts/restore-local-db.ps1` validates archive and matching source DB, targets only `partshelf` or `partshelf_test`, and uses high-impact PowerShell confirmation before replacement. |
| Isolated recovery drill | PASS | Inserted one temporary marker into `partshelf_test`, backed it up, changed the marker, restored the backup, and verified original marker value. Deleted marker and temporary archive afterward. |
| Data isolation | PASS | Read-only post-check: main `partshelf` has 0 products; `partshelf_test` has 0 P12 marker rows. No data was added to the shop database. |
| Script syntax | PASS | Both PowerShell scripts parsed without syntax errors. |
| Restore target mismatch guard | NOT_RUN | Automatic review rejected the proposed negative-path invocation that selected the live `partshelf` target with confirmation disabled because an incorrect guard could drop/recreate the shop database. No command ran and no database was modified; the allowlist/source-name guard was inspected, and only the safe `partshelf_test` restore was exercised. |
| Local usage and UAT guide | PASS | `docs/LOCAL_GUIDE.md` records local startup, core workflow, backup/restore commands, recovery warning and Owner acceptance checklist. |
| Environment | PASS | Compose healthy; ports remain loopback 5175/8083/5442; temporary E2E port 5176 released. |
| Owner UAT | PASS — Owner confirmed | Owner stated in chat on 2026-10-28 that the `LOCAL_GUIDE.md` checklist was reviewed and passed. This records the Owner's confirmation; it does not add per-item measurements beyond that attestation. |

**PASS — P12 acceptance met.** Backup/restore implementation and isolated recovery drill passed, and Owner confirmed the UAT checklist passed. P00–P12 are complete for the authorized local/test scope. No production operation occurred.

## Production planning review — 2026-10-28

| Check | Result | Evidence |
|---|---|---|
| Scope/authority | PASS | Owner requested planning and LabSphere folder/chat inspection; no deploy until approval. Only PartShelf documentation/checkpoints changed. |
| Source gap inspection | PASS | Read PartShelf manifests, Dockerfiles, Vite routes, Laravel session/cache/filesystem and image/XLSX paths. Found hard-coded local public disk, missing explicit S3 adapter, dev-only Vite proxy, 5,120 KiB upload limits and no Git/deploy config. |
| LabSphere reference | PASS — inspected historical evidence | Read its Vercel config, Fastify/API manifest, R2 storage source, Demo deployment report/runbook and chat `พัฒนา Laboratory Management Platform`. Evidence is Preview/Neon/private-R2, not Laravel production proof; no private credential files read or copied. |
| Current provider docs | PASS — retrieved | Vercel Services/OCI containers currently Beta; Functions payload limit and container applicability checked; Laravel S3/Sanctum, R2 private/presigned/CORS and Neon pooled/direct guidance referenced in plan. |
| Plan content | PASS — documentation review | `PRODUCTION_DEPLOYMENT_PLAN.md` includes architecture/fallback, code/config gaps, isolation, approval gates, meaningful acceptance, costs/open decisions, backup/rollback and source links. |
| Production candidate tests | NOT_RUN | No runtime code/config changed; TDD not applicable for this documentation-only request. Existing local test results are historical and not claimed as production readiness. |
| Cloud readiness/deployment | NOT_RUN / NOT_AUTHORIZED | No account capability audit, resource creation, billing, credential change, cloud migration, Preview/Staging/Production deployment or DNS change. |

**Planning document ready for Owner review. Production execution remains unapproved.**

## DP01 — approved local production preparation, 2026-10-28

| Check | Result | Actual evidence |
|---|---|---|
| Authority | PASS | Owner approved Services/Container Beta + Neon + private R2, local DP01 tests and Git first commit/main/push. Cloud resource creation/deployment remains unapproved. |
| Test-first | PASS | Initial object transport 4 tests failed on missing endpoints/binary export. Final object/config focused suite 7 passed / 48 assertions. |
| Full Laravel regression | PASS | Isolated `partshelf_test`: 66 passed / 354 assertions with `APP_ENV=testing`. New file-transfer migration applied only through isolated test migrations in this phase. |
| Actual local S3 transport | PASS — emulator only | RustFS pinned digest in `compose.dp01.yaml`, no host ports, tmpfs. Explicit integration test 1 passed / 18 assertions: 5 MiB signed PUT, private signed GET/unsigned403, immutable promoted image after source overwrite, workbook preview/confirm/replay, XLSX export download and cleanup retaining referenced image. Not actual R2 compatibility proof. |
| Production image | PASS — local | Final image `sha256:03548e002fd616f46774e92232f937cb4f57cb0a37974f58e693559bc0bf98b4`: built with pinned PHP/Composer bases and no-dev dependencies; internal PORT8080 health DB ok, CSRF endpoint emits Secure/HttpOnly session flags, missing API stays404, no `.env`/tests/PHPUnit in image, writable framework/bootstrap dirs verified as www-data. No host port published. |
| Frontend | PASS | Lint/build clean, npm audit 0 vulnerabilities. Playwright 2 scenarios passed: original workflow and direct-storage byte/checksum/token API flow plus signed export descriptor download. Browser tests mock API/storage; real-byte backend integration is separate. Temporary 5176 checked before binding and released. |
| Review regression | PASS | Reviewer attempt1 found old-image deletion before outer transaction commit. Regression reproduced missing `old.png`; R2 deletion deferred to retained ledger/grace cleanup; injected rollback now preserves old product and image. Attempt2 accepted frozen candidate. See `assurance/DP01_REVIEW*.json`. |
| PHP dependencies/style | PASS | Composer audit no advisories; Pint check passes on 16 changed PHP files. |
| Deployment config | PASS — local assertions | API/Sanctum routing before SPA and correct service roots/runtime checked; `git.deploymentEnabled=false`. Connected Vercel project lookup filtered to this repo returned no projects in current scope. No project created or config written remotely. Hosted schema/ingress still untested. |
| Publication hygiene | PASS — pre-commit index | 160 staged paths inspected by `scripts/check-staged-secrets.ps1`: private/generated paths, credential patterns and actual local environment secret values excluded without printing values. Origin metadata confirms the Owner's repo is empty/public. Exact commit/push result is recorded after execution in completion report/local checkpoint. |
| Cloud readiness | NOT_RUN | Real R2 bucket/CORS/lifecycle, Neon TLS/pooling/concurrency, Vercel deployment/HTTPS browser sessions and cloud recovery remain DP02–DP03 gates. |

**PASS — DP01 local preparation and review met acceptance.** This does not claim cloud readiness or authorize cloud provisioning/deployment.

## DP02 — staging resource provisioning, 2026-10-28

| Check | Result | Actual evidence |
|---|---|---|
| Authority / isolation | PASS | Owner approved PartShelf staging Vercel/Neon/private R2 resource creation, explicitly no deploy and pause at usage<=50% remaining. All create/env/token requests targeted new PartShelf names/IDs. No LabSphere resource, credential or source was used for writes. |
| Vercel resource | PASS | Created `partshelf-staging` /`prj_9pZ790dvjdRIcZrBe3BxqkXTdjXu` under `team_oLV66sa4oVrdF5ux9pds0UbP`. Independent GET: name/account match, no Git link, `live=false`, protection enabled. Connector omitted region/plan; Brave later confirmed Hobby and saved Function Region Asia Pacific/sin1 with disabled Save. Deployed region/compatibility NOT_RUN. |
| No deployment | PASS | `list_deployments` with exact PartShelf project+team returned `deployments=[]`, pagination count0, both before and after env writes. No deployment command, migration, seed, DNS, alias or traffic change. |
| Private R2 resource | PASS | New `partshelf-staging-files`, creation timestamp2026-10-28T04:26:50.577Z, APAC/Standard/default jurisdiction. Independent GET confirms managed r2.dev disabled, custom domains empty and object list empty. |
| R2 token least privilege | PASS — policy readback | `partshelf-staging-r2-objects` active; expires2027-01-26T00:00:00Z. GET token policy confirms only bucket-item-write permission scoped to the exact PartShelf staging bucket. No bucket admin/account-wide/LabSphere access. Actual S3 object access NOT_RUN. |
| Preview secret handoff | PASS — final metadata readback | 42 total Sensitive variables, every target exactly Preview, Production targets0/hidden count0. Includes six R2 settings,16 secure core settings/unique cryptographic32-byte APP_KEY,16 Neon-injected settings and four DB_* aliases. Four aliases explicitly have visibility Secret. No raw value in evidence/source/chat; task clipboard cleared. Origin settings await DP03 target. |
| Neon Free preflight | PASS | Existing account-level installation allows provisioning; product metadata lists Singapore/sin1 and optional Auth/Realtime. Billing-plan lookup returns `free_v3`, Free, payment method not required. No plan upgrade. This does not prove a database exists. |
| Neon resource | PASS — independent readback | Initial connector/auth blocker resolved when Owner prepared Brave. Created `partshelf-staging-db`, Neon project `little-wind-47225058`, Vercel store `store_xycTwG0dVKpKtiZ7`. Browser creation summary selected sin1/Authfalse/Realtimefalse/Free. Storage API confirms available/ready, sin1/Authfalse/free_v3, and exactly one project connection: PartShelf staging, Preview only, Sensitive=true, deployment actions empty. Distinct from LabSphere project `billowing-wildflower-35651578`. |
| Reauthentication | PASS — Owner action | Copy Snippet prompted authenticator; Owner completed this directly in Brave. No OTP requested in chat, no warning bypass or new auth integration. New Neon snippet copied privately only for PartShelf Laravel aliases. |
| Bulk import regression | PASS — corrected; failure retained | Vercel .env paste reset earlier Secret/Preview selections: four DB aliases saved as Config and Preview+Production. API readback detected mismatch before any deployment. Target-only edits removed Production. Type-only edits failed HTTP400 requiring visibility=secret (edit connector schema lacks that input); `create_project_env` upsert with explicit Sensitive/Secret/Preview corrected all four. Independent final readback confirms42 vars, no duplicates, all Sensitive/Preview, four aliases Secret, Production0 and deployment0. No raw credential printed. |
| Exact-origin R2 CORS | PENDING | GET CORS returned HTTP404/code10059, no policy exists. No wildcard added. Approved ingress/origin is not available before DP03 target selection. Initial batched readback stopped at this expected absence; successful bucket/domain/token readbacks were repeated separately with explicit per-request handling. |
| App/DB/cloud transport | NOT_RUN | No deployment, schema/seed, Neon TLS/pooling/transaction tests, signed R2 byte operations, cloud browser flow or recovery drill. No app code changes in DP02 and no new local service/port. DP01 local test results are not cloud acceptance. |
| Usage | PASS — latest observed | 38% used /62% remaining, above the immediate-pause threshold. No reset credit consumed. |
| Saved evidence / documentation | PASS | JSON assertions verify phase status,42 Sensitive Preview vars, zero deployments, private/empty R2 and independent Neon identity/project connection. Credential-pattern scan of all six changed documentation/evidence files found no raw connection password/APP_KEY/R2 secret pattern. Git diff --check passed. No application code changed, so application tests were not rerun. |

Evidence: `DP02_STAGING_RESOURCES.md` and sanitized `evidence/DP02_RESOURCE_READBACK.json`. **PASS — DP02 DONE for provisioning only**: three isolated resources, required credential storage/targets and zero deployment verified. DB roles/engine/retention, origin configuration and actual cloud application/recovery compatibility are DP03+ gates, not claimed by this result. Await separate deployment/migration approval.

## DP03 — staging and actual cloud tests, 2026-10-28 (IN_PROGRESS)

| Check | Result | Actual evidence |
|---|---|---|
| Protected operations review | PASS after fix | Attempt1 rejected insufficient reused-role validation. Attempt2 accepted frozen v2. Actual local partshelf_test role cases4 pass (fresh safe/elevated/member/owner), all fixtures rolled back. Final PHP lint and missing-flag rejection pass. Initial fixture test used wrong env key and failed authentication before mutation; corrected after Compose key inspection. |
| Neon engine/TLS/identity | PASS | Actual staging project host digest fixed; initial public tables0; PostgreSQL18.6. PDO verify-full and psql client TLSv1.3/TLS_AES_256_GCM_SHA384 verified. pg_stat_ssl=false at backend is not treated as external TLS evidence. |
| Cloud migration/runtime role | PASS | Ordinary Laravel migrate --force applied10 migrations on fixed staging only; no fresh/rollback/seed. Pooled partshelf_runtime has no superuser/createdb/createrole/inherit/replication/bypassrls/membership/ownership/database/schema CREATE or migration INSERT. DML verified; admin secret injection removed from PartShelf Vercel connection. |
| Neon constraints/concurrency | PASS — direct cloud | Runtime-role runner8 cases: persistent DDL42501, case-insensitive SKU23505, negative balance23514, FK23503, decimal1.125, idempotency23505, competing row lock55P03, advisory transaction exclusion/release. Exact generated fixture removed; products0 afterward. Not hosted API proof. |
| R2 actual byte integration | PASS — direct cloud | Signed PUT/GET5MiB exact SHA256; unsigned GET denied400; promoted object unchanged after staging overwrite; expired signature403; exact test-prefix cleanup0. Bucket-only30-minute token revoked afterward. Initial token expiration with fractional seconds returned400, corrected to documented whole-second format. This test uses local production image to real R2, not a mock or hosted browser. |
| First deployment target | FAIL / CANCELED | Explicit target=staging request1 returned Production before build completion. Immediately canceled; GET confirms CANCELED before Ready, project livefalse. Retained request/id and upstream Vercel issue17069/merged PR17206 evidence. No silent retry or unapproved bootstrap. |
| Placeholder exception | PASS — exact Owner approval | Owner explicitly approved empty static placeholder on staging project only. Production env0; exact2-file payload has no app/DB/R2 credentials/data. dpl_GTr74rC3GHHCXPPHic1yBiavofiE READY/Production, bounded exception only. |
| Actual app probe | PASS — compatibility/fail closed only | dpl_2YBmHfAjaZG4YrBMwMRXGymYkbQZ READY/target staging; actual runtime logs environment=preview, region sin1, Apache peer127.0.0.1. Hosted /api/health returned503 with configuration-incomplete JSON and private/no-store, expected because origin absent at creation. PHP8.4.27/Apache container runs on Vercel. |
| Exact origin/CORS | CONFIGURED, browser pending | APP_URL/Sanctum saved only after actual staging alias readback; exact R2 origin allows GET/PUT/HEAD and Content-Type, no wildcard origin. Original combined credential/origin write was rejected by automatic approval review and replaced with runtime-only then verified-origin steps. |
| Trust proxy | APPLIED with explicit Owner approval | Initial automatic rejection retained. Owner explicitly approved exact127.0.0.1 Preview only, then API write accepted. Current30 Sensitive Preview vars/Production0. Database session metadata shows a non-loopback client IP without printing it. Authenticated spoofed-header negative still NOT_RUN; genuine forwarding is not spoof-resistance proof. |
| Configured container/health | PASS | dpl_FnHmGW3CBcUoUQZwnWzJcbZWSPi7 READY/staging, authenticated health200/databaseok. Root404 reproduced while /index.html and deep link200. |
| Root fix/release identity | PASS | One literal root rewrite to frontend/index.html. Review3 accepted bounded config/packager candidate; parent verified115-file snapshot hash fd3918bdd1f21abdc431f866649117308628520c070bd1d01ff196dff0b6ba2d. dpl_B2VSAbvHyTtMBP45dakYGEY6woZL READY/staging; only existing staging branch alias rebound. Browser root loads and retains session across deployment. No new Git commit/push. |
| Hosted business flow | PASS | Owner UI creates category/location/product, assigns location without stock mutation, opening10 + receipt2.125 =12.125. Count11.125 leaves stock unchanged; receipt3 produces15.125; recount14.125 yields-1.000; correction produces14.125. Independent Neon readback confirms4 movements,2 attempts,1 correction and expected audit actions. |
| Hosted role enforcement | PASS | Staff has0 add/stock-write/correction/Excel controls. Direct authenticated template endpoint returns403, confirmed in Vercel runtime logs. Staff logout followed by Manager login succeeds; Manager management/stock/Excel controls present. |
| Hosted export/API/R2 bytes | PASS — bounded | Manager session requests actual product/count exports through browser; signed URLs kept private. Real R2 download/ZIP/OpenSpout parsing confirms product XLSX4557 bytes/2 rows, count XLSX4680 bytes/3 rows including both11.125 and14.125 attempts. Native browser save event timed out and standard Downloads file was absent; native save NOT_VERIFIED, not claimed PASS. |
| Protection/production boundary | PASS — observed | One unauthenticated raw health request302 to Vercel authentication; no repeated raw unauthenticated requests. Browser verifies partshelf-staging.vercel.app is still the Owner-approved empty static placeholder. No application Production-target release. |
| Browser file automation | BLOCKED | PNG/XLSX chooser reports file-URL access disabled. Owner said will enable, but successful permission readiness/upload not observed. Two chooser calls stalled despite timeout parameters; no further chooser retries. Native fallback found Brave window, then Computer Use forcibly stopped the turn because current browser URL could not be established confidently for Windows policy. No UI actions after stop. Image upload/replace/read, XLSX preview/confirm/replay and native save remain unverified. |
| Cleanup/retained fixtures | PARTIAL | Direct R2 test prefix emptied and temporary test token revoked; admin/local-test/R2 credential duplicates deleted. Retained3 verification users,1 clearly named product/category/location,1 count round/2 attempts,4 movements/1 correction and immutable audit evidence for unfinished UAT. App temporary export objects/ledger remain; final browser fixture/expiry cleanup not claimed. |
| Usage / final status | IN_PROGRESS | Latest97% remaining; no reset tool called by agent. Not a50%-threshold pause. All3 protected review calls consumed; current root candidate accepted, no further source edits after that review. DP03 not DONE because file E2E/security negatives/cleanup are incomplete. |

Current sanitized evidence: `DP03_STAGING_VERIFICATION.md`, `evidence/DP03_CLOUD_READBACK.json`, `assurance/DP03_REVIEW_1.json`, `assurance/DP03_REVIEW_2.json`. DP03 remains IN_PROGRESS, not DONE. No actual production app release, paid upgrade or DNS cutover.

## DP03 continuation — actual results, 2026-10-28

| Check | Result | Evidence / scope |
|---|---|---|
| Existing cloud identity | PASS — fresh read-only | Same project/team/deployment/alias; deployment READY/staging/sin1.30 Sensitive Preview-only envs, Production0/hidden0. Neon store/project available Free/sin1/Authfalse; runtime PostgreSQL18.6 verify-full read-only connection. R2 private/APAC/Standard, r2.dev false and exact CORS origin unchanged. `evidence/DP03_CONTINUATION_READBACK.json` |
| Browser readiness | PARTIAL | Exact staging URL verified, Manager session present; chooser opens but setFiles PNG returns file-URL permission rejection. No bypass/settings change; no actual upload submitted. Browser binding reset once and was reidentified by same Brave extension/exact URL, not by guessed window. |
| Anonymous browser API | FAIL — deployed cloud | After real logout, GET /api/user renders generic Server Error. Vercel preview/sin1 stdout confirms500 at2026-10-28T10:32:57.885Z; stderr confirms Route [login] not defined. No user data in response. Edge302 historical result is not application401 proof. |
| Regression red | PASS — failure reproduced locally | Two new cases: Accept text/html and absent Accept. Both failed (2 assertions), actual500 instead of401, same missing login route. Initial restricted Docker pipe failed before execution; normal approved Docker command then ran in isolated partshelf_test. |
| Local auth fix | PASS — local only | bootstrap redirectGuestsTo returns null for api/*, root for non-API guests. Focused AuthenticationAndAuthorizationTest14 passed/46 assertions; full isolated suite68 passed/361 assertions. HTML case also asserts JSON Content-Type, safe Unauthenticated message, no Location. |
| Candidate checks | PASS — local only | Pint --test on bootstrap/app.php + auth test:2 files. Frontend TypeScript/Vite build passes, oxlint0 warnings/errors. Frozen hashes in `evidence/DP03_AUTH_FIX_CANDIDATE.json`. No production-image rebuild or cloud fix claim. |
| New candidate review/deploy | NOT_RUN / pending review authority | Previous3/3 protected review calls used; prior accept applies only old config/root snapshot. No fourth call/reset. Local backend fix not independently reviewed or deployed. Existing Git-base packager must be updated/verified to include fix before any future deploy. |
| Staff protected operation | PASS — GET scope only | Actual Staff login, write/Excel UI absent. Direct GET /api/products/import/template denied; fresh runtime log2026-10-28T10:37:07.410Z confirms403, preview/sin1. No new Staff POST write probe claimed. |
| File UAT/native save | NOT_VERIFIED | New manual procedure `DP03_MANUAL_UAT.md`;5MiB PNG, replacement PNG and valid/invalid XLSX fixtures prepared locally. Local fixture generation is not cloud preview/confirm/replay/download proof. |
| Remaining cloud negatives | NOT_RUN | Authenticated missing-CSRF, forwarded-header spoofing, confirmation replay, Staff POST. Historical R2 unsigned400/expired403 remain direct-provider proof; hosted image tests still unverified. |
| Cleanup inventory | PASS — read-only inventory | READ ONLY transaction matches3 exact test users and original fixture, product3/image null/balance14.125;3 owned export ledgers; audit action counts preserved. Exact R2 key metadata matches ledger sizes. No all-bucket scan/delete. |
| Expired temporary object cleanup | PASS — one exact object | Owner1 export ef3e8e26-6c6e-4a40-b3c2-7eed9f7a21f8/products.xlsx expired08:17:26 UTC; inventory saved before deletion. Single-object DELETE200, subsequent exact-key list200/result[]. Ledger/audit remain.2 unexpired export objects and core UAT fixtures retained; final cleanup PARTIAL. |
| Phase/production/usage | IN_PROGRESS | DP03 acceptance incomplete; DP04 NOT_STARTED, handoff backlog only. No new resource/secret/deploy/commit/push, no Production/LabSphere mutation. Latest95% usage remaining, above50%; no reset used. |

No source fix is claimed on staging. Closure matrix: `DP03_FINAL_ACCEPTANCE.md`. Manual Owner results must be received and independently reconciled before promoting any NOT_VERIFIED result.

## Working Demo — 2026-10-28 to2026-10-29 (Asia/Bangkok)

| Check | Result | Actual evidence |
|---|---|---|
| Local isolation | PASS | Separate Docker project/DB/volumes/network; ports5177/8084/5443 loopback, inspected via netstat and Docker mapping. Original local5175/8083/5442 untouched; no cloud seed/migration/deploy |
| Demo auth test-first | PASS |3 initial failures (missing username column/metadata), then3/18 login tests green. Existing email login and Sanctum session/CSRF preserved; Role query constraint cannot change stored role |
| Provision guard/idempotency | PASS after fix | Final7/33 focused tests incl hashes/no duplicate/preserve edited stock. Reviewer1 found static host vs active connection gap; no-network regression red then green after active getConfig('host') fix. Actual provision repeats seededfalse/products25 then28 |
| Count picker | PASS after fix | Browser discovered joined user.id replaced count.id. Regression red [1,1] vs[2,1], then explicit c.* selection; focused10/75 green. Browser options3/2/1 verified and Manager reopened round3 |
| Full candidate | PASS |76 tests/398 assertions on isolated partshelf_test; final Pint changed files pass, frontend TypeScript/Vite build and lint0 warnings/errors; startup/stop/reset PowerShell parse |
| Real HTTP backend/database | PASS |45 checks, real local API/PostgreSQL (no mocks):3 roles, bad password/role422, logout204 then401, missing-CSRF419, Staff writes/Excel403, catalog/location/stock/count/recount/Owner+Manager corrections; evidence/DEMO_RUNTIME_RESULTS.json |
| Real Excel HTTP | PASS |7 checks: multipart preview200, confirm201, replay422/no duplicate, readback/logout. Product XLSX5694 bytes read with OpenSpout27 rows at export time. Separate from native browser save |
| Browser real workflows | PASS |Owner/Manager/Staff actual UI login; bad password and mismatched Staff→Owner role rejected without private UI. Owner added/search/detail/location/receipt22→25/count24/recount23/correction25→23. Manager reopen/recount22/correction23→22. Staff mobile recount21 leaves stock22 and no write/Excel/correction controls |
| Mobile/persistence | PASS — viewport only |390×844 override, document clientWidth=scrollWidth375; show/hide password text/password using synthetic string only. Refresh retained Owner session/stock23; SQL final stock22 and attempts24/23/22/21 separate, reference25. Screenshot .local/demo/staff-mobile.png; physical phone not claimed |
| Browser file integration | NOT_VERIFIED |Download event timed out and stalled despite timeout parameters; no native save confirmed and no retry/bypass. Browser import not performed. Manual UAT in DEMO_TEST_REPORT.md; API Excel proof kept separate |
| Dependency startup | PASS after correction |Fresh volumes inherited stale image dependencies; qrcode error observed. Synced Composer/npm lockfiles into only Demo volumes, restarted only Demo web. Second startup no duplicate data. Dev watcher stale label fixed by Demo web restart; final Password label correct |
| Review | PASS after fix |Separate new Demo protected unit: REVIEW1 fix-first, REVIEW2 accept active-host fix, REVIEW3 accept count picker fix/all16 v3 hashes.3/3 used; no further source changes. Not a DP03 budget reset or cloud release approval |
| Secrets/handoff | PASS — scope |Random distinct passwords generated in ignored ACL-protected private file, hashed at rest, none in frontend/tracked docs. Owner explicitly requests successful Demo passwords in final chat; no service/production secret handed off |
| Reset | NOT_RUN |Script parsed, fixed Demo project only, SupportsShouldProcess high confirmation. No reset executed over completed UAT data |
| Cloud/DP03 | UNCHANGED / IN_PROGRESS |No Staging Demo: no tenant isolation in shared catalog. Historical deployment READY/staging/sin1 verified, no Demo source/data/secret written there. Owner later approved1 extra pinned DP03 auth review/staging-if-pass and Manual UAT; this is authority, not proof of tests |

Detailed19-item coverage, known limitations, role matrix and operating/reset commands: `DEMO_TEST_REPORT.md`. Latest usage78% remaining, above immediate-pause threshold; no reset credit consumed.

## DP03 Owner-approved auth-only staging release — 2026-10-29

| Check | Result | Actual evidence |
|---|---|---|
| Exact authority/review | PASS | Direct Owner reply approved1 extra read-only auth review and staging-if-pass. DP03_REVIEW_4 accepts; original3 calls retained, extendedcap4/used4. No Demo review substituted |
| Release exclusion/integrity | PASS |115 pinned files; independent manifest comparison finds only backend/bootstrap/app.php delta. All Demo username/role/seed/migration/count-picker/UI changes excluded. Parent reads46 chunks and independently SHA256 validates payload3c22f6393332c8c4e7f8916e986265b7653f17aae9666ff0cca8f7bed60118fa |
| Fresh preflight | PASS | Same project/team/branch alias;30 Sensitive Preview-only vars, Production0/hidden0; Production alias points original placeholder |
| Deploy/target/alias | PASS | dpl_EWQdag1ji9DF2T2tczv4os1qWWRo READY/staging/sin1; runtime preview. Only existing staging branch alias assigned/read back; Production alias unchanged |
| Actual health | PASS cloud | Brave GET /api/health returns status/databaseok; runtime HTTP200 at2026-10-29T01:48:59.720Z |
| Anonymous browser API | PASS cloud — supersedes previous500 | Brave GET /api/user returns JSON Unauthenticated; runtime HTTP401 at2026-10-29T01:49:05.427Z, exact new deployment/preview/sin1. Missing-Accept local regression still separate; no blanket CSRF/spoofing claim |
| DP03 data preservation | PASS read-only | Original runtime-role SELECT script verifies product3/DP03-BROWSER-20261028 stock14.125,4 movements,2 attempts,1 correction and expected audit actions. No Demo records/data migration or seed |
| Remaining acceptance | NOT_VERIFIED / IN_PROGRESS | Owner chose Manual UAT but no result attested. File/browser-native save, authenticated CSRF/spoofing/replay and final cleanup pending. DP03 not DONE |

Evidence: `evidence/DP03_AUTH_RELEASE_MANIFEST.json`, `evidence/DP03_AUTH_RELEASE_RESULT.json`, `assurance/DP03_REVIEW_4.json`. No Production resource/alias/DNS change, credentials/env change, Git commit/push or LabSphere mutation.


## UX/UI Local Demo audit — 2026-10-29 (IN_PROGRESS)
- Owner-authorized scope: all existing Demo screens/roles; no cloud release, production/DNS/LabSphere/push.
- Execution: solo-reviewed, assurance protected (real Demo sessions/data integrity); new UX assignment, independent ledger from completed Demo and DP03 reviews. Review budget reserved 0/3, target one read-only review after frozen candidate. No writer agent.
- Baseline: real Playwright, 12 role/viewport measurements; Owner/Manager320px page overflow4px; visible small buttons and 4–7-screen single-page layout. Evidence `docs/evidence/UX_BASELINE.json`; private sanitized screenshots `.local/ux-audit/before/`.
- Implemented sidebar/drawer/hash navigation and lazy existing panels; count thumbnails/accessibility/Enter save/focus-next and unsaved-change guards. Paging uses existing product/stock endpoints.
- TDD: navigation initially failed missing nav, now PASS1; count-image contract initially failed null URL, now PASS1/4 assertions. Frontend build and lint passed during implementation. Full regression/responsive/visual gate pending.
- Usage readback:24% used /76% remaining; pause at <=50% remaining; no reset used.
- DP03 stays IN_PROGRESS. This Local UI work does not establish any new cloud acceptance.


## Local UX/UI candidate — 2026-10-29 (browser verified, overall IN_PROGRESS)
- Existing Local Demo now has compact lazy routes/sidebar/mobile drawer. No Settings/User module invented. No cloud/Staging/Production/secret/DNS/LabSphere change; no commit/push.
- **PASS:** frontend lint/TypeScript/build; backend full77/402 and focused count image1/4; Pint2 files; real browser10 tests, existing mocked contract2 tests.
- **PASS:**330 loaded route/role/viewport checks including drawer/navigation,99 expanded form/detail checks,11 login layouts (440 total). Required widths320/360/375/390/430/768/1024/1280/1440/1920 plus breakpoints767/769/1023/1025 and844x390 landscape.19 axe scans zero observed violations; no full accessibility certification claimed.
- Real PNG upload/replace/render, product create/edit/search/detail/location, opening/receipt, count/recount/explicit correction, preserved other-row draft, Manager category/location updates, Staff count201 and prohibited receipt403 verified against Laravel/PostgreSQL. Native XLSX import success201/replay422 and local export save/read verified; initial successful import retained in evidence; later previews intentionally report duplicates.
-30 Demo products/4 categories/3 locations retained. Old sample stock22, attempts24/23/22/21 and2 corrections preserved. No reset/cleanup. Previous cloud/manual UAT gaps remain separate.
- **NOT_VERIFIED / completion blocker:** actual mobile OS keyboard and one-handed reach with keyboard open. Optional physical camera/scanner/printer/device font checks remain manual. No Browser permissions changed and no LAN exposure added.
- UX_UI remains **IN_PROGRESS**, not DONE, until required real-mobile UAT result. DP03 still IN_PROGRESS. Frozen21-file candidate UX_LOCAL_20261029_V1; read-only protected review1/3 reserved, verdict pending.
- Evidence: docs/UX_UI_AUDIT.md, docs/RESPONSIVE_TEST_MATRIX.md, docs/evidence/UX_BROWSER_TEST_SUMMARY.json, UX_RESPONSIVE_RESULTS.json, UX_EXPANDED_STATES.json, UX_REAL_API_RESULTS.json, UX_ROLE_OPERATIONS.json, UX_EXCEL_BROWSER_RESULTS.json, UX_FINAL_INVENTORY.json, UX_BUNDLE_RESULTS.json. Screenshots private ignored .local/ux-audit/before and after. No passwords/tokens/cookies in published evidence.


### UX protected review1 — fix-first; corrections under verification
- Vera read-only verified21 hashes but found P1 unsent-search refresh could discard another draft and P2 focus followed IDs instead of SKU display order. Old V1 test coverage was insufficient for these cases; V1 is not accepted.
- Both added regressions failed before fix and now pass focused (1 actual browser +1 mocked ordering test). Applied/draft search separated, drafts preserved, visible-order focus and pending-input lock implemented. Full V2 checks running; no new full PASS claimed yet.
- Same OWNER_UX_UI_20261029 cap3,1 consumed; prior Demo/DP03 review history untouched. UX_UI/DP03 stay IN_PROGRESS. Physical keyboard still NOT_VERIFIED.


### UX V2 full checks PASS — review2 pending
Both review defects now covered by red/green regressions. CandidateV2: backend77/402,real browser10,mocked3,lint/TS/build,440 layouts/330 navigation,19 axe scans zero all PASS. Same cap3 consumed1; review2 reserved before dispatch. V1 fix-first retained; no DONE claim; physical keyboard NOT_VERIFIED.


## UX/UI final local verification — 2026-10-29
**WORK_STATUS: browser/code verified. INDEPENDENT_REVIEW: ACCEPT, UX_UI_REVIEW_2 / UX_LOCAL_20261029_V2,21 hashes. FINAL_STATUS: IN_PROGRESS (required physical mobile OS keyboard UAT NOT_VERIFIED).**
- Latest full checks after review fixes:77 backend tests/402 assertions; real browser10; mocked contract3; frontend lint/TS/build; Pint2 PASS.440 layout checks (330 main/navigation +99 expanded +11 login),19 axe scans zero observed violations; no full certification claim.
- Reviewer1 correctly found unsent-search draft loss and ID-vs-SKU focus ordering. Both regressions failed before correction and pass now. V1/attempt1 retained, same assurance unit2/3 consumed; no extra review or deployment authorized by acceptance.
- All existing8 workspace views plus Login/role/permission/error/expanded states covered. User/Settings/date picker/new modules NOT_APPLICABLE. Required widths320/360/375/390/430/768/1024/1280/1440/1920; breakpoints767/769/1023/1025 and844x390 landscape included.
- Real PNG create/replace/render, opening/receipt, count/recount/correction/draft/zero, Manager updates and Staff positive/negative operations pass. Actual native Excel import/validation/confirm/replay/save and OpenSpout content read verified. Local Excel gap in initial Demo handoff is resolved; cloud DP03 UAT remains separate and incomplete.
- Required follow-up: real mobile keyboard field visibility/one-handed reach/native dropdown checks. Current Demo stays loopback-only; no LAN binding/Browser permissions changed. Physical scanner/camera/printer optional manual checks NOT_VERIFIED. No observed remaining critical defect within tested Chromium scope; no DONE claim.
- URL http://127.0.0.1:5177/; Docker partshelf-demo, API8084/DB5443 healthy. Existing data retained; no reset. No cloud resources, Staging/Production release, DNS/LabSphere change, commit or push.
- Evidence: docs/UX_UI_AUDIT.md, docs/RESPONSIVE_TEST_MATRIX.md, docs/evidence/UX_BROWSER_TEST_SUMMARY.json, UX_CANDIDATE_V2.json, UX_REVIEW_REGRESSION.json, UX_PRIVACY_VERIFICATION.json, UX_FINAL_INVENTORY.json. Detailed screenshot paths in matrix; no passwords/tokens/cookies in tracked evidence.

## Fresh runtime restoration and verification — 2026-10-29

**Local runtime RESTORED; tested Demo browser/API/PostgreSQL behavior PASS; overall DEMO_PARTIAL pending physical mobile keyboard UAT.** This fresh run supersedes runtime-unavailable statements for the current Local/Demo stacks. Docker CLI was installed per-user but absent from PATH; sandbox pipe access was denied, while system-approved Windows Host execution succeeded. No Docker installation/context change was needed.

The current Engine had no PartShelf containers or named volumes. Existing Dockerfiles/Compose rebuilt both stacks. PostgreSQL identity and zero public tables were checked before initializing each empty database. This is a new baseline in the current Engine, not recovery of historical Demo records. Demo reused private credentials, provisioned25 products and3 accounts; after marked QA/browser/import fixtures it contains28 products. Local partshelf remains0 users/0 products. Existing shared-test-db was untouched.

Fresh results:45 live API checks;10 unique real Browser scenarios;440 layout checks (330 route-role-viewport,99 expanded states,11 login layouts). All final checks PASS; two initial label harness failures retained, corrected and rerun.18 axe scans observed zero violations. Browser XLSX import/confirm/replay/export and OpenSpout parsing27 exported rows passed; actual PNG create/replace/render passed. SQL confirms stocks13/8/3 for marked test products, separate attempts,3 corrections, zero negative balances and no duplicate SKUs. No application bug confirmed or source behavior changed. Unit/integration suites and standalone lint/build commands were not rerun. Physical mobile keyboard/camera/scanner/printer/PWA install remain NOT_VERIFIED.

Use Demo http://127.0.0.1:5177 (API8084/DB5443) for owner.demo/manager.demo/staff.demo. Local http://127.0.0.1:5175 (API8083/DB5442) is the initialized empty shop. All three actual Browser logins, refreshes and logouts passed. Secrets remain private. No cloud/Staging/Production changes; DP03 stays IN_PROGRESS. Detailed role matrix, boundaries, Host commands and evidence: FULL_SYSTEM_VERIFICATION.md and docs/evidence/RESTORE_*. Screenshots/private batch reports: D:\git\PartShelf\.local\restore-verification. Next action: required physical mobile UAT; do not mark full acceptance DONE.

## Latest Cloud Demo preparation — 2026-10-29

**BLOCKED before cloud release:** Owner authorized latest Demo to the existing Vercel Preview/Staging project. Created codex/cloud-demo-20261029; GitHub main and base remain f8839e171725ebdfe33dffb0139b580fb5dc7ccd. Frozen120-file latest runtime candidate V2 hash6f00beff1dd0ff205ae3d739c83484d03c0439aec24d1b5fbb2ebe559b0d0ea0; no old DP03 payload substituted.

Cloud Demo preparation adds Preview-only username-login gates for dedicated schema/role, active provisioning identity checks and DB_SCHEMA configuration; .local now excluded from Vercel uploads. Review1 found DB_URL override gap; regression failed before correction. Review2 ACCEPTS preparation only, same assurance unit2/3 calls consumed. Local full81/410 and final focused5/14 PASS; Pint, frontend lint/TS/build PASS. Initial full-suite invocation inherited local APP_ENV and failed10; corrected explicit testing invocation passes, initial output retained.

Fresh read-only Neon confirms DP03 stock14.125/4movements/2attempts and existing data retained. Current partshelf_runtime has no schema/database CREATE privilege; admin credential is absent. Await Owner's protected .local/cloud-demo/neon-admin-url.txt to prepare isolated partshelf_demo schema and dedicated runtime role. Do not seed shared public tables. R2 bucket metadata PASS: APAC/private, no customdomains, old DP03 CORS origin retained. Vercel exact project/team, older staging READY and original Production placeholder unchanged;30 Sensitive Preview vars/Production0 confirmed.

No cloud schema/role/accounts/data/env/CORS/alias write or new deployment occurred. Latest-cloud Login/stock/Excel/R2/QR/responsive remain NOT_VERIFIED. DP03 stays IN_PROGRESS. Handover and next steps: CLOUD_DEMO_HANDOVER.md. This is source preparation, not delivered Cloud Demo readiness.

Publication update: GitHub repository is public. Auto-review rejected the combined push command before execution; public Source-only push approval requested separately. Source14cd8d7 remains Local; no remote branch publication claimed. Cloud release also remains blocked on scoped Neon administrator access.
