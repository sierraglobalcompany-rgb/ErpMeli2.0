# CHECKPOINT — F6A Task 1 Contract + Schema

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f6-billing-period-first-20261009`
Base branch: `impl/f5-debug-dvr-20261009`
Base checkpoint: `962ab77175815ea2839aba80cebfd09535fc4e64`
Draft PR: #15 — `F6A — Billing Period-First Ingestion`
Functional branch HEAD before this checkpoint commit: `71791fdb545755e66eaaa497028dc0da7dbbf13a`

Purpose: preserve the verified F6A Task 1 state before beginning the Billing page handler. No merge/deploy.

## Frozen F6 scope

F6 is intentionally split:

### F6A — period-first ingestion and cursor recovery

Owns:
- period/document local identity;
- normalized Billing cache;
- `billing.period.sync`;
- one remote page per execution;
- `limit=1000`;
- sequential `from_id` / `last_id`;
- BILL and CREDIT_NOTE as separate streams;
- crash-safe persistence and resume;
- 206 retry behavior;
- `ORDER_BY_ORDER_HISTORY=0`;
- `CURSOR_RECOVERY=PASS`.

### F6B — financial reconciliation

Deferred until verified ERP1 financial formulas/fixtures are recovered.

Owns:
- business reconciliation formulas;
- penny/allocation fixtures;
- late-adjustment business treatment;
- `FINANCIAL_FIXTURES=PASS`.

Do not invent accounting rules from the Mercado Libre API response shape.

## Authoritative local documents

- Contract: `docs/f6-billing-period-first-contract.md`
- Implementation plan: `docs/superpowers/plans/2026-10-09-f6a-billing-period-ingestion.md`
- Mercado Libre contract registry/documentation: `docs/meli-contracts-2026.md`, `config/meli_operations.php`

## Task 1 — DONE / GREEN

### 1. Billing schema

Migration:
- `database/migrations/005_billing.sql`

Created only:
- `billing_periods`
- `billing_details`

`billing_periods` includes:
- company/account scoping;
- monthly `period_key`;
- `document_type`;
- `cursor_last_id` default `0`;
- `sync_state`;
- `partial_flag`;
- `last_synced_at`;
- local timestamps;
- unique `(company_id, account_id, period_key, document_type)`.

`billing_details` includes:
- `billing_period_id`;
- remote `external_detail_id`;
- optional associated/detail metadata;
- exact `DECIMAL(18,4)` `detail_amount`;
- optional currency/document/marketplace/remote timestamp;
- unique `(billing_period_id, external_detail_id)`;
- no raw Billing JSON/payload or unrelated fiscal PII columns.

### 2. Mercado Libre Billing period operation

Registered operation:

```text
billing.period.details
GET /billing/integration/periods/key/{period_key}/group/ML/details
family=billing
classification=READ
verified_at=2026-10-09
```

Approved query contract:

```text
document_type=BILL | CREDIT_NOTE
limit=1000
from_id=<cursor>
sort_by=ID
order_by=ASC
```

The mass-ingestion path does not register/use the per-order Billing endpoint.

### 3. Safe `period_key` path resolution

`MeliClient::resolvePath()` now supports only an exact monthly first-day key:

```text
YYYY-MM-01
```

It rejects malformed, invalid-calendar, non-first-day, traversal-like, or unexpected path parameters before transport.

Existing Orders path behavior remains unchanged.

### 4. Tests added/updated

- `tests/Integration/BillingSchemaTest.php`
- `tests/Integration/MeliBillingPeriodDetailsContractTest.php`
- `tests/Integration/MeliClientBoundaryTest.php` updated only to include the newly implemented Billing contract in the explicit operation boundary.

## TDD evidence

### RED — QA #403

Run: `37964541682`
Job: `113935479786`
Result: expected failure.

Evidence:
- PHPStan: 0 errors;
- PHPUnit: 147 tests / 918 assertions / 7 failures;
- exactly 3 failures because Billing tables did not exist;
- exactly 4 failures because `billing.period.details` did not exist;
- no unrelated F5 regression.

### First GREEN attempt — QA #411

Run: `37964870099`
Job: `113936595251`
Result: one boundary-test failure only.

Evidence:
- PHPStan: 0 errors;
- PHPUnit: 147 tests / 955 assertions / 1 failure;
- only failure: `MeliClientBoundaryTest::testOfficialOperationRegistryContainsOnlyImplementedContracts`, because the explicit expected registry list had not yet been updated with the newly implemented operation;
- the original Task 1 RED tests were already green.

### Final GREEN — QA #413

Run: `37965080283`
Job: `113937286306`
Result: SUCCESS.

Fresh verification evidence:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=147/147 PASS
ASSERTIONS=970
REAL_MELI_HTTP=0
```

The 404 emitted by `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` is expected test output and the suite passes.

## Current state

Task 1 is complete and verified.

Not implemented yet:
- `BillingPeriodSyncHandler`;
- page normalization;
- detail persistence;
- cursor advancement;
- continuation work;
- 206 handling;
- Billing work composition;
- F6A adversarial closure;
- F6B financial formulas/fixtures.

## Exact next action — Task 2 only

1. Re-check the current official Mercado Libre period-details response shape before writing a parser.
2. Create `tests/Integration/BillingPeriodSyncHandlerTest.php` RED.
3. Prove one `billing.period.sync` logical page causes exactly one physical `billing.period.details` request.
4. Validate only the normalized F6A fields supported by the official contract.
5. Preserve money as validated decimal text; never cast Billing amounts to float.
6. Reject malformed/mismatched remote detail contracts without writing Billing rows.
7. Implement the minimum `app/Modules/Billing/SyncPeriod/BillingPeriodSyncHandler.php` necessary to turn that RED green.
8. Do not begin atomic persistence/cursor continuation from Task 3 unless the Task 2 contract truly requires a minimal seam.
9. Run a fresh full QA and checkpoint before expanding further.

## Pending gates

F6A:

```text
ORDER_BY_ORDER_HISTORY=PENDING
CURSOR_RECOVERY=PENDING
```

F6B:

```text
FINANCIAL_FIXTURES=PENDING_VERIFIED_LEGACY_SOURCE
```

## Constraints retained

- One existing `MeliClient`.
- One existing durable `work_items` engine.
- No BillingEngine.
- No new queue/retry framework.
- No raw permanent Billing payload archive.
- No financial formula invention.
- No merge/deploy without explicit authorization.
