# PartShelf — Project Progress

> **Updated:** 2026-10-29 (Asia/Bangkok)  
> **Project status:** `P12_DONE / DP01_LOCAL_DONE / DP02_DONE_PROVISIONING_ONLY / DP03_IN_PROGRESS`  
> **MVP:** products, stock, storage locations and simple counting  
> **Environment:** Docker/local plus isolated cloud staging; app deployment/testing in progress

## Inventory Integrity Audit — 2026-10-29

Docker setup VERIFIED: Owner approved disk move/restart; effective data directory is D:\DockerData\DockerDesktopWSL. Actual Engine readback4CPU/6216564736 bytes usable RAM,1GiB swap,json-file driver. Configured6GB RAM cap,2GB build-cache GC target and10MB×3 log files; Resource saver enabled5min,autostart off,Kubernetes disabled. Current Engine has0 images/containers/volumes/cache, so prior Local/Demo stack availability must not be assumed. See `DOCKER_LOCAL_SETUP.md` and `evidence/DOCKER_LOCAL_SETUP.json`. Inventory behavioral tests have not resumed yet.

**Status: NOT_VERIFIED.** Source review found transactional stock writes, row locking, idempotency keys, non-negative PostgreSQL constraints and three-decimal quantities. Existing inventory feature tests cover opening/receipt, duplicate receipt replay, zero count, movement-aware variance, correction and negative-balance rejection. This audit did not run the suite: PHP and Docker CLI were unavailable in the current shell, while Local/Demo ports 5442/5443/8083/8084/5175/5177 were already accepting loopback connections. No write requests, test data, resets, or Local/Demo/Staging changes were made.

Concurrency across sessions, injected mid-transaction rollback, and decimal precision boundaries remain NOT_VERIFIED. Existing Owner and Manager correction success tests were found, but were not rerun. Unit conversion was not found in the current base-unit model. No confirmed source bug; no application code changed. Detailed 13-item matrix and safe next steps: `INVENTORY_INTEGRITY_AUDIT.md`. Do not mark this audit PASS/DONE until a fresh run on PostgreSQL `partshelf_test` is recorded.

## DP03 auth-only release — 2026-10-29

Owner explicitly extended the original DP03 review cap by1 (original3 retained, used4/cap4) and authorized staging if accepted; Owner chose Manual UAT, not a PASS attestation. REVIEW4 accepts frozen115-file release with only backend/bootstrap/app.php changed relative to previous staging snapshot; all newer Demo code/migrations/seed/accounts excluded. Parent verifies exact payload SHA2563c22f6393332c8c4e7f8916e986265b7653f17aae9666ff0cca8f7bed60118fa after transfer.

Deployment `dpl_EWQdag1ji9DF2T2tczv4os1qWWRo` is READY/staging/sin1; runtime preview. Existing branch alias now points to it. Actual Brave health200/databaseok and anonymous browser /api/user401 JSON verified by runtime logs (previous500 fixed). Production alias still original empty placeholder. No DB migration/seed/env/resource/Production change or Demo deployment. SELECT-only Neon confirms DP03 stock14.125,4 movements,2 attempts,1 correction/audits preserved. See `evidence/DP03_AUTH_RELEASE_RESULT.json`.

**DP03 remains IN_PROGRESS:** Owner Manual File UAT, authenticated missing-CSRF/forwarded-spoofing/import replay and final scoped cleanup still pending. DP04 not activated. Latest usage77% remaining, no threshold pause/reset.

## Working Demo — latest 2026-10-29

**LOCAL_DEMO_READY / BROWSER_EXCEL_NOT_VERIFIED**. Owner's separate Working Demo prompt implemented on isolated Docker `partshelf-demo`, PostgreSQL `partshelf_demo`, loopback web5177/API8084/DB5443. Existing local/cloud DP03 data not seeded or reset. Modern Demo role login uses existing Sanctum/RBAC, DB username/actual-role constraint,3 hashed accounts, private ignored random passwords,25 idempotent sample products (28 after clearly marked QA/browser/import tests).

Actual local HTTP45 checks + Excel7 checks pass; browser Owner/Manager/Staff logins, wrong password/role, product/create/search/detail/location, receiving/count/recount/reasoned corrections/Staff read-count-only rights and390px mobile pass. Full76/398, guard focused7/33, count focused10/75, frontend build/lint/Pint pass; final Demo review3 accepts16-file v3. Active-host provisioning guard and joined count-ID bug fixed with red/green regressions. Core API proof is not mocked.

Browser-native export timed out/stalled; import/export browser save remains NOT_VERIFIED with Manual UAT in `DEMO_TEST_REPORT.md`. Local Excel HTTP bytes/preview/confirm/replay pass separately. No physical-device claim. Scripts: start-demo.ps1 / stop-demo.ps1 / reset-demo.ps1; reset execution NOT_RUN. Private credentials are not in tracked docs/bundle; final Owner handoff explicitly authorized.

**No Staging Demo:** global catalog/counts lack a tenancy boundary, so source/data were kept local rather than mixed with DP03. DP03 remains IN_PROGRESS. Owner separately approved1 extra DP03 auth review/staging-if-pass and chose Manual UAT; no test result inferred from that approval, and newer Demo code must not be included in the pinned DP03 release. Latest usage78% remaining, pause<=50%.

Report/evidence: `DEMO_TEST_REPORT.md`, `evidence/DEMO_RUNTIME_RESULTS.json`, `evidence/DEMO_EXCEL_RESULTS.json`, `evidence/DEMO_CANDIDATE_V3.json`, `assurance/DEMO_REVIEW_1.json` through3.

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
- P00–P12 local functionality is complete. DP01 adds local production preparation; DP03 core cloud behavior is verified in part, but full staging acceptance and production readiness remain incomplete.

## Next Action

Finish DP03 File UAT/security negatives and scoped cleanup. A newly observed anonymous-browser API500 has a locally tested fix awaiting review/deployment. See `DP03_MANUAL_UAT.md` and `DP03_FINAL_ACCEPTANCE.md`. Production deployment remains unapproved.

## Production planning — 2026-10-28

`DP01_LOCAL_DONE`: Production config/image, object transport, cleanup, local tests and protected review passed. Initial Git commit/push was completed with Owner approval. See `DP01_LOCAL_PREPARATION.md` and `TEST_RESULTS.md`; automatic Git deployments remain disabled.

`DP02_DONE_PROVISIONING_ONLY`: Vercel `partshelf-staging` (`prj_9pZ790dvjdRIcZrBe3BxqkXTdjXu`) has no Git link, `live=false`, 0 deployments; saved Function Region sin1 and Hobby verified in Brave. New Neon `partshelf-staging-db` (`little-wind-47225058`, store `store_xycTwG0dVKpKtiZ7`) is available/ready, Free/sin1/Authfalse, connected only to PartShelf Preview. Private APAC Standard R2 `partshelf-staging-files` has public r2.dev disabled, no custom domain, 0 objects. Bucket-only object token expires2027-01-26. Final readback confirms42 Sensitive Preview-only envs, including stable unique APP_KEY/R2/provider variables and four Laravel DB aliases; Production targets0. UI paste defaults were corrected before any deploy and recorded in evidence. See `DP02_STAGING_RESOURCES.md`, `evidence/DP02_RESOURCE_READBACK.json`, `TEST_RESULTS.md`.

`DP03_IN_PROGRESS`: Owner approved staging deploy/cloud tests. Protected review2 accepted after real local role-guard regression. Neon18.6/TLS1.3/10 migrations/pooled least-privilege runtime and8 cloud DB checks pass. Actual private R2 signed5MiB/expiry/immutability/cleanup7 checks pass; temporary test token revoked. Provider-admin16 envs removed by disconnecting only PartShelf's project connection; runtime secrets remain Preview-only, Production0. Initial explicit staging request was misclassified Production and canceled before Ready. Owner then explicitly approved an empty static Production-target placeholder on staging project only; placeholder READY, app probe now target staging/runtime environment preview, sin1. Configured app deployment is BUILDING; hosted browser/API acceptance pending. See `DP03_STAGING_VERIFICATION.md` and `evidence/DP03_CLOUD_READBACK.json`.

Latest DP03 result: snapshot `dpl_B2VSAbvHyTtMBP45dakYGEY6woZL` READY/target staging, runtime preview/sin1. Root404 fixed with one reviewed rewrite;115-file immutable snapshot hash fd3918bdd1f21abdc431f866649117308628520c070bd1d01ff196dff0b6ba2d, no new Git commit/push. Owner explicitly approved Preview TRUSTED_PROXIES127.0.0.1; setting applied. Actual browser session survives deployment; Owner product/location/stock/count/recount/correction pass. Read-only Neon confirms14.125,4 movements,2 attempts,1 correction/audit. Staff protected endpoint403 and Manager controls/export pass. Real private R2 XLSX bytes contain product and both attempts. Production alias still shows only the approved empty placeholder.

Next action: resume unfinished file UAT after browser access is usable, or obtain explicit Owner observations of PNG upload/XLSX import/native download. Extension file chooser reports file-URL permission disabled; native fallback was stopped by Computer Use because current browser URL could not be confirmed for Windows policy. No further UI action after stop. Cloud upload/import/native-save, authenticated spoofing/missing-CSRF negatives and final fixture cleanup remain unverified. DP03 stays IN_PROGRESS, not DONE. Clearly named test data/private credentials retained for resume. Latest usage97% remaining, not a usage-threshold pause; still stop immediately at <=50%.

## Update Log

### DP03 continuation — 2026-10-28 17:42 Asia/Bangkok

- Fresh readback: same Vercel project/team, READY deployment `dpl_B2VSAbvHyTtMBP45dakYGEY6woZL`, target staging/sin1, branch alias still points to it;30 Sensitive Preview-only envs, Production0/hidden0. Neon store/project unchanged, Free/sin1/Authfalse; project integration disconnected as intended, runtime DB still reachable. R2 same private bucket, r2.dev disabled, exact-origin CORS unchanged. Earlier DP02 zero-deployment/42-env descriptions above are historical closure snapshots, not current values; the older DP03 BUILDING description is superseded by READY.
- Brave staging URL verified. File chooser opens, but PNG attachment still rejected by file-URL permission; no permission changes/native workaround. Manual UAT covers all11 file steps and security negatives; unobserved outcomes remain NOT_VERIFIED.
- New cloud FAIL: logout then browser GET /api/user returns500, not401; preview/sin1 log at10:32:57 UTC shows missing login route. Added2 regression tests (both red500 first), fixed guest redirect locally; focused14/46, full68/361, Pint2 files, frontend lint/build/typecheck PASS. Fix NOT_DEPLOYED/NOT_REVIEWED; review3/3 covers old snapshot only. Frozen candidate and packager warning in `evidence/DP03_AUTH_FIX_CANDIDATE.json`; old Git-base packager would omit local backend fix.
- Staff login/UI and protected template GET403 freshly verified in preview/sin1 log. Missing-CSRF/forwarded-header/replay POST negatives still NOT_RUN. No raw secrets/cookies in evidence.
- Read-only Neon inventory confirms3 test users,1 product/image null/balance14.125,3 export ledger rows and preserved audits. Saved cleanup inventory before mutation. Deleted only expired Owner export key ef3e8e26…/products.xlsx; DELETE200, exact-key GET list200 empty. No DB deletes, no bucket-wide operation;2 unexpired exports and business/audit chain retained for UAT.
- DP03 stays IN_PROGRESS. DP04 not activated; queued handoff only. Usage95% remaining, no usage pause/reset. No new resource, secret change, deployment, Git commit/push, Production or LabSphere mutation this continuation.

Evidence: `DP03_FINAL_ACCEPTANCE.md`, `DP03_MANUAL_UAT.md`, `DP03_CLEANUP_INVENTORY.md`, `evidence/DP03_CONTINUATION_READBACK.json`.

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
| 2026-10-28 | Codex | Began Owner-approved DP02; Vercel project/private R2/scoped token and 22 Preview Secrets verified. No deploy. Neon pending authenticated browser selected by Owner; DP02 remains IN_PROGRESS. | `DP02_STAGING_RESOURCES.md`, `evidence/DP02_RESOURCE_READBACK.json`, `TEST_RESULTS.md` |
| 2026-10-28 | Codex | Owner prepared Brave and completed Vercel reauthentication; independent Neon Free/sin1 provisioned and Laravel aliases mapped. Corrected UI import defaults, final42 Sensitive Preview-only/Production0 and deployments0 verified. DP02 DONE for provisioning only; DP03 awaits approval. | `DP02_STAGING_RESOURCES.md`, `evidence/DP02_RESOURCE_READBACK.json`, `TEST_RESULTS.md` |


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
