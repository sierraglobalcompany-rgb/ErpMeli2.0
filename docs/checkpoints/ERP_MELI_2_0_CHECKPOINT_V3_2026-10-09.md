# ERP MELI 2.0 — CHECKPOINT MAESTRO V3

**Fecha:** 2026-10-09  
**Proyecto:** ERP MELI 2.0 / ErpMeli2.0  
**Propósito:** checkpoint de recuperación inmediato para continuar en otro chat/sesión sin perder contexto.  
**Autoridad:** código/Git/tests reales > decisiones recientes > docs recientes > docs antiguas > inferencias.

---

# 1. ESTADO EJECUTIVO

## Punto actual

```text
AUDITORÍA FORENSE V3
→ sustancialmente cerrada para revisión
→ Especificación Maestra V3 creada
→ Plan Maestro V3 creado
→ implementación productiva todavía CONGELADA
```

## Regla crítica

**F6A Task 2 permanece CONGELADO.**

No implementar todavía:

- `BillingPeriodSyncHandler`;
- migrations V3;
- Financial;
- historical audit productivo;
- remote writes;
- merge;
- deploy.

Antes de código productivo se requiere aprobación explícita del usuario de:

1. `docs/specs/ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md`
2. `docs/plans/ERP_MELI_2_0_PLAN_MAESTRO_V3.md`

---

# 2. REPOSITORIOS Y HEADS VERIFICADOS

## ERP2

Repositorio:

```text
sierraglobalcompany-rgb/ErpMeli2.0
```

Rama V3 actual:

```text
audit/v3-forensic-redesign-20261009
```

HEAD actual de checkpoint:

```text
b21cf7b052ae395e2a3d16af9f31cce15208dcf2
```

Commit:

```text
docs(audit): checkpoint V3 draft audit closure
```

Checkpoint anterior de auditoría al iniciar este bloque:

```text
bdaa143b16b3e36e81233308c0ce2f32eff5b0bf
```

Diferencia comprobada desde `bdaa143...` hasta `b21cf7...`:

```text
9 commits ahead
0 behind
```

Todos los cambios de este bloque están exclusivamente bajo:

```text
docs/
```

No se modificó código productivo.

## F6 rama productiva anterior

```text
impl/f6-billing-period-first-20261009
HEAD producto: 4d144b9744160c3b1ddfa542af74040b262dec42
```

Último gate recuperado:

```text
PHPUnit: 147 tests / 970 assertions
PHPStan: 0
REAL_MELI_HTTP=0
```

## ERP1

Repositorio:

```text
sierraglobalcompany-rgb/ErpMeli
```

`main` verificado:

```text
3739cb2c95b926466c8346a9ead2329908e08bf8
```

## Corpus API Mercado Libre

Repositorio:

```text
sierraglobalcompany-rgb/ApiMercadolibre
```

`main` verificado:

```text
eeb0bc9d944e2fad58c13a47ccc7660413fa6193
```

El README define como fuentes canónicas:

```text
docs/markdown/
docs/temas/
data/pages.jsonl
data/operations.jsonl
```

---

# 3. DOCUMENTOS V3 CREADOS EN ESTE BLOQUE

## Auditoría

```text
docs/audits/ERP_MELI_2_0_AUDITORIA_FORENSE_V3_2026-10-09.md
```

Ledger vivo de hallazgos ERP1/ERP2/API/casos reales.

```text
docs/audits/ERP_MELI_2_0_AUDITORIA_FORENSE_V3_CIERRE_DRAFT_2026-10-09.md
```

Cierre draft actual de auditoría.

## Matrices

```text
docs/ERP_MELI_2_0_MATRIZ_ERP1_ERP2.md
```

Clasifica:

```text
CONSERVAR
RESCATAR
MEJORAR
NO PORTAR
DIFERIR
```

```text
docs/ERP_MELI_2_0_MATRIZ_API_MERCADOLIBRE.md
```

Contratos actuales Sales/Orders/Packs/Shipping/Payment/Billing/Invoicing.

## Writes

```text
docs/ERP_MELI_2_0_WRITE_MAP_V3.md
```

Clasificación:

```text
READ
MUTATION
ACTION
FINANCIAL
DESTRUCTIVE
```

Todos los writes permanecen OFF.

## Snapshot API

```text
docs/api/MERCADOLIBRE_API_SNAPSHOT_V3_2026-10-09.md
```

Snapshot curado, anclado a:

```text
ApiMercadolibre@eeb0bc9d944e2fad58c13a47ccc7660413fa6193
```

## Fixtures financieros

```text
docs/fixtures/financial_real_cases_v3.json
```

Contiene 2 casos reales sanitizados sin PII.

## Especificación

```text
docs/specs/ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md
```

Estado:

```text
DRAFT PARA REVISIÓN
```

## Plan

```text
docs/plans/ERP_MELI_2_0_PLAN_MAESTRO_V3.md
```

Estado:

```text
DRAFT — ejecución bloqueada hasta aprobación explícita
```

---

# 4. HALLAZGOS FORENSES CONFIRMADOS

## H1 — ERP1 Billing order-by-order amplificaba llamadas

ERP1 terminó usando consultas Billing puntuales por orden. V3 conserva sólo el valor de repair/investigación puntual.

Bulk histórico:

```text
NO PORTAR
```

ERP2 continúa period-first.

---

## H2 — ERP1 tenía múltiples conceptos de “mes”

Se comprobó en código:

```text
Sales / históricos → order.date_created
Financial heredado → payment.date_approved
Billing → period_key / documento Billing
```

Conclusión:

```text
NO existe un businessMonth() universal
```

Cada dominio tiene su propia fuente temporal.

---

## H3 — Reparador de fechas ERP1 tenía dependencia circular

ERP1 seleccionaba filas por `meli_orders.date_created` ya persistido antes de releer/corregir la fecha original.

Consecuencia:

una orden clasificada en el mes equivocado puede no entrar al reparador del mes correcto.

V3 no porta ese reparador.

---

## H4 — Importar y demostrar cobertura son cosas distintas

ERP1 importer:

```text
/orders/search
offset/limit
paging.total
```

ERP1 auditor añadió:

- total remoto cambiante;
- páginas repetidas;
- offsets inesperados;
- páginas intermedias incompletas;
- conteo único de IDs;
- hash/cobertura.

V3 rescata esa propiedad sin crear un segundo engine.

---

## H5 — Repair correcto = sólo faltantes

Dirección V3:

```text
expected IDs
vs local IDs
→ missing
→ order.sync sólo missing
→ verify
```

Se reutilizan:

```text
Work
WorkRunner
MeliClient
order.sync
```

No crear otro sistema histórico.

---

## H6 — `/orders/search` vendedor excluye canceladas

Conclusión crítica:

```text
expected_remote - local = faltantes reparables
local - expected_remote ≠ corrupción automática
```

Nunca borrar una orden local sólo porque no aparezca después en seller search.

---

## H7 — `/orders/search` filtra y ordena con fechas diferentes

ERP2 actual usa:

```text
filter = order.date_created
sort = date_asc
```

La documentación actual indica que para vendedor:

```text
date_asc/date_desc ordena por date_closed
```

Esto puede mover elementos durante paginación offset.

Por tanto:

```text
loop terminado ≠ cobertura demostrada
```

---

## H8 — filtros de fechas Orders tienen granularidad horaria

La documentación actual indica que se descartan minutos/segundos/ms.

La inclusividad exacta `from/to` no queda suficientemente explícita.

Diseño V3:

```text
mes canónico America/Bogota
→ UTC
→ request remoto con guard-band ±1 hora
→ normalizar date_created exacto
→ filtro local [from,to)
```

---

## H9 — Sales month queda congelado

Para MCO actual:

```text
timezone negocio = America/Bogota
source date = order.date_created
canonical period = [month start, next month start)
```

Persistencia:

```text
UTC
```

No crear todavía timezone por cuenta.

---

## H10 — Pack no es ID global de venta

Pack puede agrupar órdenes de sellers diferentes y cada integración sólo ve las autorizadas.

V3:

```text
sale_key = company + account + pack_id
fallback legacy = company + account + order_id
```

No crear tabla `sales` todavía.

---

## H11 — cargos compartidos no pueden duplicarse por child order

Gate:

```text
PACK_NO_DOUBLE_CHARGE
```

---

## H12 — Billing period-first está alineado con API actual

Contrato:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details

document_type=BILL|CREDIT_NOTE
limit<=1000
from_id=<cursor>
sort_by=ID
order_by=ASC
```

ERP2 mantiene:

```text
limit=1000
sort_by=ID
order_by=ASC
1 página por work
```

---

## H13 — Billing 206 nunca puede marcar complete

Regla V3:

```text
206 Billing
→ no persistir como evidencia final
→ no mover cursor
→ partial_flag=1
→ retry same from_id
→ nunca caught_up
```

No manejar todo 206 globalmente como error porque Orders puede usar 206 de forma distinta.

---

## H14 — Billing CLOSED no significa inmutable para siempre

Mercado Libre admite ajustes posteriores como bonificaciones/devoluciones/documentos.

Regla:

```text
CLOSED
≠ polling frecuente
≠ inmutable para siempre
```

V3 propone revalidación explícita, idempotente y de bajo ruido.

No scheduler Billing nuevo.

---

## H15 — esquema F6A Task 1 es sólo cache mínimo

Actual:

```text
billing_periods
billing_details
```

Todavía no es modelo Financial final.

La API actual expone relaciones como:

```text
sales_info[]
shipping_info
items_info
legal_document_status
```

V3 NO crea 3 tablas hijas por intuición.

Propuesta de Spec:

```text
billing_details.legal_document_status
billing_details.context_json allowlisted/no-PII
```

Normalizar después sólo si F6B demuestra necesidad real.

---

## H16 — dos casos reales financieros quedaron formalizados

### Fixture A

```text
Productos: 99.621
Fee item 1: 11.698
Fee item 2: 6.732
Shipping: 12.200
Impuestos: 3.567
  retención: 1.230
  ReteIVA: 2.337
Official net: 65.424
```

Prueba:

```text
99621 - 11698 - 6732 - 12200 - 3567 = 65424
```

### Fixture B

```text
Producto: 19.990
Fee: 3.698
Buyer shipping contribution: +12.300
ML shipping charge: -14.900
Shipping net: -2.600
Official net: 13.692
```

Prueba:

```text
19990 - 3698 + 12300 - 14900 = 13692
```

---

## H17 — buyer shipping contribution y ML shipping charge son componentes distintos

Prohibido modelo final:

```text
shipping_cost = un único número ambiguo
```

Gate:

```text
SHIPPING_COMPONENTS_PRESERVED
```

---

## H18 — official net y cálculo analítico son distintos

Regla:

```text
official remote value
≠ analytical ERP calculation
```

Nunca sobrescribir official con recomputación local.

---

## H19 — nuevo hallazgo crítico: precisión monetaria real no está cerrada

`SyncOrderDecimalPrecisionTest` actual usa valores como strings JSON.

Pero Mercado Libre normalmente entrega números JSON.

`MeliClient` usa `json_decode()` nativo y `SyncOrderHandler::decimal4()` todavía admite `float`.

Por tanto:

```text
EXACT MONEY gate actual
NO demuestra todavía
JSON NUMBER real lossless
```

Nuevo gate V3:

```text
REMOTE_NUMBERS_LOSSLESS
```

Este cambio altera el orden del roadmap.

---

## H20 — preserving numbers como strings es compatible con reconcile actual

Se auditó `ReconcileOrdersHandler`.

Ya acepta:

```text
paging.total   int|string digits
paging.offset  int|string digits
paging.limit   int|string digits
order id       int|string
```

Por tanto un decoder lossless puede devolver números JSON como lexemas string sin romper ese contrato de paginación.

---

## H21 — Work payload no sirve como evidencia histórica durable

`WorkRepository::retryCurrentClaim()` no modifica payload.

Auditar múltiples páginas requiere persistir:

- total inicial;
- IDs esperados;
- páginas;
- repetidos;
- hash;
- cobertura.

Por evidencia real V3 autoriza **máximo dos tablas Sales audit**:

```text
sales_audit_runs
sales_audit_orders
```

No son otro engine ni otra cola.

---

## H22 — Orders GET 206 no necesita nueva infraestructura por ahora

La documentación oficial indica que `X-Content-Missing` suele señalar datos complementarios ausentes.

`SyncOrderHandler` ya:

- no depende de feedback/mediations/shipping completo;
- trata buyer como opcional;
- falla cerrado si faltan campos críticos de Sales.

Dirección:

```text
añadir tests 206
NO headers/storage/framework nuevo salvo evidencia
```

---

# 5. ESPECIFICACIÓN V3 — DECISIONES PRINCIPALES

## Arquitectura sigue igual

```text
1 app
1 repo
1 DB
1 Work
1 WorkRunner
1 MeliClient
```

## Historical Sales

Diseño propuesto:

```text
SalesMonthWindow
→ guard-band ±1h
→ seller search
→ filtro local exacto date_created
→ sales_audit_runs/orders
→ validar cobertura
→ missing only repair
→ confirmación
```

Primera certificación:

```text
capture A válida
→ repair
→ capture B válida
→ mismo hash/count
→ verified
```

Reauditoría posterior:

```text
hash igual → verified
hash cambia → nueva evidencia, no borrar local, requiere explicación/confirmación
```

## Billing

```text
1 page per work
from_id durable
200 usable → persist/upsert
206 → no persist / no cursor advance
empty 200 → caught_up
```

`caught_up` local y `CLOSED` fiscal son conceptos diferentes.

## Financial

```text
Sales facts
+ Shipping facts
+ Payment/Cash
+ Billing
→ vista financiera
```

No ledger contable genérico.

---

# 6. PLAN MAESTRO V3 — ORDEN PRODUCTIVO PROPUESTO

Después de aprobación:

```text
V3-A Exact JSON / money boundary
→ V3-B Sales month + historical coverage
→ V3-C F6A Task 2 Billing ingest
→ V3-D F6B Financial
→ F7+
```

## V3-A

Primero RED realista:

```text
JSON NUMBER literal
90071992547409.1234
```

No string artificial.

Luego decoder lossless mínimo.

## V3-B

- `SalesMonthWindow`;
- migration Sales audit;
- repository audit;
- reconcile multipágina verificable;
- missing-only repair;
- doble captura sólo donde haga falta certificar.

## V3-C

- migration Billing context mínima;
- `BillingDetailNormalizer`;
- `BillingPeriodSyncHandler`;
- 206/cursor/idempotencia/429/404;
- router Work único con Billing.

Cuando Billing aparezca como segundo dominio de Work:

```text
SalesWorkProcessor
→ ApplicationWorkProcessor
```

Switch pequeño, no CommandBus/registry genérico.

## V3-D

Financial sólo después de:

- F6A GREEN;
- fixtures A/B;
- captura MCO suficiente para cardinalidades.

---

# 7. GATES V3

```text
REMOTE_NUMBERS_LOSSLESS
MONTH_BOUNDARY_STABLE
DOMAIN_DATE_SOURCE_FIXED
SELLER_SEARCH_SEMANTICS
PAGING_COVERAGE_PROVEN
RECONCILE_REPEATABLE
NO_FALSE_COMPLETE
AUDIT_REPAIR_MINIMAL
NO_ENDLESS_MISSING_RETRY
NO_404_STORM
NO_429_STORM
PACK_NO_DOUBLE_CHARGE
SHIPPING_COMPONENTS_PRESERVED
OFFICIAL_NET_PRESERVED
INVOICE_NET_SEPARATION
PARTIAL_NEVER_COMPLETE
API_CONTRACT_SNAPSHOT_CURRENT
NOISE_REDUCTION_PASS
WRITES_DEFAULT_OFF
```

Ninguno debe declararse PASS sólo por documentación.

---

# 8. REMOTE WRITES

Mapa V3 creado.

Writes conocidos futuros:

```text
price
available_quantity
pause/active
close/delete
fiscal document upload/delete
```

Todos siguen:

```text
OFF
```

No crear framework genérico.

F16 certificará/habilitará por operación.

---

# 9. VALIDACIONES REALES TODAVÍA PENDIENTES

No bloquean revisar Spec/Plan, pero sí sus gates reales:

1. smoke real `/orders/search` MCO con guard-band;
2. si existe order ID conocido >12 meses, probar GET exacto una sola vez;
3. captura Billing MCO sanitizada;
4. confirmar cardinalidades `sales_info/items_info/shipping_info`;
5. observar si un `detail_id` Billing muta o ajustes aparecen como nuevos detalles;
6. OAuth app dedicada real;
7. Hostinger/cron real;
8. branch protection antes de integración productiva.

---

# 10. METODOLOGÍA DE TRABAJO

## ChatGPT

Actúa como:

- coordinador técnico;
- auditor;
- analista funcional;
- custodio de contexto;
- diseñador de especificación/plan;
- verificador de Git/tests/API.

No sustituye al usuario para:

- cambios grandes de producto;
- merge/deploy;
- habilitar writes;
- aceptar riesgo no demostrado.

## Codex

Después de aprobación:

```text
AGENTS.md
→ branch/base/status
→ RED
→ GREEN mínimo
→ tests
→ PHPStan
→ suite
→ SHA
→ checkpoint
```

No decide arquitectura nueva por sí solo.

## Antigravity

Rol recuperado sólo parcialmente:

```text
segunda auditoría / contraste independiente
```

No inventar protocolo formal histórico no recuperado.

---

# 11. LEYES DEL PROYECTO

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
DELETE
→ SIMPLIFY
→ REUSE
→ EXTEND
→ ADD
```

Nunca introducir:

- tabla;
- columna;
- engine;
- cola;
- estado;
- abstracción;
- endpoint;
- helper;
- flag;

sin comprobar antes si puede eliminarse, derivarse, fusionarse o reutilizarse.

---

# 12. PUNTO EXACTO DE REANUDACIÓN

Si un nuevo chat recibe este checkpoint, debe hacer en este orden:

1. leer completo este archivo;
2. verificar GitHub HEAD de `audit/v3-forensic-redesign-20261009`;
3. confirmar que `b21cf7...` sigue en la historia o identificar commits posteriores;
4. leer `AGENTS.md`;
5. revisar:
   - Especificación Maestra V3;
   - Plan Maestro V3;
   - cierre draft de auditoría;
6. reconocer que **F6A Task 2 sigue congelado**;
7. NO programar hasta aprobación del usuario;
8. si el usuario aprueba Spec/Plan:
   - iniciar **V3-A Task A1 RED**;
   - no saltar directamente a Billing;
9. trabajar en microbloques;
10. actualizar este checkpoint o crear uno nuevo antes de riesgo de contexto.

---

# 13. ESTADO FINAL DEL CHECKPOINT

```text
AUDITORÍA FORENSE V3: suficiente para revisión
SPEC V3: DRAFT creado
PLAN V3: DRAFT creado
PRODUCT CODE NUEVO: 0
F6A TASK 2: FROZEN
MERGE: NO
DEPLOY: NO
REMOTE WRITES: OFF
NEXT DECISION: aprobación usuario Spec/Plan
NEXT CODE AFTER APPROVAL: V3-A Task A1 RED
```
