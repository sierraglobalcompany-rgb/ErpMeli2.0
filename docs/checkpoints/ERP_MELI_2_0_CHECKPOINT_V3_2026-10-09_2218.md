# ERP MELI 2.0 — CHECKPOINT V3 POST-ADDENDA

**Fecha:** 2026-10-09  
**Rama:** `audit/v3-forensic-redesign-20261009`  
**Estado:** auditoría/diseño en revisión final; NO código productivo autorizado.

## HEAD al crear este checkpoint

```text
9fa9c4a77a699a6542de6e9177aa62cad7f7217a
```

Commit anterior maestro:

```text
65c870ed256a8f8fae6111416696ea5b6ebf8855
```

Después de ese checkpoint se añadieron únicamente documentos V3.

## Freeze absoluto vigente

```text
NO product code
NO F6A Task 2
NO migrations reales
NO merge
NO deploy
NO remote writes
```

El primer código después de aprobación explícita sigue siendo:

```text
V3-A1 RED — JSON NUMBER real / REMOTE_NUMBERS_LOSSLESS
```

## Documentos de autoridad actuales

Leer en este orden:

1. `docs/specs/ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md`
2. `docs/plans/ERP_MELI_2_0_PLAN_MAESTRO_V3.md`
3. `docs/specs/ERP_MELI_2_0_V3_ADDENDUM_01_COVERAGE_206_CURRENT_MONTH.md`
4. `docs/specs/ERP_MELI_2_0_V3_ADDENDUM_02_SALES_FACTS_RETRY_WRITES.md`
5. `docs/audits/ERP_MELI_2_0_AUDITORIA_FORENSE_V3_CIERRE_DRAFT_2026-10-09.md`
6. `docs/ERP_MELI_2_0_MATRIZ_ERP1_ERP2.md`
7. `docs/ERP_MELI_2_0_MATRIZ_API_MERCADOLIBRE.md`
8. `docs/ERP_MELI_2_0_WRITE_MAP_V3.md`
9. `docs/api/MERCADOLIBRE_API_SNAPSHOT_V3_2026-10-09.md`
10. `docs/fixtures/financial_real_cases_v3.json`

En caso de conflicto con Spec/Plan base, los Addenda 01 y 02 prevalecen hasta consolidación.

## Nuevos hallazgos después del checkpoint maestro

### 1. Mes actual no es histórico final

```text
closed month + verified capture → Verificado
current month + verified capture → Al día hasta <timestamp>
```

No columna/state nuevo: se deriva del período y tiempo de captura.

Gate:

```text
CURRENT_MONTH_NOT_FINAL
```

### 2. Orders 206 no requiere engine

Docs oficiales actuales: `X-Content-Missing` de Orders puede señalar `buyer`, `feedback`, `mediations`, `seller`, `shipping`.

Regla:

```text
206 + core Sales usable → válido
206/200 + core Sales invalid → fail closed
```

`date_created` pasa a ser obligatorio para nuevas sincronizaciones API Sales.

### 3. Capture y repair quedan separados

Correcto:

```text
CAPTURE
→ VALIDATE
→ REPAIR missing-only
→ VERIFY
→ CONFIRM
```

Incorrecto/descontinuado:

```text
enqueue order.sync mientras aún se pagina una captura no validada
```

### 4. Guard-band requiere distinguir observed vs canonical

`sales_audit_orders` guarda toda orden observada con:

```text
in_period TINYINT(1)
```

Derivaciones:

```text
raw_observed_count = all observed IDs
expected_count = in_period=1
canonical hash = in_period=1 only
```

Cobertura remota exige:

```text
raw_observed_count == stable paging.total
```

No tabla de páginas.

### 5. Local ID por sí solo no resuelve histórico

Local válido exige:

```text
company/account
order_id
date_created válido
pertenencia al mismo mes canónico
```

Una fila legacy con `date_created=NULL` sigue siendo gap.

### 6. Sales hoy descarta hechos oficiales útiles

Docs oficiales vigentes de Orders exponen por `order_items`:

```text
sale_fee
gross_price
```

ERP2 actual no los persiste.

V3 añade sólo:

```text
order_items.sale_fee DECIMAL(18,4) NULL
order_items.gross_price DECIMAL(18,4) NULL
```

No tabla discounts todavía.

Gate:

```text
ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED
```

### 7. Migraciones futuras renumeradas antes de existir

```text
006_sales_commercial_facts.sql
007_sales_audit.sql
008_billing_context.sql
```

### 8. Retry actual no es attempt-bounded

Código real:

```text
attempts incrementa
retryCurrentClaim reprograma
no existe max attempts global
```

No crear budget/streak engine todavía.

Semántica correcta:

```text
terminal semantic error → fail/attention
429 → cooldown + same work
5xx/transport → durable retry sin inline loop
```

No llamar `bounded` a lo que hoy no lo es.

Gate:

```text
RETRY_SEMANTICS_TRUTHFUL
```

### 9. Historical 404/unavailable no auto-loop

Dentro del mismo audit run:

```text
exact GET terminal
→ attention/unavailable
→ no replacement work chain
```

Una futura reauditoría explícita puede volver a intentar; no polling automático.

### 10. Write-map vs MeliClient classification

MeliClient actual bloquea globalmente sólo `classification=WRITE`.

Contrato futuro:

```text
classification = AUTH | READ | WRITE
effect = MUTATION | ACTION | FINANCIAL | DESTRUCTIVE
```

Todo efecto mutante usa `classification=WRITE`.

Antes del primer write registrado debe existir test fail-closed para impedir que una etiqueta futura salte `meli_writes_enabled=false`.

No RemoteWriteEngine.

## Orden V3 actualizado

```text
V3-A
  A1 RED JSON NUMBER real
  A2 lossless decoder
  A3 no float + date_created/core 206
  A4 sale_fee/gross_price

V3-B
  Sales month
  observed/canonical audit
  capture validated first
  repair missing-only
  verify/confirm

V3-C
  F6A Task2 period-first
  migration 008 billing context

V3-D
  Financial sobre fixtures + captura Billing real
```

## Gates añadidos después del checkpoint anterior

```text
CURRENT_MONTH_NOT_FINAL
REMOTE_CAPTURE_COUNT_PROVEN
ORDER_DATE_CREATED_REQUIRED
ORDERS_206_CORE_USABLE
ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED
RETRY_SEMANTICS_TRUTHFUL
TERMINAL_HISTORICAL_NO_AUTO_LOOP
WRITE_CLASSIFICATION_FAIL_CLOSED
```

## Reanudación exacta

Continuar con **revisión final de coherencia del diseño V3**, especialmente:

1. verificar Billing/Financial contra los nuevos hechos Sales;
2. revisar que ninguna tabla/estado extra pueda eliminarse;
3. verificar contracts/gates de Work y write safety;
4. después presentar al usuario un resumen de decisiones V3 para aprobación;
5. sólo con aprobación explícita, abrir branch `impl/v3-exact-money-<date>` y ejecutar A1 RED.
