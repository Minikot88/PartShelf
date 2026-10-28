# PartShelf Local Guide

This guide is for the single-shop local Docker Compose installation. It does not cover production deployment.

## Start the local application

From the project root, run:

```powershell
./scripts/start-local.ps1
docker compose ps
```

The start script checks configured host ports before starting services. Default URLs are:

- Web: `http://127.0.0.1:5175`
- API health: `http://127.0.0.1:8083/api/health`
- PostgreSQL is loopback-only on port `5442`.

Create a named login through the interactive command inside the API container. The application does not seed a default user:

```powershell
docker compose exec api php artisan partshelf:user:create
```

Use Owner for account provisioning and controlled stock correction, Manager for routine catalog/stock work, and Staff for lookup/count workflows.

## Core workflow

1. Create products with a unique SKU; add categories and optional fitment.
2. Create Zone/Shelf/Level/Bin locations and assign products.
3. Set opening stock once, then record receipts as stock arrives.
4. Start a count for the shop or one location, scan/type each product code, and save the observed quantity. A count does not change stock.
5. Owner/Manager reviews a variance and records a separate reasoned correction when needed.
6. Use the product label panel to select products and print basic Code128/QR labels. The PWA requires a network connection; it does not cache shop data for offline use.

## Local database backup

Backups are PostgreSQL custom-format archives. Store them outside the source folder in a protected location; they contain shop and account data.

Back up the local shop database without changing it:

```powershell
./scripts/backup-local-db.ps1 -Database partshelf -OutputPath "$env:USERPROFILE\Documents\PartShelf-backup.dump"
```

To verify backup/restore safely, target the isolated test database:

```powershell
./scripts/backup-local-db.ps1 -Database partshelf_test -OutputPath "$env:TEMP\partshelf-test.dump"
./scripts/restore-local-db.ps1 -Database partshelf_test -BackupPath "$env:TEMP\partshelf-test.dump"
```

Restore replaces the selected local database after PowerShell confirmation. The script validates the archive and requires its recorded source database name to match the selected target. `partshelf_test` is the default restore target. Restoring to `partshelf` requires explicitly selecting that target and confirming the destructive action. Never use a shop backup as a test database fixture.

## Owner acceptance checklist

- Sign in with the Owner account; confirm Staff cannot access catalog editing or stock correction.
- Create a product, assign a location, and verify product movement between locations does not change its stock balance.
- Record an opening balance and receipt, then check movement history and low/out-of-stock labels.
- Count a shelf, record zero and positive quantities, and verify stock is unchanged until a separate authorized correction.
- Recount one product and confirm both attempts and their variances remain visible.
- Import a small workbook using preview first; confirm rejected rows do not import. Export and reopen a workbook.
- Resolve the stable internal product code after changing the SKU; print one sample label and inspect QR/Code128 readability on the intended printer.
- On a supported device, confirm the browser offers installation and that camera scanning is optional; USB keyboard-wedge or typed code remains available.
- Verify Staff sees only the permitted dashboard summary and cannot see variance details.
- Complete an isolated `partshelf_test` backup/restore drill before relying on a backup as a recovery copy.

Physical camera, printer output, and browser-specific PWA installation still require owner-side device acceptance. Keep a verified backup before any restore to the shop database.
