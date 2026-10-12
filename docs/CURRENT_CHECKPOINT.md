# CURRENT CHECKPOINT — ERP MELI 2.0

**F13 PARTIAL — REAL SNAPSHOT PROFILED; EXPLICIT COMPANY/ACCOUNT MAPPING REQUIRED**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| F12 implementation SHA | `1da47d4e65873daeb9dd324c72d01b85bc5d9b3e` |
| F13 structural audit SHA | `f3179ab8d60ef21b900b424f3db50aa2d21ffecd` |
| Continuation preflight HEAD | `0ec4d4847d369832234daf78c365b0f1fc873c51` — clean, docs-only checkpoint |
| CURRENT | F13 PARTIAL LOCAL — authorized real snapshot captured and profiled; zero MIGRATE categories |
| NEXT | F13 — explicit company/account mapping manifest and Catalog ownership authority; artifact source if required for cutover |

## F13 real-data result

- Existing SSH alias/host-key, native password prompt and ERP1 root were verified. Current version is 2.41.0, production DB is `u390570745_erpmeli` on MariaDB 11.8.9, latest migration is `303_outer_cron_http_receipt.sql` (303 records). These are current observations, not assumptions about the prior handoff.
- The authorized READ ONLY consistent snapshot captured 155,538 sanitized projected rows from 29 tables, 309-table schema metadata and four count-only tables at `2026-10-12T06:05:56+00:00`. Source transaction was read-only; production DML/DDL/application writes, remote files and external HTTP were zero. Production access ended after capture.
- All five company NITs are null and two names duplicate. Three seller IDs are non-null/unique, but company mapping is unproven. All 100 products have explicit company/SKU/name and no key duplicates; originating-account companies differ from explicit ownership. No company/account authority or SKU label is invented.
- ERP1 physical inventory tables exist but warehouses, balances and movements have zero rows: SKIP the empty source; absent balances do not mean zero stock. There are zero canonical product links and 93 pending suggestions.
- Source-quality limits include 2,043 orders without raw dates, 8,565 provisional packs, 65,400 local Billing details, payment default-zero/unknown-currency semantics and no verified invoice-artifact source. F12 remains frozen; F14 remains pending; F16 remains unauthorized.
- No importer, schema or application code was added. Classification transitions, complete quality matrix and required next inputs are in [F13_MIGRATION_REHEARSAL.md](F13_MIGRATION_REHEARSAL.md).
- [Local sanitized evidence package](../storage/exports/f13/ERP1_F13_20261012_09c47f54b7ba.zip) contains snapshot, code, notebook, complete matrix, manifest and QA logs. NDJSON SHA-256: `09c47f54b7bafac882b7db52158d38805292df5862b3c981b39a0f1938e1e2a9`. HMAC values cannot be reversed into an import; any future justified importer needs the smallest authorized original non-secret fields after mapping.

### F13 local QA

| Check | Result |
|---|---|
| Snapshot validation | PASS — 155,538 rows / 29 tables / zero count mismatches or forbidden values |
| Composer | PASS — `composer validate --strict` |
| PHPStan | PASS — zero errors |
| PHPUnit | PASS — 405 tests / 2,976 assertions / one skipped / zero failures or errors |
| Existing skip | Platform cannot create the symlink required by DebugMaintenanceTest |
| PHP syntax | PASS — 161 PHP files |
| Diff check | PASS |

The initial QA invocation had local DB startup errors and transient immediate-claim failures; unchanged affected tests passed 17/114 and the full ready-DB suite passed 405/2,976. Both runs are retained in the evidence package. Only local disposable `erp_meli2_test` on port 3307 was used; its container was stopped. Remote HTTP guards stayed zero.

## F12 closed contract

- Cancellation is derived from the synchronized local `orders.status` (`cancelled`/`canceled`). F11 already exports it.
- `GET /api/integrations/v1/invoicing/sources` exposes `post_sale_status`, `original_invoice_artifact_present`, and `credit_note_artifact_present` from local invoice/credit-note artifacts.
- States: active → `NONE`; cancelled without invoice artifact → `CANCELLED_BEFORE_INVOICE`; cancelled with invoice and without credit note → `CREDIT_NOTE_REVIEW_REQUIRED`; both stored → `CREDIT_NOTE_RECORDED`.
- The existing pre-release `invoicing_artifacts` table stores one `INVOICE` and one `CREDIT_NOTE` per source. `/credit-note` accepts an externally generated PDF and optional XML only for a cancelled non-pack source with its stored original invoice; the server links the note to that invoice and computes hashes. A different second note conflicts.
- Billing `document_type=CREDIT_NOTE` remains a Mercado Libre reconciliation stream and is never used as a customer invoice credit note. No automatic generation, legal/tax decision, DIAN call, or remote write was added. Packs remain blocked pending F14.
- DIAN's cancellation mechanism and Mercado Libre's attached-file DELETE distinction are documented in `docs/ERP2_AUTHORITY.md` with official sources.

## F12 verification

| Check | Result |
|---|---|
| F12 focal | PASS — 17 tests / 146 assertions |
| Full PHPUnit on implementation SHA above | PASS — 405 tests / 2976 assertions / 1 skipped / 0 failures / 0 errors |
| PHPStan | PASS — 0 errors |
| Composer validation | PASS — `composer validate --strict` |
| PHP syntax | PASS — 162 files |
| `REAL_MELI_HTTP` | `0` |
| `ALEGRA_REAL_HTTP` | `0` |
| `DIAN_HTTP` | `0` |
| Remote writes | OFF |

QA ran against fresh migrations in local MariaDB 11.4 on port 3307. No Mercado Libre, Alegra, or DIAN HTTP was made. The one skipped PHPUnit case is the existing platform-dependent symlink test.

## Gates

```text
REAL_MELI_HTTP=0
ALEGRA_REAL_HTTP=0
DIAN_HTTP=0
remote writes=OFF
F14=PENDING — certify pack membership, billing-info READ, and a complete local snapshot
F16=UNAUTHORIZED — remote fiscal-document writes remain prohibited
```

F12 does not start F13. F10 and F11 remain unchanged. Alegra remains deferred. `ORDER_READY` covers local source/pack safety and artifact acceptance, not fiscal identity or legal validity.

## Guardrails

No real remote HTTP, OAuth, production DB mutation, push, merge, deploy, pack reconstruction, Alegra call, F13 implementation, F14 execution, or F16 execution. Remote fiscal writes remain OFF.
