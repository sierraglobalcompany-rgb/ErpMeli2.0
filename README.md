# ERP MELI 2.0 — README MAESTRO VIVO

> **Propósito de este README:** ser el mapa maestro autocontenido del producto, la arquitectura, las reglas de ingeniería, los problemas que ERP2 debe resolver, el roadmap, los gates y el estado operativo necesario para continuar el proyecto sin reconstruir conversaciones antiguas.
>
> **No es un museo ni un changelog.** Git conserva la historia. Este archivo conserva la verdad útil actual y se actualiza cuando cambian decisiones, hallazgos, contratos o el punto de continuidad.

**Proyecto:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Arquitectura:** modular monolith / vertical slices  
**Stack:** PHP 8.5 + Slim 4 + PDO/MariaDB + PHPUnit + PHPStan  
**Estado:** desarrollo activo, pre-release, remote writes OFF  
**Rama V3-B activa:** `impl/v3-b-sales-audit-20261010`

---

# 1. LEER ESTO PRIMERO

Antes de diseñar, programar, revisar o continuar ERP MELI 2.0:

```text
1. verificar branch/HEAD real en GitHub
2. leer AGENTS.md
3. leer docs/ERP2_AUTHORITY.md
4. leer docs/CURRENT_CHECKPOINT.md
5. leer este README maestro
6. si HEAD cambió, auditar sólo el delta
7. no preguntar "¿dónde quedamos?" si está documentado
```

## Orden de autoridad

Si hay contradicción:

```text
1. código real del branch activo + schema real
2. tests/CI del mismo HEAD
3. docs/CURRENT_CHECKPOINT.md
4. docs/ERP2_AUTHORITY.md
5. decisiones explícitas recientes del usuario
6. este README maestro
7. handoffs/planes/documentos históricos
8. inferencias
```

Para contratos externos:

```text
documentación oficial actual
+ evidencia API real controlada
> nuestras suposiciones
```

Un README correcto **no puede convertir una suposición en contrato**.

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

ERP2 reemplaza progresivamente ERP1, pero **ERP1 es una fuente de aprendizaje y evidencia, no un blueprint arquitectónico**.

Objetivos principales:

1. importar y mantener hechos operativos de Mercado Libre sin pérdida de precisión;
2. auditar períodos de ventas sin declarar completitud falsa;
3. recuperar órdenes faltantes de manera acotada y segura;
4. separar claramente hechos de Orders, Billing y futuros cálculos Financial;
5. manejar OAuth, 429, retries y fallos remotos sin loops ni storms;
6. conservar evidencia suficiente para explicar discrepancias sin crear historiales infinitos;
7. poder operar multi-company / multi-account con aislamiento correcto;
8. permitir crecimiento futuro de Catalog, Inventory, ERP API, Invoicing, Provider y migración ERP1 sin convertir el núcleo en una arquitectura grande;
9. llegar a remote writes sólo después de demostrar seguridad y con autorización explícita;
10. permitir que cualquier nuevo chat/agente recupere rápidamente el estado real desde Git + CI + checkpoint + este README.

---

# 3. PROBLEMAS REALES QUE ERP2 DEBE SOLUCIONAR

ERP1 dejó aprendizajes importantes que **no deben arrastrarse como implementación**.

Problemas observados históricamente:

- reconciliadores complejos;
- demasiados estados técnicos;
- recovery difícil de razonar;
- backlogs históricos mezclados con trabajo actual;
- Billing order-by-order;
- problemas de límites mensuales y fechas;
- discrepancias entre importer/auditor/Financial;
- 404/429 storms;
- cierres mensuales que parecían completos y después mostraban faltantes;
- pack/shared-cost difíciles de reconciliar;
- Financial difícil de auditar;
- lógica que podía depender de timezone del servidor;
- mecanismos de wakeup/claim con estados incompatibles;
- verdad de negocio dependiendo demasiado de filas técnicas temporales.

Caso histórico crítico que motivó V3:

```text
se conciliaba un mes
→ parecía completo
→ una auditoría posterior encontraba una orden faltante
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

> Si un período cerrado se declara válido respecto de una fuente y un contrato definidos, repetir la misma auditoría debe producir el mismo conjunto salvo un cambio remoto explícitamente observable.

---

# 4. LEYES DE ORO

Estas reglas son vinculantes para usuario técnico, ChatGPT, Codex, Antigravity o cualquier agente.

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

Si no, rediseñar antes de implementar.

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

No mantener código/documentos muertos "por si acaso".

Si una implementación reemplaza otra y ya no tiene consumidor:

```text
buscar referencias
→ ejecutar tests
→ revisar contratos
→ DELETE
```

## 4.5 Prohibido parche sobre parche

Cuando una solución nueva reemplaza estructuralmente una anterior, el mismo ciclo debe revisar:

- código viejo;
- tests transitorios;
- helpers duplicados;
- flags obsoletos;
- estados innecesarios;
- documentación superseded.

No crear rutas `old`, `legacy`, `new`, `v2`, `final` como forma normal de evolución.

## 4.6 No generalizar sin segundo caso real

```text
un caso específico → solución local
segundo consumidor real + menor complejidad → considerar abstracción
```

## 4.7 Pregunta final de cada cambio

> ¿Podemos resolverlo correctamente dejando el sistema con menos cosas que entender y mantener?

---

# 5. BUDGET ARQUITECTÓNICO

Presupuesto actual deliberadamente pequeño:

```text
1 aplicación PHP/Slim
1 repositorio
1 MariaDB
1 tabla Work
1 WorkRunner
1 MeliClient
1 operation registry
1 cooldown mechanism
1 agregado api_usage_daily
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

KISS no significa "una sola clase gigante". Significa **menor complejidad neta**.

---

# 6. METODOLOGÍA DE DESARROLLO

El proyecto se ejecuta en microbloques pequeños.

Flujo obligatorio:

```text
A. problema real
B. evidencia
C. DELETE/SIMPLIFY/REUSE/MERGE check
D. RED
E. confirmar que RED falla por la causa correcta
F. GREEN mínimo
G. QA focal
H. QA completa
I. noise audit
J. checkpoint
K. parar o abrir un solo microbloque siguiente
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

- cada 2–4 microbloques como máximo;
- antes si el contexto/chat crece;
- antes de cambiar de dominio;
- antes de un gate externo;
- inmediatamente si aparece una desviación documental importante.

## Edición segura

Preferir:

```text
patch/local edit
> reescritura completa de archivo
```

Una regresión real en V3-A demostró que reemplazar archivos completos con contexto viejo puede reintroducir contratos eliminados.

---

# 7. ROLES

## Usuario

Decide:

- producto;
- prioridades;
- alcance;
- merge;
- deploy;
- remote writes;
- aceptación de cambios sustantivos.

## ChatGPT

Actúa como:

- coordinador técnico;
- auditor;
- custodio de contexto;
- diseñador de microbloques;
- implementador cuando las herramientas lo permiten;
- revisor de Git/CI;
- guardián KISS/noise.

Debe verificar repo/checkpoint antes de continuar y no inventar contexto perdido.

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

Puede servir como segunda revisión, pero sus conclusiones están subordinadas a:

```text
código + tests + documentación oficial actual + evidencia real
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

Canonical deployment target actual:

```text
Public URL:
https://erpmeli.bodegadigitalmedellin.com/

Hostinger physical path:
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

Document root debe ser `public/`.

`storage/` debe permanecer fuera del web root.

Hostinger real todavía pertenece a G8 y no debe darse por certificado por CI.

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
- retry bounded;
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
- espera operacional, por ejemplo parent REPAIR esperando child.

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
bin/work.php      → frecuente
bin/cleanup.php   → diario
```

Objetivo operativo cuando Hosting esté certificado:

```text
work.php cada minuto
cleanup.php diario
```

No cron por Sales/Billing/Financial.

No scheduler table.

`maxItems` y `maxSeconds` son constantes operativas, no switches UI.

---

# 11. MERCADO LIBRE CORE / OAUTH / RATE SAFETY

ERP2 usa una aplicación Mercado Libre dedicada, separada de ERP1.

No migrar refresh tokens de ERP1.

Núcleo existente:

- un solo `MeliClient`;
- operation registry;
- OAuth PKCE/state;
- tokens cifrados;
- refresh lock/reread;
- pacing;
- durable cooldown;
- api usage aggregate.

Clasificaciones permitidas:

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

No crear:

- switch 429;
- quota engine por account;
- workers paralelos;
- adaptive rate engine.

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

`LosslessJsonDecoder` no es un parser JSON completo:

```text
json_validate
→ scan lexical NUMBER fuera de strings
→ preservar lexema
→ json_decode nativo
```

`orders.search` usa decode nativo + `JSON_BIGINT_AS_STRING` porque su contrato actual necesita IDs grandes, no dinero decimal exacto.

`billing.period.details` deberá usar boundary lossless cuando se implemente sync.

## Dinero

Prohibido usar `float` para dinero exacto.

Persistencia comercial debe respetar `DECIMAL` y overflow explícito.

## Fechas

Timestamp remoto canónico requiere:

```text
Z
ó
±HH:MM
```

Sin zona explícita:

```text
invalid contract
```

Instantes persistidos:

```text
UTC
```

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

Sales debe responder de forma auditable:

```text
¿Qué órdenes esperaba la fuente para este período?
¿Qué órdenes válidas existen localmente?
¿Cuáles faltan?
¿Se pudieron reparar?
¿La fuente produjo el mismo conjunto al confirmar?
¿Qué puede afirmarse honestamente sobre ese período?
```

No debe responder "completo" sólo porque una búsqueda terminó sin error.

Fuente temporal del contrato actual:

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

Work types objetivo y actuales:

```text
order.sync
sales.audit
```

No mantener motores paralelos.

El antiguo:

```text
orders.reconcile
```

fue eliminado.

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

# 16. SALES AUDIT — MODELO

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

## Flujo objetivo

```text
CAPTURE
→ VALIDATE
→ REPAIR sólo faltantes
→ VERIFY local
→ CONFIRM independiente
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

# 17. SALES AUDIT — CAPTURE

CAPTURE:

- procesa una página por Work;
- valida run/company/account;
- exige account conectado;
- usa IDs exactos;
- exige `date_created` zoned;
- persiste observaciones durables;
- valida `remote_total` estable;
- no encola `order.sync` directamente;
- termina traversal sólo cuando evidencia durable es coherente.

Página terminal requiere:

```text
COUNT(sales_audit_orders) == remote_total
```

Si no:

```text
rollback
→ fail closed
```

## Canonical fingerprint

Se deriva desde observaciones cuyo `remote_date_created` cae dentro del mes canónico.

Persistir:

```text
canonical_count
SHA-256(sorted canonical external_order_id)
```

Guard-band fuera del mes no forma parte del set canónico.

---

# 18. SALES AUDIT — POST-CAPTURE / REPAIR / VERIFY

Decisión durable post-CAPTURE actual:

```text
canonical missing existe
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

Así el resultado durable no depende de que Work terminal sobreviva al cleanup de 30 días.

VERIFY local actual:

```text
no canonical gap
→ repairing → confirming
```

sin crear estado `verifying`.

---

# 19. SALES AUDIT — CONTRATO DE FUENTE

Hallazgo crítico revalidado en 2026-10-10:

1. seller Orders Search tiene un horizonte aproximado de **12 meses**;
2. seller search filtra órdenes canceladas;
3. por ello seller search **no equivale al universo histórico absoluto de Mercado Libre**.

Consecuencia:

```text
valid
```

significa:

> consistente/verificado respecto de seller-search y del contrato conocido para ese run.

No significa:

> Mercado Libre garantiza que éstas son absolutamente todas las órdenes históricas posibles.

## Mes fuera del horizonte

Debe ser:

```text
unavailable
```

antes de OAuth/HTTP cuando el período solicitado está fuera de la cobertura soportada.

Nunca:

```text
vacío = completo
retry forever
```

Fuentes históricas alternativas futuras pueden incluir ERP1, export oficial o import confiable, pero **no se mezclan dentro del seller-search normal**.

## Página corta no terminal

Mientras no exista evidencia oficial fuerte de que una página no terminal siempre viene llena:

```text
count(results) < paging.limit
AND offset + limit < total
→ fail closed
```

No crear pagination engine.

---

# 20. ORDER SYNC

`order.sync` mantiene hechos de orden individuales.

Nueva respuesta API exige core usable y timestamps zoned.

Errores 404/permanentes no deben generar retry infinito.

Cuando un exact GET esperado por Sales Audit termina permanentemente y el gap persiste:

```text
attention / unavailable según contrato
```

Nunca transformar ese fallo en "período completo".

Commercial fact demostrado y pendiente de alinear en schema:

```text
order_items.sale_fee
```

`gross_price` permanece DEFER hasta que exista consumidor/caso probado.

---

# 21. WEBHOOK

Webhook es una señal, no business history.

Target:

```text
validate topic/app
→ resolve unique connected account
→ extract order id
→ enqueue order.sync
→ ack rápido
```

`webhook_events` existe actualmente pero está bajo auditoría.

Regla:

```text
si no tiene consumidor real y sólo duplica historia técnica
→ DELETE

si tiene utilidad real
→ bounded retention
```

No borrar a ciegas y no crear archive table.

---

# 22. DEBUG DVR

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

No fusionarlo en una mega-clase sólo para reducir número de archivos.

---

# 23. BILLING

Billing es **period-first**.

Tablas previstas/creadas:

```text
billing_periods
billing_details
```

Operación:

```text
billing.period.details
```

Conceptos actuales:

- BILL / CREDIT_NOTE;
- `from_id`;
- `limit`;
- `sort/order`.

## Gate C0 obligatorio

Antes de F6A Task 2 / handler real se necesita un smoke MCO sanitizado que demuestre:

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

No batch masivo.

---

# 24. FINANCIAL

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

- nunca doble contar el mismo cargo entre Orders y Billing;
- shared pack charge una sola vez por pack/concept;
- no asumir `sum(billing_details) = official net`;
- separar billed facts, analytical value y official/reference value cuando exista fuente explícita;
- no history table de late adjustments hasta demostrar mutación real del mismo detail.

G6 permanece cerrado hasta demostrar estas reglas con datos/casos.

---

# 25. SCHEMA PRE-RELEASE

ERP2 todavía está pre-release.

Mientras no exista instalación persistente real que deba preservarse, cambios estructurales pueden editar los archivos base existentes en vez de crear migraciones numeradas sólo para historia de desarrollo.

Después del primer deploy real:

```text
migrations → inmutables
```

No crear 006/007/008 por inercia si 004/005 aún son pre-release editables.

---

# 26. CLEANUP Y RETENCIÓN

Un cleanup diario debe cubrir únicamente responsabilidades demostradas.

Actual:

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

# 27. SWITCHES

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

Remote writes no es un switch normal de usuario antes de F16.

---

# 28. GATES MACRO

| Gate | Objetivo | Estado actual |
|---|---|---|
| G1 REMOTE_TRUTH | contratos remotos exactos y honestos | PASS para boundary implementado; source-truth hardening en progreso |
| G2 WORK_SAFETY | dedupe/retry/defer/recovery/cleanup seguros | PASS |
| G3 RATE_SAFETY | 429/cooldown sin storms | PASS para Sales actual |
| G4 SALES_AUDIT_TRUTH | captura/reparación/verificación/confirmación honestas | IN PROGRESS |
| G5 BILLING_CURSOR_TRUTH | cursor/terminal/206 probado | BLOCKED ON C0 |
| G6 FINANCIAL_NO_DOUBLE_COUNT | reconciliación sin duplicar conceptos | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | mutaciones remotas bloqueadas por defecto | PASS |
| G8 HOSTING_REALITY | runtime/cron/DB/HTTPS/document root reales | NOT CERTIFIED |

Siempre en CI normal:

```text
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

excepto smoke real explícitamente autorizado.

---

# 29. ROADMAP MAESTRO

El roadmap es orientativo y se ajusta cuando evidencia real invalida una premisa.

## Foundation / seguridad — cerradas o ampliamente implementadas

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

```text
CAPTURE
→ canonical fingerprint
→ post-capture decision
→ bounded REPAIR
→ local VERIFY
→ source horizon guard
→ short-page guard
→ independent CONFIRM/capture B
→ valid/baseline semantics
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

Catalog, Inventory, ERP API, Invoicing y Provider son roadmap de producto; sus contratos detallados se definen cuando llegue evidencia/alcance real. No inventar arquitectura anticipadamente.

---

# 30. ESTADO OPERATIVO ACTUAL — 2026-10-10

> Para el SHA exacto de branch HEAD, leer GitHub y `docs/CURRENT_CHECKPOINT.md`; no asumir que este bloque sustituye al checkpoint.

Rama activa:

```text
impl/v3-b-sales-audit-20261010
```

Último commit funcional totalmente GREEN verificado:

```text
81068b6e4cca808e3534e275ed01edc9e9204fb1
feat(v3-k5b): advance terminal capture directly to next durable state
```

QA verificado:

```text
RUN=38057589089
JOB=114229115845
PHP=8.5.11
PHPSTAN=0
PHPUNIT=199/199 PASS
ASSERTIONS=1355
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Checkpoint de auditoría K1–K5b:

```text
4796f1805445d5853e500be76787eef31d005bc5
```

RED actual bajo prueba al pausar:

```text
35730c23fafd59b3fc7c6f737b21bcbcf87b8c91
test(v3-k6a1): prove old seller-search month becomes unavailable
```

Checkpoint de pausa posterior:

```text
bfe8ae56c2e909659631dff98b826d31dcd00e7e
docs(checkpoint): pause at K6a1 RED without further implementation
```

## Cerrado hasta K5b

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
B3d1      canonical fingerprint primitive
B3d2      terminal fingerprint wiring
B3e1      deterministic local missing set
B3e2      guarded post-CAPTURE decision
B3e3a     repair missing read
B3e3b1    deterministic single candidate
B3e3b2    one bounded repair enqueue
B3e3b3a   no terminal child recreation
K1        missing-read prune + LIMIT 1
K2        runtime REPAIR through same sales.audit
K3        terminal child + gap -> durable attention
K4        CAPTURE -> repairing continuation
K5        local VERIFY -> confirming
K5b       CAPTURE fast-path -> repairing or confirming
```

---

# 31. PUNTO EXACTO DE REANUDACIÓN

Proyecto **PAUSADO deliberadamente** antes de producción GREEN de K6a-1.

## K6a-1 — source horizon

RED existente debe demostrar:

```text
mes canónico fuera de seller-search horizon
→ run unavailable
→ Work done
→ zero OAuth dependency
→ zero remote HTTP
→ zero audit evidence
```

Antes de modificar producción:

```text
1. fetch branch HEAD
2. leer CURRENT_CHECKPOINT
3. inspeccionar workflow 38060132448
4. confirmar que RED falla por la causa esperada
5. sólo entonces GREEN mínimo K6a-1
6. full QA
7. DELETE/MERGE audit
8. checkpoint
```

## K6a-2 — short non-terminal page

No iniciado.

Debe ser un RED separado después de cerrar K6a-1.

No iniciar todavía:

- capture B;
- `valid`;
- Billing Task 2;
- Financial;
- merge;
- deploy;
- real ML batch;
- remote writes.

---

# 32. GAPS / DEUDA REAL ACTUAL

Sólo conservar aquí huecos que todavía existan.

| Gap | Severidad | Estado/acción |
|---|---:|---|
| Seller-search horizon puede false-complete | Crítica | K6a-1 en RED |
| Short non-terminal page podría saltar posiciones | Alta | K6a-2 pendiente, fail closed si no hay contrato fuerte |
| Independent capture B / CONFIRM | Alta | pendiente después de K6a |
| Baseline/valid lifecycle | Alta | pendiente después de CONFIRM |
| Exact order 404 dentro de audit | Alta | atención/unavailable, contrato final pendiente |
| Start UX + duplicate active-run guard | Media | después del core G4 |
| `sale_fee` falta en schema Sales actual | Alta para Financial | microbloque independiente antes de G6 |
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
- canonical fingerprint;
- deterministic missing set;
- LIMIT 1 repair candidate;
- bounded one-child repair;
- terminal child recreation guard;
- durable parent attention;
- local VERIFY;
- CAPTURE fast-path a confirming.

---

# 33. NO HACER AHORA

```text
NO Billing handler antes de C0
NO Financial ledger
NO new queue
NO RepairEngine
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
NO marcar Sales Audit valid antes de K6a + CONFIRM
```

---

# 34. DOCUMENTOS ACTIVOS

Leer preferentemente:

```text
AGENTS.md
README.md                       ← mapa maestro vivo
docs/ERP2_AUTHORITY.md          ← leyes/contratos aprobados
docs/CURRENT_CHECKPOINT.md      ← punto exacto de ejecución
docs/meli-contracts-2026.md
docs/mercadolibre-app-erp2.md
docs/runtime-preflight.md
docs/hostinger-runtime-evidence.md
docs/f6-billing-period-first-contract.md
```

Handoffs y planes históricos sólo se consultan cuando se necesita contexto que no esté ya consolidado.

No releer automáticamente toda la historia para empezar un microbloque.

---

# 35. PROTOCOLO PARA MANTENER ESTE README VIVO

Este bloque es obligatorio para continuidad futura.

## Después de cada microbloque GREEN + checkpoint

Revisar si cambió alguno de estos elementos:

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

1. actualizar la sección correspondiente de este README;
2. **reemplazar** información superseded, no anexar otra versión paralela;
3. actualizar `CURRENT_CHECKPOINT.md` con SHA/CI/punto exacto;
4. actualizar `ERP2_AUTHORITY.md` sólo si cambia una regla/contrato vinculante;
5. correr búsqueda para detectar documentos/tests/código que quedaron viejos;
6. Git conserva las versiones anteriores.

## Qué NO guardar en README

No convertirlo en:

- diario de commits;
- transcript de chat;
- lista de todos los RED/GREEN históricos;
- archivo de patches viejos;
- copia completa de logs;
- repositorio de propuestas rechazadas sin relevancia actual.

El README debe contener:

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

Cada 2–4 microbloques preguntar:

```text
¿README refleja el producto real?
¿checkpoint refleja HEAD/CI real?
¿Authority sigue vigente?
¿hay sección superseded que podamos DELETE/MERGE?
¿un nuevo chat sabría continuar sin preguntarnos dónde íbamos?
```

Si alguna respuesta es no:

```text
STOP feature work
→ corregir continuidad
→ recién entonces seguir
```

---

# 36. PROTOCOLO DE RECUPERACIÓN EN UN NUEVO CHAT

```text
1. abrir este README
2. leer CURRENT_CHECKPOINT
3. leer Authority + AGENTS
4. fetch branch activo
5. verificar HEAD
6. revisar CI del último functional SHA y del RED actual si existe
7. comparar sólo delta desde checkpoint si HEAD avanzó
8. confirmar que no se mezcló main ni otro proyecto
9. retomar exactamente el microbloque documentado
10. RED → causa correcta → GREEN → QA → noise audit → checkpoint
```

Nunca responder sólo "continúo" sin ejecutar/verificar.

Nunca reconstruir el proyecto desde memoria si Git/checkpoint pueden demostrar el estado.

---

# 37. DEVELOPMENT

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

La foundation bloquea tráfico real `api.mercadolibre.com` en `local` y `test`.

Ver configuración en `.env.example`.

---

# 38. DEFINICIÓN DE ÉXITO

ERP MELI 2.0 no estará "bien" porque tenga muchas funciones o muchas capas.

Estará bien cuando:

- los hechos remotos importantes sean exactos y auditables;
- los meses no puedan declararse completos sin evidencia suficiente;
- los límites de la fuente sean visibles y honestos;
- retries/429/404 no creen storms;
- Work sea pequeño y predecible;
- business truth sobreviva cleanup técnico;
- Billing tenga cursor probado;
- Financial no doble cuente;
- cada módulo se pueda razonar con pocas piezas;
- la operación real en Hostinger esté certificada;
- remote writes sólo se habiliten de forma consciente y segura;
- un nuevo chat/agente pueda recuperar el proyecto desde este README + checkpoint + Git sin volver a inventar arquitectura.

---

# 39. RESUMEN EN UNA PANTALLA

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

LAST FULL GREEN:
81068b6e4cca808e3534e275ed01edc9e9204fb1
199/199 tests
1355 assertions
PHPStan 0
REAL_MELI_HTTP=0

CURRENT PAUSE:
K6a-1 RED
35730c23fafd59b3fc7c6f737b21bcbcf87b8c91
old seller-search month must become unavailable before OAuth/HTTP

NEXT:
inspect RED CI
→ minimal K6a-1 GREEN
→ QA/noise audit/checkpoint
→ K6a-2 short-page RED

DO NOT START YET:
CONFIRM/capture B
valid
Billing Task 2
Financial
merge/deploy
real batch
remote writes
```

---

# FIN
