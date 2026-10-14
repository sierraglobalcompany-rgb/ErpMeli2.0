# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=L8 HUMAN LOCAL E2E — PREPARED / WINDOWS EVIDENCE PENDING**

| Campo | Estado actual |
|---|---|
| Fecha | 2026-10-14 |
| Rama activa | `impl/l8-human-local-e2e-chatgpt` |
| Functional HEAD certificado | `9a67e843b189d7a958b184a64235022a6b36b578` |
| Stable separado | `impl/v3-b-sales-audit-20261010` — no merge autorizado |
| Actions | `#746` / run `38628786800` — PASS PHP 8.3 / 8.4 / 8.5 |
| PHP 8.3 | 764 tests / 6441 assertions / 0 failures |
| LOCAL_CORE_CONTRACTS_READY | YES |
| LOCAL_PRODUCT_READY | NO |
| L1 Web Installer | CLOSED |
| L2 Company Management | CLOSED |
| L3 Users/Roles/Memberships | CLOSED |
| L4 Meli App Settings | CLOSED |
| L5 Canonical Seller + Account Management | CLOSED |
| L6 Local OAuth / MLSIM | CLOSED |
| L7 Account Lifecycle | CLOSED |
| L8 Human Local E2E | IN PROGRESS |
| L9 Local Release Candidate | NOT STARTED |
| HOSTINGER | DEFERRED |
| F14 LIVE | DEFERRED |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| NEXT | Recibir evidencia Windows/local de Codex y ejecutar/certificar un único smoke humano L8 |

## Qué está cerrado localmente

L1–L7 están cerrados en cobertura local. El producto ya dispone de:

- instalador web humano/local-first/DB-safe;
- empresas;
- usuarios, roles y memberships;
- configuración de la aplicación Mercado Libre;
- identidad seller canónica y administración de cuentas;
- OAuth local mediante MLSIM sin HTTP real a Mercado Libre;
- lifecycle de cuenta: disconnect, reauthorization canónica y hard-delete sólo de cuentas prístinas.

L8 no está cerrado porque todavía falta evidencia del runtime Windows/local real y un smoke humano por navegador desde instalación vacía. L9 no debe abrirse en el mismo bloque.

## L8 — preparación GitHub cerrada

La preparación previa al smoke humano quedó certificada en el functional HEAD `9a67e843...`.

### Flujo humano navegable

```text
fresh install
→ login
→ empresa inicial / selección de empresa
→ usuarios y roles
→ settings Mercado Libre
→ cuentas Mercado Libre
→ OAuth local MLSIM
→ cuenta connected
→ disconnect
→ reauthorize misma canonical row
```

La UI de `/settings/mercadolibre` puede guardar `synthetic-client` / `synthetic-secret` en `storage/private/runtime.json`; para L8 el proceso ERP no debe heredar `MELI_CLIENT_ID` ni `MELI_CLIENT_SECRET` como variables externas, porque en ese caso el panel queda correctamente en modo administrado por servidor.

`APP_ENV=local` sólo acepta `MELI_LOCAL_SIM_BASE_URL=http://127.0.0.1:<port>`. Sin seam válido OAuth local falla 503. `RemoteHostPolicy` bloquea `api.mercadolibre.com` fuera de producción.

### Hueco detectado y corregido antes del smoke

RED:

```text
380da74c2cb64163ba9459dfd5b83c5f8f98d9cd
```

El segundo ciclo OAuth humano fallaba porque `OAUTH_CONNECT` tenía sólo dos pasos globales. PHP 8.3 confirmó exactamente un fallo: el segundo callback devolvía 502 en vez de 303.

GREEN:

```text
9a67e843b189d7a958b184a64235022a6b36b578
```

MLSIM repite únicamente el patrón exacto `oauth.token → users.me` de `OAUTH_CONNECT`; los demás escenarios continúan finitos. No se modificó código productivo en `app/`. Diff del arreglo frente al checkpoint previo: test E2E +25 líneas y router MLSIM +6/-2.

Actions `#746` certificó PHP 8.3/8.4/8.5 GREEN; PHP 8.3 terminó con 764 tests / 6441 assertions.

## Gate humano mínimo para cerrar L8

1. runtime/preflight local PASS;
2. instalación real desde base vacía;
3. login con el administrador creado por el wizard;
4. empresa inicial visible y seleccionable;
5. usuarios/roles/memberships accesibles desde UI;
6. guardar credenciales sintéticas desde UI con `MELI_CLIENT_ID` y `MELI_CLIENT_SECRET` ausentes del entorno del proceso ERP;
7. MLSIM loopback activo y `MELI_LOCAL_SIM_BASE_URL` presente sólo en el proceso local;
8. conectar seller sintético `100` / `MCO` por navegador;
9. comprobar estado `connected`;
10. desconectar;
11. reautorizar la misma canonical row mediante un segundo OAuth completo;
12. demostrar que no hubo HTTP real, remote writes, Hostinger ni gates externos.

Hard-delete se valida sólo si existe una cuenta realmente prístina. Nunca se elimina historia para forzar el caso.

## Papel de Codex en L8

Codex se usa aquí únicamente para evidencia que requiere el PC Windows/local: runtime, procesos, puertos, navegador y filesystem local. No debe rediseñar el sistema ni implementar otra arquitectura. Si detecta un hueco real, se vuelve al owner raíz con RED → GREEN mínimo → QA → checkpoint.

## Guardrails permanentes

```text
KISS: simple, eficiente, optimizado, eficaz; sin arquitectura innecesaria
NOISE: borrar sólo con evidencia; si no, simplificar/mergear/optimizar
ANTI-PATCH: corregir el owner raíz, no parche sobre parche
REAL_MELI_HTTP=0 durante L8
remote writes=OFF
no Hostinger / no F14 / no LIVE
no L9 hasta cerrar L8
no merge a stable/main sin autorización explícita
```

R-02 costo de historial, R-03 pacing entre procesos y R-04 límite blando del runner permanecen como límites conocidos previos; L8 no los declara resueltos salvo evidencia nueva específica.

## NEXT — un solo microbloque

Recibir la evidencia Windows/local de Codex y ejecutar/certificar el smoke humano L8. Si pasa, cerrar L8 y STOP. Si falla, corregir únicamente el owner raíz del fallo, certificarlo y volver al smoke antes de declarar L8 cerrado.

La continuidad documental adicional está en Issue #16. Los contratos durables siguen en [`ERP2_AUTHORITY.md`](ERP2_AUTHORITY.md).
