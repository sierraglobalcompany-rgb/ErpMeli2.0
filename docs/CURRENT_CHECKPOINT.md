# CURRENT CHECKPOINT — ERP MELI 2.0

**F13 PARTIAL — STOP UNTIL THE CURRENT ERP1 SSH IDENTITY IS VERIFIED**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| F12 implementation SHA | `1da47d4e65873daeb9dd324c72d01b85bc5d9b3e` |
| F13 audit HEAD | `f3179ab8d60ef21b900b424f3db50aa2d21ffecd` |
| CURRENT | F13 PARTIAL LOCAL — structural audit complete; no verified current ERP1 production identity or data snapshot |
| NEXT | Verify the supplied ERP1 SSH host against the existing trusted host identity, then create a local read-only snapshot and resume F13 |

## F13 partial result

- ERP1 code/schema were identified locally; the inspectable snapshot is ERP1 2.35.1 pre-HF1. A local RC1 browser test database has no representative order/catalog/payment/claim/invoice facts and is not a real migration source.
- ERP1 companies have nullable NIT; ERP2 companies have no NIT key. Legacy internal product company ownership is nullable. No real rows exist to prove company/SKU identity, so no entity qualifies for `MIGRATE`.
- The complete category matrix and inspected row counts/source identity are in [F13_MIGRATION_REHEARSAL.md](F13_MIGRATION_REHEARSAL.md). No importer, schema, HTTP call, or production write was made.
- The 2026-10-12 continuation found a historical root-read-only SSH script, but its configured endpoint does not match the currently supplied ERP1 hostname; the supplied hostname has no existing known-host entry. No SSH connection or SQL read was attempted, and no password was requested. F13 remains blocked until the current endpoint identity can be verified safely.
- Do not start F14 until F13 can be resumed against an authorized ERP1 data snapshot. F14 remains pending; F16 remains unauthorized.

### F13 local QA

| Check | Result |
|---|---|
| Composer | PASS — `composer validate --strict` |
| PHPStan | PASS — 0 errors |
| PHPUnit | PASS — 405 tests / 2976 assertions / 1 skipped / 0 failures / 0 errors |
| Skip | Existing `DebugMaintenanceTest::testMaintenanceNeverFollowsSymlinkOutsideManagedRoots`; symlink creation unavailable on this platform |
| PHP syntax | PASS — 161 files (`app/`, `public/`, `bin/`, `tests/`) |
| `git diff --check` | PASS |

QA used `REAL_MELI_HTTP=0`, `ALEGRA_REAL_HTTP=0`, `DIAN_HTTP=0` and only disposable local ERP2 `erp_meli2_test` on port 3307. No external HTTP or production write occurred.

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
