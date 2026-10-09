# Mercado Libre API — Snapshot curado ERP MELI 2.0 V3

**Snapshot:** 2026-10-09  
**Uso:** contratos relevantes para ERP2; no es copia del corpus completo.  
**Corpus canónico:** `sierraglobalcompany-rgb/ApiMercadolibre`  
**Commit corpus:** `eeb0bc9d944e2fad58c13a47ccc7660413fa6193`  
**Fuente externa:** documentación oficial Mercado Libre Colombia, revalidada 2026-10-09.  
**Writes:** OFF.

## Regla de mantenimiento

Este snapshot sólo contiene operaciones que:

1. ERP2 ya usa; o
2. una fase V3 aprobada necesitará con alta probabilidad y cuya frontera conviene fijar desde ahora.

No duplicar páginas completas, ejemplos extensos ni esquemas completos del corpus `ApiMercadolibre`.

Antes de registrar una operación nueva en `config/meli_operations.php`, volver a verificar la documentación oficial vigente.

## Operaciones READ activas ERP2

| Key ERP2 | Método | Path | Módulo | Contrato clave |
|---|---|---|---|---|
| `users.me` | GET | `/users/me` | OAuth | identidad de cuenta autorizada |
| `orders.get` | GET | `/orders/{order_id}` | Sales | exact order; fuente autoritativa para persistir orden individual |
| `orders.search` | GET | `/orders/search` | Sales/History | discovery acotado; seller search filtra canceladas; hasta 12 meses documentados |
| `billing.period.details` | GET | `/billing/integration/periods/key/{period_key}/group/ML/details` | Billing | period-first; cursor `from_id`/`last_id`; BILL/CREDIT_NOTE |

`oauth.token` es AUTH, no READ ni write comercial.

## Orders / Sales

### `GET /orders/{ORDER_ID}`

Uso ERP2:

- webhook follow-up;
- `order.sync` exacto;
- reparación puntual de ID faltante;
- confirmación posterior a discovery.

Campos relevantes para V3:

- `id`;
- `date_created`;
- `date_closed`;
- `last_updated` / `date_last_updated` según representación;
- `pack_id`;
- `order_items[].quantity`;
- `order_items[].unit_price`;
- `order_items[].sale_fee`;
- `order_items[].gross_price` cuando esté disponible;
- `total_amount`;
- `paid_amount`;
- `payments[]`;
- shipping id;
- taxes/status/cancellation facts necesarios.

Reglas:

- `order.date_created` es la fuente del dominio **Sales occurrence**;
- `date_closed` no reemplaza automáticamente `date_created` como mes de venta;
- `last_updated` es stale/reconciliation, no sale month;
- `sale_fee` puede existir por item;
- persistir sólo datos funcionales necesarios; no raw/PII permanente.

### `GET /orders/search`

Filtros documentados relevantes:

```text
seller
order.status
order.date_created.from/to
order.date_closed.from/to
order.date_last_updated.from/to
sort
offset
limit
```

Semántica V3:

- seller search omite órdenes canceladas;
- la documentación indica retención/search de órdenes creadas hasta 12 meses;
- seller `date_asc/date_desc` ordena por `date_closed`;
- filtros de fecha usan la hora y descartan minutos/segundos/milisegundos;
- paginación es offset;
- inclusividad exacta de `from/to` no se toma como supuesto no documentado.

### Contrato de frontera V3

```text
período Sales canónico
→ ventana remota ampliada por guard-band horario mínimo
→ /orders/search
→ exact/normalized date_created por resultado
→ filtro local al período canónico
→ prueba de cobertura
```

El guard-band es una propiedad del auditor/importador; no cambia el período de negocio.

## Packs

### `GET /packs/{PACK_ID}` — READ, futuro puntual

Relaciones documentadas:

```text
Pack 1:N Orders
Pack 0..1 Shipping
Order 1:N Payments
```

Una orden representa un ítem/variación, aunque puede llevar varias unidades.

Un pack puede incluir órdenes de sellers distintos y el caller sólo ve las child orders de sellers que su integración puede consultar.

Regla ERP2:

```text
sale_key = company + account + pack_id
fallback legacy = company + account + order_id
```

`sale_key` se deriva primero. No crear tabla `sales` sólo por agrupar la UI.

## Shipping

### `GET /shipments/{SHIPMENT_ID}/orders` — READ, futuro

Útil para relación shipment→order/pack/item cuando Shipping lo necesite.

Requiere `X-New-Domain: true` según contrato actual.

Reglas:

- Shipping es dominio separado de Sales y Billing;
- no pedir vistas de direcciones si no son necesarias;
- no confundir costo de envío mostrado en Order con efecto financiero final del vendedor.

## Payment / Cash dates

Campos remotos relevantes:

```text
payment.date_approved
money_release_date
money_release_status
```

Regla:

```text
order.date_created
≠ payment.date_approved
≠ money_release_date
```

Cada dominio define su propio período.

## Billing / Provisiones

### `GET /billing/integration/periods/key/{KEY}/group/ML/details`

Contrato F6A:

```text
document_type = BILL | CREDIT_NOTE
limit = 1..1000; default 150
from_id = 0 inicial
next = response.last_id
sort_by = ID
order_by = ASC
```

ERP2 usa:

```text
limit=1000
sort_by=ID
order_by=ASC
una página por work
```

Reglas:

- Billing es conciliación fiscal/financiera, no fuente primaria de Sales;
- BILL y CREDIT_NOTE son streams separados;
- `206` Billing = incompleto, nunca final/complete;
- `429` usa pacing/cooldown existente; no limiter Billing nuevo;
- cache local evita reconsultas innecesarias;
- no loop histórico por order.

### Períodos Billing

La familia de períodos permite OPEN/CLOSED.

- OPEN cambia mientras se generan cargos;
- CLOSED normalmente estabiliza, pero la documentación admite excepciones posteriores (bonificaciones/devoluciones/documentos);
- revalidación posterior debe ser explícita, de bajo ruido e idempotente;
- evidencia nueva genera ajuste/revisión auditable, no reescritura silenciosa.

### Estructuras relevantes en Billing details

La documentación actual expone estructuras/relaciones como:

- detalle: id, tipo/subtipo, amount, fecha, documento, marketplace, moneda;
- `sales_info[]`;
- `shipping_info`, incluido pack/shipping y aportes asociados;
- `items_info`;
- charge/discount/document status.

F6A Task 1 conserva únicamente cache mínimo. El modelo F6B final se decide con fixtures MCO; no asumir `detail → order` 1:1.

## Billing puntual por Order/Pack

### `GET /billing/integration/group/ML/order/details` — READ, no registrado aún

Uso permitido:

- investigación concreta;
- repair de discrepancia;
- reconstrucción puntual no resoluble desde cache period-first.

Uso prohibido:

- ingestión histórica masiva.

El contrato actual soporta uno o varios order IDs y pack; las buenas prácticas recomiendan no repetir órdenes/packs ya procesados.

## Billing Documents / Summary

READ futuros:

```text
/billing/integration/periods/key/{key}/documents
/billing/integration/periods/key/{key}/summary/details
```

Uso: documentación/resumen fiscal ML.

No confundir con factura electrónica DIAN emitida al comprador.

## Billing-info / Invoicing

Flujo READ futuro:

1. obtener `buyer.billing_info.id` desde Order;
2. consultar recurso billing-info vigente para datos fiscales del comprador.

Reglas:

- pertenece a Invoicing;
- PII/fiscal sensible: acceso mínimo;
- jamás incluir en fixtures/debug raw.

## Fiscal documents por venta

Contrato futuro de Invoicing:

```text
POST   /packs/{pack_id}/fiscal_documents
GET    /packs/{pack_id}/fiscal_documents
GET    /packs/{pack_id}/fiscal_documents/{id}
DELETE /packs/{pack_id}/fiscal_documents
```

Si `pack_id` es null en una orden legacy, el contrato oficial indica usar `order_id` manteniendo recurso `/packs/{id}`.

Writes siguen OFF.

## Catalog / Inventory writes conocidos, OFF

### `PUT /items/{ITEM_ID}`

Usos futuros certificados por slice:

- `price` → MUTATION;
- `available_quantity` → MUTATION;
- `status=paused|active` → ACTION;
- `status=closed` → DESTRUCTIVE;
- `deleted=true` → DESTRUCTIVE.

Stock tiene semántica lateral documentada:

- `available_quantity=0` puede pausar con `out_of_stock`;
- subir cantidad puede reactivar si el subestado es `out_of_stock`;
- `paused_by_seller` no se reactiva automáticamente sólo por stock.

No crear `RemoteWriteEngine`; cada slice registra y certifica su operación cuando llegue su fase.

## Códigos/semánticas que ERP2 debe tratar por operación

| Señal | Regla |
|---|---|
| 200 | éxito técnico; completitud depende del contrato |
| 206 Orders/otros | puede haber contenido utilizable; evaluar campos críticos |
| 206 Billing reports | parcial/incompleto; nunca complete |
| 401 | OAuth/reauth según flujo existente |
| 403 | permiso/alcance; no retry infinito |
| 404 exact resource | clasificar por operación/contexto; histórico conocido puede ser terminal |
| 409 write | conflicto; no retry ciego |
| 429 | cooldown/pacing; no fan-out agresivo |
| 5xx/transport | retry bounded en Work cuando sea seguro |

## Riesgos todavía abiertos

- horizonte exacto de `GET /orders/{id}` frente al horizonte de search;
- inclusividad remota exacta de `from/to`; el diseño con guard-band elimina la dependencia para clasificación, pero requiere smoke real;
- cardinalidades MCO reales de `sales_info[]`, `shipping_info`, `items_info`;
- cadencia mínima de revalidación CLOSED Billing;
- contratos de módulos F7+ deben volver a verificarse cuando se implementen.

## Gates vinculados

```text
API_CONTRACT_SNAPSHOT_CURRENT
MONTH_BOUNDARY_STABLE
SELLER_SEARCH_SEMANTICS
PAGING_COVERAGE_PROVEN
RECONCILE_REPEATABLE
PARTIAL_NEVER_COMPLETE
NO_404_STORM
NO_429_STORM
PACK_NO_DOUBLE_CHARGE
DOMAIN_DATE_SOURCE_FIXED
WRITES_DEFAULT_OFF
```
