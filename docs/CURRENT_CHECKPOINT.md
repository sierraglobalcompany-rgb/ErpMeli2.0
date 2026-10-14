# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LOCAL COMPANY MANAGEMENT — PASS**

| Campo | Estado actual |
|---|---|
| Fecha | 2026-10-14 |
| Rama | impl/v3-b-sales-audit-20261010 |
| Base Git de la QA | 78aacaf8c9b90b103a3361ef20d80dffc898614a |
| Árbol probado | Snapshot `storage/exports/local-company-management/diff-freeze.txt`; SHA final/sync en receipt; HEAD actual: `git rev-parse HEAD` |
| LOCAL_CORE_CONTRACTS_READY | YES |
| LOCAL_PRODUCT_READY | NO |
| WEB_INSTALLER_V2 | PASS |
| LOCAL_HUMAN_ONBOARDING_FOUNDATION | PASS |
| LOCAL_COMPANY_ADMIN_READY | YES |
| LOCAL_USER_ADMIN_READY | NO |
| LOCAL_MELI_ACCOUNT_ADMIN_READY | NO |
| HOSTINGER | DEFERRED |
| F14 LIVE | DEFERRED |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| DEPLOY_PACKAGE | DEFERRED — LOCAL RELEASE CANDIDATE posterior |
| NEXT | LOCAL USER + ROLE MANAGEMENT |

## QA y límites actuales

- Full #1: 716 tests / 6084 assertions / 1 skip existente; 0 failures/errors. Focal 119/1046 PASS y verificación final Company 37/297 PASS (121 casos focales distintos). Composer/PHPStan/sintaxis de 17 PHP/diff PASS; full #2 no usado. 205 hashes congelados de source/config/tests, sin cambios posteriores al full; sólo docs/evidencia de cierre.
- System Admin es un booleano comprobado en DB con usuario activo; Company admin es una membresía independiente. El bootstrap crea ambas autoridades y el backfill sólo promueve un admin único en una única empresa. Usuario normal con cero membresías activas no ingresa; System Admin sí, sin selección.
- Gestión local de empresas permite crear/editar/archivar/reactivar y borrar sólo empresa archivada sin uso previo y con la única membresía admin del actor. Crear y borrar son transaccionales; el usuario nunca se elimina. Archivo rechaza Work live/cuentas Meli no disabled/keys activas, conserva historia y elimina selección tenant archivada desde CompanyContext. OAuth rechaza Company archivada antes del proveedor y vuelve a comprobarla bajo lock antes de persistir. Settings globales sólo pertenecen a System Admin, incluso sin tenant.
- L1 mantiene wizard humano/local-first/DB-safe, Meli opcional y username/legacy. No se reconstruyó paquete de deploy. Faltan usuarios/roles, aplicación/cuentas Meli y E2E humano local. Un caso legacy ambiguo no recibe promoción automática ni un bypass.
- R-02 costo de historial pendiente; R-03 pacing entre procesos no garantizado, sin defecto 429 proveedor probado; R-04 límite runner blando aceptado, sin garantía de drenaje total. Core/simulación local no certifica hosting, cuotas, cron ni OAuth LIVE.
- Truth: UNKNOWN no es 0; CONFLICT explícito; PACK_MEMBERSHIP_UNVERIFIED fail-closed; billing-info/buyer_id no son verdad fiscal; Billing CREDIT_NOTE no es artifact del cliente.
- OP-02/OP-03 y entrega de recuperación de contraseña siguen diferidos. Gates externos runtime Hostinger/protección main y app ERP2/OAuth productivo permanecen diferidos.

## Orden local antes de gates externos

```text
L1 Web Installer humano/local-first/DB-safe      CLOSED
L2 Company management                           CLOSED
L3 Users + roles/memberships                     NEXT
L4 Mercado Libre application settings
L5 Mercado Libre account management
L6 local MLSIM account linking
L7 account lifecycle
L8 human E2E local desde instalación vacía
L9 Local Release Candidate + package
L10 decisión explícita para Hostinger / F14 LIVE
```

## Evidencia vigente

- `storage/exports/local-company-management/`: QA, snapshot, reporte, patches, receipt, manifest y ZIP de evidencia. Sin deploy ZIP. El receipt contiene commit/remoto/árbol comprobados.
- `storage/exports/web-installer-v2/`: certificación L1 anterior, relevante para owners no modificados; no sustituye la QA de L2.
- `storage/exports/web-installer-v1/`: intento y borrador anteriores, sólo evidencia; no usar para upload.
- `storage/exports/mlsim-2/`, `local-stability-r02-r03-r04/`, `post-mb-op-01-focal-ux-recheck/`: evidencia previa por procedencia y cobertura de owners.

[Contratos durables](ERP2_AUTHORITY.md). Este cierre no inicia gestión de usuarios/roles, cuentas ni fases externas.
