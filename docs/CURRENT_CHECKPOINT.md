# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-1 STATIC CLEANUP CERTIFIED / PR #18 BODY DRIFT ONLY PENDING / RECOVERY CHECKPOINT / STOP**

> Autoridad operativa para recuperar el proyecto si el chat se corta. Retomar exactamente desde `NEXT`; no reconstruir estado por intuición ni reabrir bloques ya certificados.

## 1. REPOSITORY / BRANCH AUTHORITY

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Repo | `sierraglobalcompany-rgb/ErpMeli2.0` |
| Stable | `impl/v3-b-sales-audit-20261010` |
| Stable SHA | `d56183584840187e215e225942831f40ec9a04ae` |
| Rama UI activa | `ui/operator-shell-v1-20261014` |
| PR UI | `#18 feat(ui): modernize operator workspace` |
| PR #18 | OPEN / DRAFT / NOT MERGED / MERGEABLE |
| Head UI antes de este checkpoint docs | `f9c7c0b00f17727dea749068d2a43e486fb1df91` |
| Último source UI/product certificado | `f9c7c0b00f17727dea749068d2a43e486fb1df91` |
| Hostinger | ERP2 instalado; rama UI NO desplegada |
| Mercado Libre real | CERRADO |
| Remote writes | OFF |

PR #18 al momento de este checkpoint:

```text
base=impl/v3-b-sales-audit-20261010
base_sha=d56183584840187e215e225942831f40ec9a04ae
head=ui/operator-shell-v1-20261014
head_sha=f9c7c0b00f17727dea749068d2a43e486fb1df91
commits=58
changed_files=26
mergeable=true
draft=true
merged=false
```

**No mergear PR #18 a stable ni desplegar a Hostinger sin autorización explícita del usuario.**

Continuidad histórica principal: Issue `#16 Local product readiness — Companies/Users/Meli Accounts antes de gates externos`; comentario/checkpoint reutilizable `6178211534`.

## 2. LEYES OBLIGATORIAS

```text
KISS
- camino A→B
- simple, eficiente, optimizado
- sin capas, frameworks ni abstracciones innecesarias

ANTI-RUIDO
- un artefacto estándar por lote
- no duplicar CSS, navegación, handoffs o contratos
- eliminar sólo con evidencia; si no, unir/simplificar/optimizar

ANTI-PARCHE
- corregir el owner real / causa raíz
- no encadenar workarounds
```

Metodología obligatoria:

```text
un solo microbloque
→ RED esperado cuando aplica
→ GREEN mínimo
→ QA
→ checkpoint
→ STOP
```

Reglas operativas:

```text
- ChatGPT implementa/audita lo que pueda hacer directamente.
- Codex sólo para Windows/runtime/DB/browser/hPanel/portal/evidencia cuando ChatGPT no tenga esa capacidad.
- No delegar a Codex análisis GitHub visible que ChatGPT sí puede hacer.
- REAL_MELI_HTTP=0 para QA local/CI.
- No secretos en chat/Git/logs.
- No main/stable merge sin autorización explícita.
```

## 3. PRODUCT / PHASE AUTHORITY

```text
LOCAL_PRODUCT_READY=YES
L1 Installer=CLOSED
L2 Companies=CLOSED
L3 Users/Roles/Memberships=CLOSED
L4 Meli App Settings=CLOSED funcionalmente
L5A Seller Identity=CLOSED
L5B Account Management=CLOSED
L6 OAuth/MLSIM=CLOSED local
L7 Account Lifecycle=CLOSED
L8 Human Local E2E=CLOSED
L9 RC/package=CLOSED
L10=GO sólo certificación externa READ-only por microbloques
F15 Cutover=PAUSED
F16 Remote Writes=UNAUTHORIZED
REMOTE_WRITES=OFF
```

Base funcional RC histórica:

```text
FUNCTIONAL_RC_SOURCE=f05377cb17afc3971997f686102ddf29dfbe6bc0
RC=ERP_MELI_2_0_RC_L9_f05377cb.zip
SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
```

Stable actual sigue intacto en:

```text
d56183584840187e215e225942831f40ec9a04ae
```

## 4. HOSTINGER / EXTERNAL GATES

Target público:

```text
https://erpmeli.bodegadigitalmedellin.com/
```

Document root histórico/objetivo:

```text
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

ERP2 ya fue instalado live desde el RC anterior. Smoke manual previo reportado por usuario:

```text
login=OK
Companies=OK
Users=OK
Configuration=OK
Sales=OK
```

La rama UI actual **NO está desplegada**.

G8 `HOSTINGER_REALITY` sigue **NO CERTIFIED** hasta evidencia real de:

```text
HOST_DISK_QUOTA
HOST_DB_QUOTA
HOST_INODE_QUOTA
HOST_CRON_CAPACITY
HOST_MIN_CRON_INTERVAL
WEB_PHP_VERSION
CLI_PHP_VERSION
HTTPS_STATUS
SUBDOMAIN_DOCUMENT_ROOT
```

Old F14-A `BLOCKED_NOT_DEPLOYED` es histórico/stale porque ERP2 ya está instalado; no reutilizarlo como estado live actual.

Mercado Libre futuro:

```text
OAuth callback=https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
Webhook=https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
usar app MELI dedicada ERP2
NO migrar/reusar tokens ERP1
real OAuth/API aún CERRADO
```

## 5. ERP1 FAILURE MODES / ERP2 SAFEGUARDS QUE NO SE DEBEN ROMPER

ERP1 sufría 429 storms, FIFO/HOL blocking y desincronización de jobs/batches.

ERP2 guardrails ya certificados:

```text
WorkRepository::claimNext()
- ignora deferred/future
- ordena eligible available_at,id

cooldown durable
- una request HTTP física
- no retry inline
- follow-up durante cooldown = 0 requests
- scope por familia

BillingPeriodSyncHandler
- difiere el mismo Work row
- no child/page explosion

FinancialReadModel
- SQL local
- HTTP=0
- Work=0
- Queue=0
- ledger=0

unknown poison work
- terminal failed

infra
- no gRPC
- no RabbitMQ
- no Redis
- no broker
- no parallel queue
```

No tocar estas garantías por motivos UI.

## 6. UI-1 — COBERTURA CERTIFICADA

PR #18 cubre el barrido visual identificado de:

```text
- navegación operador
- login
- ventas listado
- detalle de venta
- catálogo
- facturación
- empresas
- usuarios
- configuración del sistema
- selector de empresa
- instalador
- cuentas Mercado Libre
- configuración global aplicación Mercado Libre
```

También incluye cambios funcionales DELIBERADOS y ya certificados:

```text
GET / guest → 303 /login
sesión integer inválida/deshabilitada conserva authority validation → 403
/assets/app.css servido sin autenticación por ruta fija
DB_HOST default=localhost
DB_PORT default=3306
APP_URL autodetect scheme://authority cuando está vacío
valores guardados de installer conservan precedencia
```

Root/login hotfix histórico dentro del PR:

```text
GREEN root redirect=c560c53d9c5eba1046a01bf9139eaab5320eb2bd
alignment/cert source=c003ba368c812be0da8e84a2108f1dab7fb5413e
```

QA UI relevante:

```text
#780 PASS PHP 8.3/8.4/8.5 — installer DB defaults
#783 PASS PHP 8.3/8.4/8.5 — APP_URL autodetect
#788 PASS PHP 8.3/8.4/8.5 — installer visual shell
#794 PASS PHP 8.3/8.4/8.5 — Cuentas Mercado Libre
#799 PASS PHP 8.3/8.4/8.5 — Settings Mercado Libre
#807 PASS PHP 8.3/8.4/8.5 — Sales detail
#814 PASS PHP 8.3/8.4/8.5 — CSS link anti-noise cleanup
```

## 7. STATIC PR #18 AUDIT — RESULT

Auditoría final estática previa contra stable:

```text
BASE=d56183584840187e215e225942831f40ec9a04ae
HEAD auditado entonces=4e346fc9f0dbc736f271c7c34da125c3e50aae48
status=ahead
behind_by=0
reviews=0
review_threads=0
```

No se detectaron cambios funcionales accidentales en:

```text
schema/data model
repositorios/queries de negocio
WorkRunner/colas
Billing handlers
Sales processors
invoice/post-sale logic
OAuth services
transport real MELI
remote writes
F15/F16
```

Veredicto tras la auditoría:

```text
KISS=PASS
ANTI-PATCH=PASS
BUSINESS LOGIC REGRESSION STATICALLY FOUND=NO
UNEXPECTED REMOTE WRITE=NO
MELI REAL OPENED=NO
```

Se detectaron dos findings anti-ruido/documentales. Finding A ya está cerrado; Finding B sigue pendiente.

## 8. CLOSED MICROBLOCK — ANTI-NOISE CSS LINK CLEANUP

Finding A original:

```text
navigation.php ya carga /assets/app.css
pero Billing, Catalog y Sales list también lo cargaban directamente
company-select duplicaba el link cuando había empresa/navegación
```

Contrato RED añadido:

```text
tests/Integration/ViewStylesheetDeduplicationTest.php
commit RED=db6ebf7632e70c2ff01cb67c84ff6fe68d4852a3
```

Actions RED:

```text
#810 / run_id 38763072151
PHP 8.3=FAIL esperado
778 tests / 6497 assertions / 2 failures
únicos fallos=ViewStylesheetDeduplicationTest
causa=2 referencias href="/assets/app.css" donde se exige 1
Syntax=PASS
PHPStan=PASS
```

GREEN mínimo:

```text
Billing remove duplicate link=4d695d4bf041d8f0a50eb31ceb174e451c94c345
Catalog remove duplicate link=6bdf25489c405f6dbed6db1fe9637a6141374f9c
Sales list remove duplicate link=a2dae97d0b19c5d83b395338746bef3c91d16527
company-select conditional link=f9c7c0b00f17727dea749068d2a43e486fb1df91
```

Resultado final:

```text
Billing → stylesheet exactamente una vez vía navigation.php
Catalog → stylesheet exactamente una vez vía navigation.php
Sales list → stylesheet exactamente una vez vía navigation.php
company-select sin company → link directo exactamente una vez
company-select con company → link vía navigation.php exactamente una vez
```

No se añadieron partials/head framework ni nuevas abstracciones.

Certificación final:

```text
Actions #814 / run_id 38763721289
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
QA completo=PASS
```

**FINDING A=CLOSED / GREEN CERTIFIED.**

Latest certified source:

```text
f9c7c0b00f17727dea749068d2a43e486fb1df91
```

## 9. ONLY REMAINING STATIC FINDING — PR BODY DRIFT

El body actual de PR #18 sigue diciendo:

```text
Installer auto-defaults are intentionally not included in this checkpoint yet.
```

Eso es falso: el PR ya contiene y certificó defaults/autodetect/visual installer.

El body tampoco enumera completamente:

```text
- installer visual
- Meli accounts
- global Meli settings
- sales detail
- CSS anti-noise cleanup
```

Clasificación:

```text
FINDING_B=OPEN
SEVERITY=LOW
DOCUMENTATION_DRIFT=YES
PRODUCT_LOGIC_IMPACT=NO
PRE_RELEASE_CLEANUP=YES
```

**No corregir código para este finding. Owner real = metadata/body del PR #18.**

## 10. VISUAL QA GATE

UI-1 todavía **NO se declara final** aunque código/CI estén GREEN porque falta QA visual real en navegador.

Orden correcto:

```text
1. cerrar Finding B (body PR #18) — bloque documental separado
2. checkpoint
3. browser QA real local/test — bloque separado
4. REAL_MELI_HTTP=0
5. desktop + mobile
6. login
7. sales list/detail
8. catalog
9. billing
10. companies/users/system settings
11. Meli accounts/settings sólo simulación/local
12. installer
13. comprobar /assets/app.css 200 y sin duplicación
14. checkpoint
```

Si ChatGPT no dispone del navegador/runtime local requerido para esa evidencia, usar Codex sólo para ejecutar browser QA y capturas. No pedir al usuario clicks manuales si Codex puede hacerlo.

## 11. GUARDRAILS VIGENTES

```text
no merge a stable todavía
no deploy UI todavía
no tocar Hostinger en el siguiente microbloque
REAL_MELI_HTTP=0
no OAuth/API MELI real
remote writes=OFF
no F15/F16
no secretos en chat/Git/logs
no cambiar lógica por problemas visuales/documentales
no abrir otro frente mientras haya un microbloque activo
```

## 12. NEXT — EXACTLY ONE MICROBLOCK

Esperar nuevo `continua`.

Siguiente microbloque exacto:

```text
PR #18 BODY ALIGNMENT ONLY
- leer diff/state actual del PR
- actualizar body del PR para reflejar exactamente lo incluido
- eliminar afirmación stale sobre installer defaults
- documentar guardrails reales
- NO tocar código
- NO tocar tests
- NO merge
- NO deploy
- verificar PR OPEN/DRAFT/MERGEABLE
- actualizar este checkpoint
- STOP
```

Después de ese bloque, el siguiente separado será **QA visual real en navegador**.

Sólo después de QA visual GREEN se podrá evaluar pedir autorización explícita al usuario para integrar PR #18 a stable y preparar un delta de despliegue seguro.
