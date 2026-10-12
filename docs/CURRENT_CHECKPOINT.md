# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MLSIM-0 CLOSED — minimal local ML simulator design**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | 7e09bd80e171763eadd4e08e316794d6c3e24706 — exact MLSIM-0 anchor, clean tree |
| Functional SHA | 1ee9da64ea6d23b0f97424d72f1398dd37a8a416 |
| LC-P0-1 | CLOSED — partial Sales Audit/Billing fail-closed |
| LC-P0-2 | CLOSED — stale repair claim creates no child |
| LC-P0-3 | CLOSED — disabled session loses authority immediately |
| LOCAL_PRODUCT_READY | NO — P1 operator/bootstrap gaps remain |
| NEXT | MLSIM-1 — implement minimal simulator; NOT_STARTED |
| LC2 | PENDING AFTER MLSIM-1 |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## MLSIM-0 design receipt

[Minimal local ML simulator design](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md) contains current seam/fake inventory, exact seven-operation registry/callers, endpoint/scenario decisions, counter semantics, process/port/Windows cleanup contract, call formulas, file budget, five-task RED→GREEN plan and adversarial questions for another AI. No external review was sent.

Decision: MLSIM_NEEDED=YES / HTTP_LAYER_NEEDED=YES / OAUTH_HTTP_SIM_NEEDED=NO. Three GET resources only: orders.get, orders.search, billing.period.details. OAuth stays fake-only; packs.get/orders.billing_info CONTRACT_ONLY without business E2E consumer. Financial simulator calls must be0.

MLSIM-1 planned budget: three new test files / one PHPUnit test class / zero production classes/files/config/dependencies/schema/queues/workers. Reuse current MeliClient injected base URL/transport and Curl loopback; temporary request JSONL counter, no control HTTP endpoints or simulator DB. Production CLI/callback hardcoded URL wiring is not claimed covered by this seam.

MLSIM-0 changed only this checkpoint and the one design document. No simulator implementation, tests run, DB/server start, SSH/Hostinger/ERP1 runtime/real ML/OAuth/Alegra/DIAN/deploy/remote writes, push or merge. Documentary diff/consistency/tree validation only; last fresh functional baseline remains475 tests/3372 assertions/one skip/zero failures/errors from LC1.

No P1/P2 or LC2 implementation. LOCAL_PRODUCT_READY remainsNO; F14LIVE deferred/F15paused/F16unauthorized. One docs design/checkpoint commit only.

## LC1 receipt

- P0-1: Sales Audit A/B only accepts200 without a nonempty provider partial marker. 206/200+X-Content-Missing uses the existing contract failure; no evidence/run authority/child continuation or extra HTTP. Billing200 with provider partial marker reuses206 defer: same Work, no facts/cursor/caught_up. Complete empty200 remains terminal.
- P0-2: child selection/dedupe/enqueue and parent defer are inside existing completeCurrentClaim row lock/transaction. Callback may signal its already-performed defer with true; repository checks pending + cleared claim before commit. Missing/invalid claim skips callback, returns false, mutates nothing. Parent-transition SQL fault rolls back child/parent/audit. False defer signal is also rejected/rolled back. Normal repair wait/dedupe and linear Sales continuation remain unchanged.
- P0-3: CompanyContext::assertActiveUser queries current users.status. Bootstrap wires it before current protected session surfaces; direct tenancy also checks it. Disabled403 with old session, no writes/enqueue/OAuth start. Active/membership/admin/CSRF conventions remain covered. No blanket revocation of independent machine keys, webhooks or public health/login.
- Architecture: six existing production files (SalesAuditHandler, SalesAuditRepairHandler, BillingPeriodSyncHandler, WorkRepository, CompanyContext, Bootstrap) and five existing test files. Bootstrap wiring is needed because protected controllers currently have separate membership guards; authority rule stays centralized. No classes/tables/migrations/types/queues/workers/schedulers/services/dependencies/HTTP operations added. No P1/P2 implementation.
- RED: eight confirmed partial failures (first Sales fixture claim timing corrected before implementation), two repair failures, ten disabled-session failures with valid business fixtures. GREEN partial11/105; repair+stale19/174; combined200/1910. Fresh full475 tests/3372 assertions/one existing Windows symlink skip/zero errors/failures. Billing38/501, Work27/205, AuthTenancy13/102, Financial5/126. PHPStan/strict Composer PASS; syntax163 PASS; diff check PASS.
- Financial production files unchanged; HTTP/Work/Queue/derived ledger=0. Cooldown, Retry-After, OAuth refresh and bounded401 recovery unchanged. Work types order.sync/sales.audit/billing.period.sync unchanged; Billing child continuation=0.
- LOCAL ONLY: SSH/Hostinger/real Mercado Libre/OAuth/Alegra/DIAN/deploy/remote writes=0. Disposable127.0.0.1:3307/erp_meli2_test only; container stopped after QA. No push/merge. One functional commit plus one docs closure commit.

[LC0 audit + LC1 P0 closure](LC0_LOCAL_COMPLETENESS_AUDIT.md) owns historical matrix/evidence and remaining P1/P2. [ERP2 authority](ERP2_AUTHORITY.md) owns durable session/completeness/claim contracts. Local evidence under storage/exports/lc1; no upload.

STOP — MLSIM-0 design complete; MLSIM-1, LC2, F14 live, F15 and F16 not executed.
