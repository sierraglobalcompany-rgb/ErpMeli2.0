# ERP MELI 2.0 — AUTHORITY DURABLE

Este archivo contiene únicamente contratos durables de arquitectura y dominio. No conserva SHAs, runs, changelog, planes de implementación ni el siguiente microbloque; todo eso pertenece a `docs/CURRENT_CHECKPOINT.md`. Git conserva la historia.

## 1. Autoridad y alcance

Para saber qué está implementado hoy:

```text
código/schema real del branch activo
> tests/CI del SHA relevante
> docs/CURRENT_CHECKPOINT.md
> este ERP2_AUTHORITY.md
> README/documentación de apoyo
> historia/inferencias
```

Para contratos externos:

```text
documentación oficial vigente + evidencia API real controlada > suposiciones
```

Nunca convertir una inferencia en contrato.

Las leyes de ingeniería, TDD, KISS, reducción de ruido y reglas de eliminación viven en `AGENTS.md` y no se duplican aquí.

---

## 2. Presupuesto arquitectónico

Arquitectura deliberadamente pequeña:

```text
1 aplicación PHP/Slim
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
queues por dominio
priority queue
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

KISS significa menor complejidad neta, no mínimo número de clases a cualquier costo.

---

## 3. Work y runtime

Tabla única: `work_items`.

Estados:

```text
pending
running
done
failed
```

Orden de claim: `available_at, id`.

Capacidades aceptadas:

- active dedupe;
- claim token;
- bounded retry;
- defer;
- crash recovery;
- un solo runner;
- terminal cleanup.

`retryCurrentClaim` consume intento; `deferCurrentClaim` no consume presupuesto neto.

Crash recovery:

```text
running + attempt < cap -> pending
running + attempt >= cap -> failed
```

Retención de Work terminal: 30 días. Work es ejecución, no historial durable de negocio.

Cron/runtime presupuestado:

```text
bin/work.php
bin/cleanup.php
```

Cuando hosting esté certificado: `work.php` cada minuto y `cleanup.php` diario. No cron ni scheduler por Sales/Billing/Financial.

---

## 4. Mercado Libre core, OAuth y rate safety

ERP2 usa una aplicación Mercado Libre dedicada y separada de ERP1. No migrar refresh tokens de ERP1.

Núcleo único:

- `MeliClient`;
- operation registry;
- OAuth PKCE/state;
- tokens cifrados;
- refresh lock/reread;
- pacing;
- cooldown durable;
- api usage aggregate.

Clasificaciones remotas:

```text
READ
AUTH
WRITE
```

Unknown/missing classification -> BLOCK.

429 registra cooldown y difiere Work; no consume retry budget y no hace retry inline. 5xx/transport usan bounded retry. Remote writes permanecen OFF hasta autorización explícita del usuario y el gate correspondiente.

---

## 5. Exactitud de datos y tiempo

Dinero exacto se persiste como DECIMAL/string exacta; nunca derivar valores comerciales exactos con `float`.

Timestamp remoto canónico requiere `Z` o `±HH:MM`; sin zona explícita, fail closed. Instantes persistidos en UTC.

La lógica de negocio no depende del timezone de PHP, MariaDB o hosting.

`orders.get` preserva números JSON donde la precisión comercial lo exige. `orders.search` usa IDs exactos y fechas para Sales Audit; no se usa para inferir dinero decimal.

---

## 6. Sales operational truth y fuente mensual

Membership mensual de Sales Audit:

```text
order.date_created
```

Contrato soportado actualmente:

```text
site_id = MCO
business timezone = America/Bogota
mes = [primer día local 00:00, primer día del mes siguiente 00:00)
```

Search remoto usa guard-band UTC ±1 hora; membership final se decide desde `date_created` exacto.

Seller Orders Search tiene horizonte aproximado de 12 meses y, como seller, filtra canceladas. Por tanto un run `valid` significa consistente respecto de seller-search + contrato conocido, no snapshot absoluto de toda la historia de Mercado Libre.

Fuera de horizonte soportado:

```text
run -> unavailable
Work -> done
antes de OAuth/HTTP
sin evidencia falsa
```

Short non-terminal page, fechas sin zona, IDs/paging/total incoherentes o contrato remoto malformed -> fail closed.

---

## 7. Sales Audit durable

Work types de Sales:

```text
order.sync
sales.audit
```

No crear subtipos `sales.audit.capture/repair/verify/confirm`; el mismo `sales.audit` se enruta por estado durable.

Flujo aceptado:

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

Estados permitidos de `sales_audit_runs`:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

`sales_audit_orders` separa `capture_pass` A/B y usa identidad `(audit_run_id, capture_pass, external_order_id)`.

No agregar page/repair/confirm/history tables, estado `verifying`, confirm hash columns ni engines auxiliares.

### Repair

- recalcula el gap real;
- candidato determinista por `external_order_id`;
- exactamente un child `order.sync` activo/reutilizado a la vez;
- parent se difiere mientras child está pending/running;
- child terminal + gap persistente -> `attention`;
- child terminal no se recrea automáticamente;
- gap reparado -> `confirming`.

### Capture B / confirm

- B es captura remota independiente;
- A permanece intacto;
- terminal B exige count observado coherente;
- fingerprint B se compara con A durable;
- mismatch -> `attention`;
- equality -> `valid`;
- terminal no encola continuación extra ni `order.sync`.

### Baseline

- sin baseline previo: current confirmed -> valid;
- baseline válido equivalente: current -> valid y equivalente previo puede ser reemplazado;
- baseline válido divergente: baseline previo se conserva, current -> attention;
- nunca borrar baseline válido sólo por edad o por un nuevo run attention.

### Active-run guard

Una sola auditoría activa por `(company_id, account_id, period_key, contract_version)` mediante UNIQUE MariaDB sobre la versión activa generada. Activos: capturing/repairing/confirming. Terminales: valid/attention/unavailable.

### Start Audit

`POST /sales/audits` exige sesión autenticada, empresa seleccionada, admin membership, CSRF válido, cuenta conectada del tenant y mes MCO completamente cerrado.

UI usa `<input type="month">`; boundary convierte a `YYYY-MM-01`. Rechazo de current/future ocurre antes de crear durable state.

HTTP:

```text
success -> 303 /sales
duplicate active -> 409
non-admin -> 403
invalid CSRF -> 419
invalid input/account/current/future -> 422
```

Success crea run `capturing` + Work `sales.audit` de forma atómica.

---

## 8. Exact-order 404

Para `GET /orders/{order_id}`:

```text
HTTP 404
-> Work failed
-> last_error_code = meli_order_not_found
-> no retry/defer
-> no order persistence/mutation
```

Clasificación por status HTTP, no por body.

`401 -> refresh once -> retried GET 404` termina también `meli_order_not_found`; no cuarto request. Otros 4xx permanecen `meli_remote_permanent`; 429/5xx/transport/malformed-200 conservan sus contratos propios.

Si el 404 corresponde a un child de repair y el gap persiste, Sales Audit termina `attention`; nunca convertirlo en período completo.

---

## 9. Webhook, Debug y cleanup

Webhook es señal, no business history:

```text
validate topic/app
-> resolve unique connected account
-> extract order id
-> enqueue order.sync
-> ack rápido
```

Hardening multi-company/timestamp/retention se trata por separado cuando corresponda.

Debug DVR se conserva por responsabilidades reales: sanitización, cap, correlación, gzip, retention, export, checksums, filesystem security y fail-safe. `DEBUG OFF` implica cero DVR writes.

Cleanup diario sólo cubre responsabilidades demostradas: Debug retention, Work terminal >30d y API usage >90d. Sales Audit no se poda por edad ciega.

---

## 10. Billing: contrato aceptado y hard gate C0

Billing es conciliación fiscal/financiera; no es Sales operational truth.

Operación externa principal registrada:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
operation = billing.period.details
```

Request contract actualmente aceptado:

```text
period_key = YYYY-MM-01
document_type = BILL | CREDIT_NOTE
limit = 1000
from_id = cursor actual
sort_by = ID
order_by = ASC
```

BILL y CREDIT_NOTE son streams separados. La paginación es secuencial y parte de `from_id=0`.

Existe tooling C0 read-only y sanitizado que valida guards de producción/opt-in/argumentos/cuenta/token y compone `BillingC0Probe` con el `MeliClient` existente. C0 no persiste Billing, no refresca OAuth, no persiste api usage/cooldown y no hace remote writes.

### Hard gate

Antes de diseñar/activar el handler durable de Billing, cerrar schema final o iniciar Billing Task2, se requiere evidencia real sanitizada de seller MCO que observe:

```text
first-page shape
last_id shape
sequential cursor progress
terminal signal
HTTP 206 sólo si realmente aparece
repeated/non-progress sólo si realmente aparece
```

No sintetizar 206, non-progress ni señal terminal. La documentación oficial puede describir 206 como parcial/en proceso, pero las decisiones de lifecycle/persistencia del handler se congelan hasta que C0 real confirme la forma necesaria.

Cualquier schema Billing pre-release existente es provisional y debe auditarse contra la evidencia C0 aceptada antes de continuar. No existe autoridad vigente para crear por inercia `billing.period.sync`, nuevas tablas, nuevos estados o un Billing-specific retry engine antes de ese gate.

---

## 11. Financial

Financial no está iniciado. No crear ledger/table inicialmente.

Modelo futuro esperado:

```text
Orders operational facts
+ Billing billed facts
+ pack relationships
+ shipping evidence
-> reconciliation/read model
```

Debe probar no-double-count, shared pack charge una vez y separación entre billed/analytical/official values. Antes de Financial se alinea `order_items.sale_fee`. No inventar fórmulas desde la forma del API.

---

## 12. Schema pre-release

Mientras no exista instalación persistente real que deba preservarse, los schemas pre-release se corrigen en sitio en los archivos de fase existentes; no crear migrations numeradas sólo para conservar historia.

Después del primer deploy persistente, migrations pasan a inmutables.

---

## 13. Documentación de evidencia

Documentos de apoyo vigentes:

- `docs/meli-contracts-2026.md` — contratos externos verificados;
- `docs/mercadolibre-app-erp2.md` — app/OAuth ERP2;
- `docs/runtime-preflight.md` — runtime preflight;
- `docs/hostinger-runtime-evidence.md` — evidencia de hosting.

El estado actual, gates, SHAs, QA y siguiente microbloque viven exclusivamente en `docs/CURRENT_CHECKPOINT.md`.
