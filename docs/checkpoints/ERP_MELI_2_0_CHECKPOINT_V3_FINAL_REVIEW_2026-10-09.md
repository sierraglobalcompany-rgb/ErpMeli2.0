# ERP MELI 2.0 — CHECKPOINT V3 FINAL REVIEW

**Fecha:** 2026-10-09  
**Rama:** `audit/v3-forensic-redesign-20261009`  
**Estado:** V3 forense/diseño cerrado para revisión del usuario; implementación sigue congelada.  
**HEAD de autoridad antes de este commit:** `bc04caac34e9d62e12c311057f619c72f3057b9e`  
**Último código productivo:** `4d144b9744160c3b1ddfa542af74040b262dec42` — F6A Task 1.

---

## 1. Fuente principal para reanudar

Leer primero y tratar como autoridad ejecutable:

```text
docs/specs/ERP_MELI_2_0_V3_AUTORIDAD_CONSOLIDADA_2026-10-09.md
```

Ese documento incorpora las decisiones vigentes de:

- Especificación Maestra V3;
- Plan Maestro V3;
- Addenda 01–05;
- auditoría ERP1;
- auditoría ERP2;
- documentación oficial Mercado Libre;
- casos financieros reales sanitizados.

Los documentos anteriores se conservan como rastro forense. No reconstruir decisiones combinándolos si la autoridad consolidada ya resuelve el punto.

---

## 2. Freeze

Hasta aprobación explícita del usuario:

```text
NO product code
NO migrations
NO F6A Task 2
NO merge
NO deploy
NO remote writes
```

No interpretar “continúa” en otro chat como aprobación implícita para código si el usuario todavía no ha aprobado V3 consolidado.

---

## 3. Resultado central de V3

ERP2 NO portará la maquinaria de ERP1.

Rescata únicamente propiedades demostradas:

```text
remote timestamps explícitos
month boundaries determinísticas
capture coverage verificable
missing-only repair
pack awareness
period-first Billing
exact decimals
source-aware Financial
late adjustment auditable
```

El diseño sigue siendo modular monolith + un solo Work/MeliClient.

---

## 4. Presupuesto productivo V3

### Exact JSON

```text
1 LosslessJsonDecoder
MeliClient + operation metadata mínimo
```

### Sales

```text
006_sales_commercial_facts.sql
+ order_items.sale_fee
+ order_items.gross_price
```

### Historical

```text
007_sales_audit.sql
sales_audit_runs
sales_audit_orders
```

`sales_audit_orders` incluye `in_period`; no tabla de páginas.

### Billing

```text
008_billing_context.sql
+ billing_details.legal_document_status
+ billing_details.context_json
1 BillingDetailNormalizer
1 BillingPeriodSyncHandler
```

### Work

Cuando Billing exista:

```text
SalesWorkProcessor → ApplicationWorkProcessor
```

No otro engine.

### Financial

```text
read model primero
NO tabla Financial de entrada
```

---

## 5. Orden exacto después de aprobación

```text
V3-A
A1 RED JSON NUMBER real
A2 LosslessJsonDecoder
A3 no float + date_created required + Orders 206
A4 sale_fee/gross_price
Gate A

V3-B
B1 SalesMonthWindow
B2 migration 007
B3 SalesAuditRepository
B4 capture-only
B5 capture validity adversarial
B6 missing-only repair
B7 repeatability/confirm
B8 minimal UI
Gate B

V3-C
C0 real Billing cursor terminal smoke MCO
C1 migration 008
C2 BillingDetailNormalizer
C3 BillingPeriodSyncHandler
C4 partial/cursor/idempotency/errors
C5 ApplicationWorkProcessor
C6 trigger/re-sync
Gate C

V3-D
Financial sobre fixtures + Billing evidence
Gate D
```

No saltar A ni C0.

---

## 6. Hallazgos que no se pueden perder

### Money

El test F4.1 anterior demostraba precisión con strings, no con JSON NUMBER real. `json_decode` nativo puede materializar decimal como float.

Gate:

```text
REMOTE_NUMBERS_LOSSLESS
```

### Dates

Sales month usa:

```text
order.date_created
UTC storage
America/Bogota projection
[month start, next month start)
```

Servidor no decide el mes.

### Search

Seller search:

- excluye canceladas;
- filtra por date_created cuando se pide;
- date sort del seller usa date_closed;
- filtro tiene granularidad horaria.

Usar guard-band ±1h + filtro local exacto.

### Historical

```text
CAPTURE → VALIDATE → REPAIR → VERIFY → CONFIRM
```

No emitir order.sync mientras la captura no sea válida.

Expected canónico se compara sólo contra órdenes locales con `date_created` válido en el mismo mes.

### Current month

```text
closed verified → Verificado
current snapshot → Al día hasta timestamp
```

Nunca final histórico.

### Retry

No existe max attempts global.

No crear retry engine.

Terminal semantics terminan; 429/5xx siguen flujo transitorio durable.

### Billing period

```text
caught_up != CLOSED
```

No monthly-period preflight en F6A.

No hardcode 12 months Billing.

### Billing cursor

Contrato probado:

```text
from_id → response.last_id → siguiente página
```

Condición terminal exacta NO está suficientemente documentada.

Por eso C0 smoke MCO es obligatorio antes del handler GREEN.

No asumir short-page ni empty-page terminal sin evidencia.

### Billing effect

No colapsar:

```text
document_type
detail_type
status
relations
```

Un CHARGE puede tener `status=BONUS_ON_BILL`.

F6A conserva hechos; F6B interpreta efecto con evidencia.

### Financial

```text
Orders/Shipping/Billing pueden describir el mismo concepto
→ reconcile
≠ sum all sources
```

No cross-source double count.

### Packs

Sale key lógico:

```text
company + account + pack_id
fallback company + account + order_id
```

No tabla `sales` todavía.

### Writes

```text
classification = AUTH|READ|WRITE
effect = MUTATION|ACTION|FINANCIAL|DESTRUCTIVE
```

Todo mutante sigue classification=WRITE y switch OFF hasta F16.

---

## 7. API canonical source

Repositorio:

```text
sierraglobalcompany-rgb/ApiMercadolibre
main=eeb0bc9d944e2fad58c13a47ccc7660413fa6193
```

Corpus útil:

```text
data/pages.jsonl
docs/markdown/
docs/temas/
```

`data/operations.jsonl` está vacío en esta captura; no depender de él.

Página Billing clave:

```text
Provisiones
source updated 2026-06-08
```

Confirma limit/from_id/last_id, pero no regla terminal textual inequívoca.

---

## 8. Fixtures

```text
docs/fixtures/financial_real_cases_v3.json
```

Dos casos sanitizados, sin PII:

- pack de dos productos/costos compartidos;
- shipping split comprador vs ML.

No son raw API payloads.

---

## 9. Git truth

Al checkpoint final, desde el último product commit F6A Task 1 no existen cambios en:

```text
app/
database/
config/
bin/
tests/
```

V3 es documental/gobierno/fixtures.

No afirmar que tests nuevos pasaron: todavía no existen cambios productivos V3.

---

## 10. Pendientes reales, no documentales

Antes de producción:

- aprobación usuario V3;
- implementar A/B/C/D por TDD;
- C0 Billing smoke MCO autorizado;
- OAuth dedicated app smoke;
- Hostinger runtime/cron/limits;
- branch protection;
- real sales boundary smoke;
- Billing/Financial MCO capture sanitizada;
- shadow/cutover posteriores.

---

## 11. Punto exacto de reanudación

Si el usuario aún NO aprobó V3:

```text
presentar resumen consolidado
→ obtener aprobación explícita
```

Si el usuario aprueba explícitamente:

```text
verify current Git heads
→ create isolated implementation branch/worktree
→ V3-A1 RED only
```

No empezar Billing directamente.
