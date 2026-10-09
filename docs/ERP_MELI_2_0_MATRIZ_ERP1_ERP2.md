# ERP MELI 2.0 — MATRIZ FORENSE ERP1 → ERP2

**Fecha:** 2026-10-09  
**Estado:** BORRADOR V3 basado en código real ERP1/ERP2 y evidencia recuperada.  
**Regla:** este documento no autoriza implementación productiva. F6A Task 2 sigue congelado.

## 1. Criterio de decisión

Cada concepto legacy se clasifica como:

- **CONSERVAR:** ERP2 ya tiene una implementación simple/correcta.
- **RESCATAR:** la propiedad de negocio/técnica es valiosa, pero no su implementación ERP1.
- **MEJORAR:** ERP2 actual es válido como base, pero necesita pruebas/contrato adicional.
- **ELIMINAR / NO PORTAR:** deuda, duplicación, amplificación o solución parche.
- **DIFERIR:** no hay todavía evidencia suficiente para añadir estructura.

La regla KISS es siempre:

`DELETE → SIMPLIFY → REUSE → EXTEND → ADD`

## 2. Sales, históricos y fechas

| Concepto ERP1 | Evidencia/valor | Problema ERP1 | ERP2 actual | Decisión V3 |
|---|---|---|---|---|
| Rango mensual local→UTC | Evita depender de timezone del servidor | se duplicó en varios flujos | F4 acepta rangos remotos pero no tiene contrato mensual V3 | **RESCATAR** la propiedad |
| Ventana `[from,to)` | Frontera determinística local | Orders remoto sólo documenta precisión de hora | no está congelada como regla de auditoría | **MEJORAR:** guard-band remoto + filtro local exacto |
| `MeliDateTimeNormalizer` | Preserva instante remoto y offset | infraestructura duplicada | `SyncOrderHandler` ya convierte timestamps a UTC | **RESCATAR PROPIEDAD**, no clase legacy |
| Reparación de fechas por mes | Permitía corregir normalización histórica | selecciona por `date_created` ya persistido antes de corregirlo; blind spot circular | no existe | **NO PORTAR**; prevenir con contrato correcto desde ingestión |
| Importador por `/orders/search` | descubre IDs por fecha | fin de loop podía confundirse con cobertura | `orders.reconcile` una página por work | **MEJORAR** sin otro engine |
| Auditoría de páginas | detecta páginas repetidas, gaps, total cambiante, offset inesperado | demasiada maquinaria | no existe prueba de cobertura global | **RESCATAR MÍNIMO** |
| Hash/IDs de captura | evidencia de snapshot | ERP1 lo materializó con mucha infraestructura | no existe | **RESCATAR sólo si es lo mínimo para demostrar cobertura** |
| Doble captura histórica | reduce falso cierre ante captura inestable | costo/complexidad si se aplica siempre | no existe | **DIFERIR/usar sólo donde una captura no pruebe estabilidad** |
| Auditor vs importador | separa descubrimiento de prueba de cobertura | contratos divergieron | discovery y exact sync existen | **RESCATAR RESPONSABILIDADES, UN SOLO CONTRATO DE FECHA** |
| Repair exacto missing-only | evita reimportar todo | leases/colas/adapters extras | Work + `order.sync` ya existen | **RESCATAR:** faltantes → exact GET → verify |
| 404 histórico terminal | evita retry infinito | semántica mezclada por recursos | Work soporta terminal/retry | **RESCATAR POR OPERACIÓN**, no global |
| 429 diferido | protege API | ERP1 añadió budgets/rhythm engines múltiples | MeliClient cooldown ya existe | **CONSERVAR ERP2**, no portar engines |
| “remote_window_months=12” configurable | mostraba cobertura aproximada | no era evidencia oficial | no existe | **NO PORTAR COMO VERDAD**; capacidad por endpoint |
| Mes completo genérico | UI simple | mezclaba fuentes/fechas distintas | no existe formalmente | **NO CREAR**; cobertura por dominio/fuente |

## 3. Modelo visible de venta y packs

| Concepto | Evidencia | Riesgo | Decisión V3 |
|---|---|---|---|
| `order_id` | identidad remota exacta; una orden representa un ítem/variación, con posible cantidad >1 | perderlo impide repair/trazabilidad | **CONSERVAR siempre** |
| `pack_id` | API actual define Pack 1:N Orders | raw `pack_id` puede abarcar órdenes de distintos vendedores; caller sólo ve autorizadas | **CONSERVAR RELACIÓN**, siempre scopeada por company/account |
| “venta visible” agrupada | usuario/ML pueden presentar carrito/pack como una venta | crear tabla `sales` prematuramente añade duplicación | **DERIVAR `sale_key` primero** |
| `sale_key` propuesta | permite agrupar sin nueva entidad | ninguna si se mantiene derivada | `company/account + pack_id`; fallback legacy `company/account + order_id` |
| verificación `/packs/{id}` | permite conocer child orders accesibles y shipment | no garantiza ver órdenes de sellers no autorizados; llamada extra | **USO PUNTUAL**, no loop obligatorio por cada orden |
| cargos compartidos por pack | evidencia real y ERP1 2.24 | duplicación por child order | **GATE OBLIGATORIO: PACK_NO_DOUBLE_CHARGE** |

## 4. Financial / Billing

| Concepto ERP1 | Valor | Problema ERP1 | ERP2 actual | Decisión V3 |
|---|---|---|---|---|
| Billing por order con `BILLING_ORDER_IDS_PER_CALL=1` | útil para investigar discrepancia puntual | amplificación HTTP / 404 / 429 / lentitud | F6A period-first | **NO PORTAR COMO INGESTIÓN** |
| Billing period-first | reduce llamadas y sigue fuente fiscal | ERP1 llegó tarde a este patrón | F6A Task 1 ya preparado | **CONSERVAR** |
| BILL / CREDIT_NOTE separados | identidad fiscal correcta | — | `billing_periods` lo soporta | **CONSERVAR** |
| una página por ejecución | crash/retry simple | — | dirección F6A | **CONSERVAR** |
| cursor `from_id` / `last_id` | integridad de paginación | — | contrato F6A | **CONSERVAR** |
| 206 no final | evita falso complete | ERP1 tuvo que añadirlo | MeliClient acepta 2xx y handler debe decidir | **REGLA POR OPERACIÓN** |
| estado PROCESSING | evita tratar documento incompleto como definitivo | — | esquema F6A no lo modela todavía | **DEFINIR EN F6B/ingestión según fixture** |
| neto/total oficial | verdad mostrada/remota | recomputación puede redondear distinto | no existe aún Financial V3 | **PRESERVAR** |
| distribución analítica | útil para margen/producto | no debe sobrescribir oficial | pendiente | **RESCATAR COMO CAPA DERIVADA** |
| comisión por item | demostrada por casos reales y Orders | puede duplicarse si se agrega mal | order_items no guarda comisión | **NECESIDAD V3; UBICACIÓN A DECIDIR** |
| shipping compartido | demostrada por casos reales | un solo `shipping_cost` pierde semántica | no modelado | **NECESIDAD V3; no añadir columna única simplista** |
| buyer shipping contribution vs ML shipping charge | caso real B | colapsarlos altera neto | no modelado | **CONSERVAR COMPONENTES cuando fuente los entregue** |
| impuestos/retenciones | caso real A / Billing | agregarlos sin detalle impide conciliación | no modelado | **CONSERVAR COMPONENTES necesarios** |
| cálculo monetario con `float` | legacy cómodo | pérdida exactitud | ERP2 exige decimal exacto | **NO PORTAR** |
| revisiones financieras | conserva historia de cambios | ERP1 implementó mucha maquinaria | no existe | **RESCATAR AUDITABILIDAD, DISEÑO MÍNIMO** |

## 5. Fechas: separar dominios

ERP1 demuestra que no existe un único “mes del ERP”.

| Dominio | Fuente | ERP1 | Decisión ERP2 V3 |
|---|---|---|---|
| Ocurrencia de venta | `order.date_created` | históricos/auditor | **Sales month** se define desde esta fuente |
| Cierre/confirmación | `order.date_closed` | sorting/search/estado | no sustituye automáticamente sale month |
| Cambio remoto | `last_updated` / `date_last_updated` | sincronización | stale/reconciliation, no sale month |
| Pago acreditado | `payment.date_approved` | reporte mensual Financial | Financial event independiente |
| Liberación de dinero | `money_release_date` | Financial | Cash event independiente |
| Billing | `period_key` + detalle/documento | facturación ML | período fiscal ML independiente |
| auditoría local | timestamps locales | operación | nunca define período de negocio |

## 6. Arquitectura y operación legacy

| Pieza ERP1 | Problema | ERP2 V3 |
|---|---|---|
| QueueCore + QueueV4 + adaptadores legacy | demasiadas capas/estados | **NO PORTAR** |
| múltiples budgets/rhythm/retry engines | contención de síntomas | **NO PORTAR**; usar MeliClient + Work |
| campañas/workers paralelos | operación difícil de razonar | **NO PORTAR** |
| raw payload archive permanente | PII/storage/ruido | **NO PORTAR**; Debug DVR bounded/sanitized |
| reparadores específicos encadenados | patch-on-patch | **NO PORTAR**; corregir contrato fuente |
| tablas/estados sólo para UI | ruido | **DERIVAR primero; persistir sólo si prueba necesidad** |

## 7. Diseño KISS resultante — todavía sin código

### Histórico Sales

```text
ventana canónica del dominio
→ query remota con guard-band compatible con granularidad de API
→ filtrar localmente por date_created exacto
→ validar páginas/IDs/cobertura
→ expected IDs vs local IDs
→ repair sólo missing
→ verify
```

No crea `HistoricalEngine`, otra cola ni otro cliente.

### Venta visible

```text
sale_key derivada =
  pack_id presente ? account-scoped pack : account-scoped order
```

No crea tabla `sales` hasta que otra necesidad persistente lo demuestre.

### Financial

```text
Sales operational facts
+ Shipping facts
+ Billing fiscal facts
+ Payment/cash facts
→ official result preserved
→ analytical allocations separated
```

No sustituir una fuente por otra.

## 8. Gates derivados

- `MONTH_BOUNDARY_STABLE`
- `RECONCILE_REPEATABLE`
- `PAGING_COVERAGE_PROVEN`
- `NO_FALSE_COMPLETE`
- `NO_ENDLESS_MISSING_RETRY`
- `NO_404_STORM`
- `NO_429_STORM`
- `PACK_NO_DOUBLE_CHARGE`
- `OFFICIAL_NET_PRESERVED`
- `INVOICE_NET_SEPARATION`
- `AUDIT_REPAIR_MINIMAL`
- `DOMAIN_DATE_SOURCE_FIXED`
- `PARTIAL_NEVER_COMPLETE`
- `NOISE_REDUCTION_PASS`

## 9. Bloqueos antes de implementación

1. formalizar fixtures financieros reales sanitizados;
2. cerrar semántica de fronteras Orders mediante prueba real/guard-band;
3. cerrar política de período Billing OPEN/CLOSED y late adjustments;
4. fijar cardinalidades necesarias para relación Billing↔orders/items/shipping;
5. escribir Especificación Maestra V3;
6. escribir Plan Maestro V3;
7. aprobación del usuario.

Hasta entonces, **F6A Task 2 permanece congelado**.
