# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LC2C1 CLOSED — Billing Operator + CLI manual**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | 506228627465b284619fe8c3aeebb8a2d4479c60 — exact V4 anchor, clean tree |
| Functional SHA | c76f3de0ba9ed28cc93b6feac639281f098ab925 |
| LC-P0-1..3 | CLOSED LOCAL — LC1 |
| LC2A | CLOSED — bootstrap/operator/tenant/Sales history/audit visibility |
| MLSIM-1 | CLOSED — existing local HTTP contracts |
| LC2B | CLOSED — Catalog + Inventory operator UI |
| LC2C1 | CLOSED — Billing initiation/status and generic manual CLI |
| LOCAL_PRODUCT_READY | NO — remaining operator completeness and composition gates |
| NEXT | LC2C2 Financial Consumer — NOT_STARTED |
| LC3 | PENDING — full local E2E/composition-root gate |
| LC4 | PENDING — noise/call-budget/recovery |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## LC2C1 V4 receipt

- Authority: explicit execution of the consolidated LC2C1 V4 Markdown. Expected branch/HEAD/clean tree verified before edits. Local-only, no reset or discarded changes. Task1 RED: 39 missing-route failures; Task2 RED: 8 identity/resubmit/concurrency/resync failures. Billing web GREEN: 47 tests/260 assertions. Three initial resubmit assertions incorrectly compared the generated active dedupe column to its pre-terminal value; fixed the test snapshot to compare the terminal row before/after the POST, without changing production behavior. CLI RED: 9 failures, 4 existing/regression cases already passed; GREEN: 13/80. Visibility regressions: 7/111, no artificial production break to manufacture RED.
- Exactly two routes: GET `/billing`, POST `/billing/syncs`; shared navigation adds Facturación. One BillingController and one view. CompanyContext is the sole session/company/active-user/role authority; initiating Billing requires the active company's admin, connected own account and existing CSRF. Scalar inputs are validated before enqueue: positive native account ID, exact valid `YYYY-MM-01`, `BILL|CREDIT_NOTE`. Invalid input422, invalid CSRF419, unauthorized403; client company cannot change authority.
- Producer uses WorkRepository directly. Identity is company/account scope + existing Work type + hash of `period_key:document_type`; cursor is not dedupe identity. Concurrent producer transactions serialize on the existing account row. Active duplicates reuse the same Work without modifying payload/cursor/available_at/claim/progress. New explicit requests start at0 only without durable progress; after failed/done/caught_up they use the durable Billing cursor. No automatic restart, cursor reset, additional Work lifecycle or Billing persistence writer.
- Resync gate PROVEN LOCALLY: POST from a caught_up period with cursor101 → existing handler with fake transport. C1 empty results preserves cursor/caught_up and completes; C2 valid late detail102 persists, advances cursor and defers the same Work. BillingPeriodSyncHandler, normalizer and schema unchanged. This proves local accepted page semantics, not real-provider coverage of all possible adjustments or fiscal closure.
- UI reads up to100 own periods and the existing logical Work reader only for the selected identity. Shows registered request before any Billing period, persisted progress, previously observed caught_up and failed/review state. GET never restarts/enqueues/processes. Escaping and tenant guards tested; no payload/claim token/attempts/error internals, polling or Work dashboard. `partial_flag` is not queried or used as UI authority and remains unchanged in schema/runtime.
- `php bin/work.php --manual-one` delegates to existing WorkExecution::runManualOne with the unchanged unified processor/runner/lock. Processes zero or one globally eligible Work even with automation OFF, respects future eligibility and preserves unsupported-type fail-closed. It can claim another domain/company; `processed=1` does not mean Billing progressed or completed. Targeting/loop arguments rejected before claims. No-argument automatic25/45s behavior unchanged. CLI Billing routing tested with missing synthetic token and zero dispatch, not a loopback seam.
- Fresh focal QA185/1864; full577/4564, failures0/errors0, existing Windows symlink skip1 and existing curl_close() PHP8.5 deprecation1. Composer strict/PHPStan PASS; syntax175 files0failures; diff check PASS. Independent read-only review: Critical0/Important0/Minor0. No production changes after these checks. Local sanitized receipts/JUnit under `storage/exports/lc2c1`; no upload.
- Architecture: five production paths total, new classes1/new views1; tables/migrations/Work types/queues/schedulers/runners/rate engines/dependencies0. Work engine, Billing handler, OAuth/rate/pacing, FinancialReadModel and Sale detail unchanged. Web Meli dispatch0, real Mercado Libre/OAuth/remote writes0. Disposable MariaDB only; stopped after QA. One functional commit plus one documentation closure commit; no push/merge/deploy/SSH/Hostinger/ERP1 runtime.

## Evidence and remaining gates

- [Durable authority](ERP2_AUTHORITY.md): current domain and operator contracts.
- [MLSIM design](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md): local HTTP fixtures and explicit composition limits.
- [LC0 audit](LC0_LOCAL_COMPLETENESS_AUDIT.md): historical findings; use code and this checkpoint for current closures.

LC2C2 Financial Consumer NOT_STARTED. LC3 full local E2E/composition-root gate pending; LC2C1 adds no production seam for CLI → CurlMeliTransport → MLSIM. LC4 noise/call-budget/recovery pending; no new global limiter or prolonged-defer policy. Remaining LC2D/P2 work is not closed by this block. F14 LIVE deferred, F15 paused, F16 unauthorized. Git retains prior checkpoint receipts.

STOP — LC2C1 CLOSED; no LC2C2, LC3, LC4, F14 LIVE, F15 or F16 execution.
