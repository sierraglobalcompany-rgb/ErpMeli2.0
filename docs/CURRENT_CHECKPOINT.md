# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LC4 CLOSED — minimal compatibility cleanup and authority correction**

| Field | Value |
|---|---|
| Date | 2026-10-13 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | ed0b16ebc910a7452c3cd37d651bbfc5cb4d2159 — LC4 CLOSE V1 anchor, clean tree |
| Functional SHA | 8786ce1e32d9646ebda81d1a35a711c84f6d6e63 |
| LC-P0-1..3 | CLOSED LOCAL — LC1 |
| LC2A | CLOSED — operator/bootstrap/tenant/Sales history/audit visibility |
| MLSIM-1 | CLOSED — existing local HTTP contracts |
| LC2B | CLOSED — Catalog + Inventory operator UI |
| LC2C1 | CLOSED — Billing Operator + CLI manual |
| LC2C2 | CLOSED — Financial Consumer in existing Sale detail |
| LC2C | CLOSED |
| LC2D | CLOSED — Invoice/PostSale operator review |
| LC3-W1 | CLOSED — unchanged test-only loopback seam |
| LC3 | CLOSED — existing local composition certified |
| LOCAL_PRODUCT_READY | NO |
| NEXT | FINAL LOCAL AUDIT — NOT_STARTED |
| LC4 | CLOSED — inherited evidence + minimal compatibility/doc cleanup |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## LC4 CLOSE V1 receipt

- Only demonstrated microfix: removed the two obsolete curl_close calls in the existing CurlMeliTransport. No artificial RED: LC3 full QA already showed the PHP8.5 deprecation. curl_init/setopt/exec/getinfo, responses, exception/message and transport contract remain unchanged. One productive file, two deleted lines; no architecture, interfaces, services, dependencies, routes, schema, Work types or frameworks.
- Removed the temporary child-CLI E_DEPRECATED mask and its obsolete comment. Existing real CLI tests pass unmasked with clean stdout. Focal50 tests/602 assertions/deprecations0; direct literal-loopback refused-connection check under E_ALL preserves RuntimeException/message and emits no deprecation. No permanent probe/test file or new suppression.
- Single full QA620 tests/5108 assertions, failures0/errors0/deprecations0; existing Windows symlink skip1 retained. Composer strict/PHPStan PASS; syntax177 files0failures; diff check PASS. No functional changes after full QA. One functional commit and one docs closure commit; disposable MariaDB stopped after QA.
- Authority corrects only the stale no-seam statement: MELI_TEST_BASE_URL is accepted only in APP_ENV=test for literal HTTP127.0.0.1 with a valid port; non-test keeps the official ML URL and RemoteHostPolicy is unchanged. README unchanged. W1, call budgets, Work/growth, recovery, stale claims, cleanup/retention and root unauthenticated403 remain unchanged and closed/KEEP by inherited evidence.
- Evidence: storage/exports/lc4 contains preflight, receipt, focal/full QA/JUnit, Composer/PHPStan/syntax, local transport-failure check, functional.patch, docs.patch and manifest/hashes. REAL_MELI_HTTP=0 and remote writes0. FINAL LOCAL AUDIT, F14/F15/F16 not executed.

## Evidence and remaining gates

- [Durable authority](ERP2_AUTHORITY.md): current domain/operator contracts.
- [MLSIM design](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md): existing local HTTP fixtures and composition limits.
- [LC0 audit](LC0_LOCAL_COMPLETENESS_AUDIT.md): historical findings; code/current checkpoint determine current closures.

LOCAL_PRODUCT_READY=NO. LC3=CLOSED. LC4=CLOSED. NEXT=FINAL LOCAL AUDIT, NOT_STARTED. F14 LIVE=DEFERRED, F15=PAUSED, F16=UNAUTHORIZED. Git retains prior checkpoint receipts.

STOP — LC4 CLOSED; FINAL LOCAL AUDIT, F14, F15 and F16 not executed.
