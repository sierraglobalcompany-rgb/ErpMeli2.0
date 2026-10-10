# ERP MELI 2.0 — AUTHORITY ACTUAL / KISS V3.3

**Fecha:** 2026-10-10  
**Estado:** APROBADA PARA EJECUCIÓN POR MICROBLOQUES  
**Base funcional GREEN verificada:** `364256b1cc9135f33c780005c398c60d95e5882a`  
**Rama activa:** `impl/v3-b-sales-audit-20261010`  
**Remote writes:** OFF  
**REAL_MELI_HTTP normal:** `0`  
**F6A Task 2:** BLOQUEADO hasta C0 real sanitizado.

Esta es la autoridad activa de arquitectura/contratos. Git conserva la historia; el árbol actual conserva la verdad actual.

---

# 1. Orden de autoridad

Si hay contradicción:

```text
1. código/schema real del branch activo
2. tests/CI del mismo SHA
3. docs/CURRENT_CHECKPOINT.md
4. este ERP2_AUTHORITY.md
5. decisiones explícitas recientes del usuario
6. README.md maestro
7. handoffs/planes históricos
8. inferencias
```

Para contratos externos:

```text
documentación oficial vigente
+ evidencia API real controlada
> nuestras suposiciones
```

Nunca convertir una inferencia en contrato.

---

# 2. Leyes de oro

Prioridad:

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

Reglas vinculantes:

1. No persistir datos derivables sin necesidad real.
2. No crear abstracción sin segundo consumidor real.
3. No crear tabla sin query, integridad o lifecycle demostrado.
4. No crear queue, scheduler, retry, repair o recovery engine por dominio.
5. No convertir constantes técnicas en switches.
6. No usar `float` para dinero exacto.
7. No usar timezone del servidor como verdad de negocio.
8. No afirmar cobertura que la fuente no demuestra.
9. Remote writes nacen y permanecen apagados hasta F16 + autorización explícita.
10. No mantener rutas `old`, `legacy`, `new`, `v2`, `final` como evolución normal.
11. Cuando una implementación reemplaza otra, buscar referencias/tests/contratos y borrar lo superseded.
12. Git conserva historia; README/Authority/checkpoint conservan sólo verdad útil vigente.

---

# 3. Budget arquitectónico

Presupuesto deliberadamente pequeño:

```text
1 PHP/Slim app
1 repositorio
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
repair engine
command/event bus
financial ledger
generic historical engine
adaptive rate engine
campaign engine
```

KISS significa menor complejidad neta, no mínimo número de clases a cualquier costo.

---

# 4. Método obligatorio

```text
problema real
→ evidencia
→ DELETE/SIMPLIFY/REUSE/MERGE check
→ RED
→ confirmar causa del RED
→ GREEN mínimo
→ QA completa
→ noise audit
→ checkpoint
→ STOP o un solo microbloque siguiente
```

No confundir:

```text
código escrito
≠ tests verdes
≠ API real validada
≠ producción validada
```

Crear checkpoint cada 1–2 microbloques cuando el contexto esté creciendo.

---

# 5. Work

Tabla única: `work_items`.

Estados permitidos:

```text
pending
running
done
failed
```

Orden:

```text
available_at, id
```

Sin priority.

Capacidades aceptadas:

- active dedupe;
- claim token;
- bounded retry;
- defer;
- crash recovery;
- un solo runner;
- terminal cleanup.

## Retry

`retryCurrentClaim` consume intento. Usos actuales/previstos:

- 5xx;
- transport;
- futuro Billing 206.

Máximo automático pequeño/fijo. Al agotarse: `failed`.

## Defer

`deferCurrentClaim` no consume presupuesto neto del item.

Usos:

- 429;
- cooldown;
- parent REPAIR esperando child.

## Crash

```text
running recuperado + attempt < cap → pending
running recuperado + attempt >= cap → failed
```

## Retención

```text
done/failed >30 días → purge
```

Work es ejecución, **no historial ni verdad durable de negocio**.

---

# 6. Cron

Sólo:

```text
bin/work.php
bin/cleanup.php
```

Objetivo cuando Hosting esté certificado:

```text
work.php → cada minuto
cleanup.php → diario
```

Mantener un named lock global. `maxItems`/`maxSeconds` son constantes de código, no switches UI.

No cron por Sales/Billing/Financial. No scheduler table.

---

# 7. Mercado Libre core / OAuth / rate safety

ERP2 usa una aplicación Mercado Libre dedicada, separada de ERP1.

No migrar refresh tokens de ERP1.

Núcleo único:

- `MeliClient`;
- operation registry;
- OAuth PKCE/state;
- tokens cifrados;
- refresh lock/reread;
- pacing;
- cooldown durable;
- api usage aggregate.

Clasificaciones:

```text
READ
AUTH
WRITE
```

Unknown/missing classification:

```text
BLOCK
```

El método HTTP por sí solo no decide mutación.

## 429

```text
register cooldown
→ Work defer
```

No consume retry budget. No retry inline. No switch 429. No quota engine por account. No workers paralelos.

---

# 8. Remote writes

Siempre OFF hasta F16 y autorización explícita.

`meli_writes_enabled` puede permanecer como fuse interno, pero antes de F16 no debe existir UI/POST capaz de encenderlo.

No habilitar por iniciativa del agente:

- publicaciones;
- precios;
- inventario remoto;
- mutaciones de órdenes;
- otras escrituras Mercado Libre.

---

# 9. Valores remotos exactos

## JSON

`orders.get` y futuro `billing.period.details` necesitan preservación lossless de JSON NUMBER cuando valores exactos se persisten.

Patrón:

```text
json_validate
→ scan lexical NUMBER fuera de strings
→ preservar lexema
→ json_decode nativo
```

`orders.search` usa decode nativo + `JSON_BIGINT_AS_STRING`; su contrato de auditoría necesita IDs grandes pero no dinero decimal exacto.

## Dinero

Nunca `float` para dinero exacto. Persistencia comercial usa `DECIMAL` y overflow explícito.

## Fechas

Timestamp remoto canónico requiere:

```text
Z
ó
±HH:MM
```

Sin zona explícita: fail closed.

Persistir instantes en UTC.

Business month nunca depende de timezone PHP/MariaDB/Hostinger.

---

# 10. Sales time

Fuente canónica actual:

```text
order.date_created
```

Histórico soportado actualmente:

```text
site_id=MCO → America/Bogota
```

Otro site sin contrato explícito: fail closed para certificación mensual.

Mes canónico:

```text
[first local day 00:00, next local month 00:00)
```

Search remoto usa guard-band UTC ±1h. Membership final se decide localmente desde `date_created` exacto.

---

# 11. Sales Work

Work types actuales/objetivo:

```text
order.sync
sales.audit
```

`orders.reconcile` fue reemplazado y eliminado.

No crear:

```text
sales.audit.capture
sales.audit.repair
sales.audit.verify
sales.audit.confirm
```

El mismo `sales.audit` se enruta por el estado durable de `sales_audit_runs`.

Flujo objetivo:

```text
CAPTURE A
→ VALIDATE
→ REPAIR faltantes
→ VERIFY local
→ CONFIRM independiente B
→ valid relativo a seller-search
```

---

# 12. Sales Audit — datos durables

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

Estados permitidos:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

No proliferar estados técnicos.

## `sales_audit_orders`

Contrato actual GREEN:

```text
audit_run_id
capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
external_order_id
remote_date_created

PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
```

No surrogate id, `in_period`, page table, repair table, confirm table ni history table.

A y B conviven en la misma evidencia durable. Membership se deriva de `remote_date_created`.

Baseline futuro: conservar el más reciente válido; evidencia superseded equivalente puede podarse por reemplazo, no por edad ciega. Si aparece diferencia, conservar baseline previo + run attention hasta resolver.

---

# 13. Sales Audit — CAPTURE A

CAPTURE A válido exige:

- run/company/account coherentes;
- account conectado;
- seller/source contract conocido;
- `remote_total` estable;
- offset coherente;
- IDs exactos y únicos dentro de A;
- `date_created` válido y zoned;
- sin result/page malformed;
- sin página vacía no terminal;
- sin página corta no terminal;
- observed count A coherente con remote total;
- source horizon soportado.

Una página por Work.

CAPTURE no encola `order.sync` directamente.

## Source horizon GREEN — K6a-1

Seller Orders Search sólo soporta aproximadamente 12 meses.

Si el período queda fuera de la cobertura soportada:

```text
run → unavailable
Work → done
antes de OAuth
antes de HTTP
sin evidencia falsa
```

Nunca `total=0 → completo` fuera del horizonte.

## Short non-terminal GREEN — K6a-2

Mientras no exista contrato oficial fuerte de página siempre llena:

```text
count(results) < paging.limit
AND offset + limit < total
→ fail closed
```

No inferir offsets faltantes. No pagination engine.

## Canonical A fingerprint

Se deriva de observaciones A cuyo `remote_date_created` cae en el mes canónico:

```text
canonical_count
SHA-256(sorted canonical external_order_id)
```

Guard-band queda fuera del set canónico.

---

# 14. Sales Audit — REPAIR / VERIFY

Post-CAPTURE A:

```text
canonical A missing existe
→ repairing
→ una continuación sales.audit

no missing
→ confirming
```

REPAIR:

- recalcula gap real;
- obtiene un solo candidato determinista;
- `ORDER BY external_order_id LIMIT 1`;
- encola/reutiliza exactamente un `order.sync`;
- parent se defer mientras child está pending/running;
- no procesa segundo candidato en el mismo paso.

Child terminal + gap persistente:

```text
NO recrear automáticamente
→ run attention
→ parent termina
```

VERIFY local:

```text
no canonical A gap
→ repairing → confirming
```

sin estado `verifying`.

Todos los queries de repair/verify están explícitamente anclados a `capture_pass='A'`, para impedir que futura evidencia B contamine reparación.

---

# 15. Sales Audit — primitives A/B GREEN

Contrato GREEN K6b-2:

```text
recordObservation(runId, orderId, dateCreated, capturePass='A')
observationCount(runId, capturePass='A')
canonicalFingerprint(runId, window, capturePass='A')
```

Reglas:

- A es default para compatibilidad del CAPTURE existente;
- B puede registrar el mismo order ID independientemente;
- conteos A/B son independientes;
- fingerprint A/B es independiente;
- pass distinto de A/B falla cerrado;
- `persistCanonicalFingerprint()` persiste A y reutiliza `canonicalFingerprint`;
- no existe `ConfirmRepository` separado.

---

# 16. Sales Audit — CONFIRM objetivo, aún no runtime

Primera certificación de mes cerrado:

```text
capture A
→ repair
→ verify local
→ capture B independiente
→ same canonical count/hash
→ valid
```

Esto demuestra repetibilidad respecto de seller-search y su contrato conocido, **no snapshot absoluto de todo Mercado Libre**.

Diseño KISS congelado:

1. B usa el mismo `sales.audit`.
2. B persiste `capture_pass='B'` en la misma tabla.
3. A permanece intacto durante B.
4. No agregar `confirm_count` ni `confirm_hash` por defecto.
5. Al terminal B, derivar count/hash con `canonicalFingerprint(...,'B')`.
6. Comparar directamente contra `canonical_count/set_hash` de A.
7. Primer `remote_total` de B puede vivir en payload de Work si resulta suficiente; no persistir business column sólo para ejecución.
8. mismatch → durable `attention`.
9. igualdad A/B es necesaria antes de `valid`.

Rechazado sin nueva evidencia:

```text
second audit run
A<->B relation table
confirm repository
confirm engine
history table
second queue
new Work type
new Work status
```

Estado actual: `confirming` existe durablemente, pero `SalesWorkProcessor` todavía no ejecuta CONFIRM B. Eso se abre sólo mediante RED específico.

---

# 17. Sales source semantics

Seller Orders Search:

- horizonte aproximado 12 meses;
- como seller filtra canceladas;
- no equivale al universo histórico absoluto.

Por tanto:

```text
valid
```

significa:

> consistente/verificado respecto de seller-search y del contrato conocido de ese run.

No significa:

> garantía absoluta de todas las órdenes históricas posibles de Mercado Libre.

Fuentes históricas alternativas futuras (ERP1, export oficial, import confiable) se tratan como contratos separados; no se mezclan silenciosamente dentro del seller-search normal.

---

# 18. Order Sync

`order.sync` mantiene hechos de orden individuales.

Nueva respuesta API requiere core usable y timestamps zoned.

Errores 404/permanentes no generan retry infinito.

Cuando exact GET esperado por Sales Audit falla permanentemente y el gap persiste:

```text
attention / unavailable según contrato final
```

Nunca transformar fallo en período completo.

Commercial fact probado pendiente de schema/persistencia:

```text
order_items.sale_fee
```

`gross_price` permanece DEFER hasta consumidor/caso probado.

---

# 19. Webhook

Webhook es señal, no business history.

Target:

```text
validate topic/app
→ resolve unique connected account
→ extract order id
→ enqueue order.sync
→ ack rápido
```

`webhook_events` sigue candidato a DELETE sólo después de búsqueda repo-wide que demuestre ausencia de consumidor real.

No guardar raw webhook. No archive table por inercia.

---

# 20. Debug DVR

Se conserva porque tiene responsabilidad real:

- sanitización;
- cap de almacenamiento;
- correlación;
- gzip;
- retention;
- export;
- manifest/checksums;
- seguridad filesystem;
- fail-safe.

```text
DEBUG OFF → cero writes DVR
```

No fusionarlo en mega-clase sólo para reducir file count.

---

# 21. Billing

Billing es period-first.

Tablas:

```text
billing_periods
billing_details
```

Operación:

```text
billing.period.details
```

Antes del handler real:

```text
C0 real sanitized MCO smoke
```

Debe demostrar:

- primera página;
- cursor siguiente;
- señal terminal real;
- forma real de `last_id`;
- 206;
- non-progress guard.

No inferir terminal por short/empty/missing-last-id sin evidencia.

Un futuro `billing.period.sync` procesa una sola página por Work.

Billing 206:

```text
no canonical page commit
no cursor advance
no caught_up
bounded retry
then attention
```

```text
caught_up != fiscal closed
```

F6A Task 2 permanece bloqueado hasta C0.

---

# 22. Billing context

Strict allowlist JSON. Conservar sólo relaciones útiles de charge/bonus, discount, sales, shipping e items.

Excluir PII, raw body, títulos/categorías y objetos desconocidos.

No promover `order_id`/`shipping_id` a columnas hasta que una query F6B lo demuestre.

---

# 23. Financial

No table ni ledger inicialmente.

Read model futuro:

```text
Orders operational facts
+ Billing billed facts
+ pack relationships
+ shipping evidence
→ reconciliation/read model
```

Reglas:

- nunca doble contar mismo concepto entre fuentes;
- shared pack charge una sola vez;
- no asumir `sum(billing_details) = official net`;
- separar billed facts, analytical value y official/reference value;
- late adjustment se re-sincroniza/reconcilia;
- no history table hasta demostrar mutación real del mismo detail.

---

# 24. Schema pre-release

ERP2 sigue pre-release.

Mientras no exista instalación persistente real que deba preservarse:

```text
editar 004_sales.sql en sitio
editar 005_billing.sql en sitio
```

No crear 006/007/008 sólo para historia de desarrollo.

Después del primer deploy real, migrations pasan a inmutables.

---

# 25. Cleanup

Un cleanup diario cubre sólo responsabilidades demostradas:

```text
Debug retention
Work terminal >30d
API usage >90d
```

Sales Audit no se poda por edad ciega. Baseline válido superseded puede podarse por reemplazo cuando exista lifecycle confirmado.

No archive tables sin necesidad real.

---

# 26. Switches

Visibles:

```text
AUTOMATION
DEBUG
```

`AUTOMATION OFF`:

```text
cron no procesa
pending permanece
webhook puede seguir encolando
```

Remote writes no es switch normal de usuario antes de F16.

No feature-flag forest.

---

# 27. Gates

| Gate | Estado |
|---|---|
| G1 REMOTE_TRUTH | PASS para boundary implementado + guards K6a |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS para Sales actual |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + source guards + A/B persistence/primitives GREEN; B runtime/compare/valid pendiente |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

Siempre CI normal:

```text
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

salvo smoke explícitamente autorizado.

---

# 28. Orden inmediato vigente

Dominio activo: G4 Sales Audit.

Cerrado y no reabrir sin nueva evidencia:

```text
CAPTURE A
canonical A fingerprint
post-capture decision
bounded one-child REPAIR
local VERIFY
seller-search horizon guard
short non-terminal page guard
A/B evidence identity
A/B record/count/fingerprint primitives
A-only repair isolation
```

Próximo comportamiento a abrir mediante RED:

```text
K6b-3 — confirming dispatch + primera página independiente B
```

Ese RED debe demostrar:

1. `sales.audit` con run `confirming` no cae como estado desconocido;
2. usa el mismo read path / `orders.search`;
3. evidencia sólo en pass B;
4. A queda intacto;
5. una página por Work;
6. conserva guards de source horizon y short-page;
7. no crea segundo Work type, engine, table o run.

No implementar todavía en el mismo salto:

```text
valid
baseline lifecycle
Billing Task 2
Financial
```

---

# 29. Stop conditions

No iniciar F6A Task 2 antes de C0.

No merge.
No deploy.
No remote writes.
No real ML batch salvo smoke sanitizado explícitamente autorizado.
No `valid` antes de completar CONFIRM B + comparación A/B.

Cada microbloque termina con QA/noise audit/checkpoint antes de continuar.
