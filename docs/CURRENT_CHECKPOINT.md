# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=F14-A BLOCKED — HOSTINGER TARGET NOT DEPLOYED**

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
| L1–L9 | CLOSED / GREEN |
| L10 External-gate decision | CLOSED — GO sólo para preflight/certificación READ-only |
| F14 READ-only external certification | F14-A BLOCKED — target Hostinger todavía no contiene ERP2 |
| HOSTINGER_REALITY G8 | NOT CERTIFIED |
| F15 Cutover | PAUSED |
| F16 Remote Writes | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| NEXT | Autorizar o rechazar deploy controlado del RC certificado a Hostinger; después repetir F14-A |

## Base certificada

L8 cerró instalación humana/local, MLSIM OAuth, lifecycle y evidencia visual. L9 cerró el RC autocontenido y corrigió en causa raíz la política duplicada de contraseña.

```text
FUNCTIONAL_SOURCE=f05377cb17afc3971997f686102ddf29dfbe6bc0
Actions #758=PASS PHP 8.3 / 8.4 / 8.5
PHP 8.3=767 tests / 6450 assertions / 0 failures
RC=ERP_MELI_2_0_RC_L9_f05377cb.zip
RC_SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
LOCAL_PRODUCT_READY=YES
```

## L10 — decisión cerrada

La evidencia local es suficiente para abrir únicamente la certificación externa READ-only por microbloques. L10 no autoriza OAuth real, requests a la API real, cutover ni escrituras remotas.

## F14-A — resultado real

Codex ejecutó el preflight externo sin mutaciones y devolvió:

```text
F14A_RESULT=BLOCKED
BLOCKER=BLOCKED_NOT_DEPLOYED
HTTPS_STATUS=200
PUBLIC_PAGE=Hostinger default page
WEB_PHP_VERSION=8.3.35
/bin/runtime-preflight.php=404
.env=403
.git/config=403
other_private_paths_checked=404
MUTATIONS=0
```

Conclusión: el subdominio/HTTPS existen, pero ERP2 todavía no está desplegado en el target. Por tanto no es posible certificar todavía CLI PHP, runtime ERP2, cuotas/cron desde el contexto de la aplicación ni completar G8.

La ausencia del deploy no es un defecto del producto y no se corrige con código ni con más pruebas locales.

## Puerta siguiente

Antes de repetir F14-A hace falta una autorización separada para desplegar **exactamente el RC L9 certificado** en Hostinger.

Ese deploy debe conservar:

```text
remote writes=OFF
no OAuth seller real
no API Mercado Libre real
no token ERP1
no POST/PUT/DELETE remotos
no cutover
no F16
```

Después del deploy se repite únicamente F14-A para medir runtime real, document-root, exposición web, CLI PHP, cuotas/cron y configuración de la app ERP2. Sólo si F14-A queda GREEN se considera un bloque separado para OAuth fresco de un seller y GET reales controlados.

## Guardrails permanentes

```text
KISS: simple, eficiente, optimizado, eficaz; sin arquitectura innecesaria
NOISE: eliminar/simplificar/unir antes de añadir
ANTI-PATCH: corregir causa raíz, no workaround sobre workaround
F14=READ-only certification
remote writes=OFF
no token ERP1 migration/reuse
no POST/PUT/DELETE reales
no retry/polling framework nuevo
no main move ni cutover sin autorización explícita
```

## NEXT — un solo microbloque

**Decisión de deploy controlado del RC certificado a Hostinger.** No preparar ni ejecutar el deploy hasta autorización explícita del usuario. Si se autoriza, usar el artefacto L9 certificado y detenerse otra vez antes de OAuth/API real.

Los contratos durables siguen en [`ERP2_AUTHORITY.md`](ERP2_AUTHORITY.md). La evidencia local F14 previa sigue en [`F14_READ_CERTIFICATION.md`](F14_READ_CERTIFICATION.md).
