# ERP MELI 2.0 — AUTHORITY ACTUAL / KISS V3.4

**Fecha:** 2026-10-10  
**Estado:** G4 SALES_AUDIT_TRUTH CERRADO / ejecución por microbloques  
**Rama activa:** `impl/v3-b-sales-audit-20261010`  
**Último GREEN funcional verificado:** `6b6093b65ad0ab65351140d79f8cfc78d1a56910`  
**QA funcional:** `38093953630` — PHP 8.3/8.4/8.5 SUCCESS, PHPStan 0, PHPUnit 224/1571  
**Remote Mercado Libre writes:** OFF  
**REAL_MELI_HTTP normal:** `0`

Este archivo conserva sólo la autoridad útil vigente. Git conserva la historia.

---

# 1. ORDEN DE AUTORIDAD

Si hay contradicción:

```text
1. código/schema del branch activo
2. tests/CI del SHA relevante
3. docs/CURRENT_CHECKPOINT.md
4. este ERP2_AUTHORITY.md
5. decisiones explícitas recientes del usuario
6. README/handoffs/planes históricos
7. inferencias
```

Para contratos externos:

```text
documentación oficial vigente + evidencia API real controlada > suposiciones
```

Nunca convertir una inferencia en contrato.

---

# 2. LEYES DE EJECUCIÓN

```text
1 microblock at a time
RED -> confirmar fallo previsto -> GREEN mínimo -> QA completa -> noise audit -> checkpoint -> STOP
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
Correct -> Simple -> Stable -> Maintainable -> Efficient -> Scalable
```

Reglas vinculantes:

1. No persistir datos derivables sin necesidad real.
2. No crear abstracción sin segundo consumidor real.
3. No crear tabla sin query/integridad/lifecycle demostrado.
4. No crear queue/scheduler/retry/repair/recovery engine por dominio.
5. No usar `float` para dinero exacto.
6. No usar timezone del servidor como verdad de negocio.
7. No afirmar cobertura que la fuente no demuestra.
8. Remote writes permanecen OFF hasta F16 + autorización explícita.
9. No merge ni deploy sin autorización explícita.
10. Git conserva historia; autoridad/checkpoint conservan verdad vigente.

---

# 3. BUDGET ARQUITECTÓNICO

Arquitectura deliberadamente pequeña:

```text
1 PHP/Slim app
1 repositorio
1 MariaDB
1 Work table
1 WorkRunner
1 MeliClient
1 operation registry
1 MeliCooldownRepository
1 api_usage_daily aggregate
1 Debug DVR
1 sales.audit Work type
1 SalesAuditHandler
1 SalesAuditRepository
1 SalesAuditRepairHandler
```

No crear sin evidencia nueva:

```text
microservices
brokers
priority/domain queues
domain schedulers
generic retry/recovery engines
repair engine
command/event bus
financial ledger
generic historical engine
adaptive rate engine
SalesAuditStateMachine
ConfirmRepository
BaselineService
FinalizerEngine
StartAuditService
generic transaction/lock manager
extra audit tables/states
```

KISS = menor complejidad neta, no mínimo número de clases a cualquier costo.

---

# 4. WORK

Tabla única: `work_items`.

Estados:

```text
pending
running
done
failed
```

Orden:

```text
available_at, id
```

Capacidades aceptadas:

- active dedupe;
- claim token;
- bounded retry;
- defer;
- crash recovery;
- un solo runner;
- terminal cleanup.

`retryCurrentClaim` consume intento. Usos actuales: 5xx/transport y futuro Billing 206.

`deferCurrentClaim` no consume presupuesto neto. Usos: 429/cooldown y parent repair esperando child.

Crash recovery:

```text
running + attempt < cap -> pending
running + attempt >= cap -> failed
```

Retención:

```text
done/failed >30d -> purge
```

Work es ejecución, no historial durable de negocio.

---

# 5. CRON / RUNTIME

Sólo:

```text
bin/work.php
bin/cleanup.php
```

Objetivo tras certificar Hosting:

```text
work.php -> cada minuto
cleanup.php -> diario
```

Mantener named lock global. No cron por Sales/Billing/Financial. No scheduler table.

---

# 6. MERCADO LIBRE CORE / OAUTH / RATE SAFETY

ERP2 usa aplicación Mercado Libre dedicada y separada de ERP1.

No migrar refresh tokens de ERP1.

Core único:

- `MeliClient`;
- operation registry;
- OAuth PKCE/state;
- tokens cifrados;
- refresh lock/reread;
- pacing;
- cooldown durable;
- api usage aggregate.

Clasificaciones:

```text
READ
AUTH
WRITE
```

Unknown/missing classification -> BLOCK.

429:

```text
register cooldown -> Work defer
```

No consume retry budget. No retry inline. No workers paralelos.

Remote writes permanecen OFF. Antes de F16 no debe existir UI/POST que los habilite.

---

# 7. EXACTITUD DE DATOS

## JSON / números

`orders.get` y futuro `billing.period.details` preservan JSON NUMBER cuando se requiere exactitud comercial.

`orders.search` usa decode nativo + `JSON_BIGINT_AS_STRING`; para Sales Audit importa ID exacto y fecha, no dinero decimal.

## Dinero

Persistencia comercial: `DECIMAL`, nunca `float`.

## Timestamps

Timestamp remoto canónico requiere:

```text
Z
ó
±HH:MM
```

Sin zona explícita -> fail closed.

Persistir instantes en UTC. Business month no depende de PHP/MariaDB/Hostinger timezone.

---

# 8. SALES TIME / SOURCE CONTRACT

Fuente de membership mensual:

```text
order.date_created
```

Soporte histórico actual:

```text
site_id=MCO -> America/Bogota
```

Otro site sin contrato explícito -> fail closed para certificación mensual.

Mes canónico:

```text
[first local day 00:00, next local month 00:00)
```

Search remoto usa guard-band UTC ±1h. Membership final se decide desde `date_created` exacto.

Seller Orders Search:

- horizonte aproximado ~12 meses;
- como seller filtra canceladas;
- no equivale al universo histórico absoluto.

Por tanto `valid` significa consistente/verificado respecto de seller-search + contrato conocido, no snapshot absoluto de todo Mercado Libre.

---

# 9. SALES WORK TYPES

```text
order.sync
sales.audit
```

No crear:

```text
sales.audit.capture
sales.audit.repair
sales.audit.verify
sales.audit.confirm
```

El mismo `sales.audit` se enruta según estado durable del run.

Flujo GREEN:

```text
Capture A
-> durable evidence A
-> canonical fingerprint A
-> compare local
-> bounded one-child repair si falta una orden
-> verify local
-> independent Capture B
-> compare A/B
-> baseline lifecycle
```

---

# 10. SALES AUDIT — DATOS DURABLES

`sales_audit_runs` conserva:

```text
id
company_id
account_id
period_key
contract_version
status
remote_total
canonical_count
set_hash
started_at
completed_at
updated_at
```

Estados permitidos:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

`sales_audit_orders`:

```text
audit_run_id
capture_pass ENUM('A','B')
external_order_id
remote_date_created
PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
```

No surrogate id, page/repair/confirm/history table, `in_period`, confirm_count/hash columns ni estados adicionales.

---

# 11. CAPTURE A — GREEN

Capture A exige:

- run/company/account coherentes;
- account conectado;
- source/site conocido;
- remote_total estable;
- offset/paging coherente;
- IDs exactos/únicos;
- `date_created` zoned;
- sin result/page malformed;
- sin página vacía/corta no terminal;
- observed count coherente;
- source horizon soportado.

Una página por Work. Capture no encola `order.sync` directamente.

Fuera de horizonte:

```text
run -> unavailable
Work -> done
antes de OAuth/HTTP
sin evidencia falsa
```

Short non-terminal:

```text
count(results) < paging.limit
AND offset + limit < total
-> fail closed
```

Fingerprint A:

```text
canonical_count
SHA-256(sorted canonical external_order_id)
```

Guard-band queda fuera del set canónico.

---

# 12. REPAIR / VERIFY — GREEN

Post Capture A:

```text
missing canonical A -> repairing -> una continuación sales.audit
sin missing         -> confirming
```

Repair:

- recalcula gap real;
- candidato determinista `ORDER BY external_order_id LIMIT 1`;
- encola/reutiliza exactamente un `order.sync`;
- parent se defer mientras child pending/running;
- no procesa segundo candidato en el mismo paso.

Child terminal + gap persistente:

```text
NO recrear child
-> run attention
-> parent termina
```

Sin gap:

```text
repairing -> confirming
```

Sin estado `verifying`. Repair/verify se ancla a `capture_pass='A'`.

---

# 13. CAPTURE B / CONFIRM — GREEN

A/B comparten `sales_audit_orders`, separados por `capture_pass`.

Reglas:

1. B usa el mismo `sales.audit`.
2. B procesa una página por Work.
3. A permanece intacto durante B.
4. El primer remote_total B vive en payload de continuación, no en columna nueva.
5. Terminal B exige count observado == remote_total.
6. Fingerprint B se compara con `canonical_count/set_hash` durable de A.
7. A/B mismatch -> `attention` + Work `done` atómicamente.
8. A/B equality -> `valid`, `completed_at`, Work `done` atómicamente.
9. Terminal B no encola continuación ni `order.sync`.

No second audit run, ConfirmRepository/Engine, relation/history table, second queue, Work type/status nuevo.

---

# 14. BASELINE LIFECYCLE — GREEN

No prior valid baseline:

```text
current internally confirmed -> valid
```

Prior equivalent valid:

```text
current -> valid
old equivalent run/evidence -> removed by replacement
```

Prior divergent valid:

```text
prior valid preserved
current -> attention
```

Nunca borrar baseline válido por edad ni por un nuevo run `attention`.

---

# 15. ACTIVE-RUN GUARD — GREEN

Una sola auditoría activa por:

```text
company_id
account_id
period_key
contract_version
```

Estados activos:

```text
capturing
repairing
confirming
```

Terminales:

```text
valid
attention
unavailable
```

Guard final de concurrencia = generated `active_contract_version` + UNIQUE MariaDB. No SELECT-before-INSERT ni lock manager.

---

# 16. START AUDIT — GREEN

Endpoint:

```text
POST /sales/audits
```

Boundary:

```text
authenticated session
selected company
admin membership
valid CSRF
connected account in selected company
UI YYYY-MM -> canonical YYYY-MM-01
legacy YYYY-MM-01 accepted
real MCO month/site validation
only fully closed MCO months
```

HTTP:

```text
success -> 303 /sales
duplicate active -> 409
non-admin -> 403
invalid CSRF -> 419
invalid input/account/current/future -> 422
```

Success atomically:

```text
create capturing run
-> enqueue initial sales.audit Work
-> COMMIT
```

Current/future rejection ocurre antes de durable state.

Admin UI usa `<input type="month">`, default/max = último mes cerrado MCO.

---

# 17. EXACT-ORDER 404 — GREEN

Para `GET /orders/{order_id}`:

```text
HTTP 404
-> Work failed
-> last_error_code = meli_order_not_found
-> no retry/defer
-> no order persistence/mutation
```

Clasificación por status HTTP; no depende del cuerpo.

```text
401 -> refresh once -> 404
```

termina también `meli_order_not_found`, con exactamente tres requests físicos: order GET, OAuth POST, retried order GET.

403/otros 4xx permanecen `meli_remote_permanent`. 429, 5xx, transport y malformed-200 conservan sus contratos previos.

Cuando este child terminal pertenece a repair y el gap persiste:

```text
NO recrear child
-> audit attention
```

Nunca convertir un 404 en período completo.

---

# 18. G4 ADVERSARIAL CLOSURE — PASS

No se añadió mega-test redundante. La cobertura existente compone los invariantes críticos:

```text
SalesAuditCaptureHandlerTest
- horizon -> unavailable antes de OAuth/HTTP
- short non-terminal -> fail closed
- offsetless date -> fail closed sin evidencia parcial
- capture no fan-out a order.sync

SalesAuditUnauthorizedTest
- 401 refresh exactly once
- second 401 terminal
- OAuth 429 defer
- post-refresh 5xx retry
- 403 terminal

SalesAuditTransientFailureTest
- 429 defer sin burn
- 5xx bounded retry
- transport bounded retry

SalesAuditRepairRuntimeTest / Repair* tests
- one-child repair
- terminal child + gap -> attention
- no child recreation
- repaired gap -> confirming

SalesAuditConfirmValidTest
- independent B equality -> valid
- A preserved
- no extra continuation/order.sync

SalesAuditConfirmMismatchTest
- independent B mismatch -> attention
- A fingerprint preserved

SalesAuditBaselineLifecycleTest
- equivalent valid supersedes equivalent baseline
- divergent internally confirmed run preserves prior baseline and ends attention

SalesAuditActiveRunGuardTest
- every active state blocks duplicate via DB UNIQUE
- terminal allows replacement

SalesAuditHttpStartRouteTest
- auth/admin/CSRF/tenant/duplicate/current/future contracts

SyncOrderHandlerRemoteFailureTest
- exact 404 terminal
- 401-refresh-404 terminal/no fourth request
```

Último GREEN funcional:

```text
6b6093b65ad0ab65351140d79f8cfc78d1a56910
RUN 38093953630 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
PHPStan 0
PHPUnit 224 tests / 1571 assertions
REAL_MELI_HTTP=0
```

G4 no requiere nueva producción ni test duplicado sin nueva evidencia.

---

# 19. ORDER SYNC / COMMERCIAL FACTS

`order.sync` mantiene hechos individuales de orden.

Commercial fact probado pendiente de schema/persistencia:

```text
order_items.sale_fee
```

`gross_price` permanece DEFER hasta consumidor/caso probado.

---

# 20. WEBHOOK

Webhook = señal, no business history.

Target:

```text
validate topic/app
-> resolve unique connected account
-> extract order id
-> enqueue order.sync
-> ack rápido
```

`webhook_events` sólo se elimina tras prueba repo-wide de ausencia de consumidor real. No raw webhook/archive por inercia.

Hardening separado pendiente:

- seller multi-company scope;
- explicit timestamp timezone;
- retention.

---

# 21. DEBUG DVR

Se conserva por responsabilidades reales: sanitización, cap, correlación, gzip, retention, export, checksums, filesystem security y fail-safe.

```text
DEBUG OFF -> cero DVR writes
```

No mezclar su cleanup con G4 Sales Audit.

---

# 22. BILLING — BLOQUEADO EN C0

Tablas pre-release:

```text
billing_periods
billing_details
```

Operación:

```text
billing.period.details
```

Antes de handler real se exige C0 real sanitizado MCO que demuestre:

- primera página;
- cursor siguiente;
- señal terminal real;
- forma real de `last_id`;
- 206;
- non-progress guard.

No inferir terminal por short/empty/missing-last-id sin evidencia.

F6A Task 2 permanece bloqueado hasta C0.

---

# 23. FINANCIAL — NO INICIADO

No ledger/table inicialmente.

Read model futuro:

```text
Orders operational facts
+ Billing billed facts
+ pack relationships
+ shipping evidence
-> reconciliation/read model
```

Reglas: no double-count, shared pack charge una vez, no asumir sum(details)=official net, separar billed/analytical/official values.

Antes de Financial: alinear `order_items.sale_fee`.

---

# 24. SCHEMA PRE-RELEASE

Mientras no exista instalación persistente real a preservar:

```text
editar 004_sales.sql en sitio
editar 005_billing.sql en sitio
```

No 006/007/008 sólo para historia. Después del primer deploy persistente, migrations pasan a inmutables.

---

# 25. CLEANUP / SWITCHES

Cleanup diario sólo para responsabilidades demostradas:

```text
Debug retention
Work terminal >30d
API usage >90d
```

Sales Audit no se poda por edad ciega; baseline equivalente superseded se poda por replacement lifecycle.

Switches visibles:

```text
AUTOMATION
DEBUG
```

`AUTOMATION OFF`: cron no procesa, pending permanece, webhook puede seguir encolando.

Remote writes no es switch normal de usuario antes de F16.

---

# 26. GATES ACTUALES

| Gate | Estado |
|---|---|
| G1 REMOTE_TRUTH | PASS para boundary implementado |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS para Sales actual |
| G4 SALES_AUDIT_TRUTH | **PASS / CLOSED** |
| G5 BILLING_CURSOR_TRUTH | BLOCKED ON C0 |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

CI normal:

```text
PHPStan = 0
PHPUnit = PASS
REAL_MELI_HTTP=0
```

salvo smoke explícitamente autorizado.

External gates permanecen:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality
```

---

# 27. ORDEN INMEDIATO VIGENTE

```text
CLOSED:
G4 Sales Audit Truth

NEXT:
small DOC-CLEAN / GitHub issue hygiene

THEN:
Billing C0 real sanitized MCO smoke
-> Billing Task2
-> sale_fee alignment
-> Financial no-double-count
```

Hardening separado que NO se mezcla por inercia:

```text
Sales detail multi-account scope
webhook seller multi-company scope
webhook timestamp timezone
webhook_events retention
MariaDB session UTC
Slim diagnostic noise
```

---

# 28. STOP CONDITIONS

No iniciar Billing Task2 antes de C0.

No merge.  
No deploy.  
No remote writes.  
No producción DB change.  
No real Mercado Libre batch salvo smoke sanitizado explícitamente autorizado.

Cada microbloque termina con QA/noise audit/checkpoint antes de continuar.
