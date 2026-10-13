# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LC2C2 CLOSED — Financial Consumer**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | 6d7074030d6c2634af7e2b657b6f8a8691acb3cd — exact V4 FINAL anchor, clean tree |
| Functional SHA | 7b005aef2f681ba836e567ae8b148a28a8a1bb7b |
| LC-P0-1..3 | CLOSED LOCAL — LC1 |
| LC2A | CLOSED — operator/bootstrap/tenant/Sales history/audit visibility |
| MLSIM-1 | CLOSED — existing local HTTP contracts |
| LC2B | CLOSED — Catalog + Inventory operator UI |
| LC2C1 | CLOSED — Billing Operator + CLI manual |
| LC2C2 | CLOSED — Financial Consumer in existing Sale detail |
| LC2C | CLOSED |
| LOCAL_PRODUCT_READY | NO |
| NEXT | LC2D — Invoice/PostSale review; NOT_STARTED |
| LC3 | PENDING — full local E2E/composition-root gate |
| LC4 | PENDING — noise/call-budget/recovery |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## LC2C2 V4 FINAL receipt

- Authority: explicit execution of final audited compact V4, not V2/V3. Expected repo/branch/HEAD/clean tree verified. RED → GREEN by task, KISS review → full QA → functional commit → documentary closure. No pull/fetch/reset/stash/rebase/merge/push/deploy.
- Task1 identity RED: existing arbitrary LIMIT1 returned200 for duplicate external_order_id across two own accounts; GREEN uses LIMIT2, exactly one row, its account_id, and existing404 for absent/foreign/ambiguous. No new URL, account selector or client authority. Unique own-sale/foreign controls were already correct and retained as regressions.
- Task2 RED: two missing-consumer/scope failures; GREEN connects the existing detail to FinancialReadModel::forOrder once with company, the exact row's account and external ID. Order and pack scopes render unchanged. Initial test query marker also matched the separate pack-members SELECT; refined the test-only marker to the initial external-order predicate without changing Financial or production behavior.
- Task3 RED: five missing Billing/shipping/coverage-render failures; one exact-money/read-only regression already passed. GREEN displays model-provided values and KNOWN/UNKNOWN/CONFLICT without completion, float, net/profit, sign inference or Billing aggregate. Shared pack Billing fact renders once; shipping evidence stays separate. Task4 list/denial/escaping regressions passed directly; no synthetic production break to manufacture RED. Final consumer17 tests/152 assertions.
- Exactly two production files: SalesListController.php and existing views/detail.php, 54 additions/4 removals. One test file FinancialConsumerHttpRouteTest.php. New classes/routes/views/tables/migrations/Work types/queues/services/repositories/dependencies/HTTP operations/caches0. FinancialReadModel, Billing, Work engine, OAuth/rate, schema and dependencies unchanged.
- Detail valid → one Financial call; list of100 sales → zero. Denied/foreign/missing/ambiguous detail → zero. Test-local PDO prepare trace follows existing test seam; same-connection SHOW SESSION STATUS proves domain write counters unchanged, Work snapshots prove no create/claim/mutation, HTTP accounting unchanged. No production instrumentation or query-counter framework.
- UI only adds Reconciliación in Sale detail: exact scope and members, operative fee/coverage/item count, currency, Billing facts, shipping evidence and official-reference coverage. Dynamic fields escaped; raw context/payload/claims/tokens/debug internals not rendered. UNKNOWN remains UNKNOWN, CONFLICT remains CONFLICT, known zero and large decimals remain exact strings. These are local field/identity coverages, not certification of complete remote billing or official financial totals.
- Fresh focal45 tests/538 assertions; full594/4716, failures0/errors0. Existing Windows symlink skip1 and curl_close() PHP8.5 deprecation1 preserved. Composer strict/PHPStan PASS; syntax176 files0failures; diff check PASS. Independent read-only KISS review: Critical0/Important0/Minor0. No production changes after full QA. Evidence under storage/exports/lc2c2; synthetic CSRF values redacted from RED HTML failure log.
- Feature detail side effects: real ML HTTP0/MLSIM HTTP0/OAuth HTTP0/Work delta0/domain DB writes0/remote writes0. Full QA retains existing separate fake/MLSIM regression tests; their fixture traffic is not attributed to the consumer. Disposable MariaDB stopped after QA. One functional commit and one docs closure commit; no ZIP/framework or external upload required.

## Evidence and remaining gates

- [Durable authority](ERP2_AUTHORITY.md): current domain/operator contracts.
- [MLSIM design](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md): existing local HTTP fixtures and composition limits.
- [LC0 audit](LC0_LOCAL_COMPLETENESS_AUDIT.md): historical findings; code/current checkpoint determine current closures.

LC2C=CLOSED. LOCAL_PRODUCT_READY=NO. NEXT=LC2D — Invoice/PostSale review, NOT_STARTED. LC3 full local E2E/composition-root gate PENDING; LC4 noise/call-budget/recovery PENDING. F14 LIVE=DEFERRED, F15=PAUSED, F16=UNAUTHORIZED. Git retains prior checkpoint receipts.

STOP — LC2C2 CLOSED; LC2D, LC3, LC4, F14, F15 and F16 not executed.
