# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-1 STATIC CLEAN / PR #18 BODY ALIGNED / NEXT BROWSER VISUAL QA / STOP**

> Autoridad operativa para recuperar el proyecto si este chat termina. Retomar exactamente desde `NEXT`; no reabrir bloques ya certificados ni reconstruir estado por intuición.

## 1. REPOSITORY AUTHORITY

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Repo | `sierraglobalcompany-rgb/ErpMeli2.0` |
| Stable | `impl/v3-b-sales-audit-20261010` |
| Stable SHA | `d56183584840187e215e225942831f40ec9a04ae` |
| Rama UI | `ui/operator-shell-v1-20261014` |
| PR UI | `#18 feat(ui): modernize operator workspace` |
| PR #18 | OPEN / DRAFT / NOT MERGED / MERGEABLE |
| Último source product/UI certificado | `f9c7c0b00f17727dea749068d2a43e486fb1df91` |
| Último QA product/UI | Actions `#814` PASS PHP 8.3 / 8.4 / 8.5 |
| Hostinger | ERP2 instalado; rama UI NO desplegada |
| Mercado Libre real | CERRADO |
| Remote writes | OFF |

No mergear PR #18 a stable ni desplegar a Hostinger sin autorización explícita del usuario.

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
- corregir owner real / causa raíz
- no encadenar workarounds
```

Metodología:

```text
un solo microbloque
→ RED esperado cuando aplica
→ GREEN mínimo
→ QA
→ checkpoint
→ STOP
```

Un `continua` autoriza sólo el microbloque `NEXT` actual.

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
L10=GO sólo para certificación externa READ-only por microbloques
```

Functional RC source histórico:

```text
f05377cb17afc3971997f686102ddf29dfbe6bc0
```

RC certificado:

```text
ERP_MELI_2_0_RC_L9_f05377cb.zip
SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
```

Stable integration histórica permanece intacta en:

```text
d56183584840187e215e225942831f40ec9a04ae
```

## 4. EXTERNAL / HOSTINGER GATES

Target live:

```text
https://erpmeli.bodegadigitalmedellin.com/
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

ERP2 ya fue instalado live y el usuario reportó smoke funcional previo sin errores en login, Companies, Users, Configuration y Sales.

La rama UI actual NO está desplegada.

HOSTINGER_REALITY G8 sigue NO CERTIFIED hasta evidencia real de:

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

Gates:

```text
F15 Cutover=PAUSED
F16 Remote Writes=UNAUTHORIZED
REMOTE_WRITES=OFF
real MELI OAuth/API=CLOSED
no token ERP1 migration/reuse
```

## 5. ERP1 FAILURE MODES ERP2 MUST KEEP AVOIDING

ERP1 tuvo storms 429, FIFO/HOL blocking y desincronización gRPC/batches/jobs.

Safeguards ERP2 ya existentes y fuera del scope UI:

```text
WorkRepository::claimNext()
- ignora deferred/future
- ordena eligible por available_at,id

cooldown durable
- un request HTTP físico
- no inline retry
- follow-up durante cooldown = 0 requests
- family scoped

BillingPeriodSyncHandler
- difiere la misma Work row
- no child/page explosion

FinancialReadModel
- SQL local
- HTTP=0
- Work=0
- Queue=0
- ledger=0

unknown poison work
- terminal failed

arquitectura
- no gRPC
- no RabbitMQ
- no Redis
- no broker
- no parallel queue
```

## 6. UI-1 — CURRENT CERTIFIED COVERAGE

PR #18 cubre:

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

Shared UI:

```text
public/assets/app.css
sidebar navy
teal accent
white surfaces
responsive desktop/mobile rules
system font / Inter fallback
```

Guest root fix incluido y certificado:

```text
GET / guest → 303 /login
sesión integer inválida/deshabilitada conserva authority validation → 403
```

Installer incluido y certificado:

```text
DB_HOST default=localhost
DB_PORT default=3306
APP_URL autodetect scheme://authority cuando vacío
stored APP_URL conserva precedencia
installer visual shell modernizado
```

Asset delivery:

```text
GET /assets/app.css → 200 text/css sin autenticación
```

## 7. IMPORTANT UI CERTIFICATIONS

```text
#780 PASS PHP 8.3/8.4/8.5 — installer DB defaults + test alignment
#783 PASS PHP 8.3/8.4/8.5 — APP_URL autodetect
#788 PASS PHP 8.3/8.4/8.5 — installer visual shell
#794 PASS PHP 8.3/8.4/8.5 — MELI accounts UI
#799 PASS PHP 8.3/8.4/8.5 — global MELI settings UI
#807 PASS PHP 8.3/8.4/8.5 — sales detail final
#814 PASS PHP 8.3/8.4/8.5 — stylesheet dedup anti-noise cleanup
```

Latest product/UI source certified:

```text
f9c7c0b00f17727dea749068d2a43e486fb1df91
```

Actions #814 includes syntax, PHPStan and PHPUnit GREEN on PHP 8.3 / 8.4 / 8.5.

## 8. STATIC FINAL AUDIT RESULT

PR #18 vs stable was audited file-by-file.

Found NO accidental product changes in:

```text
- schema/data model
- migrations
- business repositories/queries
- WorkRunner / queue behavior
- Billing handlers
- Sales processors
- invoicing
- reconciliation business logic
- OAuth services
- MELI real transport
- remote-write implementation
- F15/F16
```

Deliberate non-visual changes in PR #18 are known/certified:

```text
1. guest root redirect to /login
2. DB_HOST default localhost / DB_PORT 3306
3. APP_URL autodetect when empty
4. fixed READ-only /assets/app.css route
```

### Anti-noise Finding A — CLOSED

Previously duplicate `/assets/app.css` references existed in Billing, Catalog, Sales list and conditional company selector.

RED:

```text
commit db6ebf7632e70c2ff01cb67c84ff6fe68d4852a3
Actions #810
PHP 8.3=778 tests / 6497 assertions / 2 expected failures
both failures=2 stylesheet references instead of 1
syntax=PASS
PHPStan=PASS
```

GREEN:

```text
Billing direct duplicate removed
Catalog direct duplicate removed
Sales list direct duplicate removed
company-select loads direct CSS only when navigation.php is absent
```

Certified source:

```text
f9c7c0b00f17727dea749068d2a43e486fb1df91
Actions #814 PASS PHP 8.3/8.4/8.5
```

### Anti-noise Finding B — CLOSED

The PR #18 body had stale text claiming installer defaults were not included.

On 2026-10-15 the PR body was replaced with an exact description of current contents, including:

```text
- shared UI shell
- root/login fix
- all covered operator/admin screens
- installer defaults and APP_URL autodetect
- installer visual shell
- /assets/app.css route
- stylesheet dedup cleanup
- certified behavior preserved
- QA run references
- remaining browser QA gate
```

Verified immediately after update:

```text
PR #18=open
draft=true
merged=false
mergeable=true
base=impl/v3-b-sales-audit-20261010
```

No code changed in the PR-body-alignment step.

## 9. CURRENT LAW VERDICT

```text
KISS=PASS
ANTI-PATCH=PASS
ANTI-NOISE STATIC=PASS
BUSINESS LOGIC REGRESSION STATICALLY FOUND=NO
UNEXPECTED REMOTE WRITE=NO
MELI REAL OPENED=NO
```

Static coverage is now clean. UI-1 is NOT yet final because browser visual QA is still pending.

## 10. GUARDRAILS

```text
no merge a stable todavía
no deploy UI todavía
no tocar Hostinger in browser-QA prep unless separately authorized
REAL_MELI_HTTP=0
no OAuth/API MELI real
remote writes=OFF
no F15/F16
no secretos en chat/Git/logs
no cambiar lógica para resolver observaciones visuales
```

ChatGPT owns GitHub analysis/implementation when tools allow it.

Codex is only for work ChatGPT cannot execute directly, mainly actual local Windows/runtime/DB/browser/hPanel/portal evidence. If Codex is needed, prompts must be downloadable Markdown and browser QA must remain local/test with `REAL_MELI_HTTP=0`.

## 11. NEXT — EXACTLY ONE MICROBLOCK

Esperar nuevo `continua`.

Next gate:

```text
BROWSER VISUAL QA PREPARATION / EXECUTION
- determine whether direct browser/runtime is available
- if not, prepare ONE Codex browser-QA prompt as downloadable Markdown
- use local/test environment only
- REAL_MELI_HTTP=0
- no Hostinger mutation
- no real OAuth/API MELI
- desktop + mobile
- verify login
- sales list + detail
- catalog
- billing
- companies
- users
- system settings
- company selector
- MELI accounts/settings with local/simulated data only
- installer
- verify /assets/app.css 200 and one stylesheet reference per rendered operator view
- collect screenshots/evidence
- report defects only; do not merge/deploy
- checkpoint
- STOP
```

After browser QA is GREEN, request explicit user authorization before any merge of PR #18 or Hostinger UI deployment.
