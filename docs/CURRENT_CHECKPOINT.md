# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LC2A CLOSED — operator foundation + Sales visibility**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | impl/v3-b-sales-audit-20261010 |
| HEAD before | c4b7e61c383cde0b63800acc7b1c5ecf7ebb7b00 — exact LC2A anchor, clean tree |
| Functional SHA | b014279d73555ed836348c2f02981ba08ce580f7 |
| LC-P0-1 | CLOSED — partial Sales Audit/Billing fail-closed |
| LC-P0-2 | CLOSED — stale repair claim creates no child |
| LC-P0-3 | CLOSED — disabled session loses authority immediately |
| LOCAL_PRODUCT_READY | NO — P1 operator/bootstrap gaps remain |
| LC2A | CLOSED — bootstrap/operator/tenant/Sales history/audit visibility |
| MLSIM-1 | CLOSED |
| NEXT | LC2B — Catalog + Inventory operator UI; NOT_STARTED |
| LC3 | PENDING — full local E2E + composition-root gate |
| LC4 | PENDING — noise/call-budget/restart/recovery |
| F13 | CLOSED LOCAL |
| F14 | LOCAL CONTRACT READY |
| F14 LIVE | DEFERRED |
| KISS-S1 | CLOSED LOCAL — Billing same Work |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |

## LC2A receipt

- Authority: explicit LC2A Markdown execution, local only. RED → GREEN in order A bootstrap, B root/login/navigation, C tenant selector, D older exact ID, E audit result visibility; then security/regression and complete QA. Missing supported bootstrap, root404/admin Settings redirect, selector GET405, absent older sale and absent audit result each failed before GREEN. Additional navigation RED identified missing Settings return link. No later block implemented.
- Bootstrap: new minimal `bin/bootstrap-admin.php` reuses Migrator/AuthService/PasswordService/model. Explicit company/name/email arguments + private password environment input; no default or printed password/hash. Transaction creates exactly one company/user/admin membership; existing singleton row serializes bootstrap. Repeat fails clearly without duplicate authority; invalid inputs leave authority tables empty. Executable test removes migrated tables from disposable test DB, then proves CLI migration/bootstrap/login and repeat exit1. Supported setup/hidden PowerShell password recipe lives in [ERP2 authority](ERP2_AUTHORITY.md).
- Operator: login → `/` → `/sales`; root403 without authorized active membership; Sales list/detail show selected company, Sales, Settings only current admin and CSRF logout. Settings has return link, login inputs labelled. GET selector bypasses one membership; multiple lists only own companies. Existing POST selection keeps CSRF/CompanyContext and valid `/` destination. Foreign/disabled denied; stale session role never adds Settings/audit authority.
- Sales: exact string ID form on `/sales`, scoped SQL, older known sale found beyond100 recent rows; missing/foreign same empty response, invalid422 recoverable, detail foreign404. No new DSL/pagination. Latest25 SalesAuditRepository runs show stored status/account/period/start/finishUTC/counts, including null honestly; all six current statuses tested, own runs only. Reads do not process Work; explicit audit start creates current pending sales.audit Work only.
- Architecture: 11 production files affected (8 existing + minimal CLI +2 concrete PHP views);0 new production classes/tables/migrations/Work types/queues/workers/schedulers/services/dependencies/remote operations/frontend framework. Existing Sales membership SQL removed in favor of CompanyContext; repeated rendering authority reads reduced. Work topology/retry/cooldown/OAuth untouched; Billing/Financial/Catalog/Inventory/PostSale/Integrations production diff empty.
- QA: focal35 tests/337 assertions; final full503/3839, zero failures/errors, one existing skip, one distinct existing curl_close() PHP8.5 deprecation exposed by MLSIM. Not suppressed or fixed; LC4 cleanup remains pending. Composer strict/PHPStan PASS; syntax169 files0failure; diff PASS. Final complete suite rerun after query reduction. Local evidence storage/exports/lc2a, no upload.
- HTTP realML=0; ML/MLSIM/OAuth for page rendering=0. Test fixtures need no token; pages compose only local PDO/auth/repositories. HTTP accounting stays unchanged; Work reads unchanged, audit start is explicit with pending Work never processed inline. Financial HTTP/Work=0. SSH/Hostinger/ERP1/Alegra/DIAN/deploy/remote writes=0. Disposable MariaDB stopped after QA; no push/merge.
- One functional commit `b014279d73555ed836348c2f02981ba08ce580f7` and one docs/checkpoint commit. LOCAL_PRODUCT_READY=NO; next LC2B NOT_STARTED, LC2C/D and existing P2 remain open. LC3/LC4 PENDING; F14 live DEFERRED; F15 PAUSED; F16 UNAUTHORIZED.

## Evidencia de bloques anteriores

- [MLSIM design + implementación V2](MLSIM_0_LOCAL_MELI_SIMULATOR_DESIGN.md): autoridad del simulador HTTP representativo, presupuestos y límites de certificación.
- [Auditoría LC0 + cierre LC1](LC0_LOCAL_COMPLETENESS_AUDIT.md): matriz histórica y faltantes P1/P2 restantes.
- [ERP2 authority](ERP2_AUTHORITY.md): contratos durables de bootstrap, tenant/usuario, Sales, claims y módulos existentes.

Git conserva los recibos de checkpoints anteriores; el checkpoint actual registra sólo el cierre vigente.

STOP — LC2A complete; LC2B/C/D, F14 live, F15 and F16 not executed.
