# PartShelf — fresh Local runtime verification

Verified: 2026-10-29, Asia/Bangkok. Execution: solo; operations and verification only. Application source behavior was not changed. Current result: **DEMO_PARTIAL**, with working Local/Demo runtime and passing tested Chromium workflows. Physical mobile keyboard UAT remains NOT_VERIFIED. Staging and Production were excluded; DP03 remains IN_PROGRESS.

## Runtime and data provenance

The execution shell is Windows PowerShell Core 7.6.5 on Windows NT 10.0.26300, machine DESKTOP-LH8GTKT. No WSL distro or PowerShell remote session was reported. The shell is restricted by the Codex sandbox. Docker's per-user CLI exists, but its directory was absent from PATH. Direct sandbox access to the Docker named pipe returned permission denied. System-approved Host execution succeeded against the existing desktop-linux context: Docker Desktop 4.97.0, Engine 29.9.2, Compose 5.6.1. Docker was already running; no installation, context change or Desktop restart was required.

Before startup, the current Engine had no PartShelf containers or named volumes. It contained shared-test-db, which was left untouched. Images were built from the existing Dockerfiles, and scripts/start-local.ps1 started the existing Local Compose stack. Demo was started through compose.demo.yaml without invoking start-demo.ps1, whose unconditional migration was unsuitable before inspecting database contents.

Both partshelf and partshelf_demo were independently read through PostgreSQL and had **zero public tables** before initialization. Migrations ran only after a second zero-table guard. Existing private Demo credentials were reused by the guarded provisioner in partshelf_demo only, yielding 3 accounts and 25 seed products. This initializes empty databases in the current Engine; it **does not recover historical Demo records** described in older reports. No reset, volume removal, populated-database migration, shared DB write, cloud operation or Git publication occurred. Existing modified/untracked work was preserved.

| Runtime | Fresh result |
|---|---|
| Local frontend http://127.0.0.1:5175/ | PASS HTTP 200; container healthy |
| Local API http://127.0.0.1:8083/api/health | PASS HTTP 200, database ok; container healthy |
| Local PostgreSQL 127.0.0.1:5442 | PASS database partshelf; 0 users and 0 products after initialization |
| Demo frontend http://127.0.0.1:5177/ | PASS HTTP 200 and actual browser access |
| Demo API http://127.0.0.1:8084/api/health | PASS HTTP 200, database ok; container healthy |
| Demo PostgreSQL 127.0.0.1:5443 | PASS partshelf_demo / partshelf_demo; container healthy |

Demo web has no configured Docker healthcheck; its readiness was proved by HTTP and browser checks. Do not interpret its lack of a `(healthy)` annotation as failure. Accounts owner.demo, manager.demo and staff.demo exist with their actual backend roles and passed browser login, refresh, logout and protected-after-logout 401. Passwords remain in the existing ACL-protected, Git-ignored D:\git\PartShelf\.local\demo\accounts.json; no passwords, cookies or tokens are included in published evidence.

## Fresh functional matrix

PASS in the three role columns describes the operation stated in that row. A passing authorization denial does not grant the denied permission. N/A is NOT_APPLICABLE.

| Page/function | Owner | Manager | Staff | Result | Problem / evidence limit |
|---|---|---|---|---|---|
| Login, refresh, logout, protected access after logout | PASS | PASS | PASS | PASS | Real browser and API |
| Wrong password / selected role cannot elevate Staff | N/A | N/A | PASS | PASS | HTTP 422 and still anonymous |
| Dashboard | PASS | PASS | PASS | PASS | Real loaded data and responsive matrix |
| Product list / detail / SKU search | PASS | PASS read | PASS read | PASS | Detail expanded and actual API reads |
| Product create / edit / PNG upload / replace | PASS | N/A | PASS denial | PASS | Owner UI save/render; Staff POST 403 |
| Categories | PASS read/form | PASS update | N/A | PASS | Manager saves existing Demo values unchanged |
| Locations / product assignment | PASS assignment | PASS update | PASS read | PASS | Actual backend responses |
| Stock overview / movement history | PASS | PASS read | PASS read | PASS | SQL readback and browser |
| Opening balance / receiving | PASS | N/A | PASS denial | PASS | Opening 8; repeat rejected; receiving changes balance |
| Count / recount / zero / unsent other-row draft | PASS | PASS recount | PASS count | PASS | Counts preserve stock; attempts remain separate |
| Variance / reasoned stock correction | PASS | PASS | PASS denial | PASS | Owner/Manager API correction; Staff 403 |
| Excel preview / validation / confirm / replay / export | PASS | PASS controls | PASS denial | PASS | Browser upload/save; separate XLSX content reader |
| Barcode / QR / internal-code lookup | PASS | PASS layout | PASS layout | PASS | Code128 SVG and QR canvas rendered; real resolve 200 |
| Print layout / A4 PDF | PASS | N/A | N/A | PASS | Print media PDF; physical print not verified |
| Drawer / desktop navigation / responsive | PASS | PASS | PASS | PASS | 330 route-role-viewport checks |
| Settings / user management / full analytical reports | N/A | N/A | N/A | NOT_APPLICABLE | Outside implemented MVP |
| Physical mobile OS keyboard / native dropdown / reach | N/A | N/A | N/A | NOT_VERIFIED | No physical device used |
| Physical camera / USB scanner / printer / PWA install | N/A | N/A | N/A | NOT_VERIFIED | Device acceptance remains manual |

Role columns marked N/A in a write row mean that exact role/write combination was not a distinct acceptance case in this run, not that the role necessarily lacks that right. No assertion of exhaustive role-by-endpoint authorization coverage is made.

## Counts and test boundaries

- 9 distinct views: Login and 8 workspace routes (dashboard, products, categories, locations, stock, count, excel, labels).
- Live API: **45 checks PASS**, real Vite → Laravel → PostgreSQL; no mocked target.
- Browser E2E: **10 unique scenarios PASS** across four batches. Two initial label-test failures were test assumptions (screen-hidden print sheet, then an ambiguous locator); both were corrected and the final label scenario passed. No application defect was confirmed by those failures.
- Responsive: **440 checks PASS** = 330 route/role/viewport + 99 expanded states + 11 login widths. Required widths 320/360/375/390/430/768/1024/1280/1440/1920 included, plus breakpoints and landscape. No measured document overflow or pageerror in the route matrix. 18 automated axe scans had zero observed violations; this is not an accessibility certification or a physical-device result.
- Functional API + browser scenarios: 55 PASS, 0 unresolved FAIL, 0 BLOCKED. Layout checks are reported separately because they overlap browser scenarios. Five physical acceptance areas remain NOT_VERIFIED: mobile keyboard/reach, camera, USB scanner, physical printer and PWA installation.
- Unit/integration regression suites, lint and build validation commands were NOT_RUN in this restoration task; application source was not changed. Docker image builds completed. Synthetic mock-based UI failure tests were excluded from fresh live E2E results.

## Database and file evidence

Fresh SQL readback, saved under docs/evidence/RESTORE_SQL_FINAL.txt, shows 28 products, 3 corrections, zero negative balances and no duplicate SKU groups. The API fixture DEMO-QA-1793267673057 has final stock 13.000 and attempts 11.000/14.000/13.000. Receiving during that count was included; count/recount did not mutate stock before explicit correction. The Browser product DEMO-UX-20261029 has final stock 3.000 and attempts 4.000/3.000. Imported DEMO-UX-IMPORT-20261029 has opening stock 8.000. DEMO-001 stays 0.000 after zero-count saves. These marked fixtures were retained.

Actual native XLSX preview had one valid and one invalid row. Confirm succeeded; replay returned 422. The Browser-saved products.xlsx was separately opened by OpenSpout: 27 data rows at export time, six expected headers, and both DEMO-001 and the imported SKU present. One Browser product was created after that export, so final database count is 28. PNG upload/replace/render passed. Cloud R2 was not accessed in this task.

Evidence: RESTORE_HEALTH.json, RESTORE_CONTAINERS.txt, RESTORE_RUNTIME_RESULTS.json, RESTORE_BROWSER_LOGIN.json, RESTORE_RESPONSIVE_RESULTS.json, RESTORE_UX_EXPANDED_STATES.json, RESTORE_UX_ROLE_LOGIN_RESULTS.json, RESTORE_UX_ROLE_OPERATIONS.json, RESTORE_UX_REAL_API_RESULTS.json, RESTORE_UX_EXCEL_BROWSER_RESULTS.json, RESTORE_XLSX_READBACK.json, RESTORE_LABELS.json, RESTORE_SQL_INITIAL.txt, RESTORE_SQL_FINAL.txt and RESTORE_LOCAL_DB.txt, all under docs/evidence/.

Private screenshots: D:\git\PartShelf\.local\restore-verification\screenshots and after. Browser batch reports: browser-batch1.json through browser-batch4.json in that private folder. Reporters retain the initial label failures. Runtime scripts are in the same private folder; the reproducible Browser suite/config is frontend/e2e/restore and frontend/playwright.restore.config.cjs. Browser auth state is private and must never be published. Two screenshots (mobile count and desktop products) were visually inspected by the agent; remaining screenshots have automated layout evidence, without a claim of individual visual review.

## Windows Host commands and next action

Run in Windows PowerShell if the Codex sandbox cannot reach the named pipe:

```powershell
Set-Location D:\git\PartShelf
$env:PATH = "$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin;" + $env:PATH
docker version
docker info
docker compose version
docker compose ps
docker compose --env-file .local/demo/runtime.env -f compose.demo.yaml -p partshelf-demo ps
```

If Docker Desktop is stopped, open Docker Desktop from Windows Start and wait for Engine running, then repeat the checks. Do not reinstall Docker or change context based on a sandbox error. The stacks are running at handoff. To start already-initialized stopped services without migrations:

```powershell
docker compose up -d --wait
docker compose --env-file .local/demo/runtime.env -f compose.demo.yaml -p partshelf-demo up -d --wait
```

Perform physical mobile keyboard UAT on an explicitly approved device connection: focus search/count/recount/reason fields, open the native dropdown, verify fields/buttons remain visible with the OS keyboard open, and assess one-handed counting. Keep loopback binding until access changes are separately authorized. Complete physical scanner/camera/printer/PWA acceptance as applicable. Existing historical data recovery needs a separately identified backup; this run did not establish that a backup exists.

**Final decision:** Local runtime restored; tested Demo browser/API/database behavior PASS. Overall **DEMO_PARTIAL** pending required physical mobile UAT. Local port 5175 is a clean empty shop runtime, while the tested Demo with accounts is port 5177. Staging/Production: excluded, unchanged, and not attested by this run.

