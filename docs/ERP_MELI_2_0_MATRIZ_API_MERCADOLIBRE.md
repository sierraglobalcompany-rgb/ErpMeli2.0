# ERP MELI 2.0 — MATRIZ API MERCADO LIBRE V3

**Fecha de verificación:** 2026-10-09  
**Estado:** BORRADOR FORENSE; contratos READ priorizados.  
**Writes:** OFF. Este documento no habilita operaciones remotas.

## 1. Fuentes oficiales consultadas

Principalmente:

- `https://developers.mercadolibre.com.co/gestiona-ventas`
- `https://developers.mercadolibre.com.co/provisiones`
- `https://developers.mercadolibre.com.co/reportes-de-facturacion`
- `https://developers.mercadolibre.com.co/es_ar/es_ar/buenas-practicas-para-el-consumo-de-las-apis-de-reportes-de-facturacion`
- `https://developers.mercadolibre.com.co/es_ar/api-docs-es/gestion-packs`
- `https://developers.mercadolibre.com.co/es_co/envios`
- `https://developers.mercadolibre.com.co/es_co/facturacion`
- documentación de carga/consulta de documentos fiscales.

Regla: esta matriz resume únicamente lo necesario para ERP2. No reemplaza la documentación oficial ni duplica su corpus completo.

## 2. Sales / Orders

| Operación | Clasificación | Contrato/hallazgo actual | Uso ERP2 V3 | Estado |
|---|---|---|---|---|
| `GET /orders/{order_id}` | READ | orden exacta; puede responder 206 con `X-Content-Missing`; algunos campos faltantes pueden no impedir trabajar | fuente exacta de orden / repair / webhook follow-up | YA REGISTRADA |
| `GET /orders/search` | READ | requiere filtros; seller search conserva/documenta hasta 12 meses de órdenes creadas y excluye canceladas | discovery/auditoría histórica acotada | YA REGISTRADA |
| filtro `order.date_created.from/to` | READ | filtra por fecha de creación; API documenta uso de hora y descarta minutos/segundos/ms | discovery de Sales, con guard-band y filtro local exacto | REGLA V3 PENDIENTE DE TEST |
| `order.date_closed.from/to` | READ | filtra por fecha de confirmación/cierre | no usar por defecto como sale month | DISPONIBLE |
| `order.date_last_updated.from/to` | READ | filtra por última modificación | reconciliation incremental potencial; no sale month | DISPONIBLE / A EVALUAR |
| `sort=date_asc/date_desc` | READ | seller ordena por `date_closed`; buyer por `date_created` | riesgo de movimiento durante offset crawl | RIESGO V3 |
| `GET /orders/{id}/discounts` | READ | descuentos/cupones/cashback aplicados al precio | Invoicing/comercial cuando sea necesario | FUTURO |

### 2.1 Campos Sales relevantes

La orden actual documenta, entre otros:

- `id`;
- `date_created`;
- `date_closed`;
- `last_updated` / `date_last_updated` según contrato;
- `pack_id`;
- `order_items[].quantity`;
- `order_items[].unit_price`;
- `order_items[].sale_fee`;
- `order_items[].gross_price` en órdenes compatibles;
- descuentos del item;
- `total_amount` / `paid_amount`;
- `payments[]`;
- shipping id;
- `taxes`;
- cancel detail.

Regla V3: que un dato exista en Orders no significa que sea la fuente fiscal final. Sales conserva hechos operativos/comerciales; Billing/Payment conservan sus propias verdades.

### 2.2 Riesgos de `/orders/search`

1. horizonte documentado de 12 meses para search de órdenes creadas;
2. seller search excluye canceladas;
3. filtro `date_created` + sort seller por `date_closed` significa que el criterio de pertenencia y el criterio de orden pueden diferir;
4. paginación es offset, por lo que un conjunto mutable puede desplazarse entre páginas;
5. granularidad de filtros por hora; inclusividad exacta `from/to` no quedó explícita en la documentación consultada.

### 2.3 Dirección KISS para frontera temporal

No adivinar inclusividad.

Propuesta a convertir en test/especificación:

```text
mes canónico local
→ UTC canónico
→ ampliar consulta remota con guard-band mínimo de horas
→ recibir resultados
→ normalizar date_created exacto de cada orden
→ conservar sólo los que pertenecen al mes canónico
```

Así la pertenencia final al mes no depende de la imprecisión horaria ni de la inclusividad no documentada del filtro remoto.

## 3. Packs

| Operación | Clasificación | Contrato/hallazgo | Uso ERP2 V3 |
|---|---|---|---|
| `GET /packs/{pack_id}` | READ | Pack 1:N Orders; pack 0..1 Shipping; puede agrupar órdenes de distintos sellers | verificación puntual de agrupación/child orders |

Hallazgos:

- una orden representa un solo ítem/variación, aunque con múltiples unidades;
- packs agrupan una o más órdenes;
- documentación reciente indica que nuevas órdenes están asociadas a `pack_id` de forma generalizada;
- el caller sólo puede leer child orders de sellers que su integración puede consultar;
- raw `pack_id` no debe tratarse como identidad global multi-cuenta.

Regla ERP2:

```text
sale_key lógica = company + account + pack_id
fallback legacy = company + account + order_id
```

No se crea todavía tabla `sales`.

## 4. Shipping

| Operación | Clasificación | Hallazgo | Uso futuro |
|---|---|---|---|
| `GET /shipments/{shipment_id}` / familia shipments | READ | Shipping tiene contrato propio; Orders ya no debe ser fuente de todos los datos de envío | Shipping slice cuando V3 lo programe |
| `GET /shipments/{shipment_id}/orders` | READ | permite relacionar shipment con order/pack/item | diagnóstico/relación cuando sea necesario |

Reglas:

- no depender de campos de shipping embebidos en Orders para información completa;
- no almacenar direcciones/PII salvo necesidad funcional explícita;
- costo comprador, costo vendedor y cargo Billing no se asumen equivalentes.

## 5. Payments / Cash

Campos observados/documentados en órdenes y Billing:

- `payment_id`;
- `date_created`;
- `date_approved`;
- `money_release_date`;
- `money_release_status`;
- monto/estado/medio;
- tax details según contrato.

Regla de dominio:

```text
order.date_created ≠ payment.date_approved ≠ money_release_date
```

No existe un único mes financiero.

La necesidad de consultar Mercado Pago directamente para detalle de pago se evaluará en la fase correspondiente; no se añade un segundo cliente/engine en F6A.

## 6. Billing / Provisiones

### 6.1 Período

| Operación | Clasificación | Contrato/hallazgo | Uso ERP2 |
|---|---|---|---|
| `GET /billing/integration/monthly/periods` | READ | últimos 6 por defecto; máximo 12; key mensual; OPEN/CLOSED | discovery/estado ocasional, no polling batch |
| construir `YYYY-MM-01` | local | docs recomiendan construir key directamente | identidad F6A |

El `period_status` puede ser `OPEN` o `CLOSED`.

### 6.2 Detalles ML por período

Operación primaria:

```text
GET /billing/integration/periods/key/{KEY}/group/ML/details
```

Contrato:

```text
document_type=BILL|CREDIT_NOTE
limit 1..1000; default 150
from_id default 0
next cursor = last_id
sort_by=ID|DATE
order_by=ASC|DESC
```

Dirección ERP2:

```text
sort_by=ID
order_by=ASC
limit=1000
una página por work
cursor durable
BILL y CREDIT_NOTE separados
```

### 6.3 Estado OPEN/CLOSED y late adjustments

La guía oficial actual indica:

- OPEN: resumen/detalle cambia diariamente conforme se generan cargos;
- CLOSED: normalmente no cambia, pero hay excepciones como devoluciones por cancelación de ventas y generación de documentos;
- después de disponer de documentos fiscales, pueden hacerse consultas periódicas para validar bonificaciones que afecten el total.

Regla V3:

- `CLOSED` no significa “jamás consultar otra vez”;
- tampoco significa polling frecuente;
- una revisión posterior debe ser explícita, barata e idempotente;
- un detalle nuevo se registra como nueva evidencia/ajuste;
- no reescribir silenciosamente una conciliación previa.

La cadencia exacta se decidirá en Plan V3; no crear scheduler Billing separado.

### 6.4 HTTP 206 / 429

- Billing `206`: información incompleta; esperar y reintentar en ciclo posterior; **nunca final complete**.
- `429`: bloqueo preventivo por IP; reducir frecuencia, cachear y evitar batch masivo.

MeliClient ERP2 acepta cualquier 2xx como respuesta técnica. Por tanto, la semántica `206` debe decidirla el handler/contrato de cada operación:

- Order GET 206 puede ser utilizable si los campos críticos para Sales están presentes;
- Billing 206 no puede cerrar página/período.

No añadir manejo global erróneo de “todo 206 falla”.

### 6.5 Detalle Billing relevante

La respuesta documenta estructuras como:

- `charge_info` con fecha/estado documental;
- `discount_info`;
- `sales_info[]`;
- `shipping_info` con shipping/pack y `receiver_shipping_cost`;
- `items_info` con item/order/amount/price;
- document info;
- marketplace;
- currency;
- `detail_id`, tipo/subtipo y `detail_amount`.

Consecuencia:

`billing_details` F6A es cache mínimo de ingestión, no modelo Financial final.

No decidir aún una relación 1:1 `detail → order`: `sales_info` puede contener colección y debe probarse con fixtures MCO.

## 7. Billing por Order/Pack

```text
GET /billing/integration/group/ML/order/details
```

Clasificación: READ.

Contrato actual permite order IDs y pack; la guía de buenas prácticas desaconseja reconsultar orders/packs ya procesados y el batch masivo.

Uso ERP2 permitido:

- investigación puntual;
- repair explícito;
- discrepancia específica que no pueda resolverse desde cache period-first.

Uso prohibido:

- reconstruir todo el histórico orden por orden.

## 8. Documentos Billing y resumen

| Operación | Clasificación | Uso |
|---|---|---|
| `/billing/integration/periods/key/{key}/documents` | READ | facturas/notas de crédito ML asociadas al período |
| `/billing/integration/periods/key/{key}/summary/details` | READ | resumen fiscal; docs recomiendan consumo secuencial/cache, no batch |

No confundir estos documentos con factura electrónica emitida por el vendedor al comprador.

## 9. Billing-info del comprador

Contrato actual para datos fiscales del comprador:

1. obtener `buyer.billing_info.id` desde `/orders/{id}`;
2. consultar `/orders/billing-info/{site_id}/{billing_info_id}`.

El endpoint legacy `/orders/{order_id}/billing_info` está deprecado/puede devolver 404.

Clasificación: READ con PII/fiscal sensible.

Regla ERP2:

- pertenece a **Invoicing**, no a Billing financiero;
- acceso mínimo y protegido;
- no conservar PII en Debug DVR ni fixtures.

## 10. Factura PDF/XML adjunta a venta

La documentación vigente tiene flujo para cargar/obtener/eliminar documento fiscal por pack/order. Para carga, `pack_id` es la referencia preferida y existe fallback con order en casos legacy/null.

Clasificación futura: ACTION/FINANCIAL según mapa de writes V3.

Estado ERP2: **NO IMPLEMENTAR AHORA**. Writes globales continúan OFF hasta certificación futura.

## 11. Contratos ya registrados en ERP2

`config/meli_operations.php` actualmente incluye:

- `oauth.token`;
- `users.me`;
- `orders.get`;
- `orders.search`;
- `billing.period.details`.

Esta lista es apropiadamente pequeña para F0–F6A Task 1.

No registrar endpoints futuros sólo por existir. Registrar cuando un módulo aprobado los vaya a usar y tenga test de contrato.

## 12. Riesgos / preguntas aún abiertas

1. inclusividad exacta `from/to` en `/orders/search` no está documentada con precisión suficiente → resolver con guard-band + fixture/smoke real;
2. la frase de retención de 12 meses no distingue con suficiente claridad si GET exacto de una orden antigua tiene exactamente el mismo horizonte que search → no asumir;
3. seller search excluye canceladas → definir cobertura canónica de Sales;
4. offset crawl puede moverse al ordenarse seller por `date_closed` → exigir prueba de cobertura;
5. cardinalidad MCO real de `sales_info[]`/items/shipping por detalle Billing → fixture pendiente;
6. política mínima para revalidar Billing CLOSED → Plan V3;
7. late adjustments deben conservar evidencia/versionado sin nuevo engine;
8. mapa completo de remote writes se realizará antes de Spec/Plan final, manteniendo writes OFF.

## 13. Gates derivados de API

- `MONTH_BOUNDARY_STABLE`
- `SELLER_SEARCH_SEMANTICS`
- `PAGING_COVERAGE_PROVEN`
- `RECONCILE_REPEATABLE`
- `NO_FALSE_COMPLETE`
- `PARTIAL_NEVER_COMPLETE`
- `NO_404_STORM`
- `NO_429_STORM`
- `PACK_NO_DOUBLE_CHARGE`
- `OFFICIAL_NET_PRESERVED`
- `DOMAIN_DATE_SOURCE_FIXED`
- `API_CONTRACT_SNAPSHOT_CURRENT`

## 14. Regla de actualización

Antes de implementar una operación nueva:

1. verificar documentación oficial vigente;
2. registrar sólo el endpoint necesario en `meli_operations.php`;
3. añadir test de método/path/clasificación/params críticos;
4. fijar semántica de errores/206/429;
5. mantener writes OFF si no existe autorización/certificación explícita;
6. actualizar esta matriz/snapshot sólo cuando el contrato cambie materialmente.
