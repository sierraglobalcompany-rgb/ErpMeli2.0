# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=L10 CLOSED — F14 READ-ONLY PREFLIGHT OPEN**

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
| F14 READ-only external certification | F14-A PREFLIGHT OPEN — todavía sin OAuth/API real ejecutado |
| HOSTINGER_REALITY G8 | NOT CERTIFIED |
| F15 Cutover | PAUSED |
| F16 Remote Writes | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| NEXT | Ejecutar F14-A de realidad externa; STOP antes de OAuth/API real |

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

La evidencia local es suficiente para **abrir únicamente el preflight de certificación externa READ-only**. Esta decisión no autoriza por sí sola OAuth real, requests a la API real, deploy/cutover ni ninguna escritura remota.

La nomenclatura operativa vigente sigue el roadmap actual: F14 es la certificación externa READ-only. `docs/F14_READ_CERTIFICATION.md` conserva evidencia histórica del cierre contractual local y una referencia antigua a `F15 Stage 0`; esa referencia no renumera el roadmap vigente.

## F14-A — preflight obligatorio antes de API real

Primero certificar hechos externos que no pueden demostrarse localmente:

```text
HOSTINGER_RUNTIME=MEASURED
WEB_PHP_VERSION=8.3|8.4|8.5
CLI_PHP_VERSION=8.3|8.4|8.5
HTTPS_STATUS=PASS
SUBDOMAIN_DOCUMENT_ROOT=SAFE
STORAGE_SOURCE_NOT_WEB_EXPOSED=PASS
HOST_DB_QUOTA=KNOWN
HOST_DISK_QUOTA=KNOWN
HOST_INODE_QUOTA=KNOWN
HOST_CRON_CAPACITY=KNOWN
HOST_MIN_CRON_INTERVAL=KNOWN
MELI_APP_CREATED=YES
REDIRECT_URI_MATCH=YES
CLIENT_SECRET_STORED_OUTSIDE_GIT=YES
NOTIFICATION_CALLBACK_REACHABLE=YES
REMOTE_WRITES=OFF
```

Target documentado:

```text
PUBLIC_URL=https://erpmeli.bodegadigitalmedellin.com/
OAUTH_CALLBACK=https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
WEBHOOK=https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
```

Sólo después de F14-A GREEN puede abrirse un siguiente microbloque para autorización ERP2 fresca de **un seller** y GET reales controlados. No reutilizar tokens ERP1. No refresh/polling/retries automáticos. No POST/PUT/DELETE remotos.

## Alcance futuro de calibración READ-only

Cuando se autorice el bloque posterior de GET reales, calibrar sólo lo necesario para cerrar evidencia externa pendiente, incluyendo según el flujo real disponible:

- identidad seller/account;
- `GET /orders/{order_id}`;
- `GET /packs/{pack_id}` cuando exista un pack real aplicable;
- `GET /orders/billing-info/{site_id}/{billing_info_id}` cuando exista referencia real aplicable;
- billing period details sólo dentro del contrato READ existente;
- persistencia/snapshot únicamente si el owner actual ya la define; no inventar schema ni servicio nuevo.

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

**F14-A External Reality Preflight.** Codex sólo mide hPanel/runtime/CLI/cuotas/configuración de app ERP2 porque ChatGPT no puede acceder a esas superficies privadas. No deploy, no OAuth seller, no API real. STOP al devolver PASS/BLOCKED.

Los contratos durables siguen en [`ERP2_AUTHORITY.md`](ERP2_AUTHORITY.md). La evidencia local F14 previa sigue en [`F14_READ_CERTIFICATION.md`](F14_READ_CERTIFICATION.md).
