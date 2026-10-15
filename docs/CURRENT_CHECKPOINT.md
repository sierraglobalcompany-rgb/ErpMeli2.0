# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-1 STATIC CLEAN / CODEX BROWSER-QA PROMPT READY / AWAITING EVIDENCE / STOP**

> Autoridad operativa para recuperar el proyecto si este chat termina. Retomar exactamente desde `NEXT`; no reabrir bloques ya certificados ni reconstruir estado por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-15
REPO=sierraglobalcompany-rgb/ErpMeli2.0
STABLE_BRANCH=impl/v3-b-sales-audit-20261010
STABLE_SHA=d56183584840187e215e225942831f40ec9a04ae
UI_BRANCH=ui/operator-shell-v1-20261014
PR=18 feat(ui): modernize operator workspace
PR_STATE=OPEN / DRAFT / NOT MERGED / MERGEABLE
PR_HEAD_BEFORE_THIS_DOC_CHECKPOINT=f0b52e6e2063dd9f9514e896f86e73672f04d616
LATEST_CERTIFIED_PRODUCT_UI_SOURCE=f9c7c0b00f17727dea749068d2a43e486fb1df91
LATEST_PRODUCT_UI_QA=Actions #814 PASS PHP 8.3/8.4/8.5
HOSTINGER_UI_DEPLOYED=NO
REAL_MELI= CLOSED
REMOTE_WRITES=OFF
```

Stable no se mueve sin autorización explícita del usuario.

## 2. LEYES

```text
KISS
- A→B
- simple, eficiente, optimizado
- sin arquitectura/frontend innecesariamente complejos

ANTI-RUIDO
- un artefacto estándar por lote
- no duplicar CSS, navegación, handoffs o evidencia
- eliminar sólo con evidencia; si no, unir/simplificar/optimizar

ANTI-PARCHE
- corregir owner/causa raíz
- no encadenar workarounds
```

Metodología:

```text
un microbloque
→ RED si aplica
→ GREEN mínimo
→ QA
→ checkpoint
→ STOP
```

Un `continua` autoriza sólo el `NEXT` actual.

## 3. PRODUCT / PHASE STATE

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
```

Functional RC histórico:

```text
SOURCE=f05377cb17afc3971997f686102ddf29dfbe6bc0
FILE=ERP_MELI_2_0_RC_L9_f05377cb.zip
SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
```

## 4. HOSTINGER / EXTERNAL

ERP2 ya fue instalado live y el usuario reportó smoke funcional previo sin errores en login, Companies, Users, Configuration y Sales.

La rama UI actual NO está desplegada.

Target conocido:

```text
https://erpmeli.bodegadigitalmedellin.com/
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

G8 `HOSTINGER_REALITY` sigue NO CERTIFIED hasta evidencia real de:

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

Guardrails externos:

```text
no deploy UI
no Hostinger mutation
no real MELI OAuth/API
no ERP1 token migration
remote writes=OFF
F15 paused
F16 unauthorized
```

## 5. ERP1 FAILURE MODES ERP2 MUST KEEP AVOIDING

```text
ERP1: 429 storms / FIFO-HOL blocking / gRPC-batches-jobs desync
ERP2 safeguards:
- WorkRepository::claimNext ignores deferred/future and orders eligible available_at,id
- durable family-scoped cooldown
- one physical HTTP request / no inline retry
- follow-up during cooldown = 0 requests
- BillingPeriodSyncHandler reuses same Work row; no child/page explosion
- FinancialReadModel local SQL: HTTP=0 / Work=0 / Queue=0 / ledger=0
- unknown poison work terminal failed
- no gRPC / RabbitMQ / Redis / broker / parallel queue
```

No UI block may weaken these safeguards.

## 6. UI-1 COVERAGE

PR #18 cubre:

```text
navigation/operator shell
login
guest / redirect
sales list
sales detail
catalog
billing
companies
users
system settings
company selector
installer
MELI accounts
MELI global app settings
/assets/app.css delivery
stylesheet dedup cleanup
```

Deliberate certified non-visual changes included:

```text
guest GET / → 303 /login
invalid/disabled integer session still authority validation → 403
DB_HOST default localhost
DB_PORT default 3306
APP_URL autodetect when empty
/assets/app.css fixed READ-only route
```

## 7. CERTIFIED QA HISTORY

```text
#780 PASS PHP 8.3/8.4/8.5 — installer DB defaults/test alignment
#783 PASS PHP 8.3/8.4/8.5 — APP_URL autodetect
#788 PASS PHP 8.3/8.4/8.5 — installer visual shell
#794 PASS PHP 8.3/8.4/8.5 — MELI accounts UI
#799 PASS PHP 8.3/8.4/8.5 — global MELI settings UI
#807 PASS PHP 8.3/8.4/8.5 — sales detail final
#814 PASS PHP 8.3/8.4/8.5 — stylesheet dedup anti-noise cleanup
```

Latest certified product/UI source:

```text
f9c7c0b00f17727dea749068d2a43e486fb1df91
```

## 8. STATIC FINAL AUDIT

PR #18 vs stable fue auditado file-by-file.

No se encontraron cambios accidentales en:

```text
schema / migrations
business repositories/queries
WorkRunner / queues
Billing handlers
Sales processors
invoicing
reconciliation business logic
OAuth services
real MELI transport
remote-write implementation
F15/F16
```

Findings cerrados:

```text
A. duplicate /assets/app.css references=CLOSED
   RED=db6ebf7632e70c2ff01cb67c84ff6fe68d4852a3 / Actions #810 expected failures
   GREEN=f9c7c0b00f17727dea749068d2a43e486fb1df91 / Actions #814 PASS

B. PR #18 body documentation drift=CLOSED
   PR body now enumerates installer defaults, APP_URL autodetect, MELI screens,
   sales detail, app.css route, dedup cleanup, QA and remaining browser gate.
```

Current law verdict:

```text
KISS=PASS
ANTI_PATCH=PASS
ANTI_NOISE_STATIC=PASS
BUSINESS_LOGIC_REGRESSION_STATICALLY_FOUND=NO
UNEXPECTED_REMOTE_WRITE=NO
MELI_REAL_OPENED=NO
```

## 9. BROWSER VISUAL QA — CURRENT STATUS

ChatGPT no dispone en este hilo de navegador interactivo local capaz de producir la evidencia visual requerida.

Por ello se generó un único prompt descargable para Codex:

```text
ERP_MELI_2_0_PROMPT_CODEX_UI1_QA_VISUAL_REAL_2026-10-15.md
```

El prompt ordena QA únicamente, sin cambios de producto:

```text
local/test only
REAL_MELI_HTTP=0
remote writes=OFF
no Hostinger
no real MELI
no commits/push/merge/deploy
worktree unchanged
browser desktop=1440x900
browser mobile=390x844
```

Matriz obligatoria:

```text
/login
operator navigation shell
/sales
/sales/{id}
/catalog
/billing
/companies
/users
/settings
/company/select
/mercadolibre/accounts
/settings/mercadolibre
installer isolated/local
```

Comprobaciones obligatorias:

```text
/assets/app.css HTTP 200
single app.css reference on relevant rendered views
console errors
failed assets
404/500
horizontal overflow
mobile clipping/overlap
no unexpected external MELI requests
```

Codex debe producir fuera del repo un único ZIP de evidencia con:

```text
UI1_VISUAL_QA_REPORT.md
screenshots/
browser-console/
network/
runtime/
```

Resultados válidos:

```text
PASS
FAIL
BLOCKED
```

Codex NO está autorizado a corregir findings en esa ejecución.

## 10. GUARDRAILS CURRENT

```text
NO merge PR #18
NO stable mutation
NO UI deploy
NO Hostinger
REAL_MELI_HTTP=0
NO real MELI OAuth/API
remote writes=OFF
NO F15/F16
NO secrets in chat/Git/logs
NO product fixes during browser-QA run
```

## 11. RECOVERY AUTHORITY

Si el chat termina:

1. Leer este archivo completo.
2. Verificar PR #18 y rama `ui/operator-shell-v1-20261014` contra GitHub.
3. No reabrir static audit ni UI implementation ya certificada.
4. Pedir/recibir únicamente el resultado y ZIP/reporte de Codex browser QA.
5. Auditar esa evidencia.
6. Si `PASS`, solicitar autorización explícita antes de merge/deploy.
7. Si `FAIL`, abrir sólo el primer finding verificable como microbloque bounded.
8. Si `BLOCKED`, resolver únicamente el blocker de entorno/evidencia.

## 12. NEXT — EXACTLY ONE MICROBLOCK

```text
WAIT FOR CODEX UI1_BROWSER_VISUAL_QA EVIDENCE

User action:
- run the downloadable Codex prompt
- return Codex final result and preferably upload the evidence ZIP/report

Then ChatGPT:
- audit report + screenshots/evidence
- confirm PASS / FAIL / BLOCKED independently
- update checkpoint
- STOP
```

No merge/deploy is authorized by `continua` at this checkpoint.
