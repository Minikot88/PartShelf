# PartShelf — Working Demo test report

Started2026-10-28; updated2026-10-29 (Asia/Bangkok). **Local browser Excel/UX verification now passes; UX/UI remains IN_PROGRESS for physical mobile keyboard UAT. DP03 remains IN_PROGRESS.** Earlier handoff measurements below are historical; current UX evidence is appended at the end.

## Access and isolation

- Verified local URL: http://127.0.0.1:5177/ ; API loopback8084, PostgreSQL loopback5443.
- Docker project `partshelf-demo`, isolated database `partshelf_demo`/host `demo-db`, own database/session/cache/storage/dependency volumes, network10.250.251.0/24. Original stack5175/8083/5442 untouched; occupied ports/services were not stopped. Host CIM port query was unavailable; netstat and Docker published mappings were inspected before binding instead.
- Staging Demo URL: **NONE**. Current staging has a shared global catalog/count scope and no tenancy boundary. Adding Demo data there could affect DP03 references/count scope, so no seed, migration, secret change or Demo deployment was made on staging. Existing staging deployment identity READY/staging/sin1 was read back before work; it is not the new Demo.
- Local-only development mode, loopback access and authentication retained. No public exposure, new cloud resources, Production/DNS/LabSphere mutation, Git commit/push or auth provider added.
- Credentials are three distinct random passwords in ignored owner-ACL-protected `.local/demo/accounts.json`; no password in tracked docs/source/frontend bundle. Laravel User hashed cast stores hashes. Owner explicitly requested tested Demo passwords in final chat; never include Vercel/Neon/R2/production credentials.

## Implemented behavior

- Existing Sanctum session/CSRF and Owner/Manager/Staff backend gates reused. Additive nullable unique username migration; ordinary email login remains compatible. Demo username login requires a matching role as a database query constraint, never changes account role. Default Demo flag off; username Demo flow/metadata enabled only local/testing.
- Modern white/light-gray/blue responsive login: PartShelf badge, Demo Environment label, Role selector with username autofill, password required, show/hide toggle, generic failure and rate-limit handling. No password autofill from source or bundle.
- Accounts `owner.demo`, `manager.demo`, `staff.demo` are separate from DP03 verification users. Guarded provisioning checks actual connection driver/database/host/environment, transaction/advisory lock, exact identity/role/password ownership, and idempotent seed marker. Re-running does not change an existing stock balance or reset edited Demo data.
-25 seed auto parts,4 categories,3 storage locations across A/B/C, opening stock, one low-stock and one out-of-stock item, and a sample count variance. Three clearly marked QA/import/browser products bring the tested database to28 products. Reset returns the baseline25; routine startup never resets.
- Product detail expansion, existing catalog/location/receiving/count/recount/correction/dashboard/product-count Excel capabilities. Full analytical reports remain outside MVP; Owner/Manager Excel means product/count exports, not new report modules.

## Actual verification

| Check | Result | Evidence |
|---|---|---|
| Test-first Demo auth | PASS | Initial3 failures: username schema/metadata absent. After implementation focused login3/18 pass. Test setup corrected to include existing Sanctum SPA Origin; no CSRF bypass introduced |
| Provision/role focused | PASS | Final7 tests/33 assertions incl hashes/idempotency and active-host no-network guard |
| Full regression | PASS |76 tests/398 assertions on isolated partshelf_test, not the main local DB or cloud |
| Format/frontend | PASS | Pint on all changed Demo/count files, frontend TypeScript/Vite build and oxlint0 warnings/errors; PowerShell scripts parse |
| Real HTTP integration | PASS |45 checks through local Vite → actual Laravel → PostgreSQL:3 role logins, bad password, role escalation rejection422, logout204 then JSON/HTML401, CSRF419, Staff writes403, product create/search/detail/location, receiving, count-only, recount, Owner and Manager corrections. `evidence/DEMO_RUNTIME_RESULTS.json` |
| Actual Excel HTTP | PASS |7 checks: authenticated multipart preview200, confirm201, same token replay422, single imported product readback, logout. Actual product XLSX5694 bytes opens with OpenSpout,27 rows including header at export time. `evidence/DEMO_EXCEL_RESULTS.json` |
| Browser logins | PASS | Actual Owner, Manager and Staff UI sessions against real backend. All credentials provisioned and successfully logged in. Wrong password and Staff username/password with Owner role both reject; private UI absent |
| Browser Owner workflow | PASS | Added DEMO-BROWSER-20261028, SKU search, detail expansion, assigned A-01-01-01. Received3 for DEMO-003:22→25. Started locationC count, counted24/recounted23 without stock change, then reasoned correction25→23. Refresh retained session and stock23 |
| Browser Manager | PASS | Catalog/Excel controls present; reopened round3, recounted22, correction with reason23→22 |
| Browser Staff/mobile | PASS |390×844 viewport, document width/scrollWidth375/375 (no body overflow), Staff write/Excel/correction buttons absent. Staff reopened round3 and recounted21. SQL readback balance remains22; attempts24/23/22/21 separate, reference25. Screenshot `.local/demo/staff-mobile.png` |
| Password visibility | PASS | Synthetic text only: type toggles text/password. Real password was not exposed in screenshot |
| Existing count picker regression | PASS after fix | Browser found joined user.id overwrote count.id. New test first failed [1,1] vs[2,1]. Explicit c.* projection fixes it; count focused10/75 pass. Browser reads values3/2/1 and Manager opens round3 correctly |
| Seed repeat | PASS | Actual provision second run seededfalse/products25; later repeat seededfalse/products28 preserving QA/browser changes. No duplicate seed or stock reset |
| Independent review | PASS after fix | DEMO_REVIEW_1 fix-first: static vs active host guard. No-network regression red then green; REVIEW_2 accepts v2. Count fix then REVIEW_3 accepts v3, all16 frozen source hashes verified. Separate new Demo task, not a DP03 budget reset; no cloud release authority |
| Browser Excel/native file save | NOT_VERIFIED | Actual browser export was attempted but download event timed out and stalled despite timeout controls. No native saved file confirmed, no retry/workaround. HTTP import/export/replay proof above is separate |
| Reset execution | NOT_RUN intentionally | Reset script parses and is high-impact confirmation guarded. No destructive reset performed after useful UAT data was created. Baseline regeneration method documented below |

Initial new volumes used stale image dependencies: browser reported missing qrcode. Startup now installs dependencies from lockfiles into only Demo volumes and restarts only Demo frontend. A stale dev watcher also required Demo frontend restart after label edit; final browser readback sees corrected Password label. Both failures/corrections retained; no false initial PASS.

## Roles

| Role | Existing backend-enforced rights |
|---|---|
| Owner | Catalog/location/stock receiving, counts/recounts, variance/correction with reason, dashboard and product/count Excel |
| Manager | Same existing catalog/stock/count/correction/Excel gates supported by MVP; no new user-admin interface added |
| Staff | Search/read products/locations/stock, start/count/recount, permitted dashboard; no catalog/stock writes/correction/Excel |

## Start, stop and reset

From `D:\git\PartShelf`:

```powershell
pwsh -NoProfile -File scripts/start-demo.ps1
pwsh -NoProfile -File scripts/stop-demo.ps1
```

Startup preserves credentials and existing Demo state, checks ports, starts only partshelf-demo, syncs locked dependencies, applies additive migrations only to its fixed Demo DB, and runs idempotent provision. Private state: `D:\git\PartShelf\.local\demo\accounts.json` and `runtime.env`. If either is lost, do not guess credentials or silently replace an existing database password.

To intentionally discard **only Demo** data and recreate baseline (PowerShell prompts for confirmation):

```powershell
pwsh -NoProfile -File scripts/reset-demo.ps1
```

This removes only the fixed partshelf-demo project's named volumes, keeps the private credentials/env file, then recreates baseline. It does not operate on partshelf-local/Neon/R2/LabSphere. Do not use it to reset DP03.

## Manual UAT / remaining limitations

1. Login Owner/Manager locally. In Excel panel select a Demo-only workbook (private sample `.local/demo/import-fixture.xlsx` was already imported; previewing it again should report duplicate SKU). Use a new explicitly DEMO-prefixed SKU for a new confirm test; do not rename existing records.
2. Observe actual preview/error rows, confirm once, replay same confirmation locally in Network → expect422/no duplicate. Never share Cookie/CSRF/token or unredacted HAR.
3. Click product/count export, save actual files, report local path and open results/expected SKUs/attempt history. Current agent evidence does not claim native browser save PASS.
4. Physical phone, USB/camera/printer and cloud file UAT remain outside the viewport proof. Full reports/offline mode not added.

Latest Owner separately approved1 extra read-only review of the pinned DP03 auth-fix candidate and staging release if accepted, and chose Manual UAT. This authorization is retained; it does not permit shipping this newer Demo workspace to staging or declare manual tests passed. DP03 remains IN_PROGRESS; cloud release must package only its explicitly approved pinned changes.

Subsequently executed that separate bounded DP03 approval: REVIEW4 accepted;115-file pinned release changes only bootstrap auth fix, excludes all Demo changes. Staging READY dpl_EWQdag1ji9DF2T2tczv4os1qWWRo; cloud health200/anonymous user401 verified and DP03 stock/count/audit read back unchanged. No Staging Demo or Demo credentials exist there. Evidence `evidence/DP03_AUTH_RELEASE_RESULT.json`. This does not close DP03 or its Manual UAT.

Final Demo inventory rechecks all3 actual database password hashes against private credentials,28 products/4 categories/3 locations, stock22/4 attempts/2 corrections for browser sample; original local partshelf has0 products. `evidence/DEMO_FINAL_INVENTORY.json`. Some existing list/dropdown views load the first25 records; use SKU search in catalog to find extra QA/import products, and refresh panels after catalog changes. Full pagination remains a known UI limitation.


## Local UX/UI candidate — 2026-10-29 (browser verified, overall IN_PROGRESS)
- Existing Local Demo now has compact lazy routes/sidebar/mobile drawer. No Settings/User module invented. No cloud/Staging/Production/secret/DNS/LabSphere change; no commit/push.
- **PASS:** frontend lint/TypeScript/build; backend full77/402 and focused count image1/4; Pint2 files; real browser10 tests, existing mocked contract2 tests.
- **PASS:**330 loaded route/role/viewport checks including drawer/navigation,99 expanded form/detail checks,11 login layouts (440 total). Required widths320/360/375/390/430/768/1024/1280/1440/1920 plus breakpoints767/769/1023/1025 and844x390 landscape.19 axe scans zero observed violations; no full accessibility certification claimed.
- Real PNG upload/replace/render, product create/edit/search/detail/location, opening/receipt, count/recount/explicit correction, preserved other-row draft, Manager category/location updates, Staff count201 and prohibited receipt403 verified against Laravel/PostgreSQL. Native XLSX import success201/replay422 and local export save/read verified; initial successful import retained in evidence; later previews intentionally report duplicates.
-30 Demo products/4 categories/3 locations retained. Old sample stock22, attempts24/23/22/21 and2 corrections preserved. No reset/cleanup. Previous cloud/manual UAT gaps remain separate.
- **NOT_VERIFIED / completion blocker:** actual mobile OS keyboard and one-handed reach with keyboard open. Optional physical camera/scanner/printer/device font checks remain manual. No Browser permissions changed and no LAN exposure added.
- UX_UI remains **IN_PROGRESS**, not DONE, until required real-mobile UAT result. DP03 still IN_PROGRESS. Frozen21-file candidate UX_LOCAL_20261029_V1; read-only protected review1/3 reserved, verdict pending.
- Evidence: docs/UX_UI_AUDIT.md, docs/RESPONSIVE_TEST_MATRIX.md, docs/evidence/UX_BROWSER_TEST_SUMMARY.json, UX_RESPONSIVE_RESULTS.json, UX_EXPANDED_STATES.json, UX_REAL_API_RESULTS.json, UX_ROLE_OPERATIONS.json, UX_EXCEL_BROWSER_RESULTS.json, UX_FINAL_INVENTORY.json, UX_BUNDLE_RESULTS.json. Screenshots private ignored .local/ux-audit/before and after. No passwords/tokens/cookies in published evidence.


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
