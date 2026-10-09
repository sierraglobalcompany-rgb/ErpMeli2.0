# ERP MELI 2.0 — V3 ADDENDUM 03

**Fecha:** 2026-10-09  
**Estado:** NORMATIVO PARA EL DRAFT V3; no autoriza código productivo.  
**Aplica sobre:** Spec V3, Plan V3, Addenda 01–02.  
**Freeze:** F6A Task 2 y todo código productivo V3 permanecen congelados.

Este addendum define la precedencia entre Sales/Shipping/Billing para evitar doble conteo y amplía mínimamente el contexto Billing que ya está justificado por la documentación oficial vigente.

---

## 1. Problema: varias APIs describen partes de la misma economía de una venta

La documentación oficial actual muestra, entre otras fuentes:

### Orders

```text
order_items.unit_price
order_items.quantity
order_items.sale_fee
order_items.gross_price
discounts
order.total_amount
```

### Shipping

```text
seller.cost
receiver/buyer shipping contribution según contrato
```

### Billing / Provisiones

```text
charge_info.detail_amount
charge_info.detail_type/detail_sub_type
charge_info.debited_from_operation
charge_info.status
charge_info.charge_bonified_id
discount_info.charge_amount_without_discount
discount_info.discount_amount
discount_info.rebate
discount_info.discount_reason
sales_info[]
shipping_info
items_info[]
document_info
```

Si ERP suma todos esos números como costos independientes, duplica conceptos.

---

## 2. Regla maestra: fuente ≠ concepto contable adicional

Cada dato conserva:

```text
fuente
concepto
scope
fecha
valor
```

Dos fuentes que describen el mismo concepto se **reconcilian**; no se suman por existir en dos endpoints.

Ejemplo:

```text
Orders item.sale_fee
vs
Billing CHARGE de tarifa de venta
```

Cuando ambos corresponden a la misma comisión:

```text
NO = sale_fee_orders + billing_charge
SÍ = comparar / conciliar / elegir fuente de autoridad según vista
```

---

## 3. Precedencia por propósito

### 3.1 Vista operativa de venta

Para mostrar lo conocido de la venta en tiempo cercano al evento:

```text
Orders = fuente primaria de hechos comerciales
```

Incluye:

- unit price;
- quantity;
- gross price;
- sale_fee observada;
- total_amount;
- pack/order relationships.

`Orders.sale_fee` puede usarse como estimación/referencia operativa aun antes del cierre documental de Billing.

### 3.2 Vista de cobros/bonificaciones facturados

Para responder qué cobró/bonificó Mercado Libre documentalmente:

```text
Billing detail = fuente primaria
```

`billing_details.detail_amount` + `detail_type/sub_type` + documento/contexto son la evidencia del cargo/bonificación facturada.

Orders no sustituye ese dato.

### 3.3 Vista financiera conciliada

Financial presenta ambos cuando sea útil:

```text
operational_reference
billed_amount
reconciliation_delta
```

No inventar un único valor si las fuentes aún no se pueden mapear con certeza.

---

## 4. Shipping: evitar el mismo doble conteo

Fuentes posibles:

```text
Orders legacy shipping fields
Shipping endpoint seller.cost
Billing shipping charge/bonus
Billing shipping_info.receiver_shipping_cost
```

Reglas:

- `receiver_shipping_cost` es aporte/costo a cargo del receptor; no es automáticamente costo del vendedor;
- `seller.cost` describe costo operativo del envío para el vendedor en Shipping;
- Billing `detail_amount` de subtipo shipping describe cargo/bonificación facturada;
- no sumar `seller.cost + billing shipping detail` como si fueran dos costos independientes sin demostrar que representan conceptos distintos.

Financial debe etiquetar la fuente y reconciliar.

---

## 5. Dos clases distintas de “descuento”

No mezclar:

### Descuento comercial del producto

Fuente Sales/Orders:

```text
unit_price
gross_price
orders/{id}/discounts
```

Describe reducción del precio/oferta al comprador.

### Descuento/rebate sobre un cargo de Mercado Libre

Fuente Billing:

```text
discount_info.charge_amount_without_discount
discount_info.discount_amount
discount_info.rebate
discount_info.discount_reason
```

Describe cómo se redujo/bonificó un cargo facturado.

Son conceptos diferentes aunque ambos se llamen “descuento”.

Gate:

```text
COMMERCIAL_VS_BILLING_DISCOUNT_SEPARATED
```

---

## 6. Billing context mínimo corregido

El `context_json` propuesto en Spec V3 se amplía, sin guardar raw body ni PII.

Allowlist normativa:

```json
{
  "charge": {
    "debited_from_operation": "YES|NO|INAPPLICABLE|null",
    "status": "...|null",
    "charge_bonified_id": "...|null"
  },
  "discount": {
    "charge_amount_without_discount": "decimal-string|null",
    "discount_amount": "decimal-string|null",
    "rebate": "decimal-string|null",
    "discount_reason": "bounded-string|null"
  },
  "sales": [
    {
      "order_id": "...",
      "operation_id": "...",
      "sale_date_time": "...",
      "transaction_amount": "decimal-string|null"
    }
  ],
  "shipping": {
    "shipping_id": "...|null",
    "pack_id": "...|null",
    "receiver_shipping_cost": "decimal-string|null"
  },
  "items": [
    {
      "item_id": "...",
      "order_id": "...|null",
      "item_amount": "decimal-string|null",
      "item_price": "decimal-string|null"
    }
  ]
}
```

### No PII

Excluir:

```text
payer_nickname
buyer_nickname
receiver_nickname
state_name
addresses
phones
emails
item_title
categories descriptivas
transaction_detail libre salvo necesidad futura demostrada
unknown keys
raw body
```

`discount_reason` se permite únicamente como texto acotado del contrato Billing porque describe el cargo/descuento, no identidad del comprador.

---

## 7. ¿Columnas o JSON?

Mantener columnas ya justificadas:

```text
external_detail_id
associated_detail_id
detail_type
detail_sub_type
detail_amount
currency_id
document_id
marketplace
remote_created_at
legal_document_status
```

Mantener el resto curado dentro de `context_json` por ahora.

No crear todavía columnas independientes para:

- debited_from_operation;
- charge_bonified_id;
- discount_amount;
- rebate;
- order_id;
- shipping_id.

F6B sólo normaliza alguno si aparece una segunda necesidad real de consulta/integridad que el JSON curado haga torpe o insegura.

---

## 8. Bonificaciones y relaciones

`charge_bonified_id` / `associated_detail_id` permiten relacionar eventos cuando la API lo exponga.

Regla:

```text
cargo original permanece
bonus/credit note permanece
Financial deriva efecto neto
```

Nunca actualizar destructivamente el cargo original para “convertirlo” en el valor neto posterior.

Gate:

```text
BONUS_RELATION_PRESERVED
```

---

## 9. Official net: precisión de lenguaje

Los fixtures reales contienen un `official_net` observado en evidencia de negocio/UI.

Hasta que una captura API MCO identifique un campo/contrato remoto inequívoco equivalente:

- conservar ese valor como **business reference fixture**;
- no afirmar que `SUM(Billing)` es automáticamente el “official net”;
- no etiquetar el cálculo ERP como official;
- Financial puede mostrar `analytical_net` y `delta_vs_reference` cuando la referencia esté disponible.

Gate `OFFICIAL_NET_PRESERVED` significa preservar una fuente oficial cuando exista, no fabricar una.

---

## 10. Ajustes normativos al Plan V3

### C2 BillingDetailNormalizer

Añadir tests para:

```text
charge.debited_from_operation allowlisted
charge.status allowlisted
charge.charge_bonified_id allowlisted
discount amounts exact strings
discount_reason bounded
payer/buyer/receiver nicknames excluded
item_title/category excluded
unknown keys excluded
```

### D1 Fixtures

Añadir assertion conceptual:

```text
Orders sale_fee no se suma de nuevo a Billing charge equivalente
```

Los fixtures A/B siguen siendo referencias business-level; no se fuerzan artificialmente a payload Billing.

### D2 Mapping

Antes de agregar importes:

```text
map source detail → concept → sale_key/order/item/shipping
```

Si no existe correspondencia demostrable:

```text
unmatched billing detail
```

No asignar proporcionalmente por intuición.

### D3 Vista Financial

Separar como mínimo:

```text
commercial_gross/reference
commercial_net_sale_value
operational_fee_reference
billed_fee
shipping_operational_reference
shipping_billed_charge
billing_discount/rebate
taxes/withholdings por fuente
analytical_net
reference/official net si existe
delta
```

La UI puede simplificar, pero el modelo no suma fuentes duplicadas.

---

## 11. Gates agregados

```text
FINANCIAL_SOURCE_PRECEDENCE
NO_CROSS_SOURCE_DOUBLE_COUNT
COMMERCIAL_VS_BILLING_DISCOUNT_SEPARATED
BONUS_RELATION_PRESERVED
UNMATCHED_BILLING_NOT_FORCED
```

Semántica:

- `FINANCIAL_SOURCE_PRECEDENCE`: cada vista sabe qué fuente es primaria para su propósito.
- `NO_CROSS_SOURCE_DOUBLE_COUNT`: el mismo concepto observado en Orders/Shipping/Billing no se suma dos veces.
- `COMMERCIAL_VS_BILLING_DISCOUNT_SEPARATED`: descuentos de producto y descuentos/rebates de cargos no se mezclan.
- `BONUS_RELATION_PRESERVED`: cargo y bonificación siguen siendo hechos separados relacionados.
- `UNMATCHED_BILLING_NOT_FORCED`: un detalle sin relación demostrada no se asigna arbitrariamente a una venta/item.

---

## 12. Resultado KISS

La mejora no añade ninguna tabla.

Sólo amplía el JSON curado ya aprobado para conservar campos de Billing que la evidencia oficial demuestra necesarios.

Se evita:

- ledger financiero paralelo;
- duplicación Orders+Billing;
- tabla discounts prematura;
- distribución proporcional inventada;
- pérdida de rebate/bonus relation.

---

## 13. Punto exacto de continuidad

Siguiente revisión: validar que la captura Billing real MCO futura pueda poblar esta allowlist sin PII y confirmar cardinalidades. Hasta entonces, F6B continúa bloqueado y F6A Task 2 no se implementa sin aprobación explícita del draft V3 completo.
