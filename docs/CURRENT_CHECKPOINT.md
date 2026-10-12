# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MLSIM-1 CLOSED — representative local HTTP simulator**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | 268de288d6d5ea05a8e2bc85cd2efecfd0fbf197 — exact V2 anchor, clean tree |
| Functional SHA | 3e45c756b35093f19d69cc428bcc7849e9146e43 |
| LC-P0-1 | CLOSED — partial Sales Audit/Billing fail-closed |
| LC-P0-2 | CLOSED — stale repair claim creates no child |
| LC-P0-3 | CLOSED — disabled session loses authority immediately |
| LOCAL_PRODUCT_READY | NO — P1 operator/bootstrap gaps remain |
| NEXT | LC2 — P1 operator completeness; NOT_STARTED |
| LC3 | PENDING — full local E2E + composition-root gate |
| LC4 | PENDING — noise/call-budget/restart/recovery |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## MLSIM-1 V2 receipt

[Simulator design + implementation receipt](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md) records the optimized V2 authority, four tasks, exact8 scenario/3 operation scope, request budgets, process/counter/Windows guards, QA and limits. V2 supersedes the51-result sample; Audit repair is1 result /search A +order +search B=3GET.

- Exactly3 new files under tests,1 TestCase;0 production files/classes/dependencies/tables/migrations/Work types/queues/workers/schedulers/services. No DB of simulator, separate repo or mandatory Docker. Server is ephemeral PHP -S127.0.0.1/test, no proxy/fallback/OAuth endpoints; packs/billing-info contract-only.
- Counter JSONL captures every HTTP arrival of a valid run, unexpected/excess included; exact action asserts. HappyOrder1, orderpartial1/JSONinvalid1 extra0; Audit3/partial1 extra0; Billing2 pages+terminal3, sameWork; partial1;4291/inline0/cooldownfollowup0; disabled/stale/Financial0. Eligibility changes only DB available_at, no Work sleep/polling.
- MLSIM focal17/288; neighbors189/1927; fresh full492/3660/one existing skip/zero failures/errors, exit0. PHPStan/Composer PASS; syntax165/diff PASS. Existing curl_close() PHP8.5 deprecation exposed by real loopback transport remains visible (one distinct deprecation); no production fix or suppression.
- Owned processes/temp directories cleaned, disposable MariaDB stopped. RealML/OAuth/SSH/Hostinger/ERP1/Alegra/DIAN/deploy/remote writes0. No source entrypoint/provider/browser OAuth certification; no P1/LC2 implemented.
- One functional commit `3e45c756b35093f19d69cc428bcc7849e9146e43` plus one documentary closure commit. Local evidence storage/exports/mlsim1, no upload/push/merge. LOCAL_PRODUCT_READY remainsNO.

## LC1 receipt

- P0-1: Sales Audit A/B only accepts200 without a nonempty provider partial marker. 206/200+X-Content-Missing uses the existing contract failure; no evidence/run authority/child continuation or extra HTTP. Billing200 with provider partial marker reuses206 defer: same Work, no facts/cursor/caught_up. Complete empty200 remains terminal.
- P0-2: child selection/dedupe/enqueue and parent defer are inside existing completeCurrentClaim row lock/transaction. Callback may signal its already-performed defer with true; repository checks pending + cleared claim before commit. Missing/invalid claim skips callback, returns false, mutates nothing. Parent-transition SQL fault rolls back child/parent/audit. False defer signal is also rejected/rolled back. Normal repair wait/dedupe and linear Sales continuation remain unchanged.
- P0-3: CompanyContext::assertActiveUser queries current users.status. Bootstrap wires it before current protected session surfaces; direct tenancy also checks it. Disabled403 with old session, no writes/enqueue/OAuth start. Active/membership/admin/CSRF conventions remain covered. No blanket revocation of independent machine keys, webhooks or public health/login.
- Architecture: six existing production files (SalesAuditHandler, SalesAuditRepairHandler, BillingPeriodSyncHandler, WorkRepository, CompanyContext, Bootstrap) and five existing test files. Bootstrap wiring is needed because protected controllers currently have separate membership guards; authority rule stays centralized. No classes/tables/migrations/types/queues/workers/schedulers/services/dependencies/HTTP operations added. No P1/P2 implementation.
- RED: eight confirmed partial failures (first Sales fixture claim timing corrected before implementation), two repair failures, ten disabled-session failures with valid business fixtures. GREEN partial11/105; repair+stale19/174; combined200/1910. Fresh full475 tests/3372 assertions/one existing Windows symlink skip/zero errors/failures. Billing38/501, Work27/205, AuthTenancy13/102, Financial5/126. PHPStan/strict Composer PASS; syntax163 PASS; diff check PASS.
- Financial production files unchanged; HTTP/Work/Queue/derived ledger=0. Cooldown, Retry-After, OAuth refresh and bounded401 recovery unchanged. Work types order.sync/sales.audit/billing.period.sync unchanged; Billing child continuation=0.
- LOCAL ONLY: SSH/Hostinger/real Mercado Libre/OAuth/Alegra/DIAN/deploy/remote writes=0. Disposable127.0.0.1:3307/erp_meli2_test only; container stopped after QA. No push/merge. One functional commit plus one docs closure commit.

[LC0 audit + LC1 P0 closure](LC0_LOCAL_COMPLETENESS_AUDIT.md) owns historical matrix/evidence and remaining P1/P2. [ERP2 authority](ERP2_AUTHORITY.md) owns durable session/completeness/claim contracts. Local evidence under storage/exports/lc1; no upload.

STOP — MLSIM-1 V2 complete; LC2, F14 live, F15 and F16 not executed.
