# ERP MELI 2.0 — AUTHORITY DURABLE

Este archivo contiene únicamente contratos durables de arquitectura y dominio. No conserva SHAs, runs, changelog, planes de implementación ni el siguiente microbloque; todo eso pertenece a `docs/CURRENT_CHECKPOINT.md`. Git conserva la historia.

## 1. Autoridad y alcance

Para saber qué está implementado hoy:

```text
código/schema real del branch activo
> tests/CI del SHA relevante
> docs/CURRENT_CHECKPOINT.md
> este ERP2_AUTHORITY.md
> README/documentación de apoyo
> historia/inferencias
```

Para contratos externos:

```text
documentación oficial vigente + evidencia API real controlada > suposiciones
```

Nunca convertir una inferencia en contrato.

Las leyes de ingeniería, TDD, KISS, reducción de ruido y reglas de eliminación viven en `AGENTS.md` y no se duplican aquí.

---

## 2. Presupuesto arquitectónico

Arquitectura deliberadamente pequeña:

```text
1 aplicación PHP/Slim
1 repositorio
1 MariaDB
1 Work table
1 WorkRunner
1 MeliClient
1 operation registry
1 MeliCooldownRepository
1 api_usage_daily aggregate
1 Debug DVR
1 sales.audit Work type
1 SalesAuditHandler
1 SalesAuditRepository
1 SalesAuditRepairHandler
```

No crear sin evidencia nueva:

```text
microservices
brokers
queues por dominio
priority queue
domain schedulers
generic retry/recovery engines
repair engine
command/event bus
financial ledger
generic historical engine
adaptive rate engine
SalesAuditStateMachine
ConfirmRepository
BaselineService
FinalizerEngine
StartAuditService
generic transaction/lock manager
extra audit tables/states
```

KISS significa menor complejidad neta, no mínimo número de clases a cualquier costo.

Catálogo e inventario local:

- `products` owns company-scoped product identity; inventory references `products.id` instead of copying product or company metadata.
- F8 stores one current, non-negative integer quantity per product. Inventory reads and writes verify the owning company through `products.company_id`; setting quantity replaces the current value.
- No account scope, remote quantity, warehouse, reservation, or movement history is part of this contract without a demonstrated consumer.
- A legacy company name or numeric ID does not establish ERP2 company identity. Migration requires a proven business key supported by both owners or an explicit company/account mapping authority. Product sourcing accounts do not replace explicit Catalog ownership.
- Empty legacy physical-stock tables do not establish zero stock for products, and listing availability is not physical inventory. Secret/transient state and cached/derived financial values do not become canonical imported facts.

Superficie del operador Catalog/Inventory:

- Una sola pantalla HTML `/catalog`, con el enlace Catálogo en la navegación compartida. No hay pantalla separada de Inventory ni JavaScript que autoconsuma `/api/*`.
- `GET /catalog` muestra hasta 100 productos de la empresa seleccionada, `ORDER BY id DESC`, únicamente ID/SKU/nombre. `ProductRepository::listRecentForCompany(int $companyId)` tiene límite fijo 100, sin offset, cursor, búsqueda ni selección de tamaño. No consulta Inventory para cada fila.
- `POST /catalog` usa `CompanyContext`, CSRF del formulario y `ProductRepository::create`; crea sólo identidad local y redirige a `/catalog?product_id=<new_id>`. `company_id` del cliente nunca aporta autoridad. Blank SKU/name = 422; duplicate same-company SKU = 409; longitud inválida = 422, re-renderizando la misma pantalla con mensaje recuperable y sin detalles SQL. Reutiliza validación/schema actuales; no añade editar/eliminar.
- `GET /catalog?product_id=<id>` selecciona por ID entero positivo dentro del contrato nativo existente. Ejecuta una búsqueda company-scoped y una lectura Inventory únicamente si encontró producto propio. Ajeno/ausente = mismo 404 con mensaje recuperable; ID inválido = 422. No agrega ruta de detalle.
- Cantidad ausente (`null`) se muestra como **Sin definir**, con input vacío, nunca como 0. `0` se conserva y se muestra como 0. Los enteros grandes se muestran exactamente, sin float/JavaScript numérico.
- `POST /catalog/{product_id}/inventory` valida CompanyContext y CSRF, acepta sólo dígitos de un entero >=0 dentro del rango nativo de `InventoryRepository::setForProduct(int,int,int)`, y reemplaza su cantidad actual. Normaliza ceros iniciales sin float. Rechaza negativos, decimales, scientific notation, no numérico, blank, bool/array/float y overflow. Éxito = 303 a la selección; input inválido = 422 recuperable; producto ajeno/ausente = 404; CSRF inválido = 419.
- InventoryRepository se reutiliza sin cambios. Usuario disabled, sesión ausente o empresa sin membership = 403 antes de cualquier mutación. La autoridad permanece en CompanyContext; no hay RBAC ni permisos por producto adicionales.
- Todas las acciones de esta superficie son locales: ML HTTP=0, MLSIM HTTP=0, OAuth HTTP=0 y Work delta=0. No procesan handlers ni autoconsumen la API ERP. No inventario remoto, stock Mercado Libre, warehouses, reservas, movimientos, pricing/costos, links o variants.

ERP API local (F9):

- `POST /api/products` creates a local Catalog product; `GET /api/products/{product_id}` reads it within the authorized company.
- `GET /api/products/{product_id}/inventory` reads current local quantity; `PUT` on the same path sets an exact non-negative integer quantity. A product with no inventory row returns `quantity: null`, never an inferred zero.
- Company authority comes from the authenticated PHP session's selected company, verified by `CompanyContext` against `company_users`. A client-supplied `company_id` does not change that scope. Foreign or absent product IDs return the same 404 response.
- La identidad de sesión no basta: `CompanyContext::assertActiveUser()` comprueba `users.status='active'` vigente. Bootstrap aplica ese chequeo antes del dispatch de superficies de sesión Sales, ERP API, Settings, OAuth y selección de empresa; CompanyContext también lo exige al resolver membership directamente. Un usuario deshabilitado recibe 403 en el siguiente request aun con sesión previa; los guards existentes conservan membership/admin/CSRF y tenant. Health, login, webhook y bridge de máquina mantienen sus contratos independientes.
- The API reuses the existing session authentication and requires the existing CSRF token in `X-CSRF-Token` for mutations. Expected API errors use JSON codes; unhandled errors pass through Slim's generic error handling without returning SQL details.
- This contract is local-only. It adds no account scope, Mercado Libre HTTP/OAuth, remote writes, or schema.

---

### Primera instalación y autoridad del operador

Con una DB MariaDB local vacía ya provisionada y las variables existentes `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` configuradas, la ruta soportada es:

```text
php bin/bootstrap-admin.php "Primera Empresa" "primera-empresa" "admin@example.test"
```

El comando ejecuta el Migrator actual antes de provisionar; `php bin/migrate.php` también puede ejecutarse previamente. No requiere SQL manual de empresa/usuario/membresía ni OAuth. Contraseña explícita por `ERP_BOOTSTRAP_PASSWORD`, nunca argv, output, hash impreso o password predeterminado. La contraseña debe tener 12–72 bytes; no se recorta.

Ejemplo PowerShell local con entrada oculta, sin guardar la contraseña en historial:

```powershell
$env:APP_ENV = 'local'
$taskBootstrapPassword = Read-Host 'Contraseña del primer admin' -AsSecureString
try {
    $env:ERP_BOOTSTRAP_PASSWORD = [System.Net.NetworkCredential]::new('', $taskBootstrapPassword).Password
    php bin/bootstrap-admin.php "Primera Empresa" "primera-empresa" "admin@example.test"
    if ($LASTEXITCODE -ne 0) { throw 'Bootstrap no completado; revisar el mensaje del comando.' }
} finally {
    Remove-Item Env:ERP_BOOTSTRAP_PASSWORD -ErrorAction SilentlyContinue
    Remove-Variable taskBootstrapPassword -ErrorAction SilentlyContinue
}
```

`AuthService::bootstrapAdmin()` valida nombre/slug/email/password y crea exactamente una empresa, un usuario active y una membresía admin en una transacción. Serializa el chequeo inicial mediante el singleton existente `system_settings` con `FOR UPDATE`. Cualquier autoridad preexistente bloquea el bootstrap con error claro, sin duplicados ni modificación de esa autoridad. Es instalación inicial, no CRUD ni recuperación de passwords. Error durante las inserciones revierte la transacción.

Rutas del operador:

| Ruta | Contrato |
|---|---|
| GET/POST `/login` | Formulario etiquetado y CSRF actual. Credenciales válidas con una membresía activan su empresa y rol; con múltiples autentican al usuario sin empresa/rol hasta selección explícita; sin membresías falla cerrado y no conserva contexto usable previo. Login correcto redirige a `/`. |
| GET `/` | Empresa activa autorizada: 303 a `/sales`. Usuario activo autenticado sin empresa seleccionada: 303 a `/company/select`. Usuario ausente/inactivo o contexto ajeno: 403. |
| GET `/sales` y `/sales/{order_id}` | HTML local con empresa actual, Ventas, Settings sólo para el rol admin vigente y logout POST con CSRF. |
| GET `/company/select` | CompanyContext lista sólo membresías del usuario activo, incluso sin empresa seleccionada. Sin membresías: 403. Con una membresía y empresa ya activa redirige a Sales; sin empresa activa o con múltiples muestra el selector existente, sin afirmar una empresa activa ficticia. |
| POST `/company/select` | CSRF + membership vigente; activa empresa elegida y rol de esa membresía en DB y redirige a `/`. CSRF inválido: 419; ID inválido: 422; empresa ajena/usuario inactivo: 403. Una selección rechazada no crea autoridad de tenant. |
| POST `/logout` | Convención actual con CSRF; vuelve a `/login`. |

CompanyContext conserva una única autoridad de tenant/usuario/membresía/rol: el `company_id` enviado por el cliente no crea scope y el rol de sesión no es autoridad independiente. Reautenticar con múltiples membresías elimina empresa/rol previos antes de elegir. No se persiste estado de selección de tenant ni se agrega RBAC. Settings conserva su comportamiento y ofrece un enlace de regreso a Ventas.

La búsqueda de historial es `GET /sales?order_id=<ID exacto>` mediante formulario de Sales, validando 1–32 dígitos como string y consultando exclusivamente la empresa seleccionada. Mantiene la lista reciente acotada a 100 sin filtro; con filtro obtiene el ID exacto aunque sea antiguo. Venta ausente/ajena devuelve el mismo mensaje vacío; ID inválido devuelve 422 con enlace de recuperación. El detalle ajeno sigue 404. No se crea Work ni se consulta Mercado Libre.

Sales muestra las últimas 25 auditorías propias mediante SalesAuditRepository: cuenta, período, estado persistido, inicio/fin UTC y conteos existentes (null = sin determinar). CAPTURING/REPAIRING/CONFIRMING y VALID/ATTENTION/UNAVAILABLE se muestran honestamente sin cambiar semántica runtime. No expone claim tokens, Work, payloads ni infraestructura. Actualización manual de página, sin polling. Iniciar auditoría sigue el POST explícito admin/CSRF/cuenta/mes cerrado y el productor actual; renderizar nunca procesa su Work.

Todas estas lecturas/páginas tienen ML HTTP=0, MLSIM HTTP=0 y OAuth HTTP=0, sin depender de cuentas o tokens para renderizar. No certifican composición E2E en producción ni autorización OAuth externa.

---

## 3. Work y runtime

Tabla única: `work_items`.

Estados:

```text
pending
running
done
failed
```

Orden de claim: `available_at, id`.

Capacidades aceptadas:

- active dedupe;
- claim token;
- bounded retry;
- defer;
- crash recovery;
- un solo runner;
- terminal cleanup.

`retryCurrentClaim` consume intento; `deferCurrentClaim` no consume presupuesto neto.

`maxSeconds` es un presupuesto blando del runner comprobado antes del siguiente claim: un claim ya en ejecución puede terminar y, agotado el presupuesto, no se toma otro.

`completeCurrentClaim()` guarda el claim actual con row lock antes de ejecutar su callback. El callback puede devolver `true` únicamente tras diferir él mismo ese Work mediante el owner existente; el repositorio verifica estado pending y claim limpio antes de commit. Sin defer del callback conserva completion/continueAt existentes. La reparación Sales Audit ejecuta selección/dedupe/enqueue del hijo y defer del padre dentro de esa misma transacción. Token obsoleto no ejecuta callback ni crea hijos; fallo de transición revierte todos los efectos.

Crash recovery:

```text
running + attempt < cap -> pending
running + attempt >= cap -> failed
```

Retención de Work terminal: 30 días. Work es ejecución, no historial durable de negocio.

Cron/runtime presupuestado:

```text
bin/work.php
bin/cleanup.php
```

Cuando hosting esté certificado: `work.php` cada minuto y `cleanup.php` diario. No cron ni scheduler por Sales/Billing/Financial.

---

## 4. Mercado Libre core, OAuth y rate safety

ERP2 usa una aplicación Mercado Libre dedicada y separada de ERP1. No migrar refresh tokens de ERP1.

Núcleo único:

- `MeliClient`;
- operation registry;
- OAuth PKCE/state;
- tokens cifrados;
- refresh lock/reread;
- pacing;
- cooldown durable;
- api usage aggregate.

Clasificaciones remotas:

```text
READ
AUTH
WRITE
```

Unknown/missing classification -> BLOCK.

429 registra cooldown y difiere Work; no consume retry budget y no hace retry inline. 5xx/transport usan bounded retry. Remote writes permanecen OFF hasta autorización explícita del usuario y el gate correspondiente.

---

## 5. Exactitud de datos y tiempo

Dinero exacto se persiste como DECIMAL/string exacta; nunca derivar valores comerciales exactos con `float`.

Timestamp remoto canónico requiere `Z` o `±HH:MM`; sin zona explícita, fail closed. Instantes persistidos en UTC.

La lógica de negocio no depende del timezone de PHP, MariaDB o hosting.

`orders.get` preserva números JSON donde la precisión comercial lo exige. `orders.search` usa IDs exactos y fechas para Sales Audit; no se usa para inferir dinero decimal.

---

## 6. Sales operational truth y fuente mensual

Membership mensual de Sales Audit:

```text
order.date_created
```

Contrato soportado actualmente:

```text
site_id = MCO
business timezone = America/Bogota
mes = [primer día local 00:00, primer día del mes siguiente 00:00)
```

Search remoto usa guard-band UTC ±1 hora; membership final se decide desde `date_created` exacto.

Seller Orders Search tiene horizonte aproximado de 12 meses y, como seller, filtra canceladas. Por tanto un run `valid` significa consistente respecto de seller-search + contrato conocido, no snapshot absoluto de toda la historia de Mercado Libre.

Fuera de horizonte soportado:

```text
run -> unavailable
Work -> done
antes de OAuth/HTTP
sin evidencia falsa
```

Short non-terminal page, fechas sin zona, IDs/paging/total incoherentes o contrato remoto malformed -> fail closed.

---

## 7. Sales Audit durable

Capturas A y B sólo admiten HTTP200 sin `X-Content-Missing` no vacío. 206 o declaración de incompletitud siguen el fallo cerrado de contrato existente: cero evidencia/autoridad nueva, cero continuación y ninguna llamada adicional en ese claim. Un cuerpo aparentemente terminal no sustituye la completitud del response.

Work types de Sales:

```text
order.sync
sales.audit
```

No crear subtipos `sales.audit.capture/repair/verify/confirm`; el mismo `sales.audit` se enruta por estado durable.

Flujo aceptado:

```text
Capture A
-> durable evidence A
-> canonical fingerprint A
-> compare local
-> bounded one-child repair si falta una orden
-> verify local
-> independent Capture B
-> compare A/B
-> baseline lifecycle
```

Estados permitidos de `sales_audit_runs`:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

`sales_audit_orders` separa `capture_pass` A/B y usa identidad `(audit_run_id, capture_pass, external_order_id)`.

No agregar page/repair/confirm/history tables, estado `verifying`, confirm hash columns ni engines auxiliares.

### Repair

- recalcula el gap real;
- candidato determinista por `external_order_id`;
- exactamente un child `order.sync` activo/reutilizado a la vez;
- parent se difiere mientras child está pending/running;
- child terminal + gap persistente -> `attention`;
- child terminal no se recrea automáticamente;
- gap reparado -> `confirming`.

### Capture B / confirm

- B es captura remota independiente;
- A permanece intacto;
- terminal B exige count observado coherente;
- fingerprint B se compara con A durable;
- mismatch -> `attention`;
- equality -> `valid`;
- terminal no encola continuación extra ni `order.sync`.

### Baseline

- sin baseline previo: current confirmed -> valid;
- baseline válido equivalente: current -> valid y equivalente previo puede ser reemplazado;
- baseline válido divergente: baseline previo se conserva, current -> attention;
- nunca borrar baseline válido sólo por edad o por un nuevo run attention.

### Active-run guard

Una sola auditoría activa por `(company_id, account_id, period_key, contract_version)` mediante UNIQUE MariaDB sobre la versión activa generada. Activos: capturing/repairing/confirming. Terminales: valid/attention/unavailable.

### Start Audit

`POST /sales/audits` exige sesión autenticada, empresa seleccionada, admin membership, CSRF válido, cuenta conectada del tenant y mes MCO completamente cerrado.

UI usa `<input type="month">`; boundary convierte a `YYYY-MM-01`. Rechazo de current/future ocurre antes de crear durable state.

HTTP:

```text
success -> 303 /sales
duplicate active -> 409
non-admin -> 403
invalid CSRF -> 419
invalid input/account/current/future -> 422
```

Success crea run `capturing` + Work `sales.audit` de forma atómica.

---

## 8. Exact-order 404

Para `GET /orders/{order_id}`:

```text
HTTP 404
-> Work failed
-> last_error_code = meli_order_not_found
-> no retry/defer
-> no order persistence/mutation
```

Clasificación por status HTTP, no por body.

`401 -> refresh once -> retried GET 404` termina también `meli_order_not_found`; no cuarto request. Otros 4xx permanecen `meli_remote_permanent`; 429/5xx/transport/malformed-200 conservan sus contratos propios.

Si el 404 corresponde a un child de repair y el gap persiste, Sales Audit termina `attention`; nunca convertirlo en período completo.

---

## 9. Webhook, Debug y cleanup

Webhook es señal, no business history:

```text
validate topic/app
-> resolve unique connected account
-> extract order id
-> enqueue order.sync
-> ack rápido
```

Hardening multi-company/timestamp/retention se trata por separado cuando corresponda.

Debug DVR se conserva por responsabilidades reales: sanitización, cap, correlación, gzip, retention, export, checksums, filesystem security y fail-safe. `DEBUG OFF` implica cero DVR writes.

Cleanup diario sólo cubre responsabilidades demostradas: Debug retention, Work terminal >30d y API usage >90d. Sales Audit no se poda por edad ciega.

---

## 10. Billing: contrato aceptado y desarrollo local

Billing es conciliación fiscal/financiera; no es Sales operational truth.

Operación externa principal registrada:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
operation = billing.period.details
```

Request contract actualmente aceptado:

```text
period_key = YYYY-MM-01
document_type = BILL | CREDIT_NOTE
limit = 1000
new stream without durable progress: from_id = 0
next from_id = last_id anterior
sort_by = ID
order_by = ASC
```

BILL y CREDIT_NOTE son streams separados. La paginación es secuencial. KISS-S1 reutiliza el mismo `billing.period.sync` Work durante el stream `(company_id, account_id, period_key, document_type)`; no crea hijos por página. Cada claim procesa una página lógica. Tras 200 no terminal, facts, `billing_periods.cursor_last_id`, el hint `payload.from_id` del mismo Work y su defer se confirman en una sola transacción guardada por claim_token; ID, scope, identidad y dedupe no cambian. El hint existente sólo conserva compatibilidad con el dispatcher: el cursor durable de Billing y su validación de conflicto siguen siendo la autoridad. `completeCurrentClaim(..., continueAt)` reutiliza `deferCurrentClaim()` dentro de la transacción: limpia claim/error y devuelve el incremento de attempts. Disponible en `now` de ejecución +1 segundo, normalizado UTC; sin scheduler ni loop de paginación inline. Página terminal completa el mismo Work. 206 y 429 conservan sus defers; 429 hace una sola petición física sin retry inline. El manejo 401 existente conserva como excepción su refresh/retry acotado del mismo recurso, sin cambios OAuth. Productores iniciales, WorkRunner, Sales y Financial no se rediseñan. Una página con `results` como array vacío es terminal; la ausencia de `results` es contrato inválido. HTTP 206 significa incomplete y nunca `caught_up` ni completo.

Existe tooling C0 read-only y sanitizado que valida guards de producción/opt-in/argumentos/cuenta/token y compone `BillingC0Probe` con el `MeliClient` existente. C0 no persiste Billing, no refresca OAuth, no persiste api usage/cooldown y no hace remote writes.

El desarrollo durable de Billing y Billing Task2 se diseñan e implementan localmente usando documentación oficial aceptada, fixtures contractuales, el `MeliClient` existente, fake `MeliTransport` y MariaDB local. La falta de una canary real no bloquea trabajo local; no inventar semánticas externas que no estén documentadas ni sean seguras de inferir.

El C0 real de seller MCO es un smoke de sólo lectura de F14/preproducción. No refresca OAuth, no persiste Billing, uso API ni cooldown, y no hace remote writes. No es requisito previo para Billing Task2 local.

---

Billing trata HTTP200 con `X-Content-Missing` no vacío igual que206: defer del mismo Work por mecanismo actual, sin facts/cursor/caught_up ni retry inline. Un200 vacío completo sigue siendo terminal; no se agrega estado ni se modifica partial_flag por este guard.

### Superficie del operador Billing y ejecución manual

- GET `/billing` muestra hasta100 períodos propios, ordenados por período descendente, cuenta y tipo. La identidad seleccionada usa query `account_id`, `period_key`, `document_type`; consulta progreso Billing y `WorkRepository::latestLogicalState`, sin tabla de status, modelo persistido derivado o dashboard Work. No interpreta `partial_flag` como evidencia de respuesta parcial reciente. Renderizar es read-only y nunca hace HTTP ni enqueue/restart.
- POST `/billing/syncs` exige CompanyContext vigente, usuario activo, rol admin de la empresa, cuenta propia connected y CSRF. Admin para iniciar Billing es decisión de este dominio. Company ID del cliente no aporta autoridad. Rechaza arrays/objects/null/bool/float, cuenta no positiva o fuera del rango nativo, período distinto de fecha calendario exacta `YYYY-MM-01` y tipos fuera de BILL/CREDIT_NOTE. Input inválido422; CSRF419; actor no autorizado403. Cuenta ajena/no conectada422 sin encolar. Éxito303 a la selección, HTTP remoto0.
- `scope_key=company:<company_id>:account:<account_id>`, `type=billing.period.sync` y `logicalIdentity=period_key:document_type`; WorkRepository aplica su hash/dedupe activo existente. El cursor no forma parte de la identidad. El productor serializa su transacción mediante la fila de cuenta existente con FOR UPDATE y usa el reader/enqueue de Work; no persiste Billing. Si el último Work lógico sigue pending/running lo reutiliza sin tocar payload/cursor/available_at/claim/progreso. Sin activo, un POST explícito crea un nuevo Work desde `billing_periods.cursor_last_id`, o0 si no hay período. Done/failed no se reinician automáticamente.
- `caught_up` registra el terminal de la fuente previamente consultada; no significa cierre fiscal. Un nuevo POST explícito desde ese cursor admite página vacía que conserva caught_up o hechos posteriores válidos que avanzan y continúan sobre el mismo Work. Es contrato local probado con el handler existente; no certifica cobertura de cambios remotos anteriores al cursor ni toda semántica de ajustes del proveedor.
- La UI distingue solicitud registrada sin período, progreso persistido y último Work fallido que requiere revisión. El progreso anterior puede seguir caught_up durante una nueva solicitud; se muestra como lectura persistida, no como resultado nuevo. Escapa valores y no muestra payload JSON, claim token, attempts, stack traces ni errores internos. Actualización manual, sin polling.
- `php bin/work.php --manual-one` usa exactamente WorkExecution::runManualOne, processor, handlers y lock existentes: cero o un Work elegible global incluso con automation OFF, por available_at/id, sin targeting ni procesamiento hasta terminal. Puede ejecutar otra empresa/dominio. `processed` cuenta invocaciones del processor, no completitud de Billing. Sin flag conserva el runner automático actual; argumentos de targeting/loop o desconocidos se rechazan con exit2 antes de claims.
- La composición CLI→Curl→MLSIM usa un único seam acotado: `MELI_TEST_BASE_URL` se acepta sólo con `APP_ENV=test` y únicamente como `http://127.0.0.1:<puerto válido>`. Fuera de test se conserva `https://api.mercadolibre.com`; RemoteHostPolicy permanece sin cambios. Se mantienen transporte, cooldown, OAuth/401, pacing y writes OFF existentes; una página lógica por claim no equivale necesariamente a una llamada física. El request que recibe429 no tiene retry inline por429; un claim puede añadir dispatches AUTH/401 acotados.

## 11. Financial

MB-10 implementa un read model local de reconciliación sobre los hechos existentes de Sales, Billing y pack. No crea ledger, tabla, columna, fórmula contable, asignador, ruta ni Work nuevo.

Contrato vigente:

- La consulta siempre queda limitada por company/account. Si el pedido tiene `pack_id` local, el scope incluye los pedidos locales de ese pack; si no, usa sólo el pedido solicitado.
- `order_items.sale_fee` conserva el decimal nullable por ítem recibido en `orders.get`, normalizado por el contrato decimal existente de Sales y en la moneda del ítem.
- El total operativo multiplica el hecho por `quantity` con aritmética decimal de MariaDB. La cobertura sólo es KNOWN si todos los ítems del scope tienen el hecho y la moneda operativa es consistente; la ausencia es UNKNOWN y mezcla de monedas es CONFLICT.
- Billing permanece como lista de hechos facturados, con tipo, subtipo, documento, asociación, importe exacto y moneda. Los detalles compartidos se identifican por `external_detail_id` y se muestran una vez; copias contradictorias no se eligen y marcan conflicto.
- El contexto Billing sólo vincula identidades locales de pedido/pack y relaciones explícitas entre detalles. Ambigüedad o asociación contradictoria no se resuelve por inferencia.
- `receiver_shipping_cost` se conserva como evidencia separada de shipping. `seller_shipping_cost` y `official_reference` permanecen UNKNOWN hasta que exista un hecho explícito y documentado.
- No se expone neto, total oficial, costo final ni agregado Billing inferido. Ausente/null no significa cero; cero explícito sigue siendo cero.

Hecho fuente previo a Financial:

- `order_items.sale_fee` conserva el decimal nullable por ítem recibido en `orders.get`, normalizado por el contrato decimal existente de Sales y en la moneda del ítem.
- Si dígitos no cero exceden la escala durable de cuatro decimales, Sales falla el contrato en vez de redondear; ceros finales redundantes siguen siendo exactos.
- La clave ausente o un valor `null` permanece SQL `NULL` y nunca significa cero; el cero remoto se conserva como `0.0000`.
- Es un hecho fuente por unidad y permanece así en Sales. El read model puede calcular de forma derivada la suma por ítem multiplicando por `quantity`, sin escribir ese cálculo como hecho fuente ni inferir signos o significado contable desde Billing.

El modelo conserva separación de fuentes:

```text
Orders operational facts
+ Billing billed facts
+ pack relationships
+ shipping evidence
-> reconciliation/read model
```

La cobertura local prueba no-double-count y shared pack charge una vez, separa hechos operativos, facturados y evidencia de shipping, y deja unavailable values como UNKNOWN. No inventar fórmulas desde la forma del API ni añadir llamadas remotas para completar el read model.

### Consumidor Financial en detalle de venta

- La ruta existente `GET /sales/{external_order_id}` conserva CompanyContext y membership/usuario activo. El lookup usa company_id de esa autoridad + external_order_id, con LIMIT2: cero coincidencias o dos cuentas propias coincidentes devuelven el mismo404, sin elegir una fila ni consultar Financial. Una sola coincidencia aporta SU account_id. Parámetros account/company del cliente no cambian identidad ni URL.
- El detalle válido invoca exactamente una vez `FinancialReadModel::forOrder(company_id, account_id, external_order_id)` sobre la misma conexión PDO y renderiza Reconciliación en la vista existente. El listado `/sales` y los requests rechazados no invocan Financial. No ruta, clase, DTO, renderer genérico, service, repository, cache o estado Financial adicional.
- Renderiza el scope order/pack y miembros locales devueltos por el modelo, comisión operativa, coberturas, moneda, lista de hechos Billing y shipping evidence separados. Un hecho Billing compartido aparece una vez en esa lista, sin asignarlo a cada pedido. Importes exactos permanecen strings; cero conocido no desaparece, UNKNOWN no se convierte en0 y CONFLICT no se completa con una elección. Cobertura Billing representa billing_identity, no prueba de conciliación exhaustiva de periodos ni total oficial. No sumas Billing, profit, netos, signos contables inferidos, costo final o seller shipping inventado.
- Escapa contenido dinámico mediante la convención HTML actual. No expone context_json, payloads, tokens o debug internals, ni agrega JavaScript o navegación. GET es pure read: cero HTTP ML/MLSIM/OAuth, cero Work creado/reclamado/mutado, cero escrituras de dominio y cero remote writes. No fetch para completar UNKNOWN ni instrumentación productiva nueva.

---

## 12. Invoicing boundary

`App\Modules\Invoicing\InvoiceRequest` es el contrato inmutable y provider-neutral entre los hechos locales de Sales y una futura integración. Sólo transporta company/account/source-order identity, `total_amount` y moneda fuente, hechos existentes por línea (identidad externa, título, cantidad, precio unitario y moneda), y el `buyer_id` nullable existente. Los decimales permanecen strings con la precisión durable de Sales; el total fuente no se recalcula desde líneas ni se combina con Billing o Financial.

La identidad estable es `(company_id, account_id, external_order_id)`. `idempotencyKey` es SHA-256 determinista de esa tupla bajo el prefijo `invoicing.source-order:`; no depende del ID local autoincremental. F10 no persiste intents ni estado: sólo define la solicitud y su clave reproducible; no crea Work, tabla, provider port/adapter, status/error model ni llamada remota.

Sales sólo demuestra un `buyer_id` opcional, no nombre, domicilio, identificación fiscal ni otros datos fiscales. F10 no define documento legal, impuestos, numeración ni payload fiscal. La solicitud no afirma ser suficiente para emitir; el ejecutor externo debe resolver sus datos fiscales y ERP2 conserva UNKNOWN hasta que exista una fuente/decisión autorizada. No acoplar Sales, Billing, Financial, Catalog ni ERP API al ejecutor.

---

## 13. External Billing Bridge and PostSale (F11/F12)

F11 implementa una integración local para ejecutores externos Windows/otros servidores. No modifica `InvoiceRequest`: F10 sigue siendo un boundary cerrado por pedido y no tiene `pack_id` ni facts fiscales. No hay Alegra ni otro proveedor HTTP en este bloque.

Credenciales machine-to-machine:

- `invoicing_service_keys` vincula cada clave a una empresa; los clientes no eligen `company_id`.
- Se genera un bearer aleatorio de 256 bits. MariaDB conserva sólo SHA-256, scopes `invoicing.read`/`invoicing.write` y estado activo/revocado. El comando `bin/invoicing-key.php issue COMPANY_ID [read|write|read,write]` muestra el secreto una vez; `revoke KEY_ID` lo revoca.
- Un filtro `account_id` se acepta sólo si la cuenta pertenece a la empresa autenticada.

API local:

```text
GET  /api/integrations/v1/invoicing/sources
POST /api/integrations/v1/invoicing/sources/{external_order_id}/artifact
POST /api/integrations/v1/invoicing/sources/{external_order_id}/credit-note
```

La exportación devuelve fechas Sales, estado/cancelación, importes y cantidades decimales exactos, moneda, items, buyer ID, cuenta, estado del artefacto y readiness. `buyer_id` nunca es identificación fiscal. Hoy `billing_data_status=UNKNOWN`: no existe una fuente local probada de datos fiscales; el ejecutor externo debe resolver sus requisitos fiscales. `invoice_eligible` significa que el pedido local puede entregarse al ejecutor y recibir su artefacto, no que ERP2 haya certificado datos fiscales o validez legal. No convertir UNKNOWN en cero ni en una afirmación fiscal.

Readiness tiene una sola etiqueta por pedido:

```text
pack_id != null  -> PACK_MEMBERSHIP_UNVERIFIED, visible y no facturable
pack_id == null + cancelado -> CANCELLED
pack_id == null + facts Sales incompletos -> SOURCE_DATA_MISSING
pack_id == null + facts Sales requeridos -> ORDER_READY
BILLING_DATA_MISSING -> sólo ante un hecho local explícito; hoy billing permanece UNKNOWN
```

`GET /packs/{pack_id}` es la futura evidencia autoritativa de membership. F14 debe certificar los `orders[]`, interpretación seller/account, billing-info READ y persistencia de snapshot completo antes de admitir facturación por pack. Si `pack_id` es null, el facturador remoto futuro podrá usar el order ID como fallback `/packs/{order_id}` sólo conforme al contrato certificado; F11 no hace esa llamada.

La ruta `/artifact` conserva el contrato F11: acepta un PDF y XML opcional sólo para `ORDER_READY`, exige número, fecha ISO-8601 con offset, moneda y total exacto iguales a Sales. ERP calcula hashes y nombres/rutas, limita uploads a 20 MiB por archivo y guarda archivos fuera de acceso cliente.

F12 extiende el mismo almacenamiento con `document_kind=INVOICE|CREDIT_NOTE` y permite como máximo un artefacto de cada tipo por source. La ruta `/credit-note` acepta un PDF requerido y XML opcional sólo si el pedido local está cancelado, no tiene `pack_id` y ya existe su artefacto INVOICE. El servidor enlaza la nota al artefacto original; conserva número, fecha, moneda, total decimal exacto, hashes y metadatos opcionales. Total distinto es admisible y no se infieren impuestos, motivo, códigos legales ni factura original desde el cliente. Mismo artefacto + mismos metadatos es idempotente; una segunda nota distinta devuelve conflicto. No genera documentos ni llama proveedores.

PostSale se deriva de `orders.status` y los dos tipos de artefacto locales:

```text
activo                         -> NONE
cancelado, sin artefacto INVOICE -> CANCELLED_BEFORE_INVOICE
cancelado + INVOICE, sin CREDIT_NOTE -> CREDIT_NOTE_REVIEW_REQUIRED
cancelado + INVOICE + CREDIT_NOTE   -> CREDIT_NOTE_RECORDED
```

`GET /sources` expone `post_sale_status`, `original_invoice_artifact_present` y `credit_note_artifact_present`. El estado sólo expresa hechos y revisión pendiente; no declara que fiscalmente proceda anular ni que el documento haya sido validado por DIAN. `Billing document_type=CREDIT_NOTE` permanece como stream de conciliación de documentos de Mercado Libre y nunca se consume como nota crédito de factura del cliente.

**Consumer humano LC2D.**

El detalle existente `GET /sales/{external_order_id}` muestra únicamente readiness, presencia de factura, presencia de nota crédito y PostSale en «Factura / Postventa». CompanyContext determina la empresa; la resolución canónica existente exige exactamente una venta y aporta su account_id y external_order_id. Ausencia, empresa ajena o ambigüedad entre cuentas devuelven404 antes de Invoicing; los parámetros del cliente no sustituyen esa identidad.

`InvoicingBridge::sourceForOrder(company_id, account_id, external_order_id)` reutiliza la proyección de `listSources`, con el filtro exacto aplicado en SQL antes de paginar. Un detalle válido obtiene como máximo una source; el listado Sales no consulta Invoicing. Los enums y flags F11/F12 se muestran sin recalcularlos ni certificar preparación fiscal. Billing CREDIT_NOTE no prueba presencia de una nota crédito del cliente.

Este consumer sólo lee SQL local: no usa self-HTTP, service keys, Work, escrituras de dominio/remotas, filesystem de artefactos ni controles de generación, upload o download. No cambia rutas, composition root ni semántica F11/F12.

Para factura electrónica colombiana, DIAN describe la nota crédito referenciando la factura anulada como mecanismo de anulación cuando corresponda ([Concepto DIAN 000974 int. 126 de 2025](https://normograma.dian.gov.co/dian/compilacion/docs/oficio_dian_0974_2025.htm)). Mercado Libre describe `DELETE /packs/{pack_id}/fiscal_documents` como eliminación de los archivos fiscales adjuntos ([Cargar y Obtener Facturas — Emisión Propia](https://developers.mercadolibre.com.co/es_ar/mercadolider-tiendas-oficiales/cargar-factura)); ERP2 no lo trata como cancelación fiscal. No se ejecutan llamadas DIAN ni escrituras remotas. Pack membership continúa bloqueada hasta F14 y escrituras fiscales remotas hasta F16 con autorización separada.

Alegra queda opcional y diferida como segundo ejecutor offline detrás del mismo boundary. F16, con autorización separada, será el gate de publicación fiscal remota. `REAL_MELI_HTTP=0`, `ALEGRA_REAL_HTTP=0` y remote writes OFF.

---

## 14. Schema pre-release

Mientras no exista instalación persistente real que deba preservarse, los schemas pre-release se corrigen en sitio en los archivos de fase existentes; no crear migrations numeradas sólo para conservar historia.

Después del primer deploy persistente, migrations pasan a inmutables.

---

## 15. Documentación de evidencia

Documentos de apoyo vigentes:

- `docs/meli-contracts-2026.md` — contratos externos verificados;
- `docs/mercadolibre-app-erp2.md` — app/OAuth ERP2;
- `docs/runtime-preflight.md` — runtime preflight;
- `docs/hostinger-runtime-evidence.md` — evidencia de hosting.

El estado actual, gates, SHAs, QA y siguiente microbloque viven exclusivamente en `docs/CURRENT_CHECKPOINT.md`.
