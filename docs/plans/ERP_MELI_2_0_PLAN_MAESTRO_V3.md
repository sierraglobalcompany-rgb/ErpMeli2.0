# ERP MELI 2.0 — PLAN MAESTRO V3

**Fecha:** 2026-10-09  
**Estado:** DRAFT — ejecución bloqueada hasta aprobación explícita del usuario.  
**Base:** `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md`  
**Metodología:** microbloques TDD, una sola línea de trabajo, RED → GREEN mínimo → QA → checkpoint.  

## 1. Regla de ejecución

No ejecutar tareas productivas de este plan hasta aprobación explícita de Especificación + Plan V3.

Después de aprobación:

```text
leer AGENTS.md
→ verificar branch/base/status
→ RED específico
→ confirmar fallo esperado
→ GREEN mínimo
→ refactor sólo si reduce complejidad
→ tests focales
→ PHPStan
→ suite
→ commit/checkpoint
→ actualizar documentos
```

No merge/deploy/write remoto sin autorización separada.

## 2. Orden obligatorio V3

```text
V3-A Exact JSON / money boundary
→ V3-B Sales month + historical coverage
→ V3-C Billing ingest F6A Task 2
→ V3-D Financial F6B sobre fixtures/evidencia
→ F7+ roadmap normal
```

No saltar V3-A: Billing no puede construirse sobre números que ya hayan atravesado `float`.

---

# V3-A — REMOTE_NUMBERS_LOSSLESS

## Task A1 — RED realista: número JSON, no string

**Modificar tests:**

- `tests/Integration/SyncOrderDecimalPrecisionTest.php`
- crear `tests/Integration/MeliClientLosslessNumbersTest.php`

### RED

El transport fake debe devolver body manual, por ejemplo:

```json
{
  "id": 200000000999,
  "total_amount": 90071992547409.1234,
  "order_items": [{
    "quantity": 12345.6789,
    "unit_price": 90071992547409.1234
  }]
}
```

No usar `json_encode()` con strings para esos campos.

Assertions:

```text
MeliClient entrega lexema monetario exacto como string
orders.total_amount = 90071992547409.1234
order_items.quantity = 12345.6789
order_items.unit_price = 90071992547409.1234
```

Esperado RED actual: `json_decode()` produce float y la precisión no queda demostrada.

Comando focal:

```bash
vendor/bin/phpunit tests/Integration/SyncOrderDecimalPrecisionTest.php tests/Integration/MeliClientLosslessNumbersTest.php
```

## Task A2 — GREEN: decoder lossless mínimo

**Crear:**

- `app/Integrations/MercadoLibre/Json/LosslessJsonDecoder.php`

**Modificar:**

- `app/Integrations/MercadoLibre/Client/MeliClient.php`
- `config/meli_operations.php`
- PHPDoc/config typing donde corresponda.

### Contrato propuesto

```php
final class LosslessJsonDecoder
{
    /** @return array<string,mixed> */
    public function decodeObject(string $json): array;
}
```

Propiedad:

- strings siguen strings;
- booleans/null intactos;
- objetos/arrays intactos;
- todo token JSON NUMBER se materializa como su lexema decimal string exacto.

No transformar dígitos dentro de strings.

### Scope de operación

Añadir metadato pequeño en registry, por ejemplo:

```php
'preserve_numbers' => true
```

Primero en:

- `orders.get`
- `orders.search`
- `billing.period.details`

OAuth mantiene decoder nativo para minimizar blast radius.

### Implementación KISS

Preferir scanner pequeño RFC-8259 sobre el body validado antes de `json_decode`, sin dependencia nueva, salvo que durante implementación aparezca una librería madura claramente más simple y compatible con PHP 8.5/Hostinger.

No añadir Brick/BCMath sólo para decodificar; la necesidad aquí es preservar lexema, no calcular todavía.

## Task A3 — Endurecer normalización monetaria

**Modificar:**

- `app/Modules/Sales/SyncOrder/SyncOrderHandler.php`
- tests de persistencia/precision.

Después de lossless decode:

```text
money input aceptado: string|int
float: error interno de contrato
```

Eliminar fallback silencioso `number_format((float)$value...)` para campos exactos de Sales.

RED adicional:

- un handler invocado artificialmente con float monetario debe fallar con `meli_order_contract`, no persistir aproximación.

## Gate A

```text
REMOTE_NUMBERS_LOSSLESS=PASS
FLOAT_MONEY_PATHS=0 en Sales/Billing nuevos
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Checkpoint independiente antes de tocar schema Sales audit.

---

# V3-B — SALES MONTH / HISTORICAL COVERAGE

## Task B1 — RED de frontera mensual pura

**Crear:**

- `app/Modules/Sales/ReconcileOrders/SalesMonthWindow.php`
- `tests/Unit/SalesMonthWindowTest.php`

### Contrato

```php
final class SalesMonthWindow
{
    /**
     * @return array{
     *   period_key:string,
     *   local_from:DateTimeImmutable,
     *   local_to:DateTimeImmutable,
     *   utc_from:DateTimeImmutable,
     *   utc_to:DateTimeImmutable,
     *   remote_from:DateTimeImmutable,
     *   remote_to:DateTimeImmutable
     * }
     */
    public function forMonth(int $year, int $month): array;
}
```

Reglas:

```text
timezone = America/Bogota
canonical = [month start, next month start)
remote guard-band = canonical UTC ± 1 hour
```

Casos RED:

- último microsegundo de enero pertenece a enero;
- exactamente 1-feb 00:00 local pertenece a febrero;
- timezone por defecto PHP cambiado no altera resultado;
- remote guard-band no altera canonical boundaries.

## Task B2 — Schema audit mínimo

**Crear migration:**

- `database/migrations/006_sales_audit.sql`

**Modificar tests:**

- `tests/Integration/SalesSchemaTest.php`
- crear `tests/Integration/SalesAuditSchemaTest.php`
- `tests/Integration/MigrationsTest.php` si enumera migraciones.

### Tabla `sales_audit_runs`

Mínimo recomendado:

```text
id
company_id
account_id
period_key DATE
source_contract VARCHAR
status VARCHAR
initial_remote_total INT NULL
page_count INT
expected_count INT
set_hash CHAR(64) NULL
started_at
completed_at NULL
created_at
updated_at
```

Índices:

```text
(company_id, account_id, period_key, id)
(status, updated_at, id)
```

### Tabla `sales_audit_orders`

```text
id
audit_run_id
external_order_id VARCHAR(32)
remote_date_created DATETIME(6)
created_at
UNIQUE(audit_run_id, external_order_id)
```

No payload/raw/PII.

## Task B3 — Repository audit puro/local

**Crear:**

- `app/Modules/Sales/ReconcileOrders/SalesAuditRepository.php`
- `tests/Integration/SalesAuditRepositoryTest.php`

Funciones mínimas:

```php
startRun(companyId, accountId, periodKey): int
recordExpectedOrder(runId, orderId, dateCreatedUtc): bool
recordPage(runId, remoteTotal, receivedCanonicalCount): void
finishCapture(runId): SalesAuditCaptureResult
missingLocalOrderIds(runId): array
canonicalSetHash(runId): string
latestVerifiedRun(companyId, accountId, periodKey): ?array
markStatus(runId, status): void
```

No repository genérico.

RED:

- duplicate ID no duplica expected row y marca inconsistencia de captura;
- total remoto que cambia invalida run;
- hash usa IDs ordenados y es estable;
- missing = expected - local;
- local-only ID no invalida coverage.

## Task B4 — Reconcile page usa audit run

**Modificar:**

- `app/Modules/Sales/ReconcileOrders/ReconcileOrdersHandler.php`
- `tests/Integration/ReconcileOrdersHandlerTest.php`
- `tests/Integration/ReconcileMalformedWorkTest.php`
- `tests/Integration/MeliOrdersSearchContractTest.php`

Payload V3 mínimo:

```json
{
  "audit_run_id": 123,
  "year": 2026,
  "month": 9,
  "offset": 0,
  "limit": 50,
  "mode": "capture"
}
```

El handler calcula ventana con `SalesMonthWindow`; no recibe fronteras arbitrarias del navegador como business truth.

Request remota:

```text
filter: order.date_created
remote_from/to: guard-band
seller + offset + limit
```

Cada result:

1. validar ID/date_created;
2. normalizar exacto UTC;
3. proyectar a Bogotá;
4. si fuera del canonical month: ignorar para expected;
5. si dentro: `recordExpectedOrder()` y encolar `order.sync` sólo si falta localmente.

No guardar buyer/body.

## Task B5 — Detectar captura no confiable

RED adversarial en `ReconcileOrdersHandlerTest.php`:

- `paging.total` cambia página 1→2;
- mismo ID reaparece en dos páginas;
- offset inesperado;
- página vacía antes de alcanzar recorrido coherente;
- 429 en mitad del run;
- 5xx retry;
- malformed date_created;
- resultado guard-band del mes vecino no entra en expected.

Resultado:

```text
invalid/attention != verified
```

Nunca borrar órdenes locales por ausencia en seller search.

## Task B6 — Repair missing-only + verify

Reutilizar `order.sync`.

No crear handler remoto nuevo para exact order.

Al cerrar captura válida:

```text
missing = repository.missingLocalOrderIds(run)
if missing:
  status=repairing
  enqueue order.sync sólo missing
  enqueue orders.reconcile mode=verify_local
else:
  pasar a confirmación de captura
```

`verify_local`:

- si faltantes siguen con work activo, retry corto bounded;
- si work terminó failed/permanent, status attention;
- si missing=0, capturar pass de confirmación.

No dependency/saga framework.

## Task B7 — Doble captura sólo para certificación

Primera baseline:

```text
capture A válida + local repaired
→ capture B válida independiente
→ hash/count iguales
→ latest run status=verified
```

Reauditoría posterior:

- si hash igual a baseline → verified en un pass;
- si hash cambia → no borrar; diferencias se explican/reparan y requieren nueva confirmación antes de baseline.

RED:

- enero repetido estable no cambia conjunto;
- remote added ID produce repair;
- desaparecido remote ID no borra local;
- una captura inestable nunca marca verified.

## Task B8 — UI mínima de cobertura

**Modificar sólo si ya existe lugar natural en Sales:**

- `app/Modules/Sales/ViewSales/*`
- sus tests HTTP/read model.

Mostrar por cuenta/mes, sin nueva SPA:

```text
No auditado
Capturando
Reparando
Verificando
Verificado
No disponible por search
Requiere atención
```

No mostrar `100%` con run inválido/parcial.

No construir dashboard histórico separado si Sales actual puede mostrarlo.

## Gate B

```text
MONTH_BOUNDARY_STABLE=PASS
DOMAIN_DATE_SOURCE_FIXED=PASS
SELLER_SEARCH_SEMANTICS=PASS
PAGING_COVERAGE_PROVEN=PASS
RECONCILE_REPEATABLE=PASS
NO_FALSE_COMPLETE=PASS
AUDIT_REPAIR_MINIMAL=PASS
NO_ENDLESS_MISSING_RETRY=PASS
NO_404_STORM=PASS
NO_429_STORM=PASS
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Checkpoint separado antes de Billing.

---

# V3-C — F6A TASK 2 BILLING PERIOD SYNC

## Task C1 — RED schema contexto Billing

**Crear migration:**

- `database/migrations/007_billing_context.sql`

**Modificar:**

- `tests/Integration/BillingSchemaTest.php`

Añadir únicamente:

```text
billing_details.legal_document_status VARCHAR(...) NULL
billing_details.context_json JSON NULL
```

No child tables todavía.

Test debe demostrar:

- context JSON existe;
- no es requerido para rows antiguas;
- unique/idempotency anterior sigue igual.

## Task C2 — Sanitizador Billing context

**Crear:**

- `app/Modules/Billing/SyncPeriod/BillingDetailNormalizer.php`
- `tests/Unit/BillingDetailNormalizerTest.php`

Entrada: result individual ya lossless.

Salida mínima:

```php
[
  'external_detail_id' => string,
  'associated_detail_id' => ?string,
  'detail_type' => string,
  'detail_sub_type' => ?string,
  'detail_amount' => string,
  'currency_id' => ?string,
  'document_id' => ?string,
  'marketplace' => ?string,
  'legal_document_status' => ?string,
  'remote_created_at' => ?string,
  'context' => array
]
```

Context allowlist exacta de Spec V3.

RED:

- nickname/payer/buyer/address/unknown nested field se descarta;
- money queda string exacto;
- múltiples `sales_info` no se colapsan;
- item/order relations se preservan;
- shipping components permitidos se preservan.

## Task C3 — BillingPeriodSyncHandler RED 200 happy path

**Crear:**

- `app/Modules/Billing/SyncPeriod/BillingPeriodSyncHandler.php`
- `tests/Integration/BillingPeriodSyncHandlerTest.php`

Una ejecución = una página.

RED inicial:

- lee `billing_periods` scopeado;
- request exacta `billing.period.details`;
- BILL/CREDIT_NOTE del row, no del usuario libre;
- limit 1000, `from_id` cursor, ID ASC;
- persiste detalles normalizados en transacción de claim;
- cursor avanza a `last_id`;
- encola siguiente page work.

## Task C4 — Fin explícito con página vacía

RED:

- página 200 con results no vacíos avanza/enfila siguiente;
- siguiente página 200 vacía:
  - no cambia cursor;
  - `sync_state='caught_up'`;
  - `partial_flag=0`;
  - `last_synced_at=UTC_TIMESTAMP`;
  - work done;
  - no encola otra página.

No usar sólo `count<limit` para final.

## Task C5 — 206 nunca persiste ni avanza

RED:

- response status 206 con results aparentemente válidos;
- `billing_details` sin nuevas rows;
- cursor idéntico;
- `partial_flag=1`;
- work pending/retry same from_id;
- nunca caught_up.

Gate directo:

```text
PARTIAL_NEVER_COMPLETE
```

## Task C6 — Cursor y idempotencia adversarial

RED:

- `last_id` ausente con results → attention/fail;
- last_id igual al cursor con results → no loop;
- detalle duplicado en replay → una row;
- crash/retry misma página → sin duplicados;
- CREDIT_NOTE no contamina BILL;
- period/account/company cruzados → rechazados.

## Task C7 — 429/5xx/404/auth

RED:

- 429 usa retryAt del MeliClient;
- 5xx/transport retry bounded;
- 401 refresh path existente si Billing comparte helper apropiado; no copiar OAuth engine;
- 404 período/detail no disponible → attention/terminal de esa ejecución, no retry infinito;
- 403 → attention, no storm.

## Task C8 — Router único de Work al aparecer segundo dominio

El nombre `SalesWorkProcessor` deja de ser correcto cuando Billing usa el mismo WorkRunner.

Aplicar noise reduction:

**Crear:**

- `app/Work/ApplicationWorkProcessor.php`

**Eliminar después de mover comportamiento:**

- `app/Modules/Sales/SalesWorkProcessor.php`

**Modificar:**

- `bin/work.php`
- tests actuales de SalesWorkProcessor → renombrar/adaptar a `ApplicationWorkProcessorTest.php`
- poison-loop tests.

El router sigue siendo un switch pequeño de tipos conocidos:

```text
order.sync
orders.reconcile
billing.period.sync
```

No registry/DI container/command bus todavía.

Unknown work sigue terminal `unsupported_work_type`.

## Task C9 — Trigger/re-sync mínimo

No scheduler Billing separado.

Crear el punto mínimo que ya esté previsto por F6 UI/CLI para:

```text
company/account + period_key + document_type
→ ensure billing_period row
→ enqueue billing.period.sync
```

Revalidar un período ya caught_up:

- operación explícita;
- reset cursor a 0 de forma transaccional;
- conserva detalles anteriores;
- upsert nuevos/conocidos;
- no borra ausentes;
- no crea segunda cola.

La cadencia automática de CLOSED se difiere hasta evidencia real/Plan operativo; primero manual/maintenance reutilizable.

## Gate C

```text
BILLING_PERIOD_FIRST=PASS
BILLING_ONE_PAGE_PER_WORK=PASS
BILLING_CURSOR_MONOTONIC=PASS
BILLING_IDEMPOTENT=PASS
PARTIAL_NEVER_COMPLETE=PASS
NO_404_STORM=PASS
NO_429_STORM=PASS
PII_CONTEXT_LEAK=0
FLOAT_MONEY_PATHS=0
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Este gate cierra **F6A**, no F6 completo.

---

# V3-D — F6B FINANCIAL

No empezar hasta contar con:

- F6A GREEN;
- fixtures A/B;
- al menos una captura Billing MCO sanitizada suficiente para verificar cardinalidades.

## Task D1 — RED puro sobre fixtures A/B

**Crear:**

- `app/Modules/Financial/SaleFinancialView.php` o equivalente sólo si el diseño RED demuestra que merece clase propia;
- `tests/Unit/FinancialRealCasesTest.php`.

No HTTP.

Assertions:

```text
PACK_NO_DOUBLE_CHARGE
SHIPPING_COMPONENTS_PRESERVED
OFFICIAL_NET_PRESERVED
EXACT_MONEY_NO_FLOAT
```

Si todavía no hay fuente API demostrada del “official net”, el test conserva el valor de fixture como referencia de negocio y la UI futura lo etiqueta correctamente; no inventar endpoint.

## Task D2 — Relación mínima Billing→sale_key

Primero intentar resolver desde `billing_details.context_json` curado + `orders.pack_id`.

Sólo si una consulta/integridad real no puede expresarse limpiamente, RED que justifique normalizar a child table.

No añadir tabla por anticipación.

## Task D3 — Vista analítica, no ledger paralelo

Derivar:

```text
product gross/net commercial
sale fees
shipping components
withholdings/taxes
bonuses/credit notes
analytical net
delta vs official reference when available
```

No crear contabilidad general ni double-entry ledger en F6B.

## Task D4 — Late adjustments

RED con nueva bonificación/credit note después de baseline:

- original sigue presente;
- nuevo detalle cambia vista;
- historial no se reescribe destructivamente;
- si mismo external_detail_id muta en evidencia MCO real, abrir microtask separado para revision history mínima.

## Gate D

```text
PACK_NO_DOUBLE_CHARGE=PASS
SHIPPING_COMPONENTS_PRESERVED=PASS
OFFICIAL_NET_PRESERVED=PASS cuando fuente esté demostrada
INVOICE_NET_SEPARATION=PASS
LATE_ADJUSTMENT_AUDITABLE=PASS
FLOAT_MONEY_PATHS=0
PHPSTAN=0
PHPUNIT=PASS
```

---

# V3-E — SMOKES REALES CONTROLADOS

No forman parte de QA offline.

Con OAuth/app dedicada autorizada y permiso del usuario:

1. Orders boundary smoke:
   - cuenta MCO;
   - mes conocido;
   - guard-band;
   - verificar inclusión/exclusión local exacta.
2. Historical horizon:
   - si existe un order ID >12 meses conocido, probar GET exacto una sola vez;
   - no escanear IDs.
3. Billing MCO:
   - un período reciente BILL;
   - un CREDIT_NOTE si existe;
   - capturar cardinalidades sanitizadas de sales/items/shipping;
   - verificar 200/PROCESSING/PROCESSED y cursor.
4. Hostinger:
   - 45 s WorkRunner;
   - DB locks;
   - cron real;
   - memoria/tamaño payload.

Nunca exponer tokens/PII en evidence docs.

---

# Roadmap F7–F16 reconciliado con V3

## F7 Catalog

READ primero:

- publicaciones;
- estado;
- precio;
- variaciones;
- SKU.

Writes de precio/status se diseñan en slice pero permanecen OFF hasta F16.

No portar Catalog engines de ERP1.

## F8 Inventory

- stock local como fuente ERP;
- relación item/variation;
- write `available_quantity` preparado pero OFF;
- tener en cuenta efecto `out_of_stock`/reactivación.

No ledger complejo hasta necesitar movimientos reales del ERP.

## F9 ERP API

API interna sólo para casos reales (robot/facturación/integraciones).

No exponer toda la base.

Auth/scope por empresa.

## F10 Invoicing Boundary

Separar:

```text
DIAN/provider emission
buyer fiscal data
ML fiscal-document attachment
```

Proveedor intercambiable por interfaz mínima.

Upload/delete ML preparados pero OFF hasta F16.

## F11 Provider

Implementar sólo proveedor elegido/actual.

No multi-provider framework sin segundo proveedor real.

## F12 PostSale

**Condicional.** Sólo si aparece caso de negocio aprobado.

No pre-registrar mensajes/reclamos/refunds por completitud teórica.

## F13 ERP1 Migration

Importar únicamente datos necesarios para continuidad:

- cuentas/identidades;
- historical orders necesarios;
- vínculos de producto necesarios;
- evidencia financiera útil si su calidad se demuestra.

No migrar engines, colas, logs/raw ni estados legacy accidentales.

Datos fuera del horizonte ML se etiquetan por fuente ERP1/importación, no como “verificados por API actual”.

## F14 Shadow

ERP2 lee/sincroniza en paralelo sin convertirse en sistema operativo primario.

Comparar:

- Sales count por período/fuente;
- packs;
- Financial fixtures/casos reales;
- errores/retries;
- tiempos/carga API.

## F15 Cutover

Cutover por capacidad, no big bang.

Gates:

- backups;
- OAuth;
- Work;
- históricos necesarios;
- Financial confiable;
- Hostinger;
- rollback documentado.

## F16 Remote Writes

Fuente: `docs/ERP_MELI_2_0_WRITE_MAP_V3.md`.

Certificar por operación:

```text
WRITES_DEFAULT_OFF
WRITE_OPERATION_ALLOWLIST
WRITE_TENANCY_PASS
WRITE_RBAC_PASS
WRITE_CSRF_PASS
WRITE_IDEMPOTENCY_PROVEN
WRITE_RETRY_SAFETY_PROVEN
WRITE_AUDIT_SAFE
DESTRUCTIVE_CONFIRMATION_PASS
REAL_WRITE_SMOKE_CONTROLLED
REMOTE_RESULT_RECONCILED
```

Activación selectiva; no switch global ciego.

---

# Reglas de commits y checkpoints

Cada task o subtask suficientemente pequeña:

```text
RED evidence
GREEN evidence
QA focal
commit SHA
pending/risks
```

No agrupar 8 cambios heterogéneos en un commit.

Después de cada gate grande A/B/C/D:

- suite completa;
- PHPStan;
- `REAL_MELI_HTTP=0` confirmado;
- diff revisado;
- docs actualizados;
- SHA registrado.

---

# Branch strategy después de aprobación

Una sola línea secuencial, nunca frentes paralelos:

```text
audit/v3-forensic-redesign-20261009
  ↓
impl/v3-exact-money-<date>
  ↓
impl/v3-sales-audit-<date>
  ↓
impl/f6-billing-sync-v3-<date>
  ↓
impl/f6-financial-v3-<date>
```

Cada branch nace del checkpoint aprobado anterior.

No merge a main ni deploy automáticamente.

---

# Definition of Done V3 pre-implementation

Antes de iniciar A1 deben existir:

- [x] Auditoría forense ERP1 sustancial;
- [x] matriz ERP1→ERP2;
- [x] matriz API actual;
- [x] write map;
- [x] snapshot API curado;
- [x] fixtures financieros business-level sanitizados;
- [x] Especificación Maestra V3 draft;
- [x] Plan Maestro V3 draft;
- [ ] revisión/aprobación explícita del usuario.

Hasta completar el último punto, **F6A Task 2 y todo código V3 permanecen congelados**.
