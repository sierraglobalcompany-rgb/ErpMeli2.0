# ERP Meli 2.0 — F6 Billing Period-First Contract

Date: 2026-10-09
Status: frozen implementation contract for F6A; F6B financial reconciliation remains pending verified legacy fixtures/formulas.

## Authority

This contract derives from:

- `ERP_MELI_2_0_PLAN_MAESTRO_PROYECTO_2026-10-08` — F6 Billing Period-First;
- `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_FINAL_2026-10-08` — Billing;
- official Mercado Libre Billing documentation rechecked on 2026-10-09:
  - `https://developers.mercadolibre.com.co/provisiones`
  - `https://developers.mercadolibre.com.co/es_ar/es_ar/buenas-practicas-para-el-consumo-de-las-apis-de-reportes-de-facturacion`

The repository contract registry remains the runtime source of allowed Mercado Libre operations.

## Goal

Replace order-by-order financial-history ingestion with period-first Billing reconciliation.

Billing is fiscal/financial reconciliation. It is not the operational source of sales.

## F6 split

### F6A — Period ingestion and cursor recovery

Implement:

- normalized local Billing cache;
- period/document identity;
- one-page `billing.period.sync` work;
- sequential `from_id` / `last_id` cursor progression;
- BILL and CREDIT_NOTE as separate streams;
- crash-safe atomic persistence;
- 206 and retry handling;
- proof that mass historical ingestion does not use per-order Billing calls.

F6A exit evidence:

```text
ORDER_BY_ORDER_HISTORY=0
CURSOR_RECOVERY=PASS
```

### F6B — Financial reconciliation

Deferred until verified ERP1 business fixtures/formulas are recovered.

F6B owns:

- business reconciliation formulas;
- late-adjustment accounting behavior beyond ingestion identity;
- penny-allocation fixtures;
- final `FINANCIAL_FIXTURES=PASS` gate.

Do not invent financial rules from API shape alone.

## Official period-details contract

Primary endpoint:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
```

Required/approved request semantics:

```text
document_type=BILL | CREDIT_NOTE
limit=1000
from_id=<cursor>
sort_by=ID
order_by=ASC
```

Rules:

- `period_key` is a monthly key in `YYYY-MM-01` form;
- page size is 1000;
- first cursor is `0`;
- the next cursor is the response `last_id`;
- consume sequentially, not in parallel;
- BILL and CREDIT_NOTE are independent streams;
- local cache is authoritative for already ingested Billing details;
- do not poll monthly periods repeatedly when the period key can be constructed directly.

## HTTP semantics

### 206 Partial Content

206 is not final success.

The current claim must become pending for a later cycle. Partial data must not advance the authoritative cursor as if the page were complete.

### 429

Use the existing `MeliClient` rate-limit path and its retry timestamp. Do not add a Billing-specific limiter.

### 401 / OAuth

Reuse the existing OAuth refresh semantics: at most one refresh/retry of the same physical Billing request before terminal/retry classification.

### Transport / 5xx

Use bounded work retry semantics already established by Sales. Do not create another retry engine.

## Per-order Billing endpoint

```text
GET /billing/integration/group/ML/order/details
```

Allowed only for explicit repair, investigation, or a specific discrepancy.

It is forbidden as the historical ingestion loop. F6A does not need to register or call it.

## Database

Create only:

```text
billing_periods
billing_details
```

### `billing_periods`

Natural identity:

```text
company_id
account_id
period_key
document_type
```

Minimum fields:

- `id`;
- `company_id`;
- `account_id`;
- `period_key`;
- `document_type`;
- `cursor_last_id`;
- `sync_state`;
- `partial_flag`;
- `last_synced_at`;
- `created_at`;
- `updated_at`.

Initial cursor is `0`.

### `billing_details`

Normalized minimum F6A cache:

- local `id`;
- `billing_period_id`;
- remote `external_detail_id`;
- optional `associated_detail_id`;
- `detail_type`;
- optional `detail_sub_type`;
- `detail_amount` as exact DECIMAL-compatible string/column, never float-derived;
- optional `currency_id`;
- optional `document_id`;
- optional `marketplace`;
- optional remote creation timestamp;
- local timestamps.

Natural detail identity is `(billing_period_id, external_detail_id)`.

Do not persist raw Billing JSON or unrelated PII fields. Order/item/pack expansion is not added until F6B reconciliation proves it is required.

## Atomic page commit

For a complete page, one claim transaction must contain:

```text
UPSERT/insert normalized detail identities
+
advance period cursor/state
+
create the next page work when needed
+
complete the current claim
```

A crash after HTTP but before commit must leave no authoritative cursor advance and must be safely replayable.

Duplicate/replayed pages must not duplicate financial detail rows.

## Closed periods and late details

F6A keeps remote detail rows append-safe by remote detail identity.

A later unseen detail may be inserted during an explicit re-sync. Existing financial rows must not be silently rewritten merely because a closed period is revisited. F6B decides the business adjustment/review treatment using verified fixtures.

## Work model

Work type:

```text
billing.period.sync
```

One execution processes at most one remote page.

Logical identity must include company/account/period/document/cursor so an active duplicate page dedupes while a later cursor is a distinct unit of work.

No new queue, state model, scheduler engine, EventBus, CommandBus, CQRS, BillingEngine, or separate Mercado Libre client.

## F6A adversarial gates

Must prove:

- 1000-row page is accepted;
- `last_id` continuation is exact;
- duplicate page is idempotent;
- 206 does not complete/advance as final data;
- crash after HTTP before transaction commit is replay-safe;
- resume uses the persisted cursor;
- BILL and CREDIT_NOTE are isolated streams;
- invalid/mismatched period/detail contracts fail safely;
- tenant identity is scoped by company/account;
- no raw response/PII storage;
- no per-order mass loop.

## Global constraints

- PHP 8.5 target.
- Slim 4 + PDO + MariaDB.
- Existing one `MeliClient`.
- Existing one durable `work_items` engine.
- TDD RED → correct failure → minimal GREEN → fresh full QA.
- KISS/YAGNI.
- No merge/deploy without explicit authorization.
