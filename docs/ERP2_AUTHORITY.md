# ERP MELI 2.0 — AUTHORITY ACTUAL / KISS V3.2

**Fecha:** 2026-10-09  
**Estado:** APROBADA PARA EJECUCIÓN POR BLOQUES.  
**Base productiva:** `4d144b9744160c3b1ddfa542af74040b262dec42`  
**Remote writes:** OFF.  
**F6A Task 2:** BLOQUEADO hasta C0.

Esta es la única autoridad activa de ejecución. Git conserva la historia; documentos o caminos superseded no deben mandar sobre este archivo.

---

# 1. Leyes

```text
Correcto
→ Simple
→ Estable
→ Mantenible
→ Eficiente
→ Escalable
```

Toda pieza nueva o existente pasa por:

```text
DELETE → SIMPLIFY → REUSE → MERGE → EXTEND → ADD
```

Reglas:

1. No persistir datos derivables.
2. No crear abstracción sin segundo consumidor real.
3. No crear tabla sin query, integridad o ciclo de vida demostrado.
4. No crear queue, scheduler o retry engine por dominio.
5. No convertir constantes técnicas en switches.
6. No usar float para dinero exacto.
7. No usar timezone del servidor como verdad.
8. No afirmar cobertura que la fuente no demuestra.
9. Remote writes nacen apagados.
10. Git conserva historia; el árbol actual conserva verdad actual.

---

# 2. Budget arquitectónico

```text
1 PHP/Slim app
1 MariaDB
1 Work table
1 WorkRunner
1 MeliClient
1 operation registry
1 MeliCooldownRepository
1 api_usage_daily aggregate
1 Debug DVR
```

No crear sin evidencia nueva:

```text
microservices
brokers
priority queues
domain queues
domain schedulers
generic retry/recovery engines
command/event bus
financial ledger
generic historical engine
adaptive rate engine
campaign engine
```

---

# 3. Work

Tabla única: `work_items`.

Estados:

```text
pending
running
done
failed
```

Orden: `available_at, id`. No priority.

## Retry

`retryCurrentClaim` consume intento. Usos: 5xx, transport y Billing 206. Debe terminar con un máximo automático fijo y pequeño; al agotarse, `failed`.

## Defer

`deferCurrentClaim` no consume presupuesto del item. Uso principal: 429/cooldown. No crear RetryEngine.

## Crash

Running recuperado bajo máximo vuelve a pending; en máximo termina failed.

## Retención

Work terminal >30 días se elimina en cleanup. Work no es historial de negocio.

---

# 4. Cron

Sólo:

```text
bin/work.php
bin/cleanup.php
```

Objetivo operativo cuando Hosting lo permita:

```text
work.php → cada minuto
cleanup.php → diario
```

Mantener un named lock global. `maxItems` y `maxSeconds` son constantes operativas de código, no switches UI. No cron por módulo ni scheduler table.

---

# 5. 429

MeliClient conserva pacing conservador y `MeliCooldownRepository` durable por `app:family`, usando `Retry-After` cuando sea usable, backoff y jitter.

429:

```text
register cooldown
→ Work defer
```

No quema retries del item, no genera retry inline, no crea switch 429, no crea per-account quota engine ni workers paralelos.

---

# 6. Switches

Visibles ahora:

```text
AUTOMATION
DEBUG
```

`automation OFF`: no procesa Work automático; pending permanece; webhook puede seguir encolando; manual one puede seguir funcionando.

`debug OFF`: cero debug writes.

`meli_writes_enabled` permanece como defensa en DB, pero antes de F16 no debe existir UI ni POST administrativo capaz de ponerlo en 1.

Clasificaciones permitidas:

```text
READ
AUTH
WRITE
```

Unknown/missing classification: BLOCK. `WRITE` exige writes enabled. El método HTTP no decide seguridad.

---

# 7. Valores remotos

Dinero exacto nunca pasa por float.

`orders.get` y `billing.period.details` necesitan preservación lossless de JSON NUMBER. No implementar parser JSON completo: validar, escanear sólo tokens NUMBER fuera de strings, preservar el lexema y delegar a `json_decode` nativo.

`orders.search` usa native decode + `JSON_BIGINT_AS_STRING` porque el contrato de auditoría no necesita decimales monetarios de esa respuesta.

Una utilidad pequeña `MeliValueNormalizer` puede concentrar sólo:

```text
exact decimal
required timestamp
nullable timestamp
safe scalar/id
```

Fecha remota canónica requiere `Z` u offset explícito.

---

# 8. Sales time

Fuente: `order.date_created`.

Persistir UTC. Para histórico actual soportado:

```text
site_id=MCO → America/Bogota
```

Otro site sin contrato explícito: fail closed para certificación mensual.

Mes canónico:

```text
[first day 00:00, next first day 00:00)
```

Query remota: canonical UTC ±1h guard-band. Membership siempre se decide localmente desde `date_created` exacto.

---

# 9. Sales work

Work types objetivo:

```text
order.sync
sales.audit
```

`orders.reconcile` no permanece como motor paralelo; `SalesAuditHandler` lo reemplaza.

Flujo único:

```text
CAPTURE → VALIDATE → REPAIR → VERIFY → CONFIRM
```

CAPTURE no encola `order.sync`. REPAIR sólo faltantes y fan-out limitado.

---

# 10. Sales audit data

## `sales_audit_runs`

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

Estados:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

UI deriva:

```text
closed + valid → Verificado
current + valid → Al día hasta ...
```

## `sales_audit_orders`

```text
audit_run_id
external_order_id
remote_date_created
PRIMARY KEY(audit_run_id, external_order_id)
```

No surrogate id, `in_period`, page table, repair table ni history table. Membership se deriva de `remote_date_created`.

Baseline: conservar el más reciente válido; evidencia superseded equivalente se elimina. Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.

---

# 11. Sales capture contract

Captura válida requiere como mínimo:

- remote total estable;
- offset coherente;
- IDs únicos;
- `date_created` válido y zoned;
- sin resultado/página malformed;
- sin página vacía no terminal;
- sin error remoto pendiente;
- observed count coherente.

Hash: SHA-256 de canonical IDs ordenados.

Primera certificación de mes cerrado:

```text
capture A
→ repair
→ verify local
→ capture B independiente
→ same hash/count
→ valid
```

Esto demuestra repetibilidad relativa a seller search, no snapshot absoluto. No borrar órdenes locales por desaparición remota.

---

# 12. Order sync

Nueva respuesta API requiere core usable y timestamps zoned. Orders 206 puede persistir core sólo si el core requerido está completo.

Commercial fact probado ahora:

```text
order_items.sale_fee
```

`gross_price` queda DEFER hasta consumidor/caso probado.

---

# 13. Webhook

Webhook es señal. Target final:

```text
validate topic/app
resolve unique connected account
extract order id
enqueue order.sync
ack rápido
```

`webhook_events` es candidato a DELETE sólo después de búsqueda repo-wide que demuestre ausencia de consumidor. No guardar raw webhook.

---

# 14. Billing

Period-first. Dos tablas: `billing_periods`, `billing_details`. No child tables ni scheduler Billing.

`caught_up != CLOSED`.

Antes de handler real:

```text
C0 real sanitized MCO smoke
```

Debe demostrar señal terminal del cursor. No inferir terminal por short/empty/missing-last-id sin evidencia.

Un Work `billing.period.sync` procesa una sola página.

Billing 206:

```text
no canonical page commit
no cursor advance
no caught_up
bounded retry
then attention
```

---

# 15. Billing context

Strict allowlist JSON. Conservar sólo relaciones útiles de charge/bonus, discount, sales, shipping e items. Excluir PII, raw body, títulos/categorías y objetos desconocidos.

No promover `order_id`/`shipping_id` a columnas hasta que una query F6B lo demuestre.

---

# 16. Financial

No table ni ledger inicialmente. Read model desde Orders + Billing + packs + Shipping cuando exista evidencia.

Nunca sumar dos veces el mismo concepto entre fuentes. Shared pack charge una sola vez. No llamar “official net” a un cálculo local sin fuente explícita.

Late adjustment se re-sincroniza/reconcilia. No history table hasta demostrar mutación del mismo `detail_id`.

---

# 17. Schema pre-release

Antes de cambiar schema, confirmar si `004_sales.sql`/`005_billing.sql` ya requieren migración incremental real.

Si ERP2 sigue pre-release y no existe instalación persistente a preservar:

```text
editar 004_sales.sql en sitio
editar 005_billing.sql en sitio
```

No crear 006/007/008 sólo para preservar historia de desarrollo.

Después del primer deploy real, migrations pasan a inmutables.

---

# 18. Cleanup

Un cleanup diario cubre:

```text
Debug retention
Work terminal >30d
API usage >90d
```

Sales audits superseded se podan por reemplazo de baseline, no por edad ciega. No archive tables.

---

# 19. Gates

Ocho gates macro:

```text
G1 REMOTE_TRUTH
G2 WORK_SAFETY
G3 RATE_SAFETY
G4 SALES_AUDIT_TRUTH
G5 BILLING_CURSOR_TRUTH
G6 FINANCIAL_NO_DOUBLE_COUNT
G7 WRITE_FAIL_CLOSED
G8 HOSTING_REALITY
```

Siempre:

```text
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

salvo smoke explícitamente autorizado.

---

# 20. Orden inmediato

## A

1. RED lossless JSON literal.
2. Boundary lossless mínimo.
3. `MeliValueNormalizer` cuando tenga dos usos reales.
4. Strict timestamps.
5. Fail-closed operation classification.
6. Work retry bounded.
7. Work defer sin penalización.
8. Quitar writes toggle UI.
9. Work cleanup.

## B

Sales audit único.

## C0

Billing terminal cursor smoke.

## C

Billing handler.

## D

Financial read model.

---

# 21. Stop conditions

No iniciar F6A Task 2 antes de C0.
No merge.
No deploy.
No remote writes.

La implementación comienza por A1 RED y avanza RED → GREEN → QA → checkpoint por microtarea.
