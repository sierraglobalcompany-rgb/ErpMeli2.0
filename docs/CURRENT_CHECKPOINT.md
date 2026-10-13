# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LC2D CLOSED — Invoice/PostSale operator review**

| Field | Value |
|---|---|
| Date | 2026-10-13 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | 6a686e532542c5ee319e84b16f0edb82a4a5ce3b — exact V4 FINAL anchor, clean tree |
| Functional SHA | 4a0d95c3e556728a2908d772902ba98351bc3645 |
| LC-P0-1..3 | CLOSED LOCAL — LC1 |
| LC2A | CLOSED — operator/bootstrap/tenant/Sales history/audit visibility |
| MLSIM-1 | CLOSED — existing local HTTP contracts |
| LC2B | CLOSED — Catalog + Inventory operator UI |
| LC2C1 | CLOSED — Billing Operator + CLI manual |
| LC2C2 | CLOSED — Financial Consumer in existing Sale detail |
| LC2C | CLOSED |
| LC2D | CLOSED — Invoice/PostSale operator review |
| LOCAL_PRODUCT_READY | NO |
| NEXT | LC3 — full local E2E/composition roots; NOT_STARTED |
| LC4 | PENDING — noise/call-budget/recovery |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## LC2D V4 FINAL receipt

- Authority: explicit execution of final compact V4, replacing LC2D V2/V3. Repo/branch/HEAD/clean tree verified. RED → GREEN mínimo → KISS/noise review → QA completa → real patch/evidence → functional commit → documentary closure. No pull/fetch/reset/stash/rebase/merge/push/deploy.
- Owner path C: inspected existing readers; listSources had no exact external-order filter. Added one local sourceForOrder lookup in the same InvoicingBridge, reusing its projection/rules and filtering company/account/order in SQL before pagination. Existing unique identity bounds the source; no list-all/filter-in-PHP, duplicate rule, new productive class or composition-root change.
- Identity stays LC2C2: CompanyContext plus exactly one canonical sale; account_id and external_order_id come from that row. Missing/foreign/ambiguous sale returns404 before Invoicing. Query parameters cannot select another identity.
- Task1 RED: two expected missing-reader failures; GREEN owner19 tests/168 assertions, including existing F11/F12 regressions. Task2 RED: one missing-section failure; two existing regressions already passed; minimal GREEN3/18. Task3 RED: six missing-field failures; GREEN9/86. Task4 read-only/access/list regressions GREEN11/164 without a manufactured production break.
- Existing Sale detail only adds «Factura / Postventa»: readiness, original invoice presence, customer credit-note presence and post_sale_status. Owner enums/flags and F11/F12 semantics unchanged; dynamic values escaped. No fiscal claims, metadata, artifact references/paths, generation/upload/download controls or remote operations. Billing CREDIT_NOTE cannot become a customer credit-note artifact or CREDIT_NOTE_RECORDED.
- Three productive files, 32 additions/1 removal: InvoicingBridge.php, SalesListController.php and existing detail.php. Two test files; all new productive classes/routes/views/tables/migrations/Work types/queues/schedulers/services/repositories/dependencies/remote operations/download endpoints0. Financial/Billing/Work/OAuth/rate/schema/dependencies unchanged.
- Valid detail obtains at most one bounded Invoicing source; Sales list0; denied/foreign/ambiguous0. Test-local PDO trace checks predicates/bounds and zero machine-key lookup. Same-connection write counters and Work/API usage/artifact/service-key snapshots demonstrate domain writes0/Work delta0. Source-path review confirms self-HTTP0/service-key web0/remote writes0/no filesystem access for this reader. Bootstrap route tests preserve existing auth/feature behavior.
- Focal48 tests/489 assertions; full607/4902, failures0/errors0. Existing Windows symlink skip1 and curl_close() PHP8.5 deprecation1 preserved. Composer strict/PHPStan PASS; syntax177 files0failures; diff check PASS. Independent static KISS review Critical0/Important0/Minor0. No source changes after full QA.
- Evidence under storage/exports/lc2d: RED/GREEN logs, full QA/JUnit, diffstat, real functional.patch and receipt/hashes. Synthetic CSRF value redacted from RED HTML failure log. REAL_MELI_HTTP=0, ALEGRA_REAL_HTTP=0, DIAN_HTTP=0; existing separate fixture/MLSIM regression traffic is not attributed to the consumer. Disposable MariaDB stopped after QA. One functional commit and one docs commit.

## Evidence and remaining gates

- [Durable authority](ERP2_AUTHORITY.md): current domain/operator contracts.
- [MLSIM design](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md): existing local HTTP fixtures and composition limits.
- [LC0 audit](LC0_LOCAL_COMPLETENESS_AUDIT.md): historical findings; code/current checkpoint determine current closures.

LOCAL_PRODUCT_READY=NO. NEXT=LC3 — full local E2E/composition roots, NOT_STARTED. LC4=PENDING. F14 LIVE=DEFERRED, F15=PAUSED, F16=UNAUTHORIZED. Git retains prior checkpoint receipts.

STOP — LC2D CLOSED; LC3, LC4, F14, F15 and F16 not executed.
