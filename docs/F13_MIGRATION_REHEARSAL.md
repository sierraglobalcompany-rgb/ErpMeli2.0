# F13 — ERP1 migration rehearsal

**Result: PARTIAL — authorized real snapshot profiled locally; explicit company/account mapping remains blocked.**

## Source and preflight

| Field | Verified evidence |
|---|---|
| Date | 2026-10-12 |
| ERP2 branch | `impl/v3-b-sales-audit-20261010` |
| HEAD before continuation | `0ec4d4847d369832234daf78c365b0f1fc873c51` — user-requested documentation checkpoint, clean tree |
| Structural audit baseline | `f3179ab8d60ef21b900b424f3db50aa2d21ffecd`; no functional code changed by the checkpoint |
| Previous checkpoint | F13 PARTIAL LOCAL, awaiting verified production identity and snapshot |
| Authorized ERP1 source | Existing `liquidapro-hostinger` SSH alias, native interactive password authentication and existing host key with `StrictHostKeyChecking=yes` |
| Root identity | Verified realpath of the authorized domain's `public_html/erp-meli`; ERP1 Core markers present |
| Current file/application version | `2.41.0` in `VERSION` and `app.version`; read from production, not assumed |
| Current database | `u390570745_erpmeli`, MariaDB `11.8.9-MariaDB-log`; name obtained only from existing ERP1 configuration |
| Current migration | 303 migration records; latest `303_outer_cron_http_receipt.sql` |
| Capture timestamp | `2026-10-12T06:05:56+00:00` |
| Source consistency | Selected tables are InnoDB; repeatable-read consistent snapshot; `@@session.tx_read_only=1` |
| Schema coverage | 309 table definitions with columns, indexes and FK metadata |
| Exact row coverage | 155,538 projected rows across 29 tables; four additional tables counted without exporting values |
| Snapshot format | Sanitized NDJSON, captured locally; not a restored copy or a production importer |
| Snapshot SHA-256 | `09c47f54b7bafac882b7db52158d38805292df5862b3c981b39a0f1938e1e2a9` |
| Local evidence | [Snapshot, profile, matrix, notebook, collector and QA evidence](../storage/exports/f13/ERP1_F13_20261012_09c47f54b7ba.zip) |

The user explicitly authorized production reads and a local sanitized snapshot. SSH, root/version, database identity/schema and export each ended with SSH exit 0. The earlier endpoint ambiguity was resolved by the user's exact existing alias and its trusted host key. No host key, credential or SSH configuration was created or changed. The original root check incorrectly required Laravel's `artisan`; that check was removed and the actual ERP1 layout was verified.

The historical standalone read-only preflight supplied the configuration parser and PDO/MySQL connection pattern. Current `Env.php`, `AppPaths.php` and `Database.php` hashes match the locally inspected implementations. No application bootstrap, worker, cron, OAuth or HTTP request ran. Existing DB credentials stayed inside the remote process. No collector file or temporary file was created remotely. Production access ended after the successful snapshot; all profiling and QA below used local files or the disposable local ERP2 database.

## Privacy and limits of the snapshot

No access/refresh token, OAuth state, password/hash, session, encrypted setting, buyer identity, raw API payload, configuration secret or auth header is in the row export. Tokens/states/settings have counts only: `meli_tokens=3`, `meli_oauth_states=4`, `app_settings=1045`, `company_settings=0`. Only the non-secret `app.version` setting was read for source identity.

Fiscal/name/account/external/SKU keys are HMAC pseudonyms with an in-memory key that was never exported; null/blank distinctions and relational equality are preserved within this snapshot. Buyer and invoice-reference fields are presence flags only. Native source-collation key profiles provide duplicate counts without key values. Decimal values remain strings and the local analysis uses `Decimal`, never float for business money.

This is an audit dataset, not an executable migration input: original labels, SKUs and external identity values cannot be recovered from HMACs. If a future explicit mapping makes Catalog genuinely eligible, provide the smallest authorized non-secret Catalog export required by its existing owner. Do not reopen production automatically. `information_schema.TABLE_ROWS` estimates in metadata are not exact row counts; only counted/exported tables have exact counts.

`profile_snapshot.py` validates every JSON record, all allowlisted projections, every HMAC field, each table count, the final total and the source read-only/zero-write markers. Validation found zero count mismatches and zero forbidden row values. `F13_PROFILE.ipynb` reproduces the local analysis and checks the snapshot hash. The evidence manifest contains file hashes.

## Exact business row counts

| Table | Rows |
|---|---:|
| `companies` | 5 |
| `users` | 2 |
| `user_company_access` | 8 |
| `user_meli_account_access` | 0 |
| `meli_accounts` | 3 |
| `internal_products` | 100 |
| `product_meli_links` | 0 |
| `product_match_suggestions` | 93 |
| `product_unlinked_ignores` | 0 |
| `product_import_sources` | 100 |
| `meli_items` | 352 |
| `meli_item_variations` | 79 |
| `meli_item_stock_locations` | 31 |
| `meli_orders` | 19,322 |
| `meli_order_items` | 19,322 |
| `meli_packs` | 11,459 |
| `meli_payments` | 20,279 |
| `meli_shipments` | 12,539 |
| `meli_shipment_history` | 0 |
| `meli_order_billing_details` | 65,499 |
| `monthly_reports` | 1 |
| `monthly_report_items` | 24 |
| `monthly_report_orders` | 437 |
| `meli_claims` | 8 |
| `meli_claim_events` | 0 |
| `inventory_warehouses` | 0 |
| `inventory_balances` | 0 |
| `inventory_movements` | 0 |
| `inventory_reviews` | 5,875 |

These are current real-row counts from one consistent source view. The prior empty RC1 test database and unrelated BDM exports are not sources for this result.

## Findings and their migration implications

- **Companies — essential identity blocker.** All five NITs are null; one duplicate-name group contains two rows. One company is soft-deleted, four are active, city/department are missing in all five, and person_type is `juridica`. ERP2 has no NIT identity key. Names, source IDs and row positions cannot supply the missing company mapping.
- **Accounts and Catalog — good local keys do not prove target tenancy.** Three connected accounts have non-null unique Mercado Libre user IDs and no company orphans. All 100 active products have an explicit valid company, nonblank SKU/name and no global or company/SKU duplicate. All 100 explicit owning companies differ from their originating account's company. This observation does not prove incorrect ownership: sourcing across companies may have meaning. Never overwrite explicit ownership with source-account ownership; that meaning and target mapping remain unproven. Product links are empty; all 93 match suggestions remain pending (56 SKU, 37 title). Current source schema still permits nullable product company_id and retains global SKU uniqueness; ERP2 requires an explicit company and company-scoped SKU identity.
- **Physical inventory — actual source differs from the structural baseline.** A real warehouse/balance/movement schema exists, but all three tables are empty. Inventory changes from BLOCKED to SKIP for the empty physical-stock source. The 5,875 reviews (5,870 open, five resolved; 5,872 warehouse-not-configured and three partial-return-authority-required) are review state, not stock. Listing/variation/location quantities are non-negative in this snapshot; 31 locations have `multi_origin` source. Empty physical rows never imply zero stock for each Catalog product.
- **Orders/items — rebuild; date provenance has a gap.** 19,322 unique account/order keys, no order/account orphans, all COP; 768 cancelled, 18,546 paid and eight partially_refunded. Of the 17,279 raw offset-bearing source timestamps, all agree with stored UTC; their range is 2025-08-19 to 2026-09-01 UTC. The other 2,043 lack raw source dates; do not replace them with ingestion timestamps or infer source timezone. This subset's date range does not prove freshness/completeness for all orders. Items have no parent/account mismatch or duplicate logical keys, 17,935 null variation IDs and 19,322 missing full_unit_price fields. No external rebuild was performed.
- **Packs — membership remains unproven.** 11,459 unique packs, 8,565 provisional integrity states and seven partial/count disagreements. All order pack references resolve locally; this does not certify external membership. F14 remains pending.
- **Payments/Billing — exact storage is not proof of complete economic meaning.** 20,279 payments have valid parent/account relations and no key duplicates, but no payment currency column. Detail states: 19,845 summary, 117 unavailable and 317 blank; 2,863 lack date_approved_raw. All five payment DECIMAL fields default to `0.00`. Inspected amount cells have zero invalid Decimal values; no business-money calculation used float. Observed zeros include 15,049 shipping_cost and 12,757 marketplace_fee cells; unknown is not promoted to known zero. Billing has 65,499 details: 65,400 `local` and 99 `confirmed`, all COP. Candidate `(account, detail_id)` is not unique (21 groups across the 65,400 local rows); the source uses a wider order/type/subtype key, so this alone is not a source uniqueness defect. Do not use that candidate as an ERP2 canonical key or copy locally derived rows into official Billing.
- **Reports/artifacts/PostSale.** One report, 24 report items and 437 report-order associations have no relevant parent orphans, and the report has no invoice reference. No canonical invoice-artifact table exists among the 309 table definitions and no PDF/XML was exported. Server filesystem artifact inventory was not performed: actual file availability is unproven, not asserted absent. Eight opened claims (seven mediations, one return) have null local order FKs and zero events. No Claims owner, refund interpretation or fiscal document was invented; F12 remains frozen around cancellations and real linked artifacts.

## Classification transition and data-quality matrix

`old_classification` is the original structural audit's decision; it is retained explicitly rather than silently replaced. `real_data_finding`, `new_classification` and `reason` reflect the current snapshot. There are 26 categories and exactly one current top-level classification each. No category is MIGRATE.

| Category | Old | New | Rows seen | Eligible | Blocked | Null key | Duplicate groups | Orphans | Real data finding / reason |
|---|---|---|---:|---:|---:|---:|---:|---:|---|
| companies | BLOCKED | BLOCKED | 5 | 0 | 5 | 5 | 1 | 0 | Five null NITs; one duplicate-name group affects two rows; one soft-deleted company. Explicit company mapping authority is required. |
| accounts | BLOCKED | BLOCKED | 3 | 0 | 3 | 0 | 0 | 0 | Three non-null unique seller IDs and no company orphans. Company mapping remains unproven; source account IDs never establish ERP2 authority. |
| oauth_credentials | SKIP | SKIP | 3 | 0 | 0 | not exported/established | not exported/established | not exported/established | Three token rows counted only; token values were not exported. Re-authorize under ERP2 OAuth. |
| oauth_states | SKIP | SKIP | 4 | 0 | 0 | not exported/established | not exported/established | not exported/established | Four temporary states counted only; no state values exported. |
| users | REBUILD | REBUILD | 2 | 0 | 0 | not exported/established | 0 | 0 | Two active non-temporary admins, eight company memberships, no membership orphans. Recreate identities and membership without password hashes. |
| catalog | BLOCKED | BLOCKED | 100 | 0 | 100 | 0 | 0 | 0 | All 100 have explicit company, SKU and name, no key duplicates/orphans. Explicit owner differs from originating-account company in all 100: preserve ownership and do not infer it from the source account. Company mapping is missing. |
| product_listing_links | BLOCKED | BLOCKED | 0 | 0 | 0 | 0 | 0 | 0 | Zero canonical links; 93 pending suggestions are not canonical. ERP2 has no listing-link owner. |
| listing_snapshots | REBUILD | REBUILD | 352 | 0 | 0 | 0 | not exported/established | 0 | 352 listing snapshots and 31 multi-origin stock-location rows; listing quantity is not physical stock. |
| variations | REBUILD | REBUILD | 79 | 0 | 0 | 0 | not exported/established | 0 | 79 variations, no item orphans; no negative or null observed quantity values. |
| inventory | BLOCKED | SKIP | 0 | 0 | 0 | 0 | 0 | 0 | Current ERP1 has a physical-stock owner, but warehouses, balances and movements each contain zero rows. Skip the empty source; never seed ERP2 zero stock from absence. The 5,875 reviews are derived state. |
| sales_orders | REBUILD | REBUILD | 19322 | 0 | 0 | 0 | 0 | 0 | 19,322 orders, unique account/order keys, no account orphans, all COP. 2,043 lack raw source timestamps; 17,279 offset-bearing timestamps agree with stored UTC. Rebuild via existing supported sync, after identity mapping; no external reads in F13. |
| order_items | REBUILD | REBUILD | 19322 | 0 | 0 | 0 | 0 | 0 | 19,322 items; no order/account orphans or account mismatch; 17,935 nullable variations; all full_unit_price fields missing. Rebuild alongside canonical orders. |
| packs | BLOCKED | BLOCKED | 11459 | 0 | 11459 | 0 | 0 | 0 | 11,459 unique packs, 8,565 provisional integrity states, seven partial/count disagreements. ERP2 membership authority remains gated by F14; no owner is added. |
| shipments | REBUILD | REBUILD | 12539 | 0 | 0 | 0 | not exported/established | 0 | 12,539 shipments with valid order/account/pack references; rebuild only through a supported consumer. Monetary default zeros are not automatically known economic zeros. |
| payments | BLOCKED | BLOCKED | 20279 | 0 | 20279 | 0 | not exported/established | 0 | 20,279 payments; no parent orphans or account mismatch; no currency column. 19,845 summary, 117 unavailable, 317 blank detail states. DECIMAL defaults are 0.00; retrieval state is not payment authority; ERP2 has no payment owner. |
| billing_reconciliation | REBUILD | REBUILD | 65499 | 0 | 0 | 0 | not exported/established | 0 | 65,499 details: 65,400 local and 99 confirmed; no order/account orphans. Do not copy local/derived reconciliation as official facts. |
| financial_derived | SKIP | SKIP | 25 | 0 | 0 | not exported/established | not exported/established | 0 | One report and 24 report items; 437 report/order associations have no orphans. Other derived financial tables are schema-visible but not exported; do not use their approximate metadata counts as exact counts. |
| invoicing_artifacts | BLOCKED | BLOCKED | 0 | 0 | not exported/established | not exported/established | not exported/established | not exported/established | No canonical invoice-artifact table in the 309-table schema; no invoice reference in the one report; no PDF/XML exported. Server filesystem artifact inventory was not performed, so actual file availability is unproven. |
| post_sale | REBUILD | REBUILD | 19322 | 0 | 8 | 0 | 0 | 0 | 768 cancellations can be rebuilt from canonical orders. Eight partially_refunded rows have no automatic F12 interpretation and remain withheld; no credit note is invented. |
| claims_returns | BLOCKED | BLOCKED | 8 | 0 | 8 | 8 | not exported/established | 0 | Eight opened claims (seven mediations, one return), all local order FKs null; zero claim events. ERP2 has no Claims owner; leave F12 frozen. |
| work_jobs | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Work/job values not exported; current schema and approximate table metadata only. Start from ERP2 Work authority. |
| sync_checkpoints | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Cursors, locks, leases and retries not exported or migrated. |
| webhooks | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Queue/transient event values not exported or migrated. |
| sessions_cache_debug | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Sessions/cache/debug values not exported or migrated. |
| app_settings | SKIP | SKIP | 1045 | 0 | 0 | not exported/established | not exported/established | not exported/established | 1,045 app settings counted only; company_settings has zero rows. No encrypted/config settings values exported; app.version alone was read for identity. |
| other | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | 100 product provenance rows, 93 suggestions and 5,875 inventory reviews are audit evidence, not additional canonical import categories. No logs/update/retry values copied. |


The complete machine-readable `category-matrix.json` in the evidence package also includes `stable_key`, `company_mapping_status`, `account_mapping_status`, `semantic_risk` and all required row-quality fields. Null means not exported or not established, never a fabricated zero. Eligible means eligible for actual migration into ERP2 today; REBUILD/SKIP rows are not counted as MIGRATE candidates. Duplicate counts use the described candidate grain, and orphan checks cover exported parent relationships; non-exported derived financial parents are not claimed audited. PostSale's eight partially_refunded rows remain withheld despite its cancellation REBUILD classification.

## Importer and rehearsal decision

Zero categories qualify as MIGRATE. No importer, new schema, staging, generic ETL, service, queue or alternate business owner was created. No importer dry-run or execute mode exists, and no source row was written into ERP2. New architecture decision required: NO.

| Rehearsal field | Actual result |
|---|---|
| Source rows seen | 155,538 projected business/relation rows; four count-only tables |
| Eligible / would migrate / migrated | 0 / 0 / 0 |
| Already present / rejected | Not evaluated — no importer |
| Ambiguous target identity | Five companies; three dependent accounts; 100 dependent products |
| Snapshot errors | 0 |
| Import idempotency / tenancy execution | NOT_NEEDED — no eligible importer; not claimed passed |
| Source relation and money checks | Local profile complete for the described projections; exact amounts checked without float |

## Essential blockers and next input

1. Explicit mapping authority: legacy company ID to intended ERP2 company identity, including inactive/deleted-company policy. All NITs are null and names are not keys.
2. Account mapping/re-authorization: legacy account ID/external seller identity to the intended mapped ERP2 company/account. No token or OAuth-state transfer.
3. Catalog ownership/field authority: preserve the explicit product owner; clarify the role of originating-account companies. After mappings, obtain only required original non-secret product fields if importing is justified, then decide eligibility and rehearse before adding code.
4. If historical invoices/credit notes are required for cutover, provide an authorized artifact source with actual files and canonical source identity. Neither invoice references nor assumed filesystem absence prove this.

Unsupported link/pack/payment/claim semantics remain fail-closed under their existing owner/gates. F13 remains PARTIAL; it cannot close or make F14 NEXT while essential company/account mappings remain unresolved. Do not start F14 in this run.

## QA and guardrails

No production PHP/schema changed; F13 importer focal tests are NOT_NEEDED.

| Check | Final result |
|---|---|
| Local snapshot validation | PASS — 155,538 rows / 29 tables / zero count mismatches / zero forbidden row values |
| PHPStan | PASS — zero errors |
| Composer | PASS — `composer validate --strict` |
| PHP syntax | PASS — 161 files under app/public/bin/tests |
| PHPUnit | PASS — 405 tests / 2,976 assertions / one skipped / zero errors or failures |
| Existing skip | Platform-dependent `DebugMaintenanceTest::testMaintenanceNeverFollowsSymlinkOutsideManagedRoots` |
| Diff check | PASS — `git diff --check` |

The first suite invocation began before the local DB reported ready and had two early connection errors plus three immediate Work-claim failures (405 tests / 2,941 assertions). No code was changed. The affected classes then passed 17 tests / 114 assertions, and the complete suite passed 405 / 2,976 after the DB was ready. The exact cause of the three transient claim failures was not independently proven; both failed and passing logs are retained. No broader retry was needed after the complete pass.

QA used only disposable local MariaDB 11.4, `127.0.0.1:3307/erp_meli2_test`; its container was stopped after QA. No real ERP1 business data was imported into the test DB. ERP1 production INSERT/UPDATE/DELETE/DDL/application writes = 0; ERP2 production writes = 0; remote files created = 0; remote writes OFF; `REAL_MELI_HTTP=0`, `ALEGRA_REAL_HTTP=0`, `DIAN_HTTP=0`, F14=PENDING, F16=UNAUTHORIZED. No push, merge or deploy.
