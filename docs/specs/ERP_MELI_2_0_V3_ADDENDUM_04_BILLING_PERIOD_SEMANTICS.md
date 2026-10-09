# ERP MELI 2.0 — V3 ADDENDUM 04

**Fecha:** 2026-10-09  
**Estado:** NORMATIVO PARA EL DRAFT V3; no autoriza código productivo.  
**Aplica sobre:** Spec V3, Plan V3, Addenda 01–03.  
**Freeze:** F6A Task 2 y todo código V3 continúan congelados.

---

## 1. Dos estados distintos: ingestión local vs período fiscal

### Ingestión local ERP2

`billing_periods.sync_state` describe únicamente el avance de nuestro cursor local:

```text
pending
syncing
caught_up
attention
```

`caught_up` significa:

```text
para este pass, ERP2 recorrió details desde el cursor inicial
hasta una respuesta 200 terminal/explicitamente vacía según contrato
```

No significa:

```text
período fiscal CLOSED
no habrá ajustes posteriores
documentos legales terminados
Financial conciliado
```

### Estado fiscal Mercado Libre

`period_status=OPEN|CLOSED` proviene de:

```text
GET /billing/integration/monthly/periods
```

Es metadato remoto distinto de nuestro cursor local.

Regla:

```text
caught_up != CLOSED
CLOSED != caught_up
```

---

## 2. F6A no necesita `/monthly/periods`

La documentación oficial indica que consultar períodos primero es opcional y que para MCO la key usada por details se construye como:

```text
YYYY-MM-01
```

Por KISS, F6A Task 2 no añade una request `/monthly/periods` antes de cada sync.

No añadir ahora a `billing_periods`:

```text
period_status
period_date_from
period_date_to
expiration_date
unpaid_amount
```

Esos datos sólo se incorporan si una necesidad real de UI/Financial/documentos demuestra valor.

---

## 3. Si más adelante necesitamos OPEN/CLOSED

Añadir una operación READ pequeña y separada, no una dependencia oculta del handler de details.

Contrato futuro candidato:

```text
billing.periods.list
GET /billing/integration/monthly/periods
```

Reglas:

- uso manual/maintenance o cache de baja frecuencia;
- nunca una llamada por página de details;
- no batch agresivo;
- `document_type` explícito;
- group explícito;
- money lossless si se persisten amount/unpaid_amount.

No implementarlo en F6A salvo que la revisión final del usuario cambie el alcance.

---

## 4. Horizonte histórico Billing: no codificar “12 meses” como verdad

La documentación vigente contiene dos formulaciones que no deben confundirse:

1. describe información de los últimos 12 períodos;
2. el endpoint de períodos usa paginación, default 6, limit máximo 12 y también menciona consultar períodos más antiguos.

Por tanto:

```text
12 = límite/page/ventana documentada en ese recurso
NO = horizonte universal demostrado de Billing details
```

V3 no crea:

```text
BILLING_HISTORY_MONTHS=12
```

ni rechaza períodos sólo por edad.

Para details por key:

- construir period_key;
- consultar cuando el usuario/flujo lo requiera;
- clasificar 404/not available de forma terminal para ese intento;
- registrar evidencia real MCO antes de declarar horizonte.

Gate:

```text
BILLING_HORIZON_NOT_ASSUMED
```

---

## 5. Summary como control financiero, no motor de ingestión

Existe:

```text
GET /billing/integration/periods/key/{KEY}/summary/details
```

La documentación oficial indica que:

- contiene totales de cargos/bonificaciones/percepciones y cobros del período;
- no se recomienda usarlo en batch;
- su información no cambia durante el día, por lo que una consulta diaria por usuario es suficiente.

### Decisión V3

F6A no depende de summary.

F6B puede usarlo, después de captura MCO real, como **control de conciliación de período**:

```text
normalized details
vs
period summary control totals
```

No asumir igualdad de categorías hasta probar mapping real.

Si se usa:

- una consulta explícita/de baja frecuencia;
- no por order;
- no por página;
- no nuevo scheduler dedicado;
- no PII del `user.nickname` en persistencia/fixtures;
- montos lossless.

Gate futuro candidato:

```text
BILLING_PERIOD_CONTROL_TOTALS
```

sólo cuando se demuestre en MCO.

---

## 6. OPEN/CLOSED y late adjustments

La regla anterior de V3 se mantiene pero se precisa:

```text
OPEN/CLOSED es metadata fiscal remota
caught_up es metadata de ingestión local
late adjustment es nueva evidencia
```

Incluso si un período se conoce como CLOSED:

- no borrar detalles anteriores;
- una revalidación explícita puede descubrir bonus/credit note/documento nuevo;
- no convertir CLOSED en polling frecuente;
- no declarar inmutabilidad absoluta.

---

## 7. Ajustes normativos al Plan V3

### C3–C9 F6A

No añadir llamada a monthly periods.

Task C9 trigger usa:

```text
company/account + period_key + document_type
```

y encola `billing.period.sync` directamente.

### C4

Renombrar mentalmente/semánticamente:

```text
sync_state=caught_up
```

como “pass local alcanzó fin”, nunca “período cerrado”.

### C7 404

Un 404/unavailable de details por period key:

```text
attention/unavailable para esa ejecución
NO loop infinito
NO inferencia global de horizonte
```

### D Financial

Sólo F6B puede decidir si `billing.periods.list` o `summary/details` aporta control adicional; exige RED/evidencia MCO antes de añadir persistencia.

---

## 8. Gates agregados/precisados

```text
BILLING_SYNC_STATE_SEPARATED
BILLING_HORIZON_NOT_ASSUMED
BILLING_PERIOD_METADATA_LOW_NOISE
```

- `BILLING_SYNC_STATE_SEPARATED`: caught_up nunca se presenta como OPEN/CLOSED fiscal.
- `BILLING_HORIZON_NOT_ASSUMED`: no existe hardcode 12 meses para details sin evidencia.
- `BILLING_PERIOD_METADATA_LOW_NOISE`: si se incorpora periods/summary, su consumo es explícito/cacheado y no batch agresivo.

---

## 9. Resultado KISS

Este hallazgo **elimina** una posible dependencia en lugar de añadirla:

```text
F6A details sync
NO necesita
monthly periods preflight
```

No añade tabla, columna, scheduler ni endpoint productivo ahora.

---

## 10. Punto de continuidad

La auditoría V3 queda más simple:

```text
F6A = details/cursor/caught_up local
F6B = conciliación + controles opcionales de período
```

La captura MCO real decidirá si period metadata/summary merece implementación. Hasta aprobación explícita del draft completo, no iniciar código.
