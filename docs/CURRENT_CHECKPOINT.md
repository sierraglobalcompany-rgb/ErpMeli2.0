# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=WEB INSTALLER V2 — HUMAN LOCAL-FIRST FOUNDATION PASS**

| Campo | Estado actual |
|---|---|
| Fecha | 2026-10-13 |
| Rama | impl/v3-b-sales-audit-20261010 |
| Base Git de la QA | 34716807fde39fd7414f09b53bfdaa5f327de1aa |
| HEAD remoto actual | 4f4a4b996da22053a82f645ce7626e05f7d13f15 |
| Árbol probado | Snapshot de fuentes congelado en `storage/exports/web-installer-v2/diff-freeze.txt`; commit funcional certificado `236ee9379179e8b43fa8ceabbd6c98a309df5290`; `4f4a4b99...` sólo añade continuidad README |
| LOCAL_CORE_CONTRACTS_READY | YES |
| LOCAL_PRODUCT_READY | NO |
| WEB_INSTALLER_V2 | PASS |
| LOCAL_HUMAN_ONBOARDING_FOUNDATION | PASS |
| LOCAL_COMPANY_ADMIN_READY | NO |
| LOCAL_USER_ADMIN_READY | NO |
| LOCAL_MELI_ACCOUNT_ADMIN_READY | NO |
| HOSTINGER | DEFERRED |
| F14 LIVE | DEFERRED |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| DEPLOY_PACKAGE | DEFERRED — LOCAL RELEASE CANDIDATE posterior |
| NEXT | LOCAL COMPANY MANAGEMENT |

## QA y límites actuales

- Full #1: 676 tests / 5781 assertions / 1 skip Windows existente; 0 failures/errors/deprecations. Focal instalador/auth/CLI/config: 54/552 PASS; Composer, PHPStan, sintaxis de 33 archivos y diff PASS. Full #2 no usado; 201 hashes de fuentes/config/test sin cambio después de full PASS.
- GitHub Actions run #681 del commit funcional `236ee937...` pasó en PHP 8.3/8.4/8.5. El commit posterior `4f4a4b99...` es docs-only README y no cambia producto.
- El wizard usa los owners existentes, admite URL local y Meli pendiente, prueba DDL con cleanup, exige DB vacía y dedicada, y rechaza residuo parcial sin reparación automática. Back, progreso, errores seguros y selección explícita de empresa se conservan.
- Core local y simulación de proveedor mantienen sus contratos; esto no certifica producto completo, hosting, cron, cuotas reales ni OAuth LIVE. Faltan gestión de empresas, usuarios/roles y cuentas Meli, y E2E humano local.
- R-02: costo medido de historial pendiente. R-03: separación de pacing entre procesos no garantizada, sin defecto 429 proveedor probado. R-04: presupuesto runner blando aceptado, sin garantía de drenaje total.
- Truth: UNKNOWN no es 0; CONFLICT explícito; PACK_MEMBERSHIP_UNVERIFIED fail-closed; billing-info/buyer_id no son verdad fiscal; Billing CREDIT_NOTE no es artifact de factura/nota crédito del cliente.
- OP-02/OP-03 y entrega de recuperación de contraseña siguen diferidos. Gates externos #3 runtime Hostinger/protección main y #5 app ERP2/OAuth productivo permanecen abiertos y diferidos.

## Orden local antes de gates externos

```text
L1 Web Installer humano/local-first/DB-safe      ✅ CLOSED
L2 Company management                            ← NEXT
L3 Users + roles/memberships
L4 Mercado Libre application settings
L5 Mercado Libre account management
L6 local MLSIM account linking
L7 account lifecycle
L8 human E2E local desde instalación vacía
L9 Local Release Candidate + package
L10 decisión explícita para Hostinger / F14 LIVE
```

## Evidencia vigente

- `storage/exports/web-installer-v2/`: snapshot QA, reporte, patches, receipt y manifest verificables; commit/sync y SHA finales en receipt. Sin ZIP de deploy.
- `storage/exports/web-installer-v1/`: evidencia del intento anterior y borrador no certificado; no usar para upload.
- `storage/exports/mlsim-2/`, `local-stability-r02-r03-r04/`, `post-mb-op-01-focal-ux-recheck/`: contratos/simulación/estabilidad/tenancy previos, conservados por procedencia para owners no cambiados.

[Contratos durables](ERP2_AUTHORITY.md). Git conserva historia; este bloque no inicia la gestión de empresas ni una fase externa.
