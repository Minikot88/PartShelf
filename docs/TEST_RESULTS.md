# PartShelf — Test Results

**Run date:** 2026-10-28 (Asia/Bangkok)  
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
