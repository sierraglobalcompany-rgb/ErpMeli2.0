# V3-B — Sales Audit por microbloques

**Fecha:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Base:** V3-A checkpoint `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Autoridad:** `docs/ERP2_AUTHORITY.md`  
**Método:** RED → mínimo GREEN → QA completo → checkpoint por bloque.

## Objetivo

Reemplazar gradualmente `orders.reconcile` por un único flujo durable `sales.audit` que certifique cobertura histórica relativa a seller search sin afirmar snapshot absoluto:

```text
CAPTURE -> VALIDATE -> REPAIR -> VERIFY -> CONFIRM
```

CAPTURE nunca encola `order.sync`. REPAIR sólo encola faltantes demostrados.

## B0 — preentrada / hueco de auditoría

- Probar que `orders.search` preserva JSON integers mayores que `PHP_INT_MAX`.
- GREEN mínimo: decode nativo con `JSON_BIGINT_AS_STRING`.
- No usar `LosslessJsonDecoder` para `orders.search`.

## B1 — schema durable de evidencia

Sólo schema y tests.

En `004_sales.sql`, por estado pre-deploy, añadir en sitio:

### `sales_audit_runs`

```text
id
company_id
account_id
period_key
contract_version
status
remote_total
canonical_count
set_hash
started_at
completed_at
updated_at
```

Estados permitidos:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

### `sales_audit_orders`

```text
audit_run_id
external_order_id
remote_date_created
PRIMARY KEY(audit_run_id, external_order_id)
```

No añadir surrogate id, `in_period`, page table, repair table, history table ni JSON raw.

**STOP B1:** QA completo + checkpoint. No handler todavía.

## B2 — contrato temporal y creación de run

- Soportar histórico sólo para `site_id=MCO` → `America/Bogota`.
- Otro site: fail closed.
- Periodo canónico local `[primer día 00:00, primer día mes siguiente 00:00)`.
- Query remota con guard-band UTC ±1h.
- Crear run `capturing` durable.
- Sin llamada remota multipágina todavía.

**STOP B2:** QA completo.

## B3 — una página CAPTURE durable

- `sales.audit` procesa una sola página por Work.
- `orders.search` devuelve IDs + `date_created` zoned.
- Persistir todos los IDs observados de guard-band en `sales_audit_orders` con `remote_date_created` UTC.
- Validar paging/offset/total de la página.
- CAPTURE no encola `order.sync`.
- 429 → defer; 5xx/transport → retry bounded; contrato inválido → attention/fail-safe.

**STOP B3:** QA completo.

## B4 — cierre VALIDATE de captura

Captura válida exige:

- `remote_total` estable;
- offsets coherentes;
- IDs únicos;
- `date_created` válido y zoned;
- no página vacía no terminal;
- observed count coherente;
- canonical membership derivada localmente;
- SHA-256 de canonical IDs ordenados.

No REPAIR todavía.

**STOP B4:** QA completo.

## B5 — REPAIR missing-only

- Comparar canonical IDs capturados contra órdenes locales válidas del mismo company/account/mes.
- Encolar `order.sync` sólo para faltantes.
- Fan-out limitado usando el Work existente.
- Nunca borrar órdenes locales porque desaparezcan del remoto.

**STOP B5:** QA completo.

## B6 — VERIFY + CONFIRM

- Verificar cobertura local tras REPAIR.
- Para primera certificación de mes cerrado: captura B independiente.
- Si count/hash coinciden con captura A válida: `valid`.
- Diferencias → `attention`; conservar evidencia necesaria.
- Actual mes = snapshot válido, no cierre histórico.

**STOP B6:** QA completo.

## B7 — sustitución final de reconcile

- `SalesWorkProcessor` objetivo: `order.sync` + `sales.audit`.
- Retirar `orders.reconcile` sólo cuando todos sus consumidores/tests hayan sido migrados.
- No crear segundo motor histórico.
- Full QA + checkpoint V3-B.

## Fuera de alcance V3-B

- Billing handler / F6A Task 2.
- C0 Billing smoke.
- Financial read model.
- Merge o deploy.
- Real ML HTTP.
- Remote writes.
- UI histórica avanzada salvo necesidad probada posterior.
