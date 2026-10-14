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

El entrypoint web único ofrece un instalador de una sola ejecución cuando falta el marker privado. El wizard en español prepara servidor, base de datos, ERP/Mercado Libre opcional, empresa inicial y administrador, y permite volver y revisar sin redisplay de secretos. Sólo la acción final crea las tablas mediante el Migrator existente y la primera autoridad mediante AuthService. El marker se escribe al final bajo un lock exclusivo; después `/install` no está disponible. Marker con configuración ausente o inválida falla cerrado con 503, sin reabrir instalación.

La dirección validada determina el modo: HTTP o HTTPS en `localhost`/`127.0.0.1` admite modo local; una dirección no loopback requiere HTTPS y usa producción. No se aceptan credenciales, query, fragmento ni host inválido. La configuración privada es JSON inerte, fuera de exposición HTTP: entorno real/`$_ENV` tiene precedencia sobre ella y los defaults. Un destino DB distinto en el entorno bloquea la finalización. Mercado Libre admite ambas credenciales vacías o ambas configuradas; no se valida por red. Sin Client ID, el ERP arranca y la acción de conexión autorizada devuelve un mensaje claro con 503.

El instalador requiere una DB vacía y dedicada. Probar conexión observa servidor compatible y verifica CREATE/ALTER/índice/DROP en una tabla aleatoria reservada con cleanup; no toca datos de negocio. La prueba queda ligada a host, puerto, base, usuario y contraseña actuales; cambiar cualquier valor la invalida. La acción final vuelve a comprobar destino, permisos y frescura. Cualquier tabla preexistente o residuo de migración sin marker requiere recuperación explícita, sin borrar tablas ni reparar automáticamente. Restaurar o recrear una DB vacía dedicada y volver a instalar es una recuperación operativa aceptada, no un motor de migración alternativo.

Login usa username canónico y contraseña; display_name es el nombre informativo y `users.email` conserva contacto/recuperación. Los usernames nuevos usan 3–80 caracteres minúsculos, números, punto, guion o guion bajo; los usuarios previos conservan su email canónico como username y la columna admite su ancho anterior. El administrador inicial conserva la membresía admin de su empresa y tiene autoridad System Admin de la instalación. Son autoridades independientes; no se añade entrega de password reset.

El bootstrap CLI sigue disponible como provisión manual del núcleo, con las variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` configuradas. No crea el marker/configuración web ni sustituye el wizard; no debe ejecutarse para preparar la DB que éste exige vacía:

```text
php bin/bootstrap-admin.php "Primera Empresa" "primera-empresa" "admin@example.test"
```

### Autoridad de instalación y gestión de empresas

System Admin se comprueba en users mediante el ID entero de la sesión, status active e is_system_admin=1. Company admin es una membresía tenant independiente. El hint de sesión sólo controla visibilidad: nunca concede permisos. El bootstrap inicial crea ambas autoridades; al actualizar una DB previa sólo se promueve al único admin si existe exactamente una empresa y exactamente una membresía admin. Un caso ambiguo no promueve a nadie ni infiere identidad por ID, correo o nombre.

CompanyContext es la autoridad tenant. Sólo empresas con archived_at NULL aparecen en membresías o pueden seleccionarse/usarse; una selección archivada pierde company_id y role. Un usuario normal necesita alguna membresía activa para ingresar; System Admin puede ingresar sin empresa activa y acceder a empresas/settings globales. La raíz conduce a ERP con empresa válida, selector con membresías activas o gestión global para System Admin sin ellas; el resto falla cerrado.

La gestión de empresas es System-Admin-only, con CSRF en toda mutación. Crear empresa y membresía admin del actor es una sola transacción sin seleccionar automáticamente; editar sólo permite nombre/identificador, incluso en empresa archivada. No gestiona usuarios ni cuentas Meli.

Archivar conserva toda historia y fija archived_at en UTC. Se rechaza si hay Work pending/running para company_id, cuenta Meli distinta de disabled o key de facturación active. No cancela Work, deshabilita cuentas/tokens ni revoca claves. Reactivar sólo elimina archived_at, sin seleccionar, reiniciar ni reconectar. OAuth comprueba empresa activa antes de llamar al proveedor y vuelve a comprobarla con lock al persistir para no crear cuenta activa en una empresa archivada.

Eliminar definitivamente sólo admite empresa ya archivada, sin cuentas Meli, productos, Work, service keys ni uso API por company:<id> o company:<id>:account:<id>, y con la única membresía admin del actor. Los cooldowns app:<family> son globales y no se atribuyen a una empresa. Se eliminan únicamente membresía y empresa en una transacción; el usuario permanece. Cualquier FK que rechace el borrado causa rollback y conflicto, sin cascada ni force delete.

system_settings y sus acciones update/debug requieren System Admin activo comprobado en CompanyContext, aun sin empresa seleccionada. Un Company admin sin esa autoridad recibe 403. Las escrituras remotas permanecen OFF hasta su autorización específica.
