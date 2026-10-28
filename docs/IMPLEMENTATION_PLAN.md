# PartShelf — Implementation Plan

> **Product:** PartShelf — auto-parts stock and simple inventory count  
> **Plan version:** 1.2 — reconciled with the 83-item register and Owner Master Execution Prompt  
> **Updated:** 2026-10-28 (Asia/Bangkok)  
> **Owner scope/phase decision:** Owner explicitly authorized P00–P12 local/test delivery; P00–P12=`DONE`.  
> **Execution boundary:** Local/Test only. No production deployment is authorized.

The separate `PRODUCTION_DEPLOYMENT_PLAN.md` records the Owner-approved Vercel Container Beta/Neon/private-R2 architecture and DP01 local preparation. DP01 local verification passed; cloud provisioning/deployment still awaits approval. Completed P00–P12 local acceptance remains recorded.

## 1. Approved product boundary

The MVP has four business domains: **Products, Stock, Storage Locations, and Simple Counting**, plus the explicitly approved support features: Excel import/export, barcode/QR/basic labels, online-only PWA, vehicle fitment, and a summary dashboard. Workflow: find a product or select a shelf → see current balance → enter actual count → save variance → continue.

- One shop/local deployment first. No sales, payment, branches/transfer UI, offline PWA, full reporting, claims, suppliers, customers, pricing, or advanced count workflows in MVP.
- Minimal login, roles and audit are technical safeguards for the four domains, not separate business modules.
- A count never changes the stock balance automatically. Corrections are separate, permission-checked stock movements with a reason.
- Detailed scope and exceptions: `MVP_SCOPE.md`. Requirement-by-requirement mapping: `REQUIREMENTS_REGISTER.md`.

## 2. Architecture proposal

| Layer | Choice | MVP boundary |
|---|---|---|
| Frontend | React + Vite + TypeScript; Tailwind | Thai-first responsive web/PWA, online-only; camera scan when supported |
| Backend | Laravel REST API, modular monolith | Validation, minimal auth/RBAC, stock rules and audit |
| Database | PostgreSQL | Products, location, balance, movements and count records |
| Local environment | Docker Compose | Local development only; no cloud deploy |
| Product files | Local storage adapter | Optional single product image and basic generated/exported files; no S3/cloud setup in P01 |

No microservices, Kubernetes, message broker or managed external service in MVP.

### 2.1 Data integrity rules

- `product_id` is stable; SKU is unique and may change without breaking internal relationships.
- Product has one location in the single initial shop.
- Opening stock, receipt, receipt reversal and correction create ledger movements transactionally.
- Balance must not go below zero. No sale or stock deduction by sale is implemented.
- Count records capture a reference quantity/time; movement during a count must not create a false variance.
- `NOT_COUNTED` differs from `COUNTED_ZERO`; recount attempts are retained separately and never summed.
- Count save does not change the balance. Owner/Manager correction requires a reason and audit evidence.

### 2.2 MVP entities (proposal, not implemented)

`products`, `categories`, `storage_locations`, `inventory_balances`, `stock_movements`, `stock_counts`, `stock_count_items`, `stock_count_attempts`, `users`, `audit_logs`.

## 3. Phases and verified status

**Status vocabulary:** `TODO`, `IN_PROGRESS`, `DONE`, `BLOCKED`, `ON_HOLD`. `DONE` requires fresh evidence in `TEST_RESULTS.md`; planning or file presence alone is insufficient.

| Phase | Work | Dependency | Status | Acceptance evidence |
|---|---|---|---|---|
| P00 | Scope, register reconciliation, architecture, plan and tracker | — | `DONE` | 83 unique rows checked; new counts and crosswalk match Owner Master Prompt, MVP Scope, Plan and Future Backlog; evidence recorded in `TEST_RESULTS.md`. |
| P01 | Local application bootstrap with Docker Compose | P00 | `DONE` | React/Vite UI, Laravel API and PostgreSQL are running healthy locally; API and frontend proxy return DB-backed health. Ports 5175/8083/5442 bind to loopback. Frontend/API/PostgreSQL start locally; API health and DB connection pass; frontend reaches API; no secret committed. Inspect ports first; no collision; record actual mappings. |
| P02 | Schema and migrations | P01 | `DONE` | Fresh PostgreSQL migration passes; SKU/location path uniqueness, foreign keys and non-negative stock/count constraints verified by feature tests and PostgreSQL catalog. |
| P03 | Minimal login and permissions | P02 | `DONE` | Sanctum session-cookie/CSRF login/logout, hashed passwords, rate-limited login, Owner/Manager/Staff backend guard, secure CLI provisioning; test DB isolation and protected review verified in `TEST_RESULTS.md`. Correction mutation remains P09. |
| P04 | Products, categories and basic vehicle fitment | P03 | `DONE` | Authenticated API/UI, case-insensitive unique SKU, category hierarchy/uniqueness, image upload, vehicle fitments, search and audit; 11 focused tests / 50 assertions and candidate checks pass in `TEST_RESULTS.md`. |
| P05 | Storage locations | P04 | `DONE` | Authenticated CRUD/search; case-insensitive path uniqueness; one location per product; product assignment audits changes and does not alter balance. Evidence in `TEST_RESULTS.md`. |
| P06 | Opening stock, receiving and balance | P05 | `DONE` | Opening balance/receipt produce correct ledger and balance; row-locked transaction, idempotency, no negative or duplicate operation; history/low-stock values readable. Evidence in `TEST_RESULTS.md`. |
| P07 | Excel product/count import/export | P04, P06 | `DONE` | OpenSpout one-sheet template; product import preview, duplicate/error rows, validation, product export and latest count-results export; no bulk edit or sales export. Focused/full verification evidence in `TEST_RESULTS.md`; authenticated browser flow is part of P11. |
| P08 | Simple count flow | P06 | `DONE` | Start immediately or choose shelf; show reference balance; search/keyboard-wedge scan; enter quantities including zero; counted/not-counted/not-found states; never change stock. Evidence in `TEST_RESULTS.md`. |
| P09 | Variance, recount, correction and audit integrity | P08 | `DONE` | Movement-ID watermarks keep each attempt coherent with movements; separate attempts/variances; Owner/Manager correction with reason/audit; Staff denied; row locks, idempotency and non-negative balance guard. Evidence in `TEST_RESULTS.md`. |
| P10 | Barcode/QR, basic labels and online-only PWA | P04, P08 | `DONE` | Stable product ID scan mapping survives SKU edits; USB/typed input and camera where supported; basic A4/label output; installable online-only PWA; no designer/offline mode. Focused API and static/build checks recorded in `TEST_RESULTS.md`; real browser install/camera/print acceptance is included in P11. |
| P11 | Summary dashboard, integration and browser E2E | P04–P10 | `DONE` | Role-aware counts, low/out stock, recent variances/counts implemented; 59/306 backend suite, frontend lint/build and headless E2E pass. Mocked browser flow covers login, count, stable scan code, QR/Code128, print media, unsupported camera and install prompt. Full reports remain Future. |
| P12 | Local backup/restore and Owner UAT | P11 | `DONE` | Local custom-format backup/restore scripts; isolated restore evidence and usage/UAT guide complete. Owner confirmed the `LOCAL_GUIDE.md` acceptance checklist passed in chat on 2026-10-28. No production deployment. |

## 4. P00 register reconciliation

The complete row mapping for requirements 1–83 is in `REQUIREMENTS_REGISTER.md`. The revised categories are `MVP`, `MVP-PARTIAL`, `FOUNDATION`, `FUTURE`, and `SCOPE-LIMIT`. Partial rows are split explicitly to avoid silently carrying Excel, scanning, PWA, cloud, or multi-branch scope into MVP.

Key conflicts resolved by the latest Owner Master Execution Prompt:

- Specified Excel template/import/export (#18 partial, #83) → MVP; bulk edit and sales/PDF reporting remain Future.
- Barcode/QR, supported USB/camera scan, basic labels and stable lookup (#6, #55, #58, #59) → MVP; custom designer remains Future.
- PWA/install (#27) → MVP online-only; offline, sync and push remain Future.
- Basic vehicle fitment (#5) and role-filtered summary dashboard (#63) → MVP; compatibility recommendations and full reports remain Future.
- Scheduled/assigned count cycle (#50), carry-forward/closure (#80–81) → Future; immediate or shelf-based simple count (#82) is MVP.
- Sales and payment (#2, #8, #10–12, #35–37, #52–54, #67–72) → Future; stock receipt/correction remains in scope.
- Full reports (#15), automated backup/retention (#30–31), broad multi-branch, claims, compatibility recommendations, costing, bundles and advanced unit conversion → Future or production readiness.

## 5. Critical MVP checks

1. Duplicate SKU rejected; stable product identity remains after SKU edit.
2. Product has at most one storage location in the single shop.
3. Opening 20 → receive 5 → balance 25 and ledger sum 25.
4. Count 23 against balance 25 → variance -2; balance remains 25.
5. Correction is a distinct authorized movement with reason/audit; Staff is denied.
6. Not counted is never treated as zero.
7. Recount 22 remains a separate attempt from count 23.
8. Receipt during count does not produce a comparison against a mismatched reference time.
9. Receipt reversal/correction cannot make stock negative or duplicate on retry.

Sales/finance, full reports, offline PWA, custom label design, bulk edit and production deployment are not part of MVP acceptance.

## 6. Tracking and authority

- `PROGRESS.md` is the current phase ledger; `TEST_RESULTS.md` is the evidence log; `DECISIONS.md` records scope and Owner decisions.
- Work one phase per Owner instruction. Set a phase `IN_PROGRESS` only after authorization; do not skip dependencies.
- P01–P12 are authorized for local/test only. Immediately before binding any service, inspect current listeners and configured ports; choose explicit free host mappings, give Compose a project-specific name, and never stop unrelated services to resolve a collision.
- No production deployment, push, destructive data operation, migration of real data, or new external integration without separate explicit approval.
- P00–P03 acceptance evidence is recorded. Owner authorized continuous P04–P12 execution in the attached Master Execution Prompt; no additional phase approval is required. No production deployment is authorized.
