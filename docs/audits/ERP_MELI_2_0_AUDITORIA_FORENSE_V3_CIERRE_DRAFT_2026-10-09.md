# ERP MELI 2.0 — CIERRE DRAFT DE AUDITORÍA FORENSE V3

**Fecha:** 2026-10-09  
**Rama:** `audit/v3-forensic-redesign-20261009`  
**Estado:** AUDITORÍA/Diseño V3 suficientemente cerrados para revisión del usuario.  
**Implementación productiva:** BLOQUEADA hasta aprobación explícita.  
**F6A Task 2:** CONGELADO.

Este checkpoint complementa el ledger vivo `ERP_MELI_2_0_AUDITORIA_FORENSE_V3_2026-10-09.md` y actualiza su roadmap después de los últimos hallazgos/documentos.

## 1. Hallazgos nuevos de cierre

### H20 — El gate F4.1 de dinero exacto no prueba la frontera HTTP real

`SyncOrderDecimalPrecisionTest` usa importes adversariales como strings dentro del JSON fake. En cambio, los contratos reales de Mercado Libre representan normalmente importes como números JSON.

`MeliClient` usa `json_decode()` nativo y `SyncOrderHandler::decimal4()` todavía admite `float` y ejecuta `number_format((float)$value, ...)` como fallback.

Por tanto:

```text
string decimal exacto en test
≠
JSON NUMBER real preservado de forma exacta
```

V3 introduce el gate:

```text
REMOTE_NUMBERS_LOSSLESS
```

Antes de continuar Financial/Billing debe existir un RED con número JSON literal realista y una solución pequeña que preserve el lexema numérico como string para operaciones monetarias.

Este hardening pasa a ser **V3-A**, antes de históricos y F6A Task 2.

### H21 — Work payload no basta para demostrar cobertura histórica multipágina

`work_items` sólo representa una unidad de ejecución. `WorkRepository::retryCurrentClaim()` reprograma el mismo work pero no actualiza su payload; el dedupe activo tampoco es un almacén de evidencia de captura.

Una auditoría mensual necesita conservar entre páginas:

- total remoto inicial;
- IDs canónicos ya observados;
- detección de repetidos;
- cantidad de páginas;
- hash del conjunto;
- resultado final de cobertura.

Por eso V3 justifica, por primera vez con evidencia, un máximo de dos tablas específicas:

```text
sales_audit_runs
sales_audit_orders
```

No son otra cola/engine; son evidencia durable del slice Sales y usan Work/MeliClient existentes.

### H22 — La frontera remota de Orders se desacopla de la frontera de negocio

La API documenta granularidad horaria de filtros pero no deja suficientemente explícita la inclusividad exacta `from/to`.

V3 evita depender de esa ambigüedad:

```text
mes canónico America/Bogota
→ convertir a UTC
→ query remota con guard-band ±1 hora
→ normalizar date_created exacto
→ filtro local [from,to)
```

El guard-band pertenece al transporte/auditor, no a la definición del mes.

### H23 — “Cobertura Sales” significa cobertura de una fuente declarada

Seller `/orders/search` excluye canceladas. Por tanto:

```text
expected_remote - local = faltantes reparables
local - expected_remote ≠ corrupción automática
```

No borrar órdenes locales porque una captura seller search posterior no las liste.

### H24 — Pack se scopea por cuenta; no se crea tabla sales todavía

Pack puede agrupar órdenes de sellers distintos y la integración sólo puede ver las autorizadas.

V3 usa una identidad lógica derivada:

```text
company + account + pack_id
fallback legacy: company + account + order_id
```

La tabla `sales` queda descartada por ahora por KISS.

### H25 — Billing CLOSED no equivale a inmutable para siempre

La documentación vigente admite excepciones/ajustes posteriores. V3 conserva:

- period-first;
- cache local;
- revalidación explícita y de bajo ruido;
- upsert idempotente;
- no borrar evidencia previa;
- late adjustment auditable.

No crear scheduler Billing dedicado.

## 2. Entregables V3 creados

| Entregable | Estado |
|---|---|
| Ledger forense V3 | ACTUALIZADO + este checkpoint |
| `ERP_MELI_2_0_MATRIZ_ERP1_ERP2.md` | DRAFT COMPLETO |
| `ERP_MELI_2_0_MATRIZ_API_MERCADOLIBRE.md` | DRAFT COMPLETO |
| `ERP_MELI_2_0_WRITE_MAP_V3.md` | DRAFT COMPLETO; writes OFF |
| `api/MERCADOLIBRE_API_SNAPSHOT_V3_2026-10-09.md` | COMPLETO para módulos actuales/futuros inmediatos |
| `fixtures/financial_real_cases_v3.json` | COMPLETO business-level sanitizado |
| `specs/ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md` | DRAFT COMPLETO |
| `plans/ERP_MELI_2_0_PLAN_MAESTRO_V3.md` | DRAFT COMPLETO |

## 3. Roadmap V3 actualizado

| Bloque | Estado |
|---|---|
| V3-1 ERP1 históricos/Financial/fechas/meses | ✅ suficiente para diseño |
| V3-2 importer vs auditor/repair | ✅ suficiente para diseño |
| V3-3 contrato oficial Sales/orders | ✅ suficiente para diseño; smoke real pendiente |
| V3-4 Billing/history/partial/rate-limit | ✅ suficiente para diseño; captura MCO pendiente |
| V3-5 matriz fecha/timezone | ✅ congelada en Spec V3 |
| V3-6 gap ERP2 F4/F6 | ✅ cerrado para plan |
| V3-7 casos reales → fixtures | ✅ business fixtures creados |
| V3-8 write map | ✅ creado; writes OFF |
| V3-9 snapshot API curado | ✅ creado y anclado a ApiMercadolibre commit |
| V3-10 Especificación Maestra V3 | ✅ DRAFT creado |
| V3-11 Plan Maestro V3 | ✅ DRAFT creado |
| V3-12 revisión/aprobación usuario | ⏳ PENDIENTE |

## 4. Orden productivo propuesto después de aprobación

```text
V3-A REMOTE_NUMBERS_LOSSLESS
→ V3-B Sales month / historical coverage
→ V3-C F6A Task 2 period sync
→ V3-D F6B Financial
→ F7+ roadmap
```

### Motivo del cambio de orden

No es seguro construir Billing/Financial sobre una frontera JSON que aún puede materializar dinero real como `float`.

## 5. Cambios productivos realizados durante la auditoría

```text
NINGUNO
```

No se modificó:

- `app/`;
- `database/`;
- `config/`;
- `bin/`;
- tests productivos;
- despliegue;
- remote writes.

Todo el bloque V3 actual es documentación/fixture de diseño bajo `docs/`.

## 6. Validaciones reales todavía pendientes

No impiden revisar/aprobar el diseño, pero bloquean sus gates reales correspondientes:

1. smoke `/orders/search` MCO con guard-band y frontera mensual;
2. GET exacto de una orden >12 meses sólo si existe ID real conocido;
3. captura Billing MCO sanitizada para cardinalidades `sales_info/items_info/shipping_info`;
4. comprobar si un `detail_id` existente muta o ajustes aparecen como nuevos detalles;
5. OAuth app dedicada real;
6. Hostinger/cron reales;
7. branch protection antes de integración productiva.

No se inventará arquitectura para cubrir esas incertidumbres.

## 7. Freeze de aprobación

Hasta aprobación explícita del usuario:

```text
NO product code
NO F6A Task 2
NO migrations
NO merge
NO deploy
NO remote writes
```

Una vez aprobado, el primer bloque será **V3-A Task A1 RED**, no Billing directamente.
