# Smart Stock Notes redesign — Local/Test only

Owner request: 2026-10-30. Branch: codex/smart-stock-notes, working tree based on5a0b747. Implementation and Local verification completed; protected independent review is tracked separately in evidence/simple-stock. Existing Cloud deployment dpl_6F9hPYBuPmqVt3dxZ88u1J9J8bXi/source14cd8d7 is outside this task's write scope. No push or cloud release is authorized by this redesign.

## Product and data rules

- QUANTITY: a missing inventory balance means UNSET (quantity=null), not zero. An existing balance, including explicit0, keeps its exact value. Sale/receive require an initialized balance; Owner/Manager initialize or set the actual quantity explicitly. Quantities use three decimals, cannot become negative, and are recorded as movements.
- AVAILABILITY_ONLY: quantity is always null in the new API/UI/export. IN_STOCK, OUT_OF_STOCK and UNKNOWN are distinct. Status updates record actor/time/history; notes and reorder reminders are supported.
- Quick create requires only name and tracking_mode. Empty SKU generates a unique AUTO-ULID SKU while the existing PS-ProductID barcode stays stable. Base unit defaults to ชิ้น. Category/location/image/fitment/notes are optional. QUANTITY may have optional initial_quantity; omitted is unset.
- Switching QUANTITY to AVAILABILITY_ONLY retains balances/movements as historical data and defaults availability to UNKNOWN unless explicitly selected. Switching back requires an explicit quantity and reason; the difference from retained balance is recorded, never silently overwritten. The UI warns before either change.
- Archive/restore changes archived_at; there is no hard-delete route. Archived items remain in history and reject stock writes. Active and archived lists are separately filterable.
- Existing stock-count tables, attempts and corrections remain intact. Old counting is moved to Advanced/history. New counting is a single Owner/Manager set-actual action with a reason and optimistic version check.

## Permissions

Owner/Manager manage products, metadata, initialization, receipts, sales, actual quantities, mode changes, archive/restore and Excel. Staff may read/search/history/labels, record sales, update availability and reorder notes. Staff cannot receive, initialize/set quantities, change modes, edit catalog, archive/restore, change roles or erase history. Backend enforces every rule.

## API contract

New authenticated namespace /api/stock-notes keeps legacy endpoints available for Advanced functions. GET /products supports search, stock_status, tracking_mode, reorder and archived filters. GET /products/{id}/history returns paginated new events; the UI also reads the existing /api/products/{id}/movements endpoint to display retained legacy movements. POST /products quick-creates; PATCH /products/{id} edits metadata/mode; POST /products/{id}/actions accepts sale, receipt, set_quantity, availability, reorder, archive or restore. Mutations require a UUID Idempotency-Key. Absolute/state edits require expected_version; relative sale/receipt use locked current balances.

Product representation includes id/internal_code/sku/sku_is_generated/name, tracking_mode, quantity (nullable), stock_status (UNSET/UNKNOWN/IN_STOCK/LOW_STOCK/OUT_OF_STOCK), availability_status, min_stock_quantity, location, image, fitments, notes, reorder_needed/note, status_updated_at/by, archived_at and stock_version. History includes before/after, actor/time/reason and movement association.

An append-only product_events table deduplicates request keys and retains request fingerprints/responses. Transactions acquire the same advisory request-key lock as legacy stock operations, lock the product before its balance and reject reused keys with different payloads. Sale/receipt deltas are atomic; absolute set/edit checks the current version after locking. Legacy quantity-changing endpoints must also advance the version and reject archived/availability/unset-incompatible operations.

## Migration and compatibility

Add tracking_mode, availability_status, notes, sku_is_generated, archived_at, reorder_needed/note, status_updated_at/by and stock_version to products; default old products to QUANTITY. Preserve numeric balances and all movements/counts/audits. Add sale to the movement-type constraint. Unknown quantities use absent balance rows, with no backfill to0. Add product_events with product/user/movement foreign keys. Rollback on a baseline clone must restore original shape/data; rollback after new sale/history data must refuse destructive loss rather than erase events or invalidate movements.

Before migration: private Owner-only pg_dump backups of existing PartShelf Local/Demo; no cloud access. Rehearse up/down/up against a separate clone and compare original column/value and history fingerprints. Automated tests run against partshelf_test inside a new isolated partshelf-simple Docker project, not the existing test or demo databases.

## UX and Excel

The default workspace becomes a compact product list with search, status/mode/reorder filters, quick create, readable location and inline actions (quantity default1). Unset and UNKNOWN have distinct labels. Request keys survive a network retry of the same action; changed input starts a new action. A409 conflict refreshes data and asks the user to review it. Legacy counting/history stays reachable under Advanced. White/blue mobile-first layout; no purchase order, supplier, ERP or POS workflow.

Excel keeps old six-column files readable and adds tracking_mode, quantity, availability_status, notes, reorder_needed/note. Missing quantity stays unset; availability-only exports blank quantity. Import previews validation before confirmation, rejects duplicate existing SKUs and never bulk-overwrites existing products.

## Verification plan

Red/green PostgreSQL feature tests for all Owner's18cases, permissions, idempotent retry and stale absolute edits; two real simultaneous clients for sale/receipt/set conflicts. Migration/rollback/fingerprints on a clone, complete relevant backend regression, lint/typecheck/build and real Local Browser/Desktop/Mobile-emulation tests with screenshots. Physical-device UAT remains separate if unavailable. No old Cloud result is used as a PASS for this redesign.

Execution is parent solo with one read-only protected reviewer at candidate freeze. New assurance unit SIMPLE_STOCK_20261030 concerns this new Owner-requested product/data redesign; it does not reset or reuse the completed Cloud unit's5calls. Review target1, hard cap3. The optional Superpowers package is recorded missing by project capability inventory; use explicit red-green-refactor and evidence-backed completion directly without installing a plugin or adding permissions.

## Local operation and release boundary

Use only http://127.0.0.1:5178 for this branch. Its API is http://127.0.0.1:8085 and PostgreSQL is loopback5445. compose.simple.yaml reuses existing images with independent volumes and the partshelf_simple database. Private runtime settings and three unique test passwords live under .local/smart-stock with Owner-only ACL; none are in source or reports. Existing Local/Demo database backups are also private there.

Windows PowerShell can start this already-prepared runtime with the existing Docker Desktop CLI:

```powershell
& "$env:LOCALAPPDATA/Programs/DockerDesktop/resources/bin/docker.exe" compose --env-file .local/smart-stock/runtime.env -f compose.simple.yaml -p partshelf-simple up -d
```

Do not run migrations against the original Local/Demo or Cloud databases. Original5175/5177 services share the backend source bind mount and have not received this additive schema; they are not the acceptance URLs for this branch. Return to an approved matching source/runtime through normal reviewed branch handling before using those old URLs; never overwrite pending work. The new loopback-only URL is unavailable from a physical phone without a separately approved access/release arrangement. Browser touch/viewport emulation does not verify a real mobile keyboard, camera, scanner or printer.

Images are optional in Quick Add/Edit; one fitment can be entered during quick creation. Multiple fitments remain editable in Advanced catalog. Excel accepts original6headers and new12headers; SKU/base_unit/min_stock_quantity remain required for spreadsheet rows, while Quick Add requires only name/mode. Import creates new SKUs and does not overwrite existing stock. Export excludes archived products; their history remains in the database/UI archive view.

Production/Staging/DP03 were not contacted or changed by this redesign. A later release needs Owner acceptance, a fresh release scope and a separate backup/migration plan for that exact target. Physical Mobile UAT and Cloud validation of this new version are NOT_VERIFIED.

Mutation replay is checked before mutable product-state validation. SKU0 is a valid custom SKU; only an explicitly empty trimmed string generates an automatic SKU. A consumed image upload can replay its committed actor/payload-bound event even after expiry, but cannot authorize another write. Legacy object upload behavior remains one-use; live R2 verification is deferred.
