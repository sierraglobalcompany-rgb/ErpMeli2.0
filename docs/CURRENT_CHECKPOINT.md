# CURRENT CHECKPOINT — ERP MELI 2.0

**F14 LOCAL CONTRACT READY — PASS_LOCAL**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| HEAD before local closure | `39385dd638f0adcca76bcf0814ee9ec2bfca69a4` — clean, compatible F14 docs checkpoint |
| F14 functional SHA | `fc281ee151ccf9f6f274a99785e4c6dc2d19bc14` |
| F13 | CLOSED LOCAL — unchanged |
| CURRENT | F14 LOCAL CONTRACT READY |
| LIVE_READ_CERTIFICATION | PENDING_F15_STAGE0 |
| NEXT | F15 STAGE0 — fresh ERP2 OAuth for ONE seller + direct live calibration |
| F15 | NOT_STARTED |
| F16 | UNAUTHORIZED |

## F14 local closure

- [F14 report](F14_READ_CERTIFICATION.md): existing MeliClient/response/registry and Sales handler extended; zero new production classes, tables, queues, workers, schedulers, refresh/retry frameworks or dependencies.
- ORDER_READ admits only complete matching resource/seller. 206/X-Content-Missing, missing seller and mismatches fail closed; account/company/status authority checked atomically before mutation. Source exact money/null semantics preserved.
- `packs.get` plus pure pack-id/member normalization verifies each supplied resolved order; filters proven foreign sellers, rejects unknown/incomplete membership. No reconstruction or persistence; Invoicing pack gate remains unchanged pending evidence.
- `orders.billing_info` plus MCO structural enum readiness; buyer association is not fiscal ID and shipping address is not billing address. No profile storage; unsupported/partial/null semantics UNKNOWN. Existing Invoicing readiness remains UNKNOWN without actual verified data.
- Billing consumer unchanged; from_id, exact decimal/COP, 206 unchanged facts/cursor and 429 no inline retry tested. Financial unchanged, HTTP/Work/Queue=0.
- Fresh QA PASS: focal 117 tests/839 assertions; Financial 5/126; full 450/3,108/one existing symlink skip/zero failures/errors; PHPStan, Composer, syntax 163 files and diff check PASS. RED37 cases then GREEN37/102; one new Billing fixture corrected, failed log retained.
- No SSH, ERP1 token/refresh/cron use, real Mercado Libre API HTTP, OAuth or refresh. Ordinary tests use synthetic fake transports/disposable MariaDB only. Real API GET=POST=remote writes=0; no token/real provider payload/PII export. Container stopped after QA.
- LIVE external certification NOT YET DONE. F15 Stage 0 is the next task, not executed or authorized by this closure. F16 UNAUTHORIZED; no push/merge/deploy.

## Upstream contracts remain closed

- [F13 migration report](F13_MIGRATION_REHEARSAL.md): company/account mapping complete; C3 retains Catalog ownership, 100 products rehearsed locally with idempotency/tenancy PASS. Production migration and target OAuth were not executed.
- F12 cancellation/linked invoice and credit-note artifact semantics remain frozen under [ERP2 authority](ERP2_AUTHORITY.md). Billing credit-note reconciliation is not a customer credit-note artifact; packs still need verified source membership.
- Remote fiscal writes remain OFF; F16 requires its own explicit authorization. No push, merge or deploy.

STOP — local closure only; live calibration belongs to the next authorized F15 Stage 0 task.
