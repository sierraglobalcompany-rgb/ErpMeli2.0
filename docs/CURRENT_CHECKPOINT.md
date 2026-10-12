# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

| Field | Value |
|---|---|
| Date | 2026-10-11 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| F10 | CLOSED LOCAL — unchanged functional SHA `4ac7ea64cedd7121eec6c8563ee90c91aa1f146e` |
| F11 implementation SHA | `b352b783cb0a44c6224ec39e1e2417701f9d2130` |
| CURRENT | F11 CLOSED LOCAL — External Billing Bridge |
| NEXT | F12 — PostSale audit |

## F11 verification

| Check | Result |
|---|---|
| F11 focal | PASS — 13 tests / 89 assertions |
| Full PHPUnit | PASS — 401 tests / 2919 assertions / 1 skipped / 0 failures / 0 errors |
| PHPStan | PASS — 0 errors |
| Composer validation | PASS — `composer validate --strict` |
| PHP syntax | PASS — 161 files |
| `REAL_MELI_HTTP` | `0` |
| `ALEGRA_REAL_HTTP` | `0` |
| Remote writes | OFF |

QA ran against fresh migrations in local MariaDB 11.4 on port 3307. No Mercado Libre or Alegra HTTP was made. The one skipped PHPUnit case is the existing platform-dependent symlink test.

## Closed contract

- F10 remains the closed, order-level `InvoiceRequest`; it was not modified or extended to packs.
- Company-bound bearer service keys store SHA-256 only, enforce `invoicing.read`/`invoicing.write`, and can be issued once/revoked with `bin/invoicing-key.php`.
- The bridge exports local Sales facts with readiness, exact decimal strings, preserved cancellation status, and explicit `billing_data_status=UNKNOWN`. `buyer_id` is never fiscal identity.
- Non-null `pack_id` remains visible but non-billable as `PACK_MEMBERSHIP_UNVERIFIED`; artifact intake rejects it. Null-pack orders are the only safe order fallback.
- PDF plus optional XML artifacts are server-hashed, stored under generated internal references, and idempotent per source. Different artifacts conflict. Alegra is deferred.

## Gates and next work

```text
REAL_MELI_HTTP=0
ALEGRA_REAL_HTTP=0
remote writes=OFF
F14=PENDING — certify GET /packs/{pack_id}, seller/account membership, billing-info READ, and complete local snapshot
F16=UNAUTHORIZED — fiscal-document remote writes remain prohibited
```

F12 may audit cancellation-after-invoice and credit-note behavior; F11 does not decide legal/fiscal behavior or implement F12. The external executor must resolve its fiscal requirements; ERP2 has no local fiscal-data source to certify today. An `ORDER_READY` result only covers local source/pack safety and artifact acceptance.

## Guardrails

No real remote HTTP, OAuth, production DB mutation, push, merge, deploy, pack reconstruction, Alegra call, F12 implementation, F14 execution, or F16 execution. No F10 modification.
