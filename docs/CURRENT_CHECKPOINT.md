# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=LOCAL PRODUCT READY — STABLE INTEGRATED — L10 DECISION PENDING**

| Campo | Estado actual |
|---|---|
| Fecha | 2026-10-14 |
| Rama activa | `impl/v3-b-sales-audit-20261010` |
| Functional source certificado | `f05377cb17afc3971997f686102ddf29dfbe6bc0` |
| Integración estable | COMPLETED con autorización explícita del usuario |
| Último QA código | Actions `#758` — PASS PHP 8.3 / 8.4 / 8.5 |
| PHP 8.3 | 767 tests / 6450 assertions / 0 failures |
| LOCAL_CORE_CONTRACTS_READY | YES |
| LOCAL_PRODUCT_READY | YES |
| L1–L7 | CLOSED |
| L8 Human Local E2E | CLOSED / GREEN |
| L9 Local Release Candidate | CLOSED / GREEN |
| L10 External-gate decision | PENDING — requiere decisión explícita separada |
| HOSTINGER | DEFERRED |
| F14 LIVE | DEFERRED |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| NEXT | L10: decidir explícitamente si se abre o no el gate externo READ-only; no deploy automático |

## L8 cerrado

L8-A certificó instalación local real desde base vacía, login y navegación humana. L8-B certificó configuración MELI sintética, OAuth local MLSIM, Seller `100` / `MCO`, una sola cuenta canónica, disconnect y reauthorization sobre el mismo `account_id`, con dos ciclos `oauth.token → users.me` y cero requests al proveedor real.

## L9 cerrado

L9 corrigió en causa raíz la política duplicada de contraseña: `AuthService::bootstrapAdmin()` reutiliza `PasswordService::MIN_LENGTH = 6`.

```text
FUNCTIONAL_SOURCE=f05377cb17afc3971997f686102ddf29dfbe6bc0
Actions #758=PASS PHP 8.3 / 8.4 / 8.5
PHP 8.3=767 tests / 6450 assertions / 0 failures
PHPStan=PASS
syntax=PASS
```

RC certificado:

```text
ERP_MELI_2_0_RC_L9_f05377cb.zip
SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
PACKAGE_SELF_CONTAINED=YES
SECRETS_IN_PACKAGE=NO
EXTRACTED_INSTALLER=PASS
LOGIN=PASS
SALES=PASS
COMPANIES=PASS
USERS=PASS
REAL_PROVIDER_REQUESTS=0
```

## Integración estable cerrada

Stable y L9 habían divergido desde `b26db6a62683791f9c1d7b00c6e59f8964cfeec9`. Los tres commits exclusivos de stable eran un README temporal ya obsoleto y dos commits vacíos. No se arrastraron mediante merge.

La integración KISS se preparó desde el source funcional certificado, con un único commit docs-only de continuidad (`0ed78f64224f7b5a95ffd3c0d5597e0e059e682d`). Con autorización explícita del usuario, `impl/v3-b-sales-audit-20261010` se movió mediante force-with-lease desde `7dd666552ad0e81b3a4953cffd41393b1cedc1a1` al candidato. `main` no se movió y no hubo deploy.

## Guardrails permanentes

```text
KISS: simple, eficiente, optimizado, eficaz; sin arquitectura innecesaria
NOISE: eliminar/simplificar/unir antes de añadir
ANTI-PATCH: corregir causa raíz, no workaround sobre workaround
remote writes=OFF
no Hostinger / no F14 / no LIVE sin decisión L10 explícita
no main move ni deploy sin autorización explícita
```

## NEXT — un solo microbloque

**L10 es una decisión, no una implementación automática.** Evaluar si procede abrir exclusivamente el gate externo READ-only de F14. Hasta esa decisión explícita, Hostinger, OAuth real, F14/LIVE y cualquier remote write permanecen cerrados.

Los contratos durables siguen en [`ERP2_AUTHORITY.md`](ERP2_AUTHORITY.md).
