# ERP MELI 2.0

> **Plan de cierre funcional vigente (propuesta bajo revisión):** [ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md](docs/ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md). El readiness local histórico y el roadmap F0–F16 de esta página no certifican el alcance ampliado; leer el plan antes de crear un nuevo prompt.


ERP MELI 2.0 es el ERP greenfield de Sierra Global para operar y auditar Mercado Libre con una arquitectura deliberadamente pequeña, auditable y recuperable.

**Stack soportado:** PHP 8.3 / 8.4 / 8.5 + Slim 4 + PDO/MariaDB + PHPUnit + PHPStan.  
**Estado:** pre-release; remote Mercado Libre writes OFF.  
**Rama de trabajo actual:** ver `docs/CURRENT_CHECKPOINT.md`.

## Leer en este orden

Para continuar el proyecto sin reconstruir conversaciones antiguas:

1. verificar branch y HEAD reales;
2. leer `AGENTS.md` — leyes de ingeniería y KISS;
3. leer `docs/CURRENT_CHECKPOINT.md` — estado actual, SHAs, QA, gates y siguiente microbloque;
4. leer `docs/ERP2_AUTHORITY.md` — contratos durables de arquitectura y dominio;
5. abrir sólo la documentación de evidencia específica que el microbloque necesite;
6. auditar únicamente el delta desde el checkpoint si HEAD cambió.

No usar planes, checkpoints o handoffs históricos como autoridad. Git conserva la historia.

## Separación de responsabilidades documentales

| Archivo | Responsabilidad |
|---|---|
| `AGENTS.md` | Leyes de ingeniería, reducción de ruido, TDD, KISS y límites no negociables. |
| `docs/CURRENT_CHECKPOINT.md` | Verdad transitoria: branch/HEAD, último QA, trabajo cerrado, gate actual y siguiente microbloque. |
| `docs/ERP2_AUTHORITY.md` | Contratos durables que deben sobrevivir a varios checkpoints. No debe contener changelog ni roadmap transitorio. |
| `README.md` | Producto, arquitectura permitida, roadmap maestro, fases, dependencias y guardrails operacionales. No es un changelog ni duplica el detalle transitorio del checkpoint. |

### Precedencia para saber qué existe hoy

```text
código/schema real del branch activo
> tests/CI del SHA relevante
> docs/CURRENT_CHECKPOINT.md
> docs/ERP2_AUTHORITY.md
> README/documentación de apoyo
> historia/inferencias
```

Una decisión explícita reciente del usuario gobierna el alcance y la autorización de la siguiente acción; después debe quedar reflejada en checkpoint/authority si cambia un contrato durable.

## Producto y límites

ERP2 reemplaza progresivamente ERP1, pero ERP1 es fuente de evidencia y aprendizaje, no blueprint arquitectónico.

El núcleo actual mantiene una sola aplicación PHP/Slim, una MariaDB, un `Work` durable, un `WorkRunner`, un `MeliClient`, OAuth centralizado, Sales operational truth, Sales Audit y Billing por periodos. El contrato y la cobertura local de protección de consumo están cerrados en MB-03: **G3 RATE_SAFETY = PASS LOCAL**. Las cuotas/comportamiento del proveedor y la certificación externa siguen pendientes en F14. No se agregan queues, schedulers, engines, estados, tablas o capas por dominio sin evidencia real.

De ERP1 se recuperan invariantes, fixtures y criterios de aceptación útiles; no se trasladan sus motores ni su arquitectura por defecto. Cada cambio debe demostrar el problema y qué sustituye, elimina, simplifica o reúne, conforme a `AGENTS.md`.

Reglas de seguridad permanentes durante esta etapa:

```text
REAL_MELI_HTTP normal = 0
remote writes = OFF
no merge sin autorización explícita
no deploy sin autorización explícita
no mutación de producción DB/OAuth sin autorización explícita
```

## Roadmap maestro y dependencias

El detalle verificable de estado, QA y gates vive en `docs/CURRENT_CHECKPOINT.md`. Esta secuencia define el alcance del producto; no autoriza iniciar la fase siguiente.

| Fase | Alcance / dependencia |
|---|---|
| F0 Truth / Preflight | Verificar código, entorno y contratos antes de cada bloque. Hosting real se certifica en F14. |
| F1 Foundation | Base de aplicación, configuración y MariaDB. |
| F2 Work | Ejecución durable sobre F1; un solo Work y runner. |
| F3 Mercado Libre Core | Cliente, OAuth y protección de consumo sobre F2; G3 = PASS LOCAL; certificación externa/provider/cuotas pendiente en F14. |
| F4 Sales | Verdad operacional y Sales Audit sobre Work/core. |
| F5 Debug / observabilidad | Diagnóstico reutilizando logging y core; no sustituye protección ni verdad de negocio. |
| F6 Billing + Financial foundation | Billing periódico sobre Work/core; Financial local exige facts suficientes y fixtures sin doble conteo. |
| F7 Catalog | Reutilizar core/Work; confirmar contratos y consumidores antes de ampliar capturas. |
| F8 Inventory | Reutilizar facts y contratos de catálogo según necesidad demostrada. |
| F9 ERP API | Exponer capacidades implementadas con autoridad de empresa/cuenta comprobada. |
| F10 Invoicing Boundary | Separar el contrato de facturación de las verdades Sales/Billing. |
| F11 External Billing Bridge | Claves de servicio por empresa, fuentes Sales con readiness y recepción idempotente de PDF/XML; los packs quedan bloqueados hasta F14. |
| F12 PostSale audit | Exponer hechos locales de cancelación/factura y aceptar evidencia externa de nota crédito; no generar documentos ni decidir su procedencia legal. |
| F13 Migration | Depende de cobertura y reglas de datos; rescatar de ERP1 lo útil, sin importar sobrearquitectura. |
| F14 Shadow / external certification | Certificación READ-only de hosting, OAuth ERP2 y API real, con autorización separada. |
| F15 Cutover | Depende de la certificación externa y autorización de transición. |
| F16 Remote Writes | Requiere autorización explícita separada y gate de escrituras; hasta entonces OFF. |

### Local Product Readiness antes de F14

El cierre del núcleo local no equivale a abrir gates externos. El producto local ya quedó certificado; la siguiente decisión sigue separada y requiere autorización explícita.

```text
L1 Web Installer humano/local-first/DB-safe      ✅ closed
L2 Company management                           ✅ closed
L3 Users + roles/memberships                    ✅ closed
L4 Mercado Libre application settings           ✅ closed
L5 Mercado Libre account management             ✅ closed
L6 local MLSIM account linking                  ✅ closed
L7 account lifecycle                            ✅ closed
L8 human E2E local desde instalación vacía      ✅ closed / green
L9 Local Release Candidate + package            ✅ closed / green
L10 decisión explícita para gates externos      pendiente
```

`LOCAL_PRODUCT_READY=YES` no autoriza Hostinger, OAuth real, F14 LIVE ni escrituras remotas. Esos gates continúan cerrados hasta una decisión L10 explícita y separada.

### Continuidad local después de R0

R0, MB-01/02/03 de seguridad API y F6A-LOCAL-0..4 de Billing están cerrados localmente dentro de su cobertura. **MB-09 / F6B-FACTS está cerrado localmente**: `order_items.sale_fee` conserva el hecho nullable por ítem. **MB-10 / F6B-FINANCIAL también está cerrado localmente**: un read model de reconciliación separa hechos operativos, Billing y shipping; los hechos faltantes/conflictivos y los importes oficiales o seller shipping no disponibles permanecen explícitos como UNKNOWN/CONFLICT. No se añadió ledger, schema, ruta, Work ni HTTP. **G3, G5 y G6 = PASS LOCAL.**

**F7 — Catalog, F8 — Inventory, F9 — ERP API, F10 — Invoicing Boundary, F11 — External Billing Bridge y F12 — PostSale audit están cerrados localmente.** F13 está cerrado localmente: mapping explícito resuelto y ensayo de 100 productos Catalog en MariaDB desechable mediante el owner existente. Catalog es la única categoría MIGRATE; empresas/cuentas se reconstruyen según la matriz vigente. No se ejecutó migración productiva ni cutover. La matriz, evidencia y límites de cobertura están en `docs/F13_MIGRATION_REHEARSAL.md`; la certificación externa F14 continúa pendiente. El bridge usa claves machine-to-machine por empresa y conserva F10 como contrato por pedido. Los pedidos con `pack_id` siguen bloqueados hasta F14. F12 expone revisión posterior a la cancelación y permite recibir una nota crédito generada externamente enlazada a la factura local; no la genera, transmite ni decide su procedencia fiscal. `Billing CREDIT_NOTE` permanece separado. Las escrituras remotas siguen OFF hasta F16 con autorización explícita.

F1–F12 se desarrollan y prueban localmente con `REAL_MELI_HTTP=0`, transportes inyectados/fakes y MariaDB de pruebas. F13 usa el snapshot ERP1 autorizado y de sólo lectura para medir datos; el cierre local certifica únicamente el contrato de mapping y el ensayo Catalog desechable, sin cutover. C0 real no es una puerta obligatoria para Billing local ya implementado: pertenece a F14, requiere autorización separada y conserva su contrato sin persistencia de negocio/uso/cooldown ni refresh OAuth. Ninguna fase declara inmunidad a 429 o baneos.

## Documentación de apoyo vigente

Estos documentos son evidencia o guías operativas específicas; no reemplazan checkpoint/authority:

- `docs/meli-contracts-2026.md` — contratos externos Mercado Libre verificados contra documentación oficial;
- `docs/mercadolibre-app-erp2.md` — aplicación/OAuth dedicado de ERP2;
- `docs/runtime-preflight.md` — preflight de runtime;
- `docs/hostinger-runtime-evidence.md` — evidencia de hosting/runtime.

## Forma de trabajo

Cada cambio se ejecuta como **lote coherente de entrega**; las verificaciones focales y los checkpoints por pantalla son pasos internos, no nuevas rondas de delegación:

R0 es una reconciliación docs-only: inspeccionar, comprobar contradicciones, editar, validar, commit y STOP. Los cambios de comportamiento sí requieren RED → GREEN mínimo → QA antes de cerrar su checkpoint.

```text
problema/evidencia
-> DELETE/SIMPLIFY/REUSE/MERGE
-> RED
-> confirmar fallo correcto
-> GREEN mínimo
-> QA completa
-> noise audit
-> checkpoint
-> STOP
```

El punto exacto de continuidad siempre está en `docs/CURRENT_CHECKPOINT.md`.