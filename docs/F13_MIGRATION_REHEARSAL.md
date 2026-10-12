# F13 — ERP1 migration rehearsal

**Result: PARTIAL — structural audit complete; no verified real ERP1 data source is available for rehearsal.**

## Preflight

| Field | Evidence |
|---|---|
| ERP2 branch | `impl/v3-b-sales-audit-20261010` |
| ERP2 HEAD before F13 | `b0e69f9da2c6f9d02d2d31977fa1edde95d5267d` |
| ERP2 tree before F13 | Clean |
| Previous checkpoint | F12 closed locally; F13 next |
| ERP1 source code checkout | `C:\codex\ErpMeli-github-staging`, branch `hotfix/emergency-hf1`, commit `97f56af92dd0e790bb5b24930803040b9915b699`, clean; dated 2026-08-08 |
| ERP1 schema snapshot | `C:\codex\ERP Meli Gestion Pro\git-prep\ErpMeli-2.35.1-pre-HF1-20260808-final\database\migrations`; 279 migration files, including the named core/product/claims migrations below |
| External HTTP / writes | None |

The checked-out ERP1 commit does not track migration files under `database/`; the local 2.35.1 snapshot supplied the inspectable migration schema. ERP1 files were read only.

## Source data availability

No authorized ERP1 production export was identified. The local container `erpmeli-rc1-db` is MariaDB 11.4.12 and contains `erp_meli_k1d_test_rc1_browser_226869`, explicitly a test database. It was started only to inspect metadata/counts in read-only transactions and then stopped. Counts in that candidate database:

| Tables | Rows |
|---|---:|
| `companies`, `users`, `meli_accounts` | 1 each |
| `meli_tokens`, `meli_oauth_states` | 0 each |
| Orders/items, packs, shipments/history, payments | 0 |
| Listings, variations, pictures/attributes, internal products, product links | 0 |
| Claims/events, monthly reports/items, billing detail | 0 |

This test database has no representative business facts and is not treated as a real-source snapshot. Three Downloads SQL files name their database `u390570745_bdm2`; their table lists do not contain the ERP1 `meli_*` schema family. They were not read as migration data. No data values or credentials were included in this report.

Consequently, a real-source dry-run, source data-quality profile, disposable-target import, and idempotency/tenancy execution rehearsal were not run. The test database is not evidence for production ERP1 row counts.

## ERP1 schema facts checked

- `001_initial_schema.sql`: `companies.nit` has a unique index but is nullable; `meli_accounts.meli_user_id` is unique but nullable and each account belongs to a legacy company.
- `005_products_base.sql`: `internal_products.internal_sku` is globally unique. `010_products_2_1_expansion.sql` adds nullable `company_id` with `ON DELETE SET NULL`; it does not make company ownership mandatory. Product→listing links point to legacy internal product, account, item, and variation IDs.
- `meli_orders` has stable `(meli_account_id, external_order_id)` uniqueness. ERP2 orders use `(company_id, account_id, external_order_id)`, so a proven account and company mapping is still required.
- Legacy listing quantities are `meli_items.available_quantity` / variation quantities. They describe listing snapshots and cannot be treated as physical Inventory.
- Legacy order/payment values include DECIMAL columns with defaults of zero. A defaulted zero cannot be promoted to a known financial fact without row-level/source semantics.
- `monthly_reports` stores estimated totals, manual bases, statuses, and `external_invoice_reference`; no invoice PDF/XML artifact table was found in the inspected migration schema. A reference alone does not prove an invoice artifact.
- Legacy claims/events have no ERP2 Claims owner. ERP2 F12 remains limited to local order cancellation and accepted external invoice/credit-note artifacts.

ERP2 target contracts were checked in `database/migrations/001_core.sql` through `008_invoicing_bridge.sql`, Catalog/Inventory/Sales/Billing/Financial/Invoicing owners, `docs/ERP2_AUTHORITY.md`, and their existing tests. ERP2 `companies` has `name` and `slug` but no NIT/business identifier. Names are not accepted as company identity. No migration CLI/adapter exists today.

## Mapping matrix

Exactly one classification is assigned per category. `MIGRATE` is intentionally empty until an authorized source dataset proves row quality and mappings.

| Category | Classification | Basis / constraint |
|---|---|---|
| Companies | BLOCKED | Legacy NIT can be null; ERP2 has no corresponding NIT key. No real rows to map. Never map by name or copy IDs. |
| Mercado Libre accounts | BLOCKED | `meli_user_id` is a potential stable key when non-null, but company mapping and row identity were not proven. Reconnect with ERP2 OAuth; do not import credentials. |
| OAuth credentials/tokens | SKIP | ERP2 has its own application, encryption key, OAuth and token authority. |
| OAuth states | SKIP | Temporary authorization state cannot be revived. |
| Users | REBUILD | Recreate/invite under ERP2 auth and company membership; do not carry legacy password hashes or role assumptions. |
| Catalog/internal products | BLOCKED | ERP2 `products` is the canonical owner and `(company_id, internal_sku)` fits after mapping, but legacy `company_id` is nullable and no real rows, company mapping, SKU duplicate/orphan, or required-field profile is available. |
| Product↔listing links | BLOCKED | ERP2 has no canonical listing-link owner. Do not add one for migration. |
| Listing snapshots | REBUILD | Re-fetch only if a future supported ERP2 consumer requires them; raw listing snapshots are not canonical Catalog facts. |
| Variations | REBUILD | Reconstruct from authorized listing reads if/when an ERP2 owner exists. |
| Inventory/current quantity | BLOCKED | Legacy `available_quantity` is listing quantity; no evidence it is physical stock owned by ERP2 Inventory. |
| Sales/orders | REBUILD | ERP2 `orders` is canonical and keyed by mapped company/account plus external order ID. Use existing sync where supported; do not copy a second authority. |
| Order items | REBUILD | Rebuild with canonical order sync. Legacy item uniqueness with nullable variation is not sufficient to guess ambiguous rows. |
| Packs | BLOCKED | ERP2 pack membership remains pending F14; no legacy pack import or new owner in F13. |
| Shipments | REBUILD | Existing external source may be read again if a supported ERP2 owner/consumer is established; do not migrate legacy snapshots into an invented table. |
| Payments | BLOCKED | No separate ERP2 payment owner; do not force payment snapshots into Billing or Sales. |
| Billing/reconciliation facts | REBUILD | Use existing ERP2 Billing period sync and `billing_details` owner; legacy cached/derived reconciliation is not its authority. |
| Financial derived totals/reports | SKIP | Legacy estimated net/manual aggregates are not ERP2 source facts. ERP2 Financial derives only from its current exact facts. |
| Invoicing artifacts | BLOCKED | No legacy artifact table/PDF/XML source verified. `external_invoice_reference` alone cannot create an ERP2 artifact. |
| Post-sale/cancellations | REBUILD | Cancellation is derived from canonical ERP2 `orders.status`; F12 credit-note artifacts require the real file and original invoice. |
| Claims/returns | BLOCKED | No ERP2 Claims owner; F13 must not expand F12 or create a Claims module. |
| Work/jobs | SKIP | Pending/running/retry/lease state is transient and must start from ERP2 Work. |
| Sync checkpoints | SKIP | Legacy cursors, locks, and checkpoints do not transfer to ERP2 sync authority. |
| Webhooks | SKIP | Legacy queue/events are transient signals, not business history. |
| Sessions/cache/debug | SKIP | Not canonical business data; also avoid carrying sensitive payloads. |
| App settings | SKIP | Reconfigure deliberately in ERP2; never transfer credentials or enablement flags. |
| Other | SKIP | Logs, update records, and legacy job/derived state have no current ERP2 owner or migration need. |

## Architecture and rehearsal decision

- No records qualify as `MIGRATE` based on the evidence available.
- No new schema, staging table, ETL framework, CLI, adapter, or mapper was added. Adding one without a real eligible entity would violate the KISS/identity gates.
- Dry-run/execution mode: `NOT_NEEDED` until a real source dataset and at least one eligible mapping are verified. No migration tool exists that could write by default.
- ERP1 SQL data writes: 0. ERP2 production writes: 0. Test-source SQL was read only; no target import was run.
- Architecture decision required: `NO`. The current result is partial because the real data source and mapping evidence are unavailable, not because ERP2 needs a new owner.
- F14 remains pending; F16 remains unauthorized; remote writes stay OFF.

## Data-quality gate and open gaps

For a real authorized snapshot, the next F13 rehearsal must measure stable-key nulls/duplicates, orphan rows, company/account mapping, duplicate SKU per company, ownership, exact decimals, source timestamps, and availability/integrity of invoice files. None can be inferred from the empty RC1 test dataset. Do not import any category until these checks pass for its rows.

Required to resume F13: identify/provide an authorized ERP1 data snapshot and its read-only access details. Then re-run this matrix against actual rows and implement only the entities that pass the existing-owner/identity gates. F14 is not a substitute for this source-data rehearsal.

## Verification

No production code/schema changed, so focal F13 importer tests are `NOT_NEEDED`.

| Check | Result |
|---|---|
| Composer | PASS — `composer validate --strict` |
| PHPStan | PASS — 0 errors |
| PHPUnit | PASS — 405 tests, 2976 assertions, 1 skipped, 0 failures, 0 errors |
| Skip | `DebugMaintenanceTest::testMaintenanceNeverFollowsSymlinkOutsideManagedRoots` — this platform cannot create the required symlink; same platform-dependent skip as F12 |
| PHP syntax | PASS — 161 PHP files under `app/`, `public/`, `bin/`, and `tests/` |
| `git diff --check` | PASS |

The PHPUnit process used `REAL_MELI_HTTP=0`, `ALEGRA_REAL_HTTP=0`, `DIAN_HTTP=0`, `APP_ENV=test`, and only the disposable ERP2 test database `erp_meli2_test` on local port 3307. The legacy test container was stopped before this QA. No external HTTP was made.
