# ERP MELI 2.0 — V3 ADDENDUM 05

**Fecha:** 2026-10-09  
**Estado:** NORMATIVO PARA EL DRAFT V3; no autoriza código productivo.  
**Aplica sobre:** Spec V3, Plan V3 y Addenda 01–04.  
**Freeze:** F6A Task 2 y todo código productivo V3 continúan congelados.

Este addendum cierra dos supuestos que no deben quedar implícitos antes de implementar F6A/F6B:

1. qué está realmente demostrado sobre la terminación del cursor Billing;
2. cómo conservar cobros/bonificaciones sin inventar un signo financiero prematuro.

---

## 1. Contrato oficial demostrado de paginación Billing

La documentación oficial vigente y el corpus canónico `ApiMercadolibre` confirman para:

```text
GET /billing/integration/periods/key/{KEY}/group/ML/details
```

lo siguiente:

```text
limit: 1..1000; default 150
from_id: default 0
response.last_id: cursor para la página siguiente
sort_by: ID | DATE
order_by: ASC | DESC
```

La recomendación oficial explícita es:

```text
primera página:  limit=1000&from_id=0
siguiente:       limit=1000&from_id=<last_id anterior>
repetir hasta consultar todos los detalles
```

Y recomienda `from_id` sobre `offset` para volúmenes altos.

### Propiedad segura que sí podemos congelar

Para ERP2:

```text
sort_by=ID
order_by=ASC
```

Una página autoritativa no parcial sólo puede avanzar el cursor a un `last_id` estrictamente posterior al cursor solicitado.

---

## 2. Condición terminal: NO está suficientemente especificada por texto oficial

La documentación consultada no define de forma inequívoca una de estas reglas como contrato terminal:

```text
results=[]
count(results) < limit
last_id ausente
last_id repetido
from_id == last_id
offset + count >= total
```

Aunque ejemplos/respuestas exponen paginación y el texto dice “hasta consultar todos los detalles”, V3 no convierte una inferencia en contrato productivo.

### Regla V3

```text
BILLING_CURSOR_TERMINAL_UNPROVEN
```

Antes de GREEN de F6A Task 2 debe existir un **smoke MCO controlado y sanitizado** que observe al menos:

1. primera página de un período/document_type conocido;
2. avance mediante `last_id`;
3. página final real;
4. request posterior al último cursor sólo si es seguro/necesario para demostrar la señal;
5. estructura de `results`, `last_id`, `total`, `offset`, `limit` que realmente entregue MCO;
6. comportamiento de período sin resultados, si se dispone de uno seguro.

No guardar PII del smoke.

### Consecuencia para el Plan

Task C4/C5 no puede afirmar todavía:

```text
empty 200 => caught_up
short page => caught_up
```

como contrato final.

La implementación debe aislar la decisión terminal en una regla pequeña/probada dentro del slice Billing para que el resultado del smoke se codifique sin rediseñar el handler.

No crear abstraction/strategy framework.

---

## 3. Semántica provisional segura antes del smoke

Mientras el terminal no esté probado:

### Página HTTP 206

```text
NO persistir como completa
NO avanzar durable cursor
sync_state != caught_up
partial_flag = 1 / attention según contrato final
```

### Página 200 válida con resultados

Sólo después de validar todos los detalles:

```text
persistir detalles idempotentemente
cursor candidate = response.last_id
candidate debe progresar
```

El cursor durable se actualiza en la misma transacción que la persistencia de la página.

### Cursor no progresivo

```text
last_id <= requested_from_id
→ attention / contract failure
→ NO replacement chain infinito
```

### Página terminal

No declarar `caught_up` hasta que la señal terminal observada en MCO esté convertida en test.

---

## 4. `total` no es verdad durable de Financial

Si la respuesta MCO expone `total`, puede usarse como control de consistencia de esa captura, pero no como verdad financiera permanente.

No asumir:

```text
current total = final immutable total of period
```

porque:

- períodos pueden recibir ajustes/bonificaciones posteriores;
- BILL y CREDIT_NOTE se sincronizan por separado;
- una revalidación posterior puede descubrir detalles nuevos.

Si el smoke demuestra `total`, los tests pueden usarlo como guard de página/pass, nunca como razón para borrar detalles previos.

---

## 5. Tres ejes Billing que no se deben colapsar

La documentación oficial demuestra ejes distintos:

```text
document_type = BILL | CREDIT_NOTE

detail_type = CHARGE | BONUS

charge_info.status =
  BONUS_ON_CREDIT_NOTE
  BONUS_PART_ON_CREDIT_NOTE
  BONUS_ON_BILL
  BONUS_PART_ON_BILL
  BONUS_ON
  BONUS_PART_ON
  null
```

También existen relaciones como:

```text
charge_bonified_id
detail_associated_id
```

Por tanto:

```text
document_type != financial sign
detail_type != document_type
status != detail_type
```

---

## 6. F6A preserva hechos; F6B interpreta efecto

### F6A — ingestión

Debe conservar exactamente:

```text
document_type
detail_type
detail_sub_type
detail_amount
associated_detail_id cuando exista
charge_bonified_id en context_json cuando exista
status en context_json cuando exista
debited_from_operation en context_json cuando exista
currency/document/date/context curado
```

`detail_amount` se persiste con el valor/escala que entregue la fuente normalizada; F6A no altera artificialmente el signo para convertir CHARGE/BONUS en débito/crédito.

### F6B — Financial

Sólo después de fixtures/captura real puede mapear:

```text
source fact
→ economic concept
→ direction/effect
→ sale/order/item/shipping scope
```

Si una combinación no está demostrada:

```text
unmatched/attention
```

No forzar aritmética.

---

## 7. Caso importante: CHARGE con estado de bonus

La documentación oficial muestra que un detalle puede mantener:

```text
detail_type = CHARGE
status = BONUS_ON_BILL
```

Esto demuestra que una regla como:

```text
CHARGE => siempre restar detail_amount
```

es incorrecta como algoritmo universal.

V3 exige preservar el estado y la relación antes de derivar efecto neto.

Gate:

```text
BILLING_EFFECT_NOT_INFERRED
```

---

## 8. Bonificaciones parciales

Estados como:

```text
BONUS_PART_ON_CREDIT_NOTE
BONUS_PART_ON_BILL
BONUS_PART_ON
```

impiden asumir que un cargo relacionado queda anulado al 100 %.

Regla:

```text
original charge remains immutable evidence
bonus/related detail remains separate evidence
Financial computes effect only from explicit mapped amounts/relations
```

No sobrescribir `detail_amount` original con el neto posterior.

Gate:

```text
PARTIAL_BONUS_PRESERVED
```

---

## 9. `debited_from_operation` tampoco define por sí solo el signo total

Valores documentados:

```text
YES
NO
INAPPLICABLE
```

Este campo describe si el cargo fue debitado de la operación; no reemplaza `detail_type`, status, document_type ni la relación de bonus.

Financial puede usarlo como dimensión de conciliación, no como algoritmo único de signo.

---

## 10. Cambio normativo al Plan V3 — F6A Task 2

Antes de C1/C2 productivo se inserta un gate externo pequeño:

```text
C0 — Billing cursor terminal smoke MCO
```

### C0 no cambia producto

Entregable:

```text
docs/fixtures/billing_cursor_terminal_mco_sanitized.json
```

o equivalente documental sanitizado.

Debe registrar sólo:

```text
period_key sintético/anonimizado si procede
document_type
requested_from_id
http_status
result_count
last_id presente/ausente
total/offset/limit si existen
terminal_observation
```

Sin IDs de comprador, nicknames, direcciones, teléfonos ni raw payload.

### Luego C1–C8

La prueba RED de cursor se escribe con la señal real demostrada por C0.

No implementar `BillingPeriodSyncHandler` antes de C0 si la condición terminal sigue sin demostrarse.

---

## 11. Ajustes de gates F6A/F6B

Añadir:

```text
BILLING_CURSOR_TERMINAL_PROVEN
BILLING_CURSOR_PROGRESSIVE
BILLING_EFFECT_NOT_INFERRED
PARTIAL_BONUS_PRESERVED
```

Semántica:

- `BILLING_CURSOR_TERMINAL_PROVEN`: caught_up sólo se alcanza por señal final observada y testeada.
- `BILLING_CURSOR_PROGRESSIVE`: toda página no terminal que avance usa un last_id estrictamente progresivo.
- `BILLING_EFFECT_NOT_INFERRED`: F6A conserva ejes documentales sin convertirlos prematuramente en signo financiero.
- `PARTIAL_BONUS_PRESERVED`: bonificaciones parciales no destruyen ni reemplazan el cargo original.

---

## 12. KISS / reducción de ruido

Este cierre no añade:

- tabla nueva;
- cursor engine;
- paging abstraction;
- sign engine;
- ledger doble;
- polling de monthly periods.

Añade sólo una obligación de **evidencia real antes de codificar la condición terminal**.

Eso es más simple y más seguro que fijar una heurística que luego obligue a otro reparador.

---

## 13. Fuente documental de este addendum

Cruce realizado contra:

- corpus canónico `sierraglobalcompany-rgb/ApiMercadolibre` en commit `eeb0bc9d944e2fad58c13a47ccc7660413fa6193`;
- página oficial `Provisiones`, actualización publicada 08/06/2026;
- página oficial de buenas prácticas de reportes de facturación.

El corpus confirma `limit/from_id/last_id` y la recomendación de avanzar desde el último identificador procesado; no aporta una condición terminal textual inequívoca.

---

## 14. Punto exacto de continuidad

Con este addendum, las incertidumbres que quedan **no deben resolverse con más arquitectura documental**.

Pendiente antes de código:

1. revisión final Spec + Plan + Addenda 01–05;
2. checkpoint maestro actualizado;
3. revisión/aprobación explícita del usuario;
4. después: V3-A1 RED;
5. antes de V3-C: C0 smoke MCO para terminal de Billing.
