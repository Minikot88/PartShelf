# PartShelf Cloud Demo handover — 2026-10-29

**FINAL STATUS: BLOCKED. Latest Demo has not been deployed to cloud.** Existing DP03 deployment is not the requested latest Demo and must not be presented as its delivery URL. Owner has authorized Preview/Staging only, including safe Demo preparation; Production and LabSphere are excluded.

Source commit: 14cd8d7a623d1194eba713aefd15c04704f79250 (latest Demo plus reviewed Cloud preparation). Documentation may be committed separately without changing this runtime source.

## Verified preparation

- GitHub main and local base are both f8839e171725ebdfe33dffb0139b580fb5dc7ccd. Latest Local Demo contains modified/untracked source beyond that base. Work is isolated on codex/cloud-demo-20261029; main was not overwritten.
- Runtime snapshot CLOUD_DEMO_PREP_20261029_V2: 120 files, manifest SHA256 6f00beff1dd0ff205ae3d739c83484d03c0439aec24d1b5fbb2ebe559b0d0ea0. Details: docs/evidence/CLOUD_DEMO_SOURCE_MANIFEST.json. No older DP03 snapshot was deployed in place of latest source.
- Backend full regression: 81 tests / 410 assertions PASS on local partshelf_test after the DB_URL fix; final focused boundary tests: 5 / 14 PASS, including an additional active role/schema rejection case. These are local tests, not cloud acceptance. First full invocation inherited APP_ENV=local and failed 10 tests; rerun explicitly used APP_ENV=testing. Both results are retained. Frontend lint and TypeScript/Vite production build PASS; PHP Pint PASS.
- Cloud Demo username login now requires production app mode on Vercel Preview, pgsql, dedicated schema partshelf_demo and role partshelf_demo_runtime; DB_URL overrides are rejected. Local/testing behavior remains supported. Provisioning additionally checks the active role/schema before writes. ProductionConfiguration security middleware remains in place. DB_SCHEMA defaults to public for existing deployments. Private .local is now excluded from Vercel uploads.
- Reviewer attempt 1 found the DB_URL isolation gap. A new test failed before the guard was corrected; the regression now passes. Review attempt 2 ACCEPTS the frozen preparation candidate; its result is stored in docs/assurance/CLOUD_DEMO_REVIEW_2.json. This does not certify a cloud release.

## Actual cloud readbacks

| Resource | Fresh result |
|---|---|
| Vercel | Exact project prj_9pZ790dvjdRIcZrBe3BxqkXTdjXu, team team_oLV66sa4oVrdF5ux9pds0UbP, partshelf-staging confirmed |
| Current deployment | dpl_EWQdag1ji9DF2T2tczv4os1qWWRo, READY, target staging; older DP03 auth-only release |
| Production placeholder | partshelf-staging.vercel.app still points to dpl_GTr74rC3GHHCXPPHic1yBiavofiE; unchanged |
| Preview env metadata | 30 Sensitive variables, each Preview-only; hidden Production env count 0; secret values not printed |
| Neon resource | partshelf-staging-db, little-wind-47225058, store_xycTwG0dVKpKtiZ7, available/ready, sin1, Free metadata |
| Neon live SQL | Current role partshelf_runtime, DB neondb, PostgreSQL 18.6; CREATE database/schema privileges false; only public schema in proposed scope |
| DP03 fixture | DP03-BROWSER-20261028 balance 14.125, 4 movements, 2 attempts; 3 users, 1 product and 1 correction remain |
| R2 | partshelf-staging-files, exact account 604dcc43369dc5b9d8dede5d08422280, APAC Standard; r2.dev disabled; no custom domains |
| R2 CORS | Only the existing DP03 branch origin is allowed; Demo origin has not been added |

Neon PDO connected using sslmode=verify-full and the system CA. The SQL pg_stat_ssl view on the pooled backend returned ssl=false/version=null; this view is not evidence of client-to-pooler TLS negotiation. No claim of independently measured client TLS version is made in this run. Queries were in a READ ONLY transaction and no cloud DDL/DML was performed.

## Concrete blocker and safe continuation

Only the existing least-privilege runtime credential is available locally. It cannot create a schema or run migration DDL. The previous Neon admin credential file is absent. The Vercel storage connector exposes secret metadata, not usable administrator secret values. Creating Demo accounts/products in public would mix datasets and expose DP03 through the global catalog/count routes, so that fallback was not used.

Owner was asked to place the administrator connection URL for **this exact Neon staging resource** in:

D:\git\PartShelf\.local\cloud-demo\neon-admin-url.txt

The directory is protected by an Owner-only ACL and Git-ignored. Do not paste the connection URL into Chat, Markdown, logs, source or a public form. Do not rotate any existing account simply to resolve this blocker.

Once access is available, validate the exact host/database against the current staging resource before any write. Create an isolated partshelf_demo schema and a dedicated non-owner runtime role with access only to Demo tables/sequences and no public table privileges. Apply migrations with search_path restricted to the new schema and no public fallback. Verify direct SELECT public.users is denied to the Demo role and compare retained DP03 fixtures before/after. Never grant DDL to the application runtime role.

Provision fresh, distinct random Demo passwords only into the isolated schema. Do not reuse Local Demo passwords or DP03 verification accounts. The existing provisioner supplies 25 baseline products; add three clearly marked Cloud QA/import products during functional verification to reach 28 or use an equivalent reviewed 28-product dataset. No cloud accounts or passwords have been created yet.

Before release, freeze/review any migration/provisioning runner and final payload, scan the exact staged/uploaded files for secrets, and record the immutable source commit. Configure only Preview-scoped DB/schema/session/cache/origin settings. Preserve the existing APP_KEY and private R2 credentials. Add only the verified Demo HTTPS origin to R2 CORS while retaining the DP03 origin; use a separate staging alias. Submit explicit target=staging, cancel immediately if the returned target is production, then verify READY/health/HTTPS/session and alias identities. Do not reassign the Production placeholder.

## Cloud acceptance — not performed

| Test area | Status |
|---|---|
| Owner / Manager / Staff cloud login and logout | NOT_VERIFIED |
| Dashboard / 28 products / SKU / categories / locations | NOT_VERIFIED |
| Receiving / counting / recount / variance / correction / permissions | NOT_VERIFIED |
| XLSX preview / confirm / replay / export / file content | NOT_VERIFIED |
| Actual Private R2 image upload / replace / authenticated read | NOT_VERIFIED |
| Barcode / QR / labels | NOT_VERIFIED |
| Required mobile/Desktop responsive viewports | NOT_VERIFIED |
| Physical mobile keyboard / camera / scanner / printer | NOT_VERIFIED |

Cloud Demo URL: unavailable. New Vercel deployment ID: none. Deployment target: intended staging/Preview, not submitted. Demo usernames intended: owner.demo, manager.demo, staff.demo; no cloud login success is claimed. Password handoff will use a protected local file after successful provisioning and login checks. Existing Local credentials are not Cloud credentials.

Cloud readiness acceptance passed: 0. Confirmed hosted application failures in this run: 0 (latest app was not deployed). Release blocker: missing scoped Neon administration access. All latest-cloud business tests remain NOT_VERIFIED. DP03 remains IN_PROGRESS under its original acceptance; Local evidence is not substituted for Cloud evidence.

Evidence: docs/evidence/CLOUD_DEMO_NEON_PREFLIGHT.json, CLOUD_DEMO_R2_PREFLIGHT.json, CLOUD_DEMO_SOURCE_MANIFEST.json, CLOUD_DEMO_BACKEND_TESTS.txt, CLOUD_DEMO_BACKEND_ATTEMPT1.txt, CLOUD_DEMO_BOUNDARY_TESTS.txt, CLOUD_DEMO_URL_REGRESSION_RED.txt; source assurance records under docs/assurance/CLOUD_DEMO_REVIEW_*.json. Local restoration evidence remains separately described in FULL_SYSTEM_VERIFICATION.md.


## Publication boundary discovered during handoff

GitHub metadata confirms Minikot88/PartShelf is public and the connected account has push rights. Automatic approval review rejected the combined publication command because direct authorization for external publication had not been sufficiently established. That command did not execute: there was no documentation commit or push from it. Owner was asked whether to publish only Source commit14cd8d7 to the named public Demo branch while retaining internal handover/evidence locally. Do not retry publication through another tool or endpoint without resolving this approval. Source commit exists locally; remote branch publication is NOT_VERIFIED/pending approval. Main remains at its original revision.
