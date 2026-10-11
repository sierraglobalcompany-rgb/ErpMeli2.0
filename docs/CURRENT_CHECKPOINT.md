# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

**Date:** 2026-10-11

**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`

**Branch:** `impl/v3-b-sales-audit-20261010`

**Authoritative implementation / QA SHA:** `5f2e510e2a7cb013d92a2725fba9777788f2a8a5`

**Working tree at QA preflight:** clean

**Local QA:** Composer validate PASS; PHPStan 0 errors; PHPUnit PASS — 270 tests / 1861 assertions / 1 skipped, PHP 8.5.8

**Normal development / QA `REAL_MELI_HTTP`:** `0`

**Remote Mercado Libre writes:** OFF

This documentation commit follows the implementation SHA above; verify actual branch/HEAD and inspect any later delta before continuing. No real Mercado Libre HTTP, real OAuth, production mutation, push, merge or deploy was performed for this checkpoint.

## 1. Authority and visibility

```text
AGENTS.md = engineering law / KISS / TDD / DELETE-first
docs/CURRENT_CHECKPOINT.md = current state / verified SHA / QA / gates / next block
docs/ERP2_AUTHORITY.md = durable architecture + domain contracts
README.md = entry index
Git = superseded history
```

Implemented truth: active code/schema > tests/QA for relevant SHA > checkpoint > authority > README/supporting evidence > history/inference.

The local branch intentionally contains unpushed commits. Local remote-tracking reference remains `82e9c82cb410a7a5d36cce342361850f6fc6b05e`; no fetch was performed. GitHub may still show older C0-era state. This is known visibility/governance drift, not a runtime defect or grounds to invalidate completed local work. Publication requires separate authorization.

## 2. Proven local state

One PHP/Slim app, one MariaDB, one Work table/runner, one MeliClient, existing OAuth/cooldown/usage core, Sales, Sales Audit, Debug and period-first Billing. No domain queue, scheduler or additional runner.

Closed foundations: local-first governance, QA CLI portability, DB session UTC, explicit webhook timezone policy, C0 tooling Cycles 1–6 and CLEAN-0 through CLEAN-3. Evidence remains in Git and tests; these are not pending implementation blocks.

C0 tooling remains guarded, read-only and sanitized. Its fake-transport composition reuses MeliClient; it does not refresh OAuth or persist Billing, usage/cooldown or Debug state. Real seller-MCO C0 evidence has not been collected and belongs to F14. It is not required before local durable Billing.

### Corrections closed since `5d651646`

| Correction | Verified commit | Current truth |
|---|---|---|
| Sales Audit confirmation handoff | `a2fbf9e35f2d9313820a05520f051bf318d14f8c` | Both capturing → confirming and repair completion → confirming atomically enqueue the first Capture B page using existing `sales.audit`; no new state/type/architecture. |
| Webhook replay after completion | `a43d32aac7afe55f29fc44349f497ccb266f20a6` | Same event_id replay after terminal sync produces no new Work. Different event_id for the same order remains a legitimate notification. No schema added. |
| Sales remote calendar strictness | `baa805921f634d492e782ab41bbd0400ba6fbf85` | Impossible zoned Order timestamps fail `meli_order_contract` without Sales writes; Audit dates fail `meli_sales_audit_contract` without partial evidence. |
| Optional webhook calendar strictness | `5f2e510e2a7cb013d92a2725fba9777788f2a8a5` | Invalid/impossible optional sent/received become NULL; webhook remains accepted and order.sync enqueues normally. |

Confirmation valid/mismatch, bounded repair, webhook replay and timezone regressions remain green in the current QA snapshot.

## 3. Billing current boundary

Billing remains fiscal/financial reconciliation, not Sales operational truth. BILL and CREDIT_NOTE are separate streams; first from_id=0, next from_id=prior last_id, limit=1000, sort_by=ID, order_by=ASC.

| Block | Status | Essential evidence |
|---|---|---|
| F6A-LOCAL-0 | COMPLETE | Schema/contract audit; context_json required; period-first model retained. |
| F6A-LOCAL-SCHEMA | COMPLETE | `b01616a3d9d8234d5dc4bbaed566fe2fb73937d0`: context_json JSON NULL; MariaDB effective LONGTEXT with json_valid constraint. |
| F6A-LOCAL-1 | COMPLETE | `03e0e266379a9cfb5f15eea8b8ceeaaa77cbd36c`: BillingDetailNormalizer, exact DECIMAL(18,4)-compatible strings, floats rejected, curated context allowlist, explicit timezone only. |
| F6A-LOCAL-2 | COMPLETE | `5d6516464af45b14820102639848e390b410cf26`: one Work processes one Billing period page; details, cursor, continuation and current Work completion are atomic. |
| F6A-LOCAL-3 | NOT EXECUTED | Remote lifecycle and durable hardening remain pending. |
| F6A-LOCAL-4 | NOT EXECUTED | Minimal application Work routing follows hardening. |

Verified F6A-LOCAL-2 outcomes:

- 200 non-empty: normalize/persist details, advance cursor, period pending, one pending continuation, current Work done.
- 200 empty results array: caught_up, cursor unchanged, no continuation, current Work done.
- 206: fail closed with zero canonical mutation, no cursor advance or continuation; current Work remains running and an exception propagates. Final retry/defer lifecycle is pending.

Missing results/last_id or malformed page contract is invalid; absence of results is not a terminal empty page. Billing is not complete: final retry, idempotency, crash lifecycle and routing are not closed.

## 4. Current gates

| Gate | Current verdict / limit |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented local boundaries; external certification remains F14. This checkpoint does not claim a fresh official-web revalidation or real API evidence. |
| G2 WORK_SAFETY | PASS for normal supported producers; conditional malformed scalar JSON robustness item remains open and is not a current Billing blocker. |
| G3 RATE_SAFETY | PASS for current Sales; cross-family orders+Billing pressure is unproven, with real evidence deferred to F14. No global limiter now. |
| G4 SALES_AUDIT_TRUTH | PASS / CLOSED: both confirming paths enqueue initial B atomically; valid/mismatch regressions pass. |
| G5 BILLING_CURSOR_TRUTH | PASS THROUGH F6A-LOCAL-2; durable Billing hardening IN PROGRESS; F6A-LOCAL-3 not executed. |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED / NOT READY. |
| G7 WRITE_FAIL_CLOSED | PASS; remote writes OFF. |
| G8 HOSTING_REALITY | DEFERRED F14 / NOT CERTIFIED. |

## 5. Roadmap and external boundaries

```text
F0  Truth / Preflight
F1  Foundation
F2  Work
F3  Mercado Libre Core
F4  Sales
F5  Debug / observability
F6  Billing + Financial foundation
F7  Catalog
F8  Inventory
F9  ERP API
F10 Invoicing Boundary
F11 Invoicing Provider
F12 PostSale if needed
F13 Migration
F14 Shadow / external certification
F15 Cutover
F16 Remote Writes
```

F1–F13 use REAL_MELI_HTTP=0, local MariaDB and injected transports/fakes, without production dependency. F14 covers Hostinger, real OAuth, Mercado Libre READ-only canaries/shadow and runtime certification. F15 is cutover. F16 remote writes require explicit authorization.

External evidence items remain separate from local implementation: Issue #3 hosting/runtime/main protection and Issue #5 dedicated ERP2 app/OAuth plus authenticated C0. No external issue or remote status was updated or newly certified here.

## 6. Known non-blocking items

- **P2 domain decision pending:** same external_user_id across companies is allowed by schema; webhook account resolution fails closed when ambiguous. This is not a confirmed schema bug or immediate blocker.
- **P2 / F14 evidence:** cooldown scope is app:{family}; shared cross-family quota pressure remains unproven. Do not add a global limiter without evidence.
- **P3 conditional Work robustness:** manually introduced/corrupt scalar payload_json can poison the claim loop. Normal producers enqueue arrays; this is not the current priority or a Billing blocker.
- **Financial:** blocked until durable Billing, sufficient facts and no-double-count fixtures. sale_fee likely needs contract-aligned preservation before F6B; gross_price only if a concrete contract/fixture proves its need. No Financial implementation or ledger now.

## 7. Next implementation — not authorized by this docs block

```text
F6A-LOCAL-3 — Billing page hardening
next bounded implementation: F6A-LOCAL-3A
```

Preferred split:

- **F6A-LOCAL-3A — remote lifecycle:** 429, 401, 5xx, transport, 206 retry/defer; partial_flag semantics only if required by the proven lifecycle.
- **F6A-LOCAL-3B — durability/idempotency:** replay, crash, cursor mismatch, repeated/non-progress cursor, stale claim, malformed last_id/page contract.
- **F6A-LOCAL-4 — minimal Work routing:** short application-level switch after hardening; no HandlerRegistry, command bus, scheduler, Billing queue or new runner.

## 8. STOP

Checkpoint alignment only. Do not start Billing 3A/3B/routing, Financial, sale_fee/gross_price changes, Work robustness, seller multi-company work, a global limiter or F14 from this block. No real OAuth/ML HTTP, production mutation, remote writes, push, merge or deploy. Reconcile this result and obtain authorization for the next bounded implementation.
