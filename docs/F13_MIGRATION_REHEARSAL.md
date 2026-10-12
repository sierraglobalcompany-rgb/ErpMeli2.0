# F13 — ERP1 migration rehearsal

**Result: PASS — F13 CLOSED LOCAL; explicit identity mapping resolved and 100 Catalog products rehearsed on disposable MariaDB.**

V4 mapping pass: 2026-10-12. Exact anchor `bbe28e8a68fa45d90721175c6b701fca1d79ce91`, correct branch and clean tree; no drift. Original source profile below was not rerun. Current contract: one local [mapping-manifest.json](../storage/exports/f13/mapping-manifest.json).

## Original snapshot/profile source and preflight

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

This is an audit dataset, not an executable migration input: original labels, SKUs and external identity values cannot be recovered from HMACs. V4 separately captured only original product ID/company/SKU/name for the now-eligible Catalog rehearsal. It did not repeat this snapshot or export other raw payloads. `information_schema.TABLE_ROWS` estimates in metadata are not exact row counts; only counted/exported tables have exact counts.

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
- **Reports/artifacts/PostSale.** One report, 24 report items and 437 report-order associations have no relevant parent orphans, and the report has no invoice reference. No canonical invoice-artifact table exists among the 309 table definitions and no PDF/XML was exported. The V4 narrow known-path check observed no PDF/XML in storage/invoices, storage/reports, storage/uploads, storage/exports and public/uploads. This is not an assertion of absence throughout hosting; historical attachments are not required by the current cutover authority. Eight opened claims (seven mediations, one return) have null local order FKs and zero events. No Claims owner, refund interpretation or fiscal document was invented; F12 remains frozen around cancellations and real linked artifacts.

## Classification transition and data-quality matrix

`old_classification` is the original structural audit's decision; it is retained explicitly rather than silently replaced. `real_data_finding`, `new_classification` and `reason` reflect the current snapshot. There are 26 categories and exactly one current top-level classification each. Current V4 reassessment makes Catalog the sole MIGRATE category (100 records); companies/accounts are REBUILD and nonrequired historical artifacts SKIP.

| Category | Old | New | Rows seen | Eligible | Blocked | Null key | Duplicate groups | Orphans | Real data finding / reason |
|---|---|---|---:|---:|---:|---:|---:|---:|---|
| companies | BLOCKED | REBUILD | 5 | 0 | 0 | 5 | 1 | 0 | C1/C3/C4/C5 CREATE_BEFORE_MIGRATION; C2 SKIP_HISTORICAL. No matching by name/legacy ID alone. |
| accounts | BLOCKED | REBUILD | 3 | 0 | 0 | 0 | 0 | 0 | Three exact seller IDs and source company FKs retained as REAUTHORIZE_NEW contracts. No real configured target account exists to compare. |
| oauth_credentials | SKIP | SKIP | 3 | 0 | 0 | not exported/established | not exported/established | not exported/established | Three token rows counted only; token values were not exported. Re-authorize under ERP2 OAuth. |
| oauth_states | SKIP | SKIP | 4 | 0 | 0 | not exported/established | not exported/established | not exported/established | Four temporary states counted only; no state values exported. |
| users | REBUILD | REBUILD | 2 | 0 | 0 | not exported/established | 0 | 0 | Two active non-temporary admins, eight company memberships, no membership orphans. Recreate identities and membership without password hashes. |
| catalog | BLOCKED | MIGRATE | 100 | 100 | 0 | 0 | 0 | 0 | Owner C3 proven; minimum original SKU/name export validated and imported through existing Catalog owner only on disposable target; exact fields, idempotency and tenancy PASS. |
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
| invoicing_artifacts | BLOCKED | SKIP | 0 | 0 | 0 | not exported/established | not exported/established | not exported/established | NOT_REQUIRED_FOR_CUTOVER. Targeted storage paths returned no PDF/XML; no absolute filesystem absence claimed. |
| post_sale | REBUILD | REBUILD | 19322 | 0 | 8 | 0 | 0 | 0 | 768 cancellations can be rebuilt from canonical orders. Eight partially_refunded rows have no automatic F12 interpretation and remain withheld; no credit note is invented. |
| claims_returns | BLOCKED | BLOCKED | 8 | 0 | 8 | 8 | not exported/established | 0 | Eight opened claims (seven mediations, one return), all local order FKs null; zero claim events. ERP2 has no Claims owner; leave F12 frozen. |
| work_jobs | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Work/job values not exported; current schema and approximate table metadata only. Start from ERP2 Work authority. |
| sync_checkpoints | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Cursors, locks, leases and retries not exported or migrated. |
| webhooks | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Queue/transient event values not exported or migrated. |
| sessions_cache_debug | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | Sessions/cache/debug values not exported or migrated. |
| app_settings | SKIP | SKIP | 1045 | 0 | 0 | not exported/established | not exported/established | not exported/established | 1,045 app settings counted only; company_settings has zero rows. No encrypted/config settings values exported; app.version alone was read for identity. |
| other | SKIP | SKIP | not exported/established | 0 | 0 | not exported/established | not exported/established | not exported/established | 100 product provenance rows, 93 suggestions and 5,875 inventory reviews are audit evidence, not additional canonical import categories. No logs/update/retry values copied. |


The current machine-readable `category-matrix.json` in the V4 evidence package also includes `stable_key`, `company_mapping_status`, `account_mapping_status`, `semantic_risk` and all required row-quality fields. Null means not exported or not established, never a fabricated zero. Eligible means eligible for actual migration into ERP2 today; REBUILD/SKIP rows are not counted as MIGRATE candidates. Duplicate counts use the described candidate grain, and orphan checks cover exported parent relationships; non-exported derived financial parents are not claimed audited. PostSale's eight partially_refunded rows remain withheld despite its cancellation REBUILD classification.

## Explicit V4 mapping and cutover contract

The accessible ERP2 runtime has no supplied DB/environment configuration and no `.env` loader/file. `Environment::all()` reads explicit environment only. No real configured company/account/Catalog authority was found. Local disposable QA identities are TEST_FIXTURE, repository names are DOC_ONLY, real target rows are NOT_PRESENT. No invented/default DB connection or external ERP2 search was attempted. Target absence resolves to creation/reauthorization under the V4 algorithm, not BLOCKED. Catalog convergence is NOT_NEEDED because no real configured Catalog exists.

| Source | Lifecycle/role | Decision | New target contract | Evidence |
|---|---|---|---|---|
| C1 Lilliam Sofia | Active; A1 owner | CREATE_BEFORE_MIGRATION | `erp1-lilliam-sofia` | Verified seller/owner FK + E_TARGET_NOT_PRESENT |
| C2 Lilliam Sofia | Soft-deleted 2026-06-26; no account/product | SKIP_HISTORICAL | None; never merge into C1 | E_SOFT_DELETED_NO_DEPENDENCY |
| C3 Bodega Digital Medellin a SG | Active; explicit owner of 100 products | CREATE_BEFORE_MIGRATION | `erp1-bodega-digital-medellin-a-sg` | E_SOURCE_OWNER_FK + E_SOURCE_CODE_SEMANTICS + E_TARGET_NOT_PRESENT |
| C4 Sierra Global Company SAS | Active; A2 owner; supplies C3 | CREATE_BEFORE_MIGRATION | `erp1-sierra-global-company-sas` | Verified seller/owner FK + E_TARGET_NOT_PRESENT |
| C5 ValeriaML | Active; A3 owner | CREATE_BEFORE_MIGRATION | `erp1-valeriaml` | Verified seller/owner FK + E_TARGET_NOT_PRESENT |

These slugs define new target creation, not inferred existing identities. Source installation + business role + source ownership establishes separate identities. Names/legacy numeric IDs alone do not map existing targets. C5's legal_name spelling `VlaeriaML` is preserved in raw evidence; it does not justify merging with C3 despite A3's nickname. No target company/account is created in production.

| Source account | Exact seller ID | Source owner | Decision / target owner |
|---|---|---|---|
| A1 LILLIAMSOFIAML | 585784977 | C1 | REAUTHORIZE_NEW / `erp1-lilliam-sofia` |
| A2 SIERRAGLOBALCOMPANYMED | 1797511852 | C4 | REAUTHORIZE_NEW / `erp1-sierra-global-company-sas` |
| A3 BODEGA.DIGITAL.MEDELLIN | 175243724 | C5 | REAUTHORIZE_NEW / `erp1-valeriaml` |

No real target seller exists to claim E_SELLER_ID_EXACT convergence. Reauthorization must return the exact seller/site contract (MCO), fail closed on a mismatch, and associate the mapped company. OAuth/token execution is not part of this phase. Token/state migration remains SKIP.

C2 has zero dependencies in the exported snapshot plus all 19 targeted company-reference counts covering Catalog/import provenance, accounts, physical inventory, campaigns, monthly/date reports and fiscal closes/items/snapshots. Other skipped transient/derived domains do not require C2 for cutover. Required durable C2 dependency: NONE.

Production ProductImportService lines 66–163 were read without execution. Its selected `$targetCompanyId` writes both product company and provenance company while `$item['meli_account_id']` records origin. SHA-256 `4b6aa332b446d6632ad083e49b691bb8cce12ecb2b0fdfe8842a15b8d02f4567`. Source schema confirms separate company and account FKs. PRESERVE_EXPLICIT_OWNER is proven, not assumed from conflicting names.

### Historical artifacts

NOT_REQUIRED_FOR_CUTOVER; blocking=NO. The current cutover authority does not require a legacy PDF/XML backfill. Known storage paths were checked narrowly without reading file contents or traversing symlinks. No PDF/XML observed; no canonical historical artifact table or invoice reference in the one snapshot report. ERP1 MonthlyReportService writes only an external invoice reference. This result makes no claim about the entire hosting account. Existing F11/F12 linked artifact requirements for future invoicing remain unchanged.

## Importer and rehearsal decision

Catalog is the sole MIGRATE domain: exactly 100 source products. Existing ProductRepository owns the implementation; no service, staging, schema, queue or generic ETL was added. `bin/catalog-rehearsal.php` consumes the one mapping manifest and checks the SHA-256 of the four-field local source file. It requires APP_ENV=test, host=127.0.0.1, port=3307 and DB `erp_meli2_test` or `erp_meli2_rehearsal`; production execute is unavailable. Default dry-run inserts within a disposable transaction then rolls back rows; AUTO_INCREMENT may advance in that disposable database. `--execute` persists only in the allowed disposable runtime.

Only SKU/name map into existing `products`; source IDs remain audit metadata. Company mapping uses the explicit target slug, never copied legacy IDs. No links/suggestions, token, stock quantity, financial fields or fiscal artifacts are imported. Existing rows must match exact SKU/name; conflicts, source key collisions under MariaDB collation, owner mismatches, duplicate source IDs and invalid required fields fail atomically.

| Rehearsal | Actual result |
|---|---|
| Source / eligible | 100 / 100 original non-secret Catalog rows |
| Dry-run | would_migrate=100, already_present=0, migrated=0 |
| Disposable execute | migrated=100, rejected=0, errors=0 |
| Repeat | already_present=100, would_migrate=0, migrated=0 |
| Exact fields | 100/100 SKU/name equal source; C3 owner mapped to target ID 2 |
| Idempotency | PASS — first/repeat target data hashes identical |
| Tenancy | PASS — same-SKU foreign-company control unchanged; foreign source owner rejected |
| Target hash | `65302375c9c6610016f6c91b8b8582ddf0ff9d98f1aeab0d0aba7cfdfd513f36` |
| Production migration | 0; no production company/account/product created |

Functional commit: `e226263b9e3f842e5dbce0af15a83308465aab06`. Source minimum export and mapping were verified in two bounded SSH sessions, both exit 0. The second session was only the newly justified Catalog migration projection; it did not redo the snapshot/profile or mapping pass. Root/version/DB/migration were reverified for each session. All subsequent work is local.

## Closure and remaining gates

Essential identity/cutover blockers: NONE. Four active companies resolve to CREATE_BEFORE_MIGRATION; historical C2 is SKIP_HISTORICAL; three accounts resolve to REAUTHORIZE_NEW; Catalog ownership/target are proven and the required importer is rehearsed. Historical artifacts have no unresolved REQUIRED dependency. F13 PASS is local contract/rehearsal closure, not production migration, company setup or OAuth completion.

Unsupported links/packs/payments/claims retain their previous fail-closed classification without new owners. They do not automatically block this V4 F13 gate. Company creation and exact-seller reauthorization remain prerequisites for eventual production use. F14=PENDING; NEXT=F14 External READ certification, not executed. F16=UNAUTHORIZED.

## QA and guardrails

Local Catalog code and CLI changed; production code/schema remained untouched. RED verified the missing method, then minimal GREEN passed. Fresh local QA ran once successfully; no inference about the previous transient Work failure was used.

| Check | Final result |
|---|---|
| Local snapshot validation | PASS — 155,538 rows / 29 tables / zero count mismatches / zero forbidden row values |
| Catalog focal RED/GREEN | Expected missing-method RED; GREEN 14 tests / 33 assertions |
| PHPStan | PASS — zero errors |
| Composer | PASS — `composer validate --strict` |
| PHP syntax | PASS — 162 files under app/public/bin/tests |
| PHPUnit | PASS — 412 tests / 2,998 assertions / one skipped / zero errors or failures |
| Existing skip | Platform-dependent `DebugMaintenanceTest::testMaintenanceNeverFollowsSymlinkOutsideManagedRoots` |
| Production CLI guard | PASS — production APP_ENV rejected before DB connection |
| Diff check | PASS — `git diff --check` |

In the previous snapshot/profile pass, the first suite invocation began before the local DB reported ready and had two early connection errors plus three immediate Work-claim failures (405 tests / 2,941 assertions). No code was changed. The affected classes then passed 17 tests / 114 assertions, and the complete suite passed 405 / 2,976 after the DB was ready. The exact cause of the three transient claim failures was not independently proven; both failed and passing logs are retained. No broader retry was needed after the complete pass.

QA used only disposable local MariaDB 11.4, `127.0.0.1:3307/erp_meli2_test`; its container was stopped after QA. The authorized 100-row Catalog minimum projection was imported only into separate disposable `erp_meli2_rehearsal`; the full test suite used `erp_meli2_test`. ERP1 production INSERT/UPDATE/DELETE/DDL/application writes = 0; ERP2 production writes = 0; remote files created = 0; remote writes OFF; `REAL_MELI_HTTP=0`, `ALEGRA_REAL_HTTP=0`, `DIAN_HTTP=0`, F14=PENDING, F16=UNAUTHORIZED. No push, merge or deploy.

## V4 inspectable evidence

- One [mapping manifest](../storage/exports/f13/mapping-manifest.json); raw non-secret names/sellers and minimum Catalog fields remain local/untracked.
- [Mapping/rehearsal evidence ZIP](../storage/exports/f13/F13_MAPPING_REHEARSAL_20261012.zip): source receipts, safe collectors, target-authority receipt, RED/GREEN/full QA, deterministic rehearsal reports, exact-field verification, source hash and SHA256 file manifest.
- Original sanitized snapshot ZIP remains unchanged and identifies the prior 155,538-row profile; V4 did not recapture it.

STOP — F13 CLOSED LOCAL; no push, merge, deploy or F14 execution.

V4 evidence ZIP SHA-256: `ffb2a2574a9ed9ca01b07f03d659849d27771823f60336bb0ac05e85e92c07ec`; 37 entries verified against SHA256 manifest and ZIP CRC; local/untracked.
