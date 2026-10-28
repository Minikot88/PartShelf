# PartShelf — MVP Scope

**Scope status:** Owner approved Master Execution Prompt local/test development through P12 on 2026-10-28; scope revision reconciled in P00.  
**Implementation status:** P00–P12=`DONE`. Owner confirmed P12 UAT passed on 2026-10-28; see `TEST_RESULTS.md`.  
**Last updated:** 2026-10-28 (Asia/Bangkok)

## Product goal

Replace the shop's Excel-based auto-parts stock checks with a short workflow: find a product or choose its storage location, see the recorded balance, enter the physical quantity, save the count and variance, then continue to the next product.

## MVP boundary

The user-facing MVP serves four business domains, with the additional supporting capabilities explicitly authorized by the latest Owner Master Execution Prompt:

1. **Products:** create, edit, search, and categorize individual products; user-defined unique SKU, name, optional part number, base unit, one optional image, and fitment by make/model/year/engine.
2. **Stock:** opening quantity, simple receiving, current non-negative balance, stock movement history, basic minimum-stock value, and a separately authorized correction with a reason.
3. **Storage locations:** Zone → Shelf → Level → Bin; assign one location to each product in the single initial shop; find products by location and correct a location without changing stock.
4. **Simple counting:** start immediately or choose a shelf; find products by SKU/name or shelf list; scan when supported; show the reference balance; enter physical quantity; save count/time/counter/variance; distinguish not counted from counted zero; preserve a recount separately. A count never changes stock automatically.

Supporting capabilities included by the latest Owner instruction:

- **Excel:** single-sheet template, product import with preview/row validation/duplicate detection, product export and count-result export. Bulk edit and sales/report exports are excluded.
- **Barcode/QR and basic labels:** map one scan code to stable internal product ID, USB scanner and camera where supported, basic A4/label-printer output. No custom designer.
- **PWA:** installable, online-only responsive web app; no offline cache, sync or push notifications.
- **Dashboard:** product count, stock quantity, low/out-of-stock products, count variances and recent counts, filtered by role. Full analytics/reports remain excluded.

## Minimum foundations (not additional business modules)

- Local-first development using Docker Compose and a local browser, once P01 is authorized.
- A single initial shop. No multi-branch UI or transfers.
- Minimal individual login and Owner / Manager / Staff permissions needed to protect stock corrections and role-specific dashboard data.
- Audit the stock corrections and other critical edits that are in these four domains.
- Thai-first, responsive basic web UI; PWA installation and supported camera access are in scope, but offline work is excluded.
- Stable internal `product_id`; SKU remains a unique, editable business code.

## Explicitly outside MVP / Future Backlog

- Sales, POS, payments, receivables, returns/refunds, sales receipts, pricing and discounts.
- Bulk product editing, custom label designer, label templates beyond basic output.
- Full inventory/sales reporting, PDF exports, bulk export beyond product/count outputs.
- PWA offline operations, background sync and push notifications.
- Multi-branch operations/UI, transfers and consolidated reports. A branch-ready schema is not required for the first local single-shop release unless later approved.
- Advanced counting schedules, assignments, multi-step closure/approval, carry-forward workflows, temporary unknown-product intake, and count dashboards beyond the simple count list/history.
- Supplier/customer registries, warranties/claims, substitute parts, lifecycle/replacement links, bundles/kits, multiple images, unit conversion, costing/margins and advanced fitment recommendation.
- Automated daily backup/7-day retention is a production-readiness item, not an MVP business feature; restore safety must still be addressed before production use.
- Cloud/VPS deployment. P01 is Docker/local only; no production deployment is authorized.

## Data integrity and safety rules

- Use stable `product_id` foreign keys; prevent duplicate SKU.
- Keep balances non-negative. Do not provide sales/stock deduction features in MVP.
- Record opening stock, receiving, reversals and authorized corrections as stock movements; do not silently overwrite an established balance.
- A count stores an observation and variance only. It never changes stock balance by itself.
- Keep the reference balance/time coherent with movements during the count.
- `NOT_COUNTED` is distinct from `COUNTED_ZERO`; a missing item must not become zero merely because it was not visited.
- Store recount attempts separately; never add attempts together.
- Enforce correction permissions in the backend and preserve reason/audit evidence.

## Scope decisions

- The Owner Master Execution Prompt supersedes the earlier four-domain-only feature boundary for its explicit supporting capabilities: specified Excel import/export, barcode/QR/basic labels, online-only PWA, vehicle fitment and the summary dashboard are now MVP. Sales/finance, custom workflows/designers and full reports remain Future Backlog.
- Requirements may be split when a row combines an in-scope capability with a deferred one. See the full 83-item mapping in `REQUIREMENTS_REGISTER.md` and evidence in `TEST_RESULTS.md`.
- Docker/local is the development target. Port availability must be inspected immediately before services bind; no occupied unrelated service may be stopped to free a port.
- Owner authorized P00–P12 local/test implementation in the Master Execution Prompt. Production deployment remains excluded.

See `DECISIONS.md` for decisions and approval record.
