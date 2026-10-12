# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

| Field | Value |
|---|---|
| Date | 2026-10-11 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| F12 implementation SHA | `1da47d4e65873daeb9dd324c72d01b85bc5d9b3e` |
| CURRENT | F12 CLOSED LOCAL — PostSale audit |
| NEXT | F13 — Migration |

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
