# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MLSIM-2 PASS — local provider simulation complete within current scope**

| Field | Value |
|---|---|
| Date | 2026-10-13 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | 54ea1ba1161897c702f1fbda0a4048722987246d — MLSIM-2 anchor, clean tree |
| Functional SHA | 84b8d6b5750be9ba187a386d5107023609601d9b |
| LC-P0-1..3 | CLOSED LOCAL — LC1 |
| LC2A | CLOSED — operator/bootstrap/tenant/Sales history/audit visibility |
| MLSIM-1 | CLOSED — original local HTTP contracts |
| MLSIM-2 | PASS — seven current registry operations classified and locally certified |
| LOCAL_PROVIDER_SIMULATION | COMPLETE_CURRENT_SCOPE |
| LC2B | CLOSED — Catalog + Inventory operator UI |
| LC2C1 | CLOSED — Billing Operator + CLI manual |
| LC2C2 | CLOSED — Financial Consumer in existing Sale detail |
| LC2C | CLOSED |
| LC2D | CLOSED — Invoice/PostSale operator review |
| LC3-W1 | CLOSED — existing CLI loopback seam reused |
| LC3 | CLOSED — existing local composition certified |
| F-01 | CLOSED — MB-A opaque Financial identities; MB-B recheck PASS |
| MB-B | PASS — delta-focused whole-repo recheck |
| LOCAL_PRODUCT_READY | YES |
| NEXT | LOCAL_STABILITY_REVIEW — user decision before external app |
| LC4 | CLOSED — inherited evidence + minimal compatibility/doc cleanup |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED_BY_USER_LOCAL_FIRST |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |

## MLSIM-2 local-first receipt

- User sequencing is LOCAL-FIRST: no real Mercado Libre app, OAuth or provider HTTP. F14 LIVE is deferred by user decision; this block does not open F15/F16.
- Existing MLSIM now has18 finite scenarios covering the seven current registry operations. Ten additions close pack/billing-info HTTP gaps and successful OAuth callback composition. Pack members resolve independently; billing-info proves COMPLETE/MISSING/UNKNOWN/UNAVAILABLE without buyer/shipping substitution or fiscal persistence.
- Only productive delta: existing Routes callback uses MELI_TEST_BASE_URL with APP_ENV=test, literal HTTP127.0.0.1 and valid port; outside test it is ignored. Same client/owners/cipher/Work;0 new classes/services/repos/tables/migrations/Work types/dependencies/queues/runners. Three existing test files extended.
- Connect uses generated state/company binding/PKCE and synthetic callback code, then Curl POST token + GET users.me in loopback. Bootstrap/Routes run in-process with PSR-7, not a browser/hosting certification. Replay0calls; refresh1POST and reread0POST. Lock/error/401 fakes remain the useful proofs for those equivalence classes.
- Pack/billing-info have contracts but no productive fetch consumer. For billing-info404 the client exception and normalizer UNAVAILABLE branch are tested explicitly without claiming nonexistent consumer wiring. This does not certify real provider semantics/quotas/grants.
- Fresh QA: MLSIM31/594; OAuth/CLI/F14 neighbors101/723; full648/5519, skip1 (existing Windows symlink limitation), failures0/errors0/deprecations0. Composer strict/PHPStan/syntax178/diff PASS;182 source/config hashes match. No functional changes after QA. MariaDB stopped; simulator processes/temp dirs0.
- Evidence/actual patch: storage/exports/mlsim-2/. R-02 history read cost, R-03 instance pacing/multiprocess coverage limit, R-04 soft-time/backlog remain report-only; C-06 external gate deferred. REAL_MELI_HTTP=0, real OAuth0, production DB0, remote writes0/OFF.

## MB-B targeted recheck receipt — historical, superseded for current QA/sequence

- MB-A closed the confirmed local P1 F-01 in the existing FinancialReadModel only, with two existing Financial test files. Enumerated/emitted order, pack and detail identities reuse exact string values; strict matching, associations, sorting, money, tenancy and pure-read behavior remain coherent. No identity-bearing raw-key enumeration remains; membership-only maps are retained.
- MB-B independently checked that delta and the applicable audit/MB-A artifact hashes. Prior whole-repo conclusions remain valid for unaffected code; no new local P0/P1, product/wiring gaps, critical unknowns, patch stacks, architecture regressions, critical integrity/test gaps, security or operability blockers were found within that coverage.
- QA applicable to MB-B was MB-A: focal36 tests/383 assertions; full634 tests/5213 assertions, skip1 (existing Windows symlink limitation), failures0/errors0/deprecations0. Composer strict/PHPStan PASS; syntax178 files0failures; diff check PASS. Tested source hashes match; no product/test changes followed that QA, so MB-B reuses it without a full rerun. MariaDB remains stopped.
- R-02 Billing-history read cost and R-04 soft-time/backlog remain structural risks; R-03 instance pacing remains a coverage limit/structural risk, not a proven429 defect. They are deferred without optimization. C-06 remains the external F14 gate. README's obsolete F13 recap was corrected against the current mapped100-product local rehearsal; no other documentation cleanup.
- MB-B changes only README and this checkpoint in one docs commit. No product/test/schema/dependency/route/Work/OAuth changes, new architecture, evidence framework or committed exports. Evidence: `storage/exports/mb-b-targeted-recheck/`; MB-A QA/patch: `storage/exports/mb-a-financial-opaque-id/`; prior audits: `storage/exports/whole-repo-audit/` and `storage/exports/whole-repo-audit-deep/`.

LOCAL_PRODUCT_READY=YES means the local core passed its applicable local gates. NEXT is LOCAL_STABILITY_REVIEW; an external app and bounded F14 LIVE READ certification require a later user decision. It does not certify production readiness, hosting/cron installation, real-provider quotas, deployment or F16 authorization. F14 was not executed in MB-B; real Mercado Libre business writes remain0/OFF.

## Evidence and remaining gates

- [Durable authority](ERP2_AUTHORITY.md): current domain/operator contracts.
- [MLSIM design](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md): existing local HTTP fixtures and composition limits.
- [LC0 audit](LC0_LOCAL_COMPLETENESS_AUDIT.md): historical findings; code/current checkpoint determine current closures.

LOCAL_PRODUCT_READY=YES. LOCAL_PROVIDER_SIMULATION=COMPLETE_CURRENT_SCOPE. MLSIM-2=PASS. LC3=CLOSED. LC4=CLOSED. NEXT=LOCAL_STABILITY_REVIEW. F14 LIVE=DEFERRED_BY_USER_LOCAL_FIRST, F15=PAUSED, F16=UNAUTHORIZED, REMOTE_WRITES=OFF. Git retains prior checkpoint receipts.

STOP — MLSIM-2 local-first certification passed; F14 LIVE, F15 and F16 not executed.
