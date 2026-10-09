# ERP MELI 2.0 — AUTORIDAD CONSOLIDADA V3

**Fecha:** 2026-10-09  
**Estado:** DRAFT FINAL PARA REVISIÓN DEL USUARIO.  
**Rama:** `audit/v3-forensic-redesign-20261009`  
**Código productivo:** CONGELADO.  
**F6A Task 2:** CONGELADO.  

> Este documento es la autoridad consolidada para revisar y, sólo después de aprobación explícita, ejecutar V3. La Especificación Maestra V3, el Plan Maestro V3 y Addenda 01–05 quedan como **rastro forense y detalle histórico**. En caso de conflicto, manda este documento.

---

# 1. Objetivo

Reemplazar ERP1 sin portar su complejidad accidental, corrigiendo antes de Financial/Billing los defectos demostrados en:

- precisión monetaria JSON;
- fechas y fronteras mensuales;
- cobertura histórica;
- paginación mutable;
- packs;
- cargos compartidos;
- Billing period-first;
- 206/404/429;
- late adjustments;
- clasificación segura de writes.

Orden vinculante:

```text
Correcto
→ Simple
→ Estable
→ Mantenible
→ Eficiente
→ Escalable
```

KISS:

```text
DELETE → SIMPLIFY → REUSE → EXTEND → ADD
```

---

# 2. Arquitectura que NO cambia

```text
1 aplicación PHP/Slim
1 repositorio
1 MariaDB
1 Work durable
1 WorkRunner
1 MeliClient
1 operation registry
```

No crear:

- microservicios;
- brokers;
- segunda cola;
- retry engine;
- recovery engine;
- command/event bus;
- Billing scheduler dedicado;
- historical engine paralelo;
- remote-write engine;
- Financial ledger general;
- raw payload archive permanente.

---

# 3. Presupuesto productivo V3 aprobado por diseño

Antes de cualquier pieza adicional, volver a demostrar necesidad.

## 3.1 Exact JSON

Crear únicamente:

```text
app/Integrations/MercadoLibre/Json/LosslessJsonDecoder.php
```

Modificar:

```text
MeliClient
meli_operations registry
```

Operaciones iniciales con preservación de JSON NUMBER:

```text
orders.get
orders.search
billing.period.details
```

Todo JSON NUMBER de estas operaciones llega como **lexema string exacto**.

No convertir money a float.

## 3.2 Sales commercial facts

Migration:

```text
006_sales_commercial_facts.sql
```

Sólo añadir a `order_items`:

```text
sale_fee DECIMAL(18,4) NULL
gross_price DECIMAL(18,4) NULL
```

No tabla discounts.

## 3.3 Sales historical audit

Migration:

```text
007_sales_audit.sql
```

Máximo dos tablas:

### `sales_audit_runs`

Mínimo:

```text
id
company_id
account_id
period_key DATE
contract_version VARCHAR(32)
status VARCHAR(24)
initial_remote_total INT NULL
canonical_count INT NULL
set_hash CHAR(64) NULL
started_at DATETIME(6)
completed_at DATETIME(6) NULL
created_at
updated_at
```

Estados permitidos iniciales:

```text
capturing
repairing
confirming
verified
attention
unavailable
```

`No auditado` se deriva de ausencia de run.

El mes actual no recibe estado DB especial.

### `sales_audit_orders`

```text
id
audit_run_id
external_order_id VARCHAR(32)
remote_date_created DATETIME(6)
in_period TINYINT(1)
created_at
UNIQUE(audit_run_id, external_order_id)
```

No tabla de páginas.

## 3.4 Billing context

Migration:

```text
008_billing_context.sql
```

Sólo añadir:

```text
billing_details.legal_document_status VARCHAR(...) NULL
billing_details.context_json JSON NULL
```

No child tables Billing en F6A.

## 3.5 Work router

Cuando Billing realmente agregue `billing.period.sync`:

```text
SalesWorkProcessor
→ reemplazar por
ApplicationWorkProcessor
```

Switch pequeño de tipos conocidos.

No registry/command bus.

---

# 4. V3-A — REMOTE_NUMBERS_LOSSLESS + Sales core

No avanzar a históricos mientras A no esté GREEN.

## A1 — RED con JSON NUMBER real

Tests:

```text
SyncOrderDecimalPrecisionTest
MeliClientLosslessNumbersTest
```

El fake HTTP debe contener números JSON literales, no strings generados:

```json
{
  "total_amount": 90071992547409.1234,
  "order_items": [{
    "quantity": 12345.6789,
    "unit_price": 90071992547409.1234
  }]
}
```

Debe fallar actualmente.

## A2 — decoder lossless mínimo

Contrato:

```php
final class LosslessJsonDecoder
{
    /** @return array<string,mixed> */
    public function decodeObject(string $json): array;
}
```

Propiedad:

- strings/booleans/null/arrays/objects intactos;
- JSON NUMBER se materializa como string exacto;
- no tocar dígitos dentro de strings;
- JSON inválido falla cerrado.

No dependencia nueva salvo que durante implementación una librería madura compatible con PHP 8.5 resulte objetivamente más pequeña/segura que implementación local.

## A3 — eliminar money float + core Order

`SyncOrderHandler`:

```text
money exacto: string|int aceptado
float: contrato inválido
```

Eliminar fallback `number_format((float)...)` para dinero exacto.

Además:

```text
date_created requerido para toda nueva sync API Sales
```

DB puede mantener nullable por compatibilidad legacy.

### Orders 206

```text
206 + núcleo Sales usable → persistir
206/200 + núcleo crítico inválido → fail closed
```

Campos complementarios ausentes (`buyer`, feedback, mediations, seller, shipping) no justifican engine 206.

Tests:

- 206 sin buyer pero core completo → GREEN;
- date_created ausente/inválido → no persistir;
- money/items/status críticos inválidos → no persistir.

## A4 — sale_fee + gross_price

Persistir exactamente:

```text
order_items.sale_fee
order_items.gross_price
```

Ambos nullable.

No confundir `sale_fee` Orders con Billing: es referencia operativa/comercial que después se reconcilia.

### Gate A

```text
REMOTE_NUMBERS_LOSSLESS
FLOAT_MONEY_PATHS=0
ORDER_DATE_CREATED_REQUIRED
ORDERS_206_CORE_USABLE
ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

---

# 5. V3-B — Sales month + historical coverage

## 5.1 Fuente temporal Sales

```text
order.date_created
```

Persistencia: UTC.

Clasificación mensual actual MCO:

```text
America/Bogota
[first day 00:00, next month first day 00:00)
```

PHP/MySQL/Hostinger no deciden el mes.

No crear timezone por account hasta segundo timezone real.

## 5.2 Query remota

La API Orders documenta granularidad horaria y no congela inclusividad exacta de extremos.

Por tanto:

```text
canonical month
→ UTC
→ remote guard-band ±1h
→ seller /orders/search
→ parse date_created exacto
→ classify locally in_period
```

Seller search:

- filtra canceladas;
- `date_asc/date_desc` del vendedor ordena por `date_closed`;
- paginación offset puede moverse.

Por eso un recorrido técnico no equivale a cobertura probada.

## 5.3 SalesMonthWindow

Crear:

```text
app/Modules/Sales/ReconcileOrders/SalesMonthWindow.php
tests/Unit/SalesMonthWindowTest.php
```

Debe producir canonical local/UTC + remote guard-band.

Tests frontera enero/febrero y timezone default PHP cambiado.

## 5.4 CAPTURE separado de REPAIR

Flujo único:

```text
CAPTURE
→ VALIDATE CAPTURE
→ REPAIR missing-only
→ VERIFY LOCAL
→ CONFIRM
```

Durante CAPTURE:

```text
NO order.sync children
```

Sólo guardar evidencia remota observada.

## 5.5 Observed vs canonical

Cada result válido:

```text
record observed order_id + exact date_created UTC + in_period
```

Derivar:

```text
raw_observed_count = COUNT(all observed)
canonical_count = COUNT(in_period=1)
canonical_set_hash = hash(sorted canonical IDs)
```

La captura remota sólo es válida si como mínimo:

```text
paging.total estable
raw_observed_count == stable remote total
no duplicate remote IDs
offsets coherentes
no malformed result
no page contract failure
```

IDs del guard-band no contaminan el mes.

## 5.6 Local válido

Un expected se considera presente sólo si la orden local tiene:

```text
company/account correctos
external_order_id correcto
date_created válido
proyección Bogotá dentro del mismo mes canónico
```

Una row legacy con ID pero `date_created=NULL` sigue siendo gap.

## 5.7 Repair

Sólo después de CAPTURE válida:

```text
missing = canonical expected IDs - valid local canonical IDs
→ enqueue order.sync sólo missing
```

No nuevo exact-order handler.

### 404/unavailable

Dentro del mismo run:

```text
terminal exact GET
→ attention/unavailable
→ no replacement-work chain
```

Una reauditoría futura explícita puede reintentar; no polling automático.

## 5.8 Confirmación

Primera certificación de un período cerrado:

```text
capture A válida
→ repair/verify
→ capture B válida independiente
→ same canonical count/hash
→ verified
```

Posterior audit estable puede verificar en una sola captura contra baseline.

Cambio remoto documentable:

- añadir ID → repair + nueva confirmación;
- desaparecer ID del seller search → no borrar local;
- captura inestable → attention, nunca verified.

## 5.9 Mes actual

```text
closed month + verified → “Verificado”
current month + valid snapshot → “Al día hasta <completed_at>”
```

Mes actual nunca se presenta como histórico final.

No columna/state extra.

### Gate B

```text
MONTH_BOUNDARY_STABLE
DOMAIN_DATE_SOURCE_FIXED
SELLER_SEARCH_SEMANTICS
REMOTE_CAPTURE_COUNT_PROVEN
PAGING_COVERAGE_PROVEN
RECONCILE_REPEATABLE
CURRENT_MONTH_NOT_FINAL
NO_FALSE_COMPLETE
AUDIT_REPAIR_MINIMAL
TERMINAL_HISTORICAL_NO_AUTO_LOOP
NO_404_STORM
NO_429_STORM
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

---

# 6. Retry semantics verdaderas

Estado actual demostrado:

```text
work_items.attempts incrementa
retryCurrentClaim reprograma
no existe max attempts global
```

No llamar esto “attempt-bounded”.

No crear budget/streak engine ahora.

Clasificación:

### Terminal semantic

```text
malformed/unsupported
contrato remoto inválido
403 permission
404 histórico unavailable/not-owned
cursor no progresivo
```

→ fail/attention/unavailable según slice.

### Transitorio

```text
429 → MeliClient cooldown + mismo work
5xx/transport → durable retry, sin retry inline
```

Si producción demuestra un work transitorio infinito, abrir microtask separado con evidencia antes de añadir budget.

Gate:

```text
RETRY_SEMANTICS_TRUTHFUL
```

---

# 7. V3-C — F6A Billing period-first

Antes de código Billing debe cerrarse **C0**.

## C0 — smoke MCO de terminal de cursor

Contrato oficial probado:

```text
limit max 1000
from_id default 0
response.last_id → siguiente request
sort_by=ID
order_by=ASC
```

No está suficientemente documentada una condición terminal inequívoca.

No asumir:

```text
short page = end
empty 200 = end
missing last_id = end
offset+count>=total = end
```

Antes de implementar caught_up, capturar smoke sanitizado MCO:

```text
requested_from_id
http_status
result_count
last_id present/value
total/offset/limit si existen
terminal observation
```

Sin raw payload ni PII.

Gate:

```text
BILLING_CURSOR_TERMINAL_PROVEN
```

## C1 — migration 008

Sólo:

```text
legal_document_status
context_json
```

## C2 — BillingDetailNormalizer

Crear:

```text
app/Modules/Billing/SyncPeriod/BillingDetailNormalizer.php
```

Conserva exacto:

```text
external_detail_id
associated_detail_id
detail_type
detail_sub_type
detail_amount
currency_id
document_id
marketplace
legal_document_status
remote_created_at
curated context
```

### context allowlist

```text
charge.debited_from_operation
charge.status
charge.charge_bonified_id

discount.charge_amount_without_discount
discount.discount_amount
discount.rebate
discount.discount_reason

sales[]:
  order_id
  operation_id
  sale_date_time
  transaction_amount

shipping:
  shipping_id
  pack_id
  receiver_shipping_cost

items[]:
  item_id
  order_id
  item_amount
  item_price
```

Excluir nicknames, addresses, phone/email, titles/categories, unknown keys y raw body.

## C3 — BillingPeriodSyncHandler

Crear sólo después de C0:

```text
app/Modules/Billing/SyncPeriod/BillingPeriodSyncHandler.php
```

Una ejecución = una página.

Identity:

```text
company + account + period_key + document_type
```

Request:

```text
billing.period.details
limit=1000
from_id=cursor
sort_by=ID
order_by=ASC
```

## C4 — atomic page semantics

### 200 válida no terminal

```text
normalize every detail
persist upsert
advance durable cursor in same transaction
next cursor must be strictly progressive
enqueue same logical sync next page
```

### 206

```text
no page commit as complete
no durable cursor advance
never caught_up
partial/attention/retry according to tested contract
```

### terminal

Sólo la señal demostrada por C0 puede producir:

```text
sync_state=caught_up
partial_flag=0
last_synced_at
```

## C5 — state semantics

`billing_periods.sync_state` sólo describe ingestión local:

```text
pending
syncing
caught_up
attention
```

```text
caught_up != fiscal CLOSED
CLOSED != caught_up
```

No llamar `/billing/integration/monthly/periods` como preflight del sync.

## C6 — no horizonte inventado

No existe:

```text
BILLING_HISTORY_MONTHS=12
```

Consultar key solicitada y clasificar respuesta real.

No extrapolar ventana Sales de 12 meses a Billing.

## C7 — exact errors

```text
429 → MeliClient cooldown/same work
5xx/transport → durable retry
404 unavailable → attention/terminal de esa ejecución
403 → attention
401 → reutilizar OAuth refresh existente
non-progressive last_id → attention/fail
```

No storms.

## C8 — router único

Al entrar Billing:

```text
ApplicationWorkProcessor
```

Tipos iniciales:

```text
order.sync
orders.reconcile
billing.period.sync
```

Unknown sigue terminal.

## C9 — re-sync explícito

Trigger mínimo:

```text
company/account + period_key + document_type
→ ensure period row
→ enqueue billing.period.sync
```

Revalidación caught_up:

```text
explicit action
reset cursor transactionally
preserve existing details
upsert known/new
never delete absent rows
```

No Billing scheduler dedicado.

### Gate C

```text
BILLING_PERIOD_FIRST
BILLING_ONE_PAGE_PER_WORK
BILLING_CURSOR_TERMINAL_PROVEN
BILLING_CURSOR_PROGRESSIVE
BILLING_IDEMPOTENT
PARTIAL_NEVER_COMPLETE
BILLING_SYNC_STATE_SEPARATED
BILLING_HORIZON_NOT_ASSUMED
PII_CONTEXT_LEAK=0
FLOAT_MONEY_PATHS=0
NO_404_STORM
NO_429_STORM
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0 (QA offline)
```

Cierra F6A, no F6 completo.

---

# 8. Billing facts ≠ financial sign

Ejes independientes:

```text
document_type = BILL | CREDIT_NOTE
detail_type = CHARGE | BONUS
charge.status = BONUS_ON_... | BONUS_PART_ON_... | null
```

También:

```text
charge_bonified_id
detail_associated_id
debited_from_operation
```

F6A conserva hechos. No altera `detail_amount` para inventar débito/crédito.

Ejemplo oficial demuestra que puede existir:

```text
detail_type=CHARGE
status=BONUS_ON_BILL
```

Por tanto es inválido:

```text
CHARGE => siempre restar
```

Bonificaciones parciales conservan cargo y bonus/relación como hechos separados.

Gates:

```text
BILLING_EFFECT_NOT_INFERRED
PARTIAL_BONUS_PRESERVED
```

---

# 9. V3-D — Financial F6B

No iniciar sin:

```text
F6A GREEN
fixtures A/B
captura Billing MCO sanitizada suficiente
```

## 9.1 Fuente y concepto

Regla central:

```text
fuente ≠ concepto adicional
```

El mismo costo visto en Orders/Shipping/Billing se reconcilia, no se suma dos veces.

### Orders

Fuente primaria de hechos comerciales/operativos:

```text
quantity
unit_price
gross_price
sale_fee
total_amount
pack/order/item
```

### Billing

Fuente primaria de cargo/bonificación facturada:

```text
detail_amount
detail_type/subtype
status
document
relations
```

### Financial

Puede mostrar:

```text
operational_reference
billed_amount
delta
```

No sustituir una fuente por otra silenciosamente.

## 9.2 Shipping

Distinguir:

```text
buyer/receiver contribution
seller operational cost
Billing shipping charge/bonus
```

No sumarlos como costos independientes sin mapping de concepto.

## 9.3 Descuentos

Separar:

```text
commercial product discount
vs
Billing charge discount/rebate
```

No tabla discounts todavía.

## 9.4 Packs

Visible sale key derivado:

```text
company + account + pack_id
fallback company + account + order_id
```

No tabla `sales` todavía.

Shared charges se cuentan una sola vez por sale/pack.

## 9.5 Official/reference net

Conservar fuente oficial cuando exista.

Fixtures actuales son business reference, no prueba de campo API equivalente.

Nunca reemplazar referencia oficial con recomputación ERP.

## 9.6 No tabla Financial de entrada

Primero read model desde:

```text
orders/order_items
billing_details/context_json
pack relation
fixtures
```

Sólo añadir child/Financial table si un RED demuestra que una consulta/integridad real no puede resolverse limpiamente.

### Gate D

```text
PACK_NO_DOUBLE_CHARGE
SHIPPING_COMPONENTS_PRESERVED
FINANCIAL_SOURCE_PRECEDENCE
NO_CROSS_SOURCE_DOUBLE_COUNT
COMMERCIAL_VS_BILLING_DISCOUNT_SEPARATED
BONUS_RELATION_PRESERVED
UNMATCHED_BILLING_NOT_FORCED
OFFICIAL_NET_PRESERVED (cuando fuente demostrada)
INVOICE_NET_SEPARATION
LATE_ADJUSTMENT_AUDITABLE
FLOAT_MONEY_PATHS=0
PHPSTAN=0
PHPUNIT=PASS
```

---

# 10. Summary/period metadata Billing

No son dependencia F6A.

### Monthly periods

Sólo incorporar si UI/Financial demuestra necesidad de OPEN/CLOSED.

### Summary/details

Puede servir en F6B como control de totales de período después de smoke MCO.

Uso:

```text
manual/low-frequency/cache
no batch agresivo
no por order
no por page
```

No persistir nickname de user.

---

# 11. Writes futuros

Mantener registry compatible:

```text
classification = AUTH | READ | WRITE
effect = MUTATION | ACTION | FINANCIAL | DESTRUCTIVE
```

Toda mutación remota:

```text
classification=WRITE
```

Antes del primer WRITE registrado:

- clasificación desconocida fail-closed;
- writes switch OFF impide transport;
- todo WRITE exige effect permitido;
- READ/AUTH no declaran efecto mutante.

No RemoteWriteEngine.

F16 certifica/activa selectivamente.

---

# 12. Smokes reales pendientes

No confundir con QA offline.

Con autorización/app real:

## Sales MCO

- mes conocido;
- guard-band;
- frontera mensual;
- estabilidad de seller search.

## Order exact >12 meses

Sólo si existe ID real conocido; una consulta, no escaneo.

## Billing C0

Obligatorio antes de F6A Task 2 GREEN:

- primera página;
- last_id;
- página final;
- terminal signal;
- cardinalidades sanitizadas.

## Billing Financial

- BILL;
- CREDIT_NOTE si existe;
- sales_info/items_info/shipping_info;
- bonus/charge relation;
- observar si mismo detail_id puede mutar.

## Hostinger

- 45s runner;
- DB locks;
- cron;
- límites reales.

No tokens ni PII en evidencia.

---

# 13. F7–F16

Se mantiene roadmap formal, revalidado por V3:

```text
F7 Catalog
F8 Inventory
F9 ERP API
F10 Invoicing Boundary
F11 Provider
F12 PostSale sólo si caso real
F13 ERP1 Migration
F14 Shadow
F15 Cutover
F16 Remote Writes
```

Reglas:

- READ first;
- writes diseñados por slice pero OFF;
- no portar engines ERP1;
- migrar datos/evidencia útil, no maquinaria legacy;
- shadow antes de cutover;
- cutover reversible.

---

# 14. Método de ejecución después de aprobación

Por microtask:

```text
inspect exact current code
→ RED narrow
→ verify expected RED
→ minimal GREEN
→ focal tests
→ PHPStan
→ full suite
→ checkpoint
→ docs/handoff update
```

No abrir dos frentes simultáneos.

No merge/deploy durante implementación sin permiso separado.

---

# 15. Orden exacto después de aprobación

```text
A1 RED realistic JSON NUMBER
A2 lossless decoder
A3 money/date_created/206 hardening
A4 sale_fee + gross_price
Gate A

B1 month window
B2 migration 007 audit schema
B3 SalesAuditRepository
B4 capture-only reconcile integration
B5 adversarial capture validity
B6 repair missing-only
B7 confirm/repeatability
B8 minimal UI
Gate B

C0 real MCO billing cursor smoke
C1 migration 008 billing context
C2 BillingDetailNormalizer
C3 BillingPeriodSyncHandler RED/GREEN
C4 206/cursor/idempotency/errors
C5 ApplicationWorkProcessor rename
C6 minimal trigger/re-sync
Gate C

D1 real financial fixtures
D2 source mapping/read model
D3 financial view
D4 late adjustment
Gate D
```

Task numbering here sustituye cualquier numeración contradictoria del Plan V3 base.

---

# 16. Qué está explícitamente descartado

```text
repair date engine ERP1
generic historical engine
page table
raw payload archive
discount table now
sales aggregate table now
financial ledger now
retry budget engine now
Billing monthly-period preflight
hardcoded Billing 12-month horizon
short-page terminal heuristic
empty-200 terminal heuristic before C0
sign inference CHARGE/BONUS
remote write engine
```

---

# 17. Freeze y aprobación

Hasta aprobación explícita del usuario de este V3 consolidado:

```text
NO product code
NO migrations
NO F6A Task2
NO merge
NO deploy
NO real remote writes
```

Después de aprobación, **primero A1 RED**, no Billing.
