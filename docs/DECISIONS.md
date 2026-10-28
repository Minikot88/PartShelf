# PartShelf — Decisions and Approvals

**Last updated:** 2026-10-28 (Asia/Bangkok)

## Decisions

| ID | Decision | State | Evidence |
|---|---|---|---|
| D-001 | Core business scope is Products, Stock, Storage Locations and Simple Counting, with the later Owner Master Prompt explicitly adding Excel import/export, Barcode/QR/basic labels, online-only PWA, vehicle fitment and summary dashboard. | Owner approved latest prompt; P00 reconciliation complete | `MVP_SCOPE.md`, `REQUIREMENTS_REGISTER.md`, attached Master Execution Prompt, `TEST_RESULTS.md` |
| D-002 | Count records observation/variance only and never changes stock automatically; correction is a separate permission-checked movement with reason/audit. | Approved scope rule | `MVP_SCOPE.md`, `IMPLEMENTATION_PLAN.md`, requirement #9/#33; correction implementation in P09 |
| D-003 | Items outside the latest prompt remain Future: sales/finance, bulk edit, custom label design, offline/PWA push, full reports, multi-branch UI and advanced workflows. | Owner-approved exclusions | `MVP_SCOPE.md`, `REQUIREMENTS_REGISTER.md` |
| D-004 | P01 local-only bindings are web 5175, API 8083 and PostgreSQL 5442 on loopback; recheck before startup and never stop unrelated services to free a port. | Implemented and verified | `PROGRESS.md`, `TEST_RESULTS.md` |
| D-005 | Owner authorized P00–P12 continuous local/test development; P01–P03 are complete. No production deployment. Pause when usage remaining reaches 50%. | Active approval | Attached Master Execution Prompt and `TEST_RESULTS.md` |
| D-006 | Reconciliation verifies the contents of the written 83-row register; it does not independently prove the register matches the original conversation. | Evidence limitation | `TEST_RESULTS.md` |
| D-007 | Docker auto address pools were exhausted; PartShelf uses a dedicated `10.250.250.0/24` bridge subnet after checking all current Docker subnets and host routes. No unrelated network was changed. | Implemented and verified | `compose.yaml`, `TEST_RESULTS.md` |
| D-008 | Keep this workspace without a Git repository for now; generated local `.env` files remain ignored. | Active project boundary | `.gitignore`; `TEST_RESULTS.md` |
| D-009 | P02 schema keeps count attempts separate, models each product with at most one optional location, and enforces non-negative quantities plus unique SKU/location paths in PostgreSQL. | Implemented and verified | P02 migration, `PartshelfSchemaTest`, `TEST_RESULTS.md` |
| D-010 | First-party SPA authentication uses Laravel Sanctum session cookies with CSRF protection; no personal access tokens or external identity provider are needed for this local MVP. | Implemented and verified in P03 | `TEST_RESULTS.md`; [Laravel Sanctum SPA authentication](https://laravel.com/framework/docs/13.x/sanctum) |
| D-011 | User accounts are provisioned through the interactive `partshelf:user:create` CLI; default database seeding must never create a known-credential login. | Implemented and verified in P03 | `DatabaseSeeder.php`, `CreateUserCommand.php`, `TEST_RESULTS.md` |
| D-012 | Automated tests use a dedicated `partshelf_test` PostgreSQL database and fail closed before migrations if the resolved connection differs. | Implemented and verified in P03 | `phpunit.xml`, `tests/TestCase.php`, `TEST_RESULTS.md` |
| D-013 | The attached Owner Master Execution Prompt revises the prior MVP. P00 reconciled its explicit supporting features into the register, Scope and plan; existing P01–P03 evidence remains valid. | Implemented and verified | Attached Master Execution Prompt, `REQUIREMENTS_REGISTER.md`, `TEST_RESULTS.md` |
| D-014 | A location is one Zone/Shelf/Level/Bin path; path uniqueness ignores case. Changing product location is an audited catalog update and must never modify its balance. | Implemented and verified in P05 | `StorageLocationController.php`, `ProductController.php`, `StorageLocationTest`, `TEST_RESULTS.md` |
| D-015 | Opening balance and receipts are append-only stock movements; each requires a UUID idempotency key; replay of the same payload is safe, changed payload conflicts; Owner/Manager may write and Staff is read-only. | Implemented and verified in P06 | `StockController.php`, P06 migration/tests, `TEST_RESULTS.md` |
| D-016 | Excel support uses OpenSpout for single-sheet XLSX import/export to fit the local PHP extensions; imports are additive, previewed, bounded to 5,000 rows, and require a one-use user-bound confirmation token. Count exports retain separate attempt rows and variances. | Implemented and verified in P07/P09 | `ProductExcelController.php`, `ExcelProductWorkflowTest.php`, `composer.lock`, `TEST_RESULTS.md` |
| D-017 | Count snapshot and each count attempt capture the maximum stock-movement ID while holding the product lock. Variance uses only movements after the reference watermark through the attempt watermark; correction applies that variance to current balance and is idempotent per attempt. | Implemented and verified in P09 | P09 migration/controllers/tests, `TEST_RESULTS.md` |
| D-018 | P10 uses `PS-` plus zero-padded Product ID as a stable scan/label code; QR and Code128 encode the same value. The service worker is online-only and never writes Cache Storage. | Implemented and statically/focused verified in P10; real browser install/camera/print check is P11 | `ProductController.php`, `ProductLabelPanel.tsx`, `manifest.webmanifest`, `sw.js`, `TEST_RESULTS.md` |
| D-019 | P11 dashboard exposes stock summary and recent counts to authenticated roles; only Owner/Manager can view variance details. Headless E2E mocks API responses to avoid writing test records into the local shop database. | Implemented and verified in P11 | `DashboardController.php`, `DashboardPanel.tsx`, `DashboardSummaryTest.php`, `frontend/e2e/partshelf-workflows.spec.ts`, `TEST_RESULTS.md` |
| D-020 | Local database backups use PostgreSQL custom format. Restore accepts only `partshelf` or `partshelf_test`, validates that archive source DB matches target, and requires PowerShell high-impact confirmation before replacing the selected local database. | Implemented; isolated `partshelf_test` recovery drill passed in P12 | `scripts/backup-local-db.ps1`, `scripts/restore-local-db.ps1`, `docs/LOCAL_GUIDE.md`, `TEST_RESULTS.md` |

## Approval record

Owner selected Vercel static frontend + Laravel container via Services (Beta), Neon PostgreSQL and private R2. DP01 local implementation/testing and Git first commit/push are approved; cloud targets/budget/provisioning/deployment still require approval. Details: `PRODUCTION_DEPLOYMENT_PLAN.md` and `DP01_LOCAL_PREPARATION.md`.

| Date | Approval / direction | Evidence and execution effect |
|---|---|---|
| 2026-10-28 | Owner approved the narrowed four-domain MVP scope and the reconciled plan in chat. | P00 and P01 are marked DONE on recorded acceptance evidence. Docker/local only; no production deployment. |
| 2026-10-28 | Owner explicitly authorized P02 according to the plan. | P02 is DONE after fresh PostgreSQL migrations, constraint tests and full Laravel suite passed. Initial host port check found 5175, 8083 and 5442 free; current Compose mappings remain loopback-only. |
| 2026-10-28 | Owner explicitly authorized P03 according to the plan. | P03 completed and protected read-only review passed; use Docker/local only. Existing Compose mappings remain loopback-only on 5175, 8083 and 5442. |
| 2026-10-28 | Owner supplied Master Execution Prompt authorizing P00–P12 local/test implementation with the listed expanded MVP features and Future exclusions. | P00 reconciliation passed with 83-row coverage; P04 began; P01–P03 evidence preserved; production deployment remains unauthorized. |
| 2026-10-28 | Owner confirmed the P12 acceptance checklist in `LOCAL_GUIDE.md` passed. | P12 is closed as DONE based on direct Owner confirmation; local/test only, no production action. |
| 2026-10-28 | Owner requested a production deployment plan using Vercel/R2 and authorized reference inspection of LabSphere folder/chat. | Read-only reference inspection and PartShelf documentation only. Deployment awaits explicit Owner approval; no LabSphere credentials/resources/approvals inherited. |
| 2026-10-28 | Owner approved Vercel Services/Container Beta + Neon + private R2; authorized DP01 local preparation/tests and Git add/first commit/main/origin/push. | DP01 local checks and protected review passed. Auto Git deployment disabled. No cloud provisioning/deployment authorized. |

## Change control

Any expansion beyond the latest Owner-approved MVP must be recorded here and explicitly approved before implementation. P01–P12 may run only against local Docker/test environments. Production deployment requires separate explicit approval.
