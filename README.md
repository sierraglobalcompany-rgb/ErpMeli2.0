# ERP MELI 2.0 — README MAESTRO VIVO

> **Propósito:** ser el mapa maestro autocontenido del producto, la arquitectura, las reglas de ingeniería, los problemas que ERP2 debe resolver, el roadmap, los gates y el estado operativo necesario para continuar el proyecto sin reconstruir conversaciones antiguas.
>
> **No es un museo ni un changelog.** Git conserva la historia. Este README conserva la verdad útil vigente y reemplaza información superseded cuando cambian decisiones, contratos, hallazgos o el punto de continuidad.

**Proyecto:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Arquitectura:** modular monolith / vertical slices  
**Stack:** PHP 8.5 + Slim 4 + PDO/MariaDB + PHPUnit + PHPStan  
**Estado:** desarrollo activo, pre-release, remote writes OFF  
**Rama activa:** `impl/v3-b-sales-audit-20261010`

---

# 1. LEER ESTO PRIMERO

Antes de diseñar, programar, revisar o continuar ERP MELI 2.0:

```text
1. verificar branch/HEAD real en GitHub
2. leer AGENTS.md
3. leer docs/CURRENT_CHECKPOINT.md
4. leer docs/ERP2_AUTHORITY.md
5. leer este README maestro
6. si HEAD cambió, auditar sólo el delta desde el checkpoint
7. no preguntar “¿dónde quedamos?” si Git/checkpoint lo demuestran
```

## Orden de autoridad

Si hay contradicción:

```text
1. código/schema real del branch activo
2. tests/CI del mismo SHA
3. docs/CURRENT_CHECKPOINT.md
4. docs/ERP2_AUTHORITY.md
5. decisiones explícitas recientes del usuario
6. este README maestro
7. handoffs/planes/documentos históricos
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

# 2. VISIÓN DEL PRODUCTO

ERP MELI 2.0 es un ERP greenfield para operar y auditar integraciones con Mercado Libre de forma:

```text
correcta
simple
auditable
recuperable
eficiente
segura
```

ERP2 reemplaza progresivamente ERP1, pero **ERP1 es fuente de aprendizaje y evidencia, no blueprint arquitectónico**.

Objetivos principales:

1. importar y mantener hechos operativos de Mercado Libre sin pérdida de precisión;
2. auditar períodos de ventas sin declarar completitud falsa;
3. recuperar órdenes faltantes de forma acotada y segura;
4. separar claramente hechos de Orders, Billing y futuros cálculos Financial;
5. manejar OAuth, 429, retries y fallos remotos sin loops ni storms;
6. conservar evidencia suficiente para explicar discrepancias sin historiales infinitos;
7. operar multi-company / multi-account con aislamiento correcto;
8. crecer hacia Catalog, Inventory, ERP API, Invoicing, Provider y migración ERP1 sin convertir el núcleo en arquitectura grande;
9. llegar a remote writes sólo después de demostrar seguridad y con autorización explícita;
10. permitir que cualquier nuevo chat/agente recupere el proyecto desde Git + CI + checkpoint + README.

---

# 3. PROBLEMAS DE ERP1 QUE ERP2 NO DEBE ARRASTRAR

Aprendizajes históricos relevantes:

- reconciliadores complejos;
- demasiados estados técnicos;
- recovery difícil de razonar;
- backlogs históricos mezclados con trabajo actual;
- Billing order-by-order;
- problemas de límites mensuales y fechas;
- discrepancias importer/auditor/Financial;
- 404/429 storms;
- cierres mensuales que parecían completos y luego mostraban faltantes;
- pack/shared-cost difíciles de reconciliar;
- Financial difícil de auditar;
- lógica dependiente del timezone del servidor;
- mecanismos wakeup/claim con estados incompatibles;
- verdad de negocio dependiente de filas técnicas temporales.

Caso que motivó V3:

```text
se conciliaba un mes
→ parecía completo
→ auditoría posterior encontraba una orden faltante
  o una orden asignada al mes equivocado
```

ERP2 debe evitar específicamente:

```text
vacío = completo
Work terminal = verdad de negocio
retry infinito = recuperación
más estados = más seguridad
más engines = más robustez
```

Regla de verdad mensual:

> Si un período cerrado se declara válido respecto de una fuente y contrato definidos, repetir la misma auditoría debe producir el mismo conjunto salvo cambio remoto explícitamente observable.

---

# 4. LEYES DE ORO

Vinculantes para usuario técnico, ChatGPT, Codex, Antigravity o cualquier agente.

## 4.1 Prioridad

```text
Correcto
→ Simple
→ Estable
→ Mantenible
→ Eficiente
→ Escalable
```

La escalabilidad futura no justifica complejidad presente sin evidencia.

## 4.2 KISS obligatorio

Antes de aceptar una solución:

```text
¿es correcta?
¿es KISS?
¿es simple?
¿está optimizada para el problema real?
¿es eficiente?
¿puede hacerse con menos piezas?
```

## 4.3 Orden obligatorio de decisión

```text
DELETE
→ SIMPLIFY
→ REUSE
→ MERGE
→ EXTEND
→ ADD
```

Antes de agregar clase, helper, tabla, columna, estado, flag, queue, cron, repository, engine, documento o test:

```text
¿sirve hoy?
¿tiene consumidor real?
¿puede derivarse?
¿duplica algo?
¿puede fusionarse?
¿puede eliminarse sin perder una garantía?
```

## 4.4 Git conserva historia

No mantener código/documentos muertos “por si acaso”.

Si una implementación reemplaza otra:

```text
buscar referencias
→ ejecutar tests
→ revisar contratos
→ DELETE lo superseded
```

## 4.5 Prohibido parche sobre parche

Cuando una solución reemplaza estructuralmente otra, revisar en el mismo ciclo:

- código viejo;
- tests transitorios;
- helpers duplicados;
- flags obsoletos;
- estados innecesarios;
- documentación superseded.

No usar rutas `old`, `legacy`, `new`, `v2`, `final` como evolución normal.

## 4.6 No generalizar sin segundo caso real

```text
un caso específico → solución local
segundo consumidor real + menor complejidad → considerar abstracción
```

## 4.7 Pregunta final de cada cambio

> ¿Podemos resolverlo correctamente dejando el sistema con menos cosas que entender y mantener?

---

# 5. BUDGET ARQUITECTÓNICO

Presupuesto deliberadamente pequeño:

```text
1 aplicación PHP/Slim
1 repositorio
1 MariaDB
1 tabla Work
1 WorkRunner
1 MeliClient
1 operation registry
1 cooldown mechanism
1 api_usage_daily aggregate
1 Debug DVR
```

No crear sin evidencia nueva:

```text
microservices
Kafka / RabbitMQ
segunda queue
queue por dominio
priority queue
scheduler por dominio
retry engine
repair engine
historical engine
recovery engine
event bus
command bus
financial ledger
adaptive rate engine
campaign engine
```

KISS = menor complejidad neta, no mínimo número de clases a cualquier costo.

---

# 6. METODOLOGÍA DE DESARROLLO

El proyecto se ejecuta en microbloques pequeños:

```text
A. problema real
B. evidencia
C. DELETE/SIMPLIFY/REUSE/MERGE check
D. RED
E. confirmar que RED falla por la causa correcta
F. GREEN mínimo
G. QA focal si aplica
H. QA completa
I. noise audit
J. checkpoint
K. STOP o abrir sólo un microbloque siguiente
```

No abrir varios frentes simultáneos.

No confundir:

```text
código escrito
≠ tests verdes
≠ API real validada
≠ producción validada
```

## Checkpoints preventivos

Crear checkpoint:

- cada 1–2 microbloques cuando el contexto crece;
- antes de cambiar de dominio;
- antes de gate externo;
- inmediatamente si aparece desviación documental importante.

## Edición segura

Preferir cambio local/pequeño sobre reescritura amplia de código.

Una regresión V3-A ya demostró que reemplazar archivos con contexto viejo puede reintroducir contratos eliminados.

---

# 7. ROLES

## Usuario

Decide producto, prioridades, alcance, merge, deploy, remote writes y aceptación de cambios sustantivos.

## ChatGPT

Coordina, audita, conserva contexto, diseña microbloques, implementa cuando las herramientas lo permiten, revisa Git/CI y protege KISS/noise.

Debe verificar repo/checkpoint antes de continuar.

## Codex / agente implementador

Cuando se use:

```text
leer AGENTS + README + checkpoint
→ inspeccionar código/tests
→ RED
→ GREEN mínimo
→ suite
→ SHA
```

No decide autónomamente nueva arquitectura.

## Antigravity

Puede actuar como segunda revisión, subordinado a:

```text
código + tests + documentación oficial + evidencia real
```

---

# 8. FOUNDATION / RUNTIME

Implementado históricamente en F0/F1:

- PHP/Slim/PDO;
- sesión/auth;
- CSRF;
- tenancy;
- settings;
- CI;
- schema core;
- runtime preflight.

Target de despliegue previsto:

```text
Public URL:
https://erpmeli.bodegadigitalmedellin.com/

Hostinger physical path:
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

Document root debe ser `public/`.

`storage/` debe permanecer fuera del web root.

Hostinger real pertenece a G8 y **no está certificado** sólo porque CI pase.

---

# 9. WORK ENGINE

Infraestructura única: `work_items`.

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

Capacidades actuales:

- active dedupe;
- claim token;
- bounded retry;
- defer;
- crash recovery;
- un solo runner.

## Retry

Consume intento:

- 5xx;
- transport;
- futuro Billing 206.

Máximo automático pequeño/fijo. No RetryEngine.

## Defer

No consume presupuesto neto del item.

Usos:

- 429;
- cooldown;
- parent REPAIR esperando child.

## Crash recovery

```text
running recuperado + attempt < cap → pending
running recuperado + attempt >= cap → failed
```

## Retención

```text
done/failed >30 días → purge
```

Work es ejecución, **no historial de negocio**.

---

# 10. CRON

Budget:

```text
bin/work.php
bin/cleanup.php
```

Objetivo cuando Hosting esté certificado:

```text
work.php cada minuto
cleanup.php diario
```

No cron por Sales/Billing/Financial.
No scheduler table.
`maxItems`/`maxSeconds` son constantes de código, no switches UI.

---

# 11. MERCADO LIBRE CORE / OAUTH / RATE SAFETY

ERP2 usa una aplicación Mercado Libre dedicada y separada de ERP1.

No migrar refresh tokens de ERP1.

Núcleo existente:

- un `MeliClient`;
- operation registry;
- OAuth PKCE/state;
- tokens cifrados;
- refresh lock/reread;
- pacing;
- durable cooldown;
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

El método HTTP por sí solo no decide si una operación es mutación.

## 429

```text
register cooldown
→ Work defer
```

No quema retry budget.
No retry inline.
No switch 429.
No quota engine por account.
No workers paralelos.

---

# 12. REMOTE WRITES

Siempre:

```text
OFF
```

hasta F16 y autorización explícita.

`meli_writes_enabled` puede existir como fuse interno, pero antes de F16 no debe existir UI/POST capaz de encenderlo.

No habilitar por iniciativa del agente:

- publicaciones;
- precios;
- inventario remoto;
- mutaciones de órdenes;
- otras escrituras Mercado Libre.

---

# 13. JSON, IDs, DINERO Y FECHAS

## Exactitud JSON

`orders.get` requiere preservación lossless de JSON NUMBER cuando valores exactos se persisten.

`LosslessJsonDecoder` no es parser JSON completo:

```text
json_validate
→ scan lexical NUMBER fuera de strings
→ preservar lexema
→ json_decode nativo
```

`orders.search` usa decode nativo + `JSON_BIGINT_AS_STRING` porque su contrato necesita IDs grandes, no dinero decimal exacto.

`billing.period.details` deberá usar boundary lossless al implementar sync.

## Dinero

Nunca `float` para dinero exacto.
Persistencia comercial usa `DECIMAL` y overflow explícito.

## Fechas

Timestamp remoto canónico requiere:

```text
Z
ó
±HH:MM
```

Sin zona explícita:

```text
invalid contract / fail closed
```

Persistir instantes en UTC.

Business month nunca depende de:

- PHP default timezone;
- MariaDB session timezone;
- Hostinger timezone;
- `created_at` local.

Actualmente:

```text
site_id=MCO → America/Bogota
```

Otro site sin contrato explícito:

```text
fail closed
```

---

# 14. SALES — OBJETIVO DE NEGOCIO

Sales debe responder auditadamente:

```text
¿Qué órdenes esperaba la fuente para este período?
¿Qué órdenes válidas existen localmente?
¿Cuáles faltan?
¿Se pudieron reparar?
¿La fuente produjo el mismo conjunto al confirmar?
¿Qué puede afirmarse honestamente sobre ese período?
```

No responde “completo” sólo porque una búsqueda terminó sin error.

Fuente temporal actual:

```text
order.date_created
```

Mes canónico:

```text
[first local day 00:00, next local month 00:00)
```

Search remoto usa guard-band UTC ±1 hora.
Membership final se decide localmente desde `date_created` exacto.

---

# 15. SALES WORK

Work types actuales/objetivo:

```text
order.sync
sales.audit
```

El antiguo `orders.reconcile` fue eliminado.

No crear:

```text
sales.audit.capture
sales.audit.repair
sales.audit.verify
sales.audit.confirm
```

como Work types separados.

El mismo `sales.audit` usa el estado durable de `sales_audit_runs`.

---

# 16. SALES AUDIT — MODELO DURABLE

Tablas justificadas:

```text
sales_audit_runs
sales_audit_orders
```

No crear:

```text
page table
repair table
retry table
confirm table
history table
child-state table
```

Estados permitidos del run:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

No proliferar estados.

## `sales_audit_orders` actual GREEN

```text
audit_run_id
capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
external_order_id
remote_date_created

PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
```

A y B conviven en la misma tabla y pueden contener el mismo order ID sin sobrescribirse.

No surrogate id, `in_period`, page table, repair table ni confirm table.

Membership se deriva de `remote_date_created`.

## Flujo objetivo

```text
CAPTURE A
→ VALIDATE
→ REPAIR faltantes
→ VERIFY local
→ CONFIRM B independiente
→ valid relativo a la fuente/contrato
```

Primera certificación de mes cerrado:

```text
capture A
→ repair
→ verify local
→ capture B independiente
→ same canonical hash/count
→ valid
```

Esto demuestra repetibilidad relativa a seller search, **no snapshot absoluto de todo Mercado Libre**.

---

# 17. SALES AUDIT — CAPTURE A

CAPTURE A:

- procesa una página por Work;
- valida run/company/account;
- exige account conectado;
- usa IDs exactos;
- exige `date_created` zoned;
- persiste observaciones `capture_pass='A'` por default;
- valida `remote_total` estable;
- no encola `order.sync` directamente;
- termina traversal sólo con evidencia durable coherente.

Página terminal exige:

```text
observationCount(run,'A') == remote_total
```

Si no:

```text
rollback
→ fail closed
```

## Canonical A fingerprint

Se deriva de observaciones A cuyo `remote_date_created` cae dentro del mes canónico:

```text
canonical_count
SHA-256(sorted canonical external_order_id)
```

Guard-band fuera del mes no forma parte del set canónico.

---

# 18. SALES AUDIT — SOURCE TRUTH K6a

Hallazgo revalidado en 2026-10-10:

1. seller Orders Search tiene horizonte aproximado de **12 meses**;
2. seller search filtra órdenes canceladas;
3. seller search no equivale al universo histórico absoluto de Mercado Libre.

## K6a-1 — mes fuera del horizonte — GREEN

```text
period fuera de cobertura soportada
→ run unavailable
→ Work done
→ antes de OAuth
→ antes de HTTP
→ cero evidencia falsa
```

Nunca:

```text
total=0 → completo fuera de horizonte
retry forever
```

## K6a-2 — página corta no terminal — GREEN

Mientras no exista garantía oficial fuerte de página no terminal siempre llena:

```text
count(results) < paging.limit
AND offset + limit < total
→ fail closed
```

No inferir posiciones faltantes.
No pagination engine.

## Semántica de `valid`

Cuando exista:

> consistente/verificado respecto de seller-search y del contrato conocido para ese run.

No significa garantía absoluta de todas las órdenes históricas posibles de Mercado Libre.

Fuentes históricas alternativas futuras —ERP1, export oficial, import confiable— se tratan como contratos separados y no se mezclan silenciosamente en seller-search.

---

# 19. SALES AUDIT — POST-CAPTURE / REPAIR / VERIFY

Decisión durable post-CAPTURE A:

```text
canonical A missing existe
→ repairing
→ una continuación sales.audit

no canonical missing
→ confirming
→ sin repair continuation
```

REPAIR:

- recalcula gap real;
- obtiene un solo candidato determinista;
- `ORDER BY external_order_id LIMIT 1`;
- encola o reutiliza exactamente un `order.sync`;
- parent se defer mientras child está pending/running;
- no procesa segundo candidato en el mismo paso.

Child terminal + gap persistente:

```text
NO recrear automáticamente
→ run attention
→ parent puede terminar
```

Así la verdad durable no depende de que Work terminal sobreviva cleanup.

VERIFY local:

```text
no canonical A gap
→ repairing → confirming
```

sin estado `verifying`.

Todos los queries de repair/verify están explícitamente limitados a:

```text
capture_pass='A'
```

B no puede contaminar reparación de A.

---

# 20. SALES AUDIT — PRIMITIVES A/B K6b

Schema A/B quedó GREEN en K6b-1.

Primitives GREEN K6b-2:

```text
recordObservation(runId, orderId, dateCreated, capturePass='A')
observationCount(runId, capturePass='A')
canonicalFingerprint(runId, window, capturePass='A')
```

Reglas:

- A es default para compatibilidad del CAPTURE existente;
- B puede registrar el mismo order ID de forma independiente;
- conteos A/B son independientes;
- fingerprints A/B son independientes;
- pass distinto de A/B falla cerrado;
- `persistCanonicalFingerprint()` persiste A y reutiliza `canonicalFingerprint`;
- no existe `ConfirmRepository` separado.

---

# 21. SALES AUDIT — CONFIRM B RUNTIME

Diseño KISS vigente y runtime GREEN hasta K6b-4:

1. B usa el mismo `sales.audit`.
2. `SalesWorkProcessor` enruta `confirming` al mismo `SalesAuditHandler` con pass B.
3. B persiste `capture_pass='B'` en `sales_audit_orders`.
4. A permanece intacto durante B.
5. B procesa una página por Work y reutiliza source-horizon + page-contract guards.
6. El primer `remote_total` de B vive sólo en payload de Work; no se añadió columna durable.
7. B no terminal encola exactamente una continuación `sales.audit` con `remote_total` estable.
8. Terminal B exige `observationCount(runId,'B') == remote_total`.
9. Terminal B deriva count/hash con `canonicalFingerprint(...,'B')`.
10. Compara B directamente contra `canonical_count/set_hash` durable de A.
11. mismatch A/B → `confirming -> attention` + Work `done` atómicamente.
12. A y B permanecen durables tras mismatch.
13. Igualdad A/B todavía no transiciona a `valid`; sigue fail-closed hasta K6b-5.

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
confirm_count / confirm_hash columns
```

---

# 22. ORDER SYNC

`order.sync` mantiene hechos de orden individuales.

Nueva respuesta API exige core usable y timestamps zoned.

Errores 404/permanentes no deben generar retry infinito.

Cuando exact GET esperado por Sales Audit falla permanentemente y el gap persiste:

```text
attention / unavailable según contrato final
```

Nunca transformar ese fallo en período completo.

Commercial fact demostrado y pendiente de alinear en schema:

```text
order_items.sale_fee
```

`gross_price` permanece DEFER hasta consumidor/caso probado.

---

# 23. WEBHOOK

Webhook es señal, no business history.

Target:

```text
validate topic/app
→ resolve unique connected account
→ extract order id
→ enqueue order.sync
→ ack rápido
```

`webhook_events` existe actualmente pero es candidato a DELETE si auditoría repo-wide demuestra que no tiene consumidor real y sólo duplica historia técnica.

No borrar a ciegas.
No raw webhook.
No archive table por inercia.

---

# 24. DEBUG DVR

Se conserva por responsabilidad real:

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

# 25. BILLING

Billing es **period-first**.

Tablas:

```text
billing_periods
billing_details
```

Operación:

```text
billing.period.details
```

## Gate C0 obligatorio

Antes de F6A Task 2 / handler real se necesita smoke MCO sanitizado que demuestre:

- primera página;
- next cursor;
- terminal real;
- forma real de `last_id`;
- comportamiento 206;
- non-progress guard.

No inferir cursor terminal a ciegas.

## Billing 206

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

Un futuro `billing.period.sync` procesa una sola página por Work.
No batch masivo.

---

# 26. BILLING CONTEXT

Strict allowlist JSON.

Conservar sólo relaciones útiles de:

- charge/bonus;
- discount;
- sales;
- shipping;
- items.

Excluir PII, raw body, títulos/categorías y objetos desconocidos.

No promover `order_id`/`shipping_id` a columnas hasta que una query real lo demuestre.

---

# 27. FINANCIAL

No crear ledger ahora.

Modelo futuro:

```text
Orders operational facts
+ Billing billed facts
+ pack relationships
+ shipping evidence
→ read model / reconciliation
```

Reglas:

- nunca doble contar mismo concepto entre Orders/Billing;
- shared pack charge una sola vez;
- no asumir `sum(billing_details) = official net`;
- separar billed facts, analytical value y official/reference value;
- late adjustment se re-sincroniza/reconcilia;
- no history table hasta demostrar mutación real del mismo detail.

G6 permanece cerrado hasta demostrarlo con datos/casos.

---

# 28. SCHEMA PRE-RELEASE

ERP2 sigue pre-release.

Mientras no exista instalación persistente real a preservar:

```text
editar 004_sales.sql en sitio
editar 005_billing.sql en sitio
```

No crear 006/007/008 sólo para preservar historia de desarrollo.

Después del primer deploy real:

```text
migrations → inmutables
```

---

# 29. CLEANUP Y RETENCIÓN

Cleanup diario actual:

```text
Debug retention
Work terminal >30d
API usage >90d
```

Sales Audit:

- no borrar evidencia por edad ciega;
- lifecycle se decide con baseline/CONFIRM;
- baseline válido superseded puede podarse por reemplazo, no por antigüedad genérica.

No archive tables sin necesidad real.

---

# 30. SWITCHES

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

No feature-flag forest.
Remote writes no es switch normal de usuario antes de F16.

---

# 31. GATES MACRO

| Gate | Objetivo | Estado actual |
|---|---|---|
| G1 REMOTE_TRUTH | contratos remotos exactos y honestos | PASS para boundary implementado + source guards K6a |
| G2 WORK_SAFETY | dedupe/retry/defer/recovery/cleanup seguros | PASS |
| G3 RATE_SAFETY | 429/cooldown sin storms | PASS para Sales actual |
| G4 SALES_AUDIT_TRUTH | captura/reparación/verificación/confirmación honestas | IN PROGRESS — A + guards + independent B traversal + mismatch→attention GREEN; equality→valid pendiente |
| G5 BILLING_CURSOR_TRUTH | cursor/terminal/206 probado | BLOCKED ON C0 |
| G6 FINANCIAL_NO_DOUBLE_COUNT | reconciliación sin duplicar conceptos | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | mutaciones remotas bloqueadas por defecto | PASS |
| G8 HOSTING_REALITY | runtime/cron/DB/HTTPS/document root reales | NOT CERTIFIED |

Siempre CI normal:

```text
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

salvo smoke real explícitamente autorizado.

---

# 32. ROADMAP MAESTRO

El roadmap se ajusta cuando evidencia real invalida una premisa.

## Foundation / seguridad cerradas o ampliamente implementadas

```text
F0/F1 Foundation
F2 Work
F3 Meli Core + OAuth
F4 Sales base
F4.1 hardening
F5 Debug DVR
V3-A exact boundary / Work safety
```

## Actual — G4 Sales Audit

Cerrado:

```text
CAPTURE A
→ canonical A fingerprint
→ post-capture decision
→ bounded REPAIR
→ local VERIFY
→ seller-search horizon guard
→ short-page guard
→ A/B evidence schema
→ A/B evidence primitives
→ A-only repair isolation
→ confirming dispatch
→ independent CONFIRM B pagination
→ terminal B count/fingerprint
→ mismatch A/B -> attention atomically
```

Pendiente:

```text
equality A/B -> valid
→ baseline lifecycle
→ start UX + active-run guard
```

## Después de G4

```text
Billing C0 real sanitized smoke
→ F6A Task 2 Billing handler
→ Financial read model
→ Catalog
→ Inventory
→ ERP API
→ Invoicing
→ Provider
→ ERP1 Migration
→ Shadow
→ Cutover
→ Remote Writes / F16
```

Catalog, Inventory, ERP API, Invoicing y Provider se detallan cuando exista alcance/evidencia real. No inventar arquitectura anticipadamente.

---

# 33. ESTADO OPERATIVO ACTUAL — 2026-10-10

> Para el SHA exacto de branch HEAD, leer GitHub y `docs/CURRENT_CHECKPOINT.md`. Este README describe el mapa y el último functional SHA verificado.

Rama activa:

```text
impl/v3-b-sales-audit-20261010
```

Último funcional totalmente GREEN verificado:

```text
3486ecc1783e40abf8321de5780535700253da2f
feat(v3-k6b4): mark terminal capture B mismatch attention
```

QA:

```text
RUN=38071706793
JOB=114270259722
PHP=8.5.11
PHPSTAN=0
PHPUNIT=205/205 PASS
ASSERTIONS=1419
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Cerrado hasta K6b-4

```text
B0        bigint-safe orders.search
B1        two-table Sales Audit schema
B2        MCO temporal contract + durable capturing run
B3a       durable observation
B3b1      atomic one-page CAPTURE
B3b2a     remote failure semantics
B3b2b1    sales.audit active
B3b2b2    old reconciler removed
B3c1      stable remote_total
B3c2      continuation
B3c3      durable terminal traversal integrity
B3d1/d2   canonical fingerprint + wiring
B3e1      deterministic local missing set
B3e2      guarded post-CAPTURE decision
B3e3      bounded REPAIR primitives
K1        missing-read prune + LIMIT 1
K2        runtime REPAIR through same sales.audit
K3        terminal child + gap -> durable attention
K4        CAPTURE -> repairing continuation
K5        local VERIFY -> confirming
K5b       CAPTURE fast-path -> repairing or confirming
K6a-1     source horizon -> unavailable before OAuth/HTTP
K6a-2     short non-terminal page -> fail closed
K6b-0     independent A/B evidence design proved
K6b-1     A/B schema GREEN
K6b-2     pass-aware record/count/fingerprint + A-only repair SQL GREEN
K6b-3     confirming dispatch + independent Capture B pagination GREEN
K6b-4     terminal B count/fingerprint + mismatch -> attention GREEN
```

---

# 34. PUNTO EXACTO DE REANUDACIÓN

Después de esta sincronización documental, el siguiente microbloque es:

```text
K6b-5 — terminal B equality -> valid RED ONLY
```

Objetivo del RED:

1. partir de un run durable `confirming` con A ya fingerprinted;
2. completar terminal B con evidence durable y count coherente;
3. demostrar `canonicalFingerprint(...,'B')` exactamente igual a A;
4. exigir `confirming -> valid` y Work `done` atómicamente;
5. conservar A y B;
6. no encolar continuación ni `order.sync`;
7. no crear tabla, columna, Work type, state técnico o engine nuevo.

En K6b-5 RED **no implementar todavía**:

```text
baseline lifecycle
start UX / active-run guard
Billing Task 2
Financial
merge/deploy
real ML batch
remote writes
```

Si el RED confirma una causa limpia, el siguiente turno podrá implementar el GREEN mínimo de igualdad→`valid`.

---

# 35. GAPS / DEUDA REAL ACTUAL

Sólo huecos vigentes:

| Gap | Severidad | Estado/acción |
|---|---:|---|
| Equality A/B → `valid` | Alta | siguiente RED K6b-5 |
| Baseline/valid lifecycle | Alta | después de equality→valid |
| Exact order 404 dentro de audit | Alta | clasificación final attention/unavailable pendiente |
| Start UX + duplicate active-run guard | Media | después del core G4 |
| `sale_fee` falta en schema/persistencia Sales | Alta para Financial | microbloque independiente antes de G6 |
| `webhook_events` posible write-only growth | Media | auditar consumidor/retention/delete |
| MariaDB session timezone | Media ops | certificar en G8; nunca business truth |
| Billing terminal cursor | Crítica Billing | C0 real smoke |
| Hostinger/runtime/main protection | Ops | G8 pendiente |

## Ya no reabrir sin evidencia nueva

- exact decimal boundary;
- bigint-safe orders.search;
- zoned timestamps;
- operation classification fail-closed;
- bounded Work retry;
- 429 defer sin attempt burn;
- old `orders.reconcile`;
- remote_total drift guard;
- terminal observed-count integrity;
- canonical A fingerprint;
- deterministic missing set;
- LIMIT 1 repair candidate;
- bounded one-child repair;
- terminal child recreation guard;
- durable parent attention;
- local VERIFY;
- CAPTURE fast-path a confirming;
- 12-month source horizon guard;
- short non-terminal page guard;
- A/B evidence identity;
- pass-aware observation count/fingerprint;
- A-only repair isolation;
- confirming dispatch through same `sales.audit`;
- independent Capture B traversal;
- B remote_total continuation guard;
- terminal B durable count/fingerprint;
- mismatch A/B → durable attention.

---

# 36. NO HACER AHORA

```text
NO Billing handler antes de C0
NO Financial ledger
NO new queue
NO RepairEngine
NO ConfirmEngine
NO RecoveryEngine
NO priority
NO nuevo cron
NO generic timezone engine
NO generic historical engine
NO event bus
NO webhook archive
NO remote writes
NO merge/deploy sin autorización
NO real Mercado Libre batch
NO migrar engines de ERP1
NO gross_price por inercia
NO borrar webhook_events sin auditoría
NO marcar Sales Audit valid antes de K6b-5 RED -> GREEN -> QA
```

---

# 37. DOCUMENTOS ACTIVOS

Leer preferentemente:

```text
AGENTS.md
README.md                       ← mapa maestro vivo
docs/CURRENT_CHECKPOINT.md      ← punto exacto de ejecución
docs/ERP2_AUTHORITY.md          ← leyes/contratos vinculantes
docs/meli-contracts-2026.md
docs/mercadolibre-app-erp2.md
docs/runtime-preflight.md
docs/hostinger-runtime-evidence.md
docs/f6-billing-period-first-contract.md
```

Handoffs/planes históricos sólo cuando falte contexto no consolidado.
No releer automáticamente toda la historia para empezar un microbloque.

---

# 38. PROTOCOLO PARA MANTENER ESTE README VIVO

Después de cada microbloque GREEN + checkpoint revisar si cambió:

```text
producto/objetivo
ley o arquitectura
contrato externo confirmado
módulo actual
estado funcional
roadmap
open gap
risk/gate
exact resume point
```

Si cambió:

1. actualizar la sección correspondiente;
2. **reemplazar** información superseded, no anexar otra versión paralela;
3. actualizar `CURRENT_CHECKPOINT.md` con SHA/CI/punto exacto;
4. actualizar `ERP2_AUTHORITY.md` sólo si cambió regla/contrato vinculante;
5. buscar documentos/tests/código que hayan quedado viejos;
6. dejar que Git conserve versiones anteriores.

## No guardar en README

No convertirlo en:

- diario de commits;
- transcript de chat;
- lista completa de todos los RED/GREEN históricos;
- archivo de patches;
- copia de logs;
- repositorio de propuestas rechazadas sin relevancia actual.

Debe contener:

```text
qué construimos
por qué
cómo debe funcionar
qué no debe volver a ocurrir
dónde estamos
qué falta
qué hacer exactamente al reanudar
```

## Auditoría de continuidad

Cada 1–3 microbloques preguntar:

```text
¿README refleja el producto real?
¿checkpoint refleja HEAD/CI real?
¿Authority sigue vigente?
¿hay sección superseded que podamos DELETE/MERGE?
¿un nuevo chat sabría continuar sin preguntar dónde íbamos?
```

Si alguna respuesta es no:

```text
STOP feature work
→ corregir continuidad
→ seguir
```

---

# 39. PROTOCOLO DE RECUPERACIÓN EN UN NUEVO CHAT

```text
1. abrir README
2. leer CURRENT_CHECKPOINT
3. leer Authority + AGENTS
4. fetch branch activo
5. verificar HEAD
6. revisar CI del último functional SHA y RED actual si existe
7. comparar sólo delta desde checkpoint
8. confirmar que no se mezcló main ni otro proyecto
9. retomar exactamente el microbloque documentado
10. RED → causa correcta → GREEN → QA → noise audit → checkpoint
```

Nunca reconstruir desde memoria si Git/checkpoint pueden demostrar el estado.

---

# 40. DEVELOPMENT

```bash
composer install
cp .env.example .env
php bin/migrate.php
composer qa
```

Tests/CI normales:

```text
APP_ENV=test
REAL_MELI_HTTP=0
```

Foundation bloquea tráfico real `api.mercadolibre.com` en `local` y `test`.

---

# 41. DEFINICIÓN DE ÉXITO

ERP MELI 2.0 no estará bien por tener muchas funciones o capas.

Estará bien cuando:

- hechos remotos importantes sean exactos y auditables;
- meses no puedan declararse completos sin evidencia suficiente;
- límites de fuente sean visibles y honestos;
- retries/429/404 no creen storms;
- Work sea pequeño y predecible;
- business truth sobreviva cleanup técnico;
- Billing tenga cursor probado;
- Financial no doble cuente;
- cada módulo se pueda razonar con pocas piezas;
- operación real Hostinger esté certificada;
- remote writes sólo se habiliten consciente y seguramente;
- un nuevo chat/agente recupere el proyecto desde README + checkpoint + Git sin inventar arquitectura.

---

# 42. RESUMEN EN UNA PANTALLA

```text
ERP MELI 2.0

GOAL:
ERP Mercado Libre correcto, auditable, simple y recuperable.

ARCHITECTURE:
modular monolith
1 app
1 DB
1 Work table
1 WorkRunner
1 MeliClient

GOLDEN LAW:
Correct → Simple → Stable → Maintainable → Efficient → Scalable
DELETE → SIMPLIFY → REUSE → MERGE → EXTEND → ADD
no patch-on-patch
Git stores history
remote writes OFF

ACTIVE DOMAIN:
Sales Audit V3-B

LAST FUNCTIONAL GREEN:
3486ecc1783e40abf8321de5780535700253da2f
205/205 tests
1419 assertions
PHPStan 0
REAL_MELI_HTTP=0

CURRENT TRUTH:
CAPTURE A + REPAIR + VERIFY GREEN
K6a source guards GREEN
A/B evidence schema/primitives GREEN
repair queries isolated to A
CONFIRM B traversal GREEN
terminal B count/fingerprint GREEN
mismatch A/B -> attention GREEN
valid equality path NOT IMPLEMENTED

NEXT:
K6b-5 terminal B equality -> valid RED ONLY

DO NOT START YET:
baseline lifecycle
Billing Task 2
Financial
merge/deploy
real batch
remote writes
```

---

# FIN
