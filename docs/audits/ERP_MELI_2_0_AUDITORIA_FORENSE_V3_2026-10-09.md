# ERP MELI 2.0 — AUDITORÍA FORENSE V3

**Fecha de inicio:** 2026-10-09  
**Rama:** `audit/v3-forensic-redesign-20261009`  
**Estado:** EN CURSO — documento vivo de auditoría; no autoriza implementación productiva.  
**Código productivo congelado:** F6A Task 2.  

## 1. Propósito

Reauditar ERP Meli 1.0, ERP Meli 2.0, la documentación/API actual de Mercado Libre y casos reales del negocio antes de continuar Billing, para producir:

1. `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md`
2. `ERP_MELI_2_0_PLAN_MAESTRO_V3.md`
3. una matriz ERP1 → ERP2 de rescatar/mejorar/eliminar/no portar;
4. una matriz API Mercado Libre por lectura/escritura/riesgo/fuente;
5. reglas verificables para Sales, packs, Financial/Billing, históricos, facturación y fechas.

## 2. Leyes obligatorias

La autoridad operativa es `AGENTS.md`.

Toda propuesta debe pasar dos puertas inseparables:

- **KISS:** correcto, simple, estable, mantenible y eficiente con el mínimo de piezas.
- **Reducción de ruido:** reutilizar, mejorar, fusionar, aislar o eliminar antes de añadir. Prohibido parche sobre parche como solución permanente.

Pregunta final obligatoria:

> ¿Podemos resolverlo correctamente dejando menos cosas que entender, operar y mantener?

## 3. Estado congelado antes de esta auditoría

- F5 Debug DVR: técnicamente cerrado y verificado antes de abrir F6.
- F6A Task 1: esquema/contrato inicial Billing period-first GREEN.
- F6A Task 2 (`BillingPeriodSyncHandler`): **CONGELADO** hasta terminar V3.
- PR #15 permanece Draft; no merge/deploy durante esta auditoría.

## 4. Fuentes de autoridad

Orden provisional:

1. API/documentación oficial vigente de Mercado Libre y evidencia real del negocio;
2. código y Git reales de ERP1/ERP2;
3. tests/CI reproducibles;
4. decisiones recientes del usuario;
5. documentación maestra anterior;
6. inferencias, siempre marcadas.

## 5. Alcance forense prioritario

### A. Sales / venta visible

Auditar:

- `order_id` vs `pack_id`;
- órdenes hijas;
- múltiples artículos;
- descuentos;
- precio original vs valor facturable;
- comisión por ítem;
- envío comprador / cargo ML / costo vendedor;
- retenciones;
- total/neto oficial ML;
- diferencia entre total oficial y total recalculado;
- shipments y payments necesarios.

### B. Financial / Billing

Auditar:

- period-first vs order-by-order de ERP1;
- `BILL` y `CREDIT_NOTE` de Billing ML sin confundirlos con factura/nota crédito DIAN;
- cargos compartidos y riesgo de doble conteo;
- conciliación oficial vs distribución analítica por producto;
- cierres mensuales y ajustes tardíos;
- cobertura de meses históricos;
- límites históricos reales por endpoint;
- `206`, `404/not_found`, `429`, `5xx` y recursos ya no disponibles.

### C. Históricos y auditor/reparador

Objetivo KISS:

`cuenta + día/mes → IDs esperados ML → IDs locales → faltantes → encolar sólo faltantes → verificar`

Sin crear otro engine, cola o scheduler.

Debe poder distinguir como mínimo:

- no iniciado;
- parcial;
- completo;
- no disponible por API;
- requiere fuente externa/ERP1.

Estos estados son conceptuales durante la auditoría; sólo se persistirán si se demuestra que realmente hacen falta.

### D. Fechas y zonas horarias

Hipótesis forense a comprobar, no asumir:

1. frontera mensual construida incorrectamente;
2. campo de fecha incorrecto para clasificar la venta;
3. paginación incompleta;
4. importador y auditor usando reglas distintas;
5. PHP/MySQL/Hostinger aplicando timezone implícita;
6. Financial usando fecha contable diferente a `date_created` de la orden.

Reglas ya aprobadas:

- hora del servidor no decide el mes de una venta;
- `created_at`/`updated_at` locales no sustituyen la fecha fuente ML;
- fecha ML se parsea con offset explícito y se normaliza determinísticamente;
- repetir una auditoría sobre misma cuenta/fuente/ventana debe devolver el mismo conjunto salvo cambio remoto documentable.

### E. Facturación electrónica

Separar estrictamente:

1. Billing/comisiones de Mercado Libre;
2. factura electrónica DIAN al comprador;
3. PDF/XML que ERP puede adjuntar a la venta/pack en Mercado Libre.

Auditar flujo futuro manual/API/RPA Windows sin acoplar ERP a un proveedor concreto.

### F. Escrituras Mercado Libre

Preparar contratos por dominio desde ahora, pero mantener `meli_writes_enabled=OFF`.

Clasificar por efecto real, no sólo por verbo HTTP:

- READ;
- MUTATION;
- ACTION;
- FINANCIAL;
- DESTRUCTIVE.

F16 debe tender a certificar/habilitar escrituras ya diseñadas por módulo, no a descubrirlas tarde.

### G. Referencias de API en GitHub

`ApiMercadolibre` será la fuente canónica completa. ERP2 debe tener sólo un snapshot curado/versionado de referencias relevantes para los módulos implementados, con commit origen y actualización controlada. No duplicar el corpus entero.

## 6. Hallazgos confirmados hasta ahora

### H1 — ERP1 Billing order-by-order fue una fuente de amplificación

La especificación anterior ya auditó `BILLING_ORDER_IDS_PER_CALL = 1`. V3 debe conservar la propiedad necesaria (reparación puntual) pero no el patrón histórico masivo.

### H2 — ERP1 aprendió después a agrupar por pack y separar total oficial de distribución analítica

Debe rescatarse el aprendizaje, no su maquinaria legacy.

### H3 — ERP2 F4 Sales actual es insuficiente para el modelo financiero final

El slice actual guarda principalmente orden, ítems, total y precio unitario. V3 debe decidir qué datos adicionales pertenecen realmente a Sales/Shipping/Financial sin convertir `orders` en una tabla gigante.

### H4 — Casos reales demuestran que envío y neto no son una resta simple

Existen ventas donde el comprador aporta al envío, ML cobra otro importe y el vendedor soporta sólo la diferencia. Deben conservarse componentes reales cuando la API/Billing los suministre.

### H5 — Comisión puede ser por ítem

Packs con múltiples productos muestran cargos por venta separados por ítem. El resumen de venta puede agregarlos, pero la evidencia detallada no debe perderse.

### H6 — Valor a facturar != neto recibido

Descuentos comerciales determinan el monto facturable al comprador; comisiones, envíos asumidos y retenciones ML determinan el neto del vendedor y no deben mezclarse.

### H7 — No confiar en recomputar el neto oficial desde números visibles

Puede existir diferencia de redondeo entre componentes mostrados y el total oficial. ERP debe conservar el total oficial y usar el cálculo propio para conciliación, no sustituir la fuente.

### H8 — Riesgo de fechas ya tiene evidencia concreta en ERP1

ERP1 terminó incorporando normalización explícita de timestamps y rangos locales→UTC, pero su reparador de fechas selecciona inicialmente las órdenes del período utilizando `meli_orders.date_created` ya persistido. Sólo después de seleccionar esas filas relee la evidencia original y recalcula `date_created`, `date_closed` y `last_updated`.

Consecuencia forense: una orden que ya esté clasificada en el mes equivocado puede quedar fuera del conjunto de reparación del mes correcto. Esto es una **debilidad circular demostrada por código** y una causa plausible del síntoma histórico de meses que cambiaban al reauditarse. Aún no se declara como causa única.

### H9 — ERP1 tenía más de una semántica de “mes”

El código real muestra al menos tres líneas temporales distintas:

- Sales/históricos: `order.date_created`;
- reporte financiero mensual heredado: `payment.date_approved` / `date_approved_local`;
- Billing: período/documento de Billing.

Por tanto, V3 no creará una abstracción genérica de “business month” aplicable a todo. La fuente temporal se define **por dominio**.

### H10 — El importador y el auditor de ERP1 no eran equivalentes

El importador histórico de órdenes usó `/orders/search`, `order.date_created.from/to`, orden descendente y paginación por `offset/limit`, avanzando por cantidad de resultados y usando `paging.total` como referencia.

El auditor exacto posterior añadió comprobaciones que el importador básico no tenía: cobertura temporal, hash de páginas, total remoto cambiante, páginas repetidas, huecos, offset inesperado, páginas intermedias incompletas y conteo de IDs únicos.

Conclusión V3: **importar correctamente y demostrar cobertura son responsabilidades distintas**, pero deben compartir el mismo contrato de rango/fecha. No se portarán dos motores independientes.

### H11 — La idea útil del reparador ERP1 es mínima: reparar sólo faltantes

`SalesAuditExactRepairService` materializa sólo IDs clasificados como faltantes, consulta cada uno de forma exacta, considera 404 histórico como no disponible, difiere 429 y vuelve a auditar al final.

Esto confirma la dirección KISS para ERP2:

`captura esperada → comparar IDs → reparar sólo faltantes → verificar`

No se portan sus colas, leases, adapters y engines adicionales; se reutiliza `work_items` + `MeliClient`.

### H12 — El “límite de 12 meses” de ERP1 era heurístico, pero Sales actual sí documenta 12 meses

ERP1 usaba `sales_control.remote_window_months` con valor predeterminado 12 y rango configurable 1–24; ese código declaraba explícitamente que era una aproximación local, no una capacidad descubierta de Mercado Libre.

La documentación oficial vigente de Mercado Libre, verificada 2026-10-09 en `https://developers.mercadolibre.com.co/gestiona-ventas`, sí documenta para `/orders/search` disponibilidad de órdenes creadas hasta 12 meses.

Regla: esos 12 meses se aplican al **search de Sales**. No se generalizan automáticamente a GET exacto, Billing, pagos, envíos ni otras familias.

### H13 — `/orders/search` para vendedor no representa “todas las órdenes conocidas”

La documentación vigente indica que la búsqueda como vendedor filtra órdenes canceladas. Por tanto, el conjunto devuelto por `/orders/search` y el universo local de órdenes previamente vistas por webhook/GET exacto pueden diferir de forma legítima.

V3 debe definir “completo” como cobertura respecto de una **fuente y semántica declaradas**, no como igualdad ingenua entre todas las filas locales y el search actual.

Las cancelaciones ya conocidas localmente no deben borrarse ni convertirse en errores sólo porque una captura posterior de seller search no las liste.

### H14 — El filtro y el orden de `/orders/search` no usan necesariamente la misma fecha

ERP2 F4 actualmente filtra por `order.date_created.from/to` y usa `sort=date_asc`.

La documentación vigente de Mercado Libre indica que, para búsquedas del vendedor, `date_asc/date_desc` ordenan por `date_closed`; el filtro temporal elegido puede ser `date_created`, `date_closed` o `date_last_updated`.

Esto crea una superficie real de inestabilidad para paginación `offset`: el conjunto se filtra por creación pero el orden puede variar cuando cambia `date_closed`. No se declara aún una solución de cursor inexistente; V3 exige **prueba de cobertura/snapshot** antes de marcar un período completo.

### H15 — La granularidad documentada del filtro de fecha es por hora

La documentación actual señala que los filtros de fecha de Orders toman la hora e ignoran minutos, segundos y milisegundos.

Consecuencia: ERP2 no debe asumir que enviar fronteras con precisión arbitraria produce una frontera remota con esa misma precisión. La Especificación V3 deberá congelar ventanas compatibles con la granularidad real y probar fronteras mensuales explícitamente.

### H16 — Billing period-first está alineado con la recomendación oficial actual

La documentación oficial vigente de Provisiones/Billing recomienda consultar por período, paginar con `from_id`/`last_id`, ordenar por ID, cachear y evitar consultas masivas por orden.

Contrato confirmado para F6A:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
document_type=BILL|CREDIT_NOTE
limit <= 1000
from_id=<cursor>
sort_by=ID
order_by=ASC
```

`206` no es final; `429` exige disminuir ritmo/reusar cache; no se necesita limiter nuevo.

La dirección F6A Task 1 sigue siendo válida. El freeze de Task 2 se mantiene porque aún faltan semánticas de completitud, fixtures y modelo mínimo de relación financiera.

### H17 — La respuesta Billing contiene relaciones que el esquema F6A todavía no modela, deliberadamente

La documentación actual expone, entre otros:

- `detail_id`, tipo/subtipo, monto y fecha;
- estado documental/processing;
- `sales_info[]` con orden/operación/fecha/importe;
- `shipping_info` con shipping/pack y aporte de envío del receptor;
- `items_info` con item/importe/precio/order;
- documento, marketplace y moneda.

Esto demuestra que el esquema mínimo `billing_periods + billing_details` de F6A Task 1 **no es todavía el modelo F6B final**. No es un defecto de Task 1: la expansión se había diferido correctamente hasta obtener evidencia.

No se añade aún una columna `order_id` ni una tabla puente por intuición. `sales_info` es una colección; la cardinalidad real debe quedar probada por fixtures/capturas MCO antes de escoger la forma mínima.

### H18 — ERP2 F4 normaliza correctamente el instante remoto, pero todavía no demuestra cobertura histórica

`SyncOrderHandler` interpreta timestamps remotos y los convierte a UTC antes de persistirlos; `last_updated` protege contra sobrescritura stale. Esto rescata la propiedad correcta de tiempo remoto.

Sin embargo, `ReconcileOrdersHandler` actual sólo procesa páginas y encola GET exactos. Aún no demuestra por sí solo:

- estabilidad del total entre páginas;
- ausencia de páginas/IDs repetidos;
- cobertura canónica del período;
- que el conjunto no se movió mientras se recorría con offset;
- semántica correcta para canceladas ausentes del seller search.

Por tanto, un `orders.reconcile` técnicamente completado **no equivale** a `MES_COMPLETO`.

### H19 — F6A Task 1 sigue siendo un contrato de ingestión, no de resultado financiero

`005_billing.sql` mantiene únicamente identidad de período/documento, cursor/estado/parcial y detalle normalizado mínimo. Esto es compatible con el objetivo period-first y evita adelantar decisiones financieras.

La conciliación de neto, cargos compartidos, impuestos, shipping split, asignación por item y ajustes tardíos pertenece a F6B después de fixtures verificables.

## 7. Matriz de decisiones — borrador vivo

| Área ERP1/ERP2 | Valor | Problema | Decisión V3 provisional |
|---|---|---|---|
| Webhook-first Sales ERP2 | Alto | no cubre histórico por sí solo | CONSERVAR |
| `orders.reconcile` ERP2 | Alto | no prueba por sí solo cobertura estable | MEJORAR SIN OTRO ENGINE |
| Billing period-first ERP2 | Alto | falta completitud/Financial | CONSERVAR Y COMPLETAR |
| Billing order-by-order ERP1 | Bajo como ingestión | amplificación / 404 / 429 | NO PORTAR; sólo repair puntual si caso real lo exige |
| Pack grouping ERP1 | Alto | implementación legacy compleja | RESCATAR CONCEPTO, SIMPLIFICAR |
| Financial revisions ERP1 | Medio/alto | demasiada maquinaria | RESCATAR PROPIEDAD DE EVIDENCIA/VERSIONADO, NO ENGINE |
| QueueCore/QueueV4 | Bajo para ERP2 | sobrearquitectura | NO PORTAR |
| múltiples retry/budget/rhythm engines | Bajo | contención de síntomas | NO PORTAR |
| auditor histórico ERP1 | Alto | mucha infraestructura | RESCATAR PRUEBA DE COBERTURA, USAR Work EXISTENTE |
| reparación exacta ERP1 | Alto | infraestructura pesada | RESCATAR `missing only → exact GET → verify` |
| reparador de fechas ERP1 | Bajo tal como está | selección circular por fecha ya persistida | NO PORTAR; prevenir el defecto desde contrato de fechas |
| normalización remota ERP1 | Alto | duplicada en varios servicios | RESCATAR PROPIEDAD, CENTRALIZAR SÓLO SI HAY 2º USO REAL |
| rango `[from,to)` local→UTC ERP1 | Alto | API Orders sólo documenta granularidad horaria | RESCATAR CON ADAPTACIÓN/TEST DE CONTRATO |
| `sales_control.remote_window_months` | Bajo como autoridad | era heurística | NO PORTAR COMO VERDAD; usar capacidad documentada por endpoint |
| raw payload archive permanente | Bajo | PII/storage/ruido | NO PORTAR |
| cálculo Financial con `float` | Bajo | exactitud monetaria | NO PORTAR |

## 8. Criterios que V3 debe convertir en pruebas

1. **MONTH_BOUNDARY_STABLE:** una orden nunca cambia de mes por timezone del servidor.
2. **RECONCILE_REPEATABLE:** misma cuenta/fuente/ventana → mismo conjunto, salvo evidencia remota nueva.
3. **NO_FALSE_COMPLETE:** éxito técnico no implica cobertura completa.
4. **NO_ENDLESS_MISSING_RETRY:** recurso histórico terminal/no disponible no genera bucle eterno.
5. **NO_404_STORM:** IDs inexistentes conocidos no se vuelven a consultar masivamente.
6. **NO_429_STORM:** históricos ceden ante cooldown/pacing y no compiten agresivamente con operación actual.
7. **PACK_NO_DOUBLE_CHARGE:** cargos/envío compartidos no se duplican por orden hija.
8. **OFFICIAL_NET_PRESERVED:** total/neto oficial nunca es reemplazado por recomputación ERP.
9. **INVOICE_NET_SEPARATION:** valor facturable y neto vendedor son conceptos distintos.
10. **AUDIT_REPAIR_MINIMAL:** reparar sólo faltantes, usando Work/MeliClient existentes.
11. **NOISE_REDUCTION_PASS:** toda pieza nueva justifica por qué no puede reutilizar/fusionar/eliminar algo existente.
12. **SELLER_SEARCH_SEMANTICS:** ausencia por filtrado de canceladas no se clasifica automáticamente como corrupción local.
13. **PAGING_COVERAGE_PROVEN:** un recorrido offset no se declara completo sólo porque alcanzó `paging.total` una vez.
14. **DOMAIN_DATE_SOURCE_FIXED:** cada dominio tiene una única fecha fuente documentada y probada.
15. **PARTIAL_NEVER_COMPLETE:** HTTP 206/processing/missing content nunca produce estado financiero completo.

## 9. Matriz provisional de fuentes temporales

| Dominio | Fuente remota candidata/confirmada | Uso | Regla provisional de período | Estado |
|---|---|---|---|---|
| Sales occurrence | `order.date_created` | cuándo nació la venta | normalizar instante remoto; proyectar con TZ de negocio explícita | CONFIRMADO COMO FUENTE DE SALES; regla final de frontera pendiente de test |
| Sales close | `order.date_closed` | confirmación/cierre y sort seller | no usar automáticamente como mes de venta | CONFIRMADO |
| Sales update | `order.last_updated` | stale protection / cambios | nunca define mes de ocurrencia | CONFIRMADO |
| Payment | `date_approved` | evento financiero de pago | dominio Financial, no Sales | EVIDENCIA ERP1/API; regla final pendiente |
| Money release | `money_release_date` | disponibilidad/caja | no confundir con venta/pago | PENDIENTE DE CONTRATO FINAL |
| Billing period | `period_key` | cargos/documentos ML | identidad propia de período Billing | CONFIRMADO |
| Billing detail | fecha remota de detalle / `creation_date_time` | temporalidad del cargo | asociada al período Billing y evidencia remota | CONFIRMADO PARCIALMENTE |
| Local audit/work | `created_at`, `updated_at`, `last_synced_at` | operación | UTC local; nunca período de negocio | CONFIRMADO |

### Regla V3 provisional

No existe una única función `businessMonth(timestamp)` válida para todos los módulos.

Sí debe existir una única regla **por dominio**, documentada y testeada:

```text
fuente remota declarada
→ parsear offset/instante
→ normalización determinística
→ proyección de período según regla del dominio
```

## 10. Auditoría ERP1 importer vs auditor vs repair — conclusión de diseño

### Importer

Responsabilidad correcta:

- descubrir IDs remotos por ventana;
- incorporar/actualizar orden exacta.

Defecto histórico a evitar:

- tratar fin del loop/páginas permitidas como prueba implícita de cobertura.

### Auditor

Responsabilidad correcta:

- demostrar qué fuente/ventana se consultó;
- demostrar que la captura es internamente consistente;
- comparar IDs esperados con IDs locales;
- clasificar faltantes/extras/limitaciones sin mutar silenciosamente historia.

No necesita motor propio; puede materializar evidencia mínima dentro del Work existente.

### Repair

Responsabilidad correcta:

- recibir únicamente faltantes reparables;
- exact GET sólo de esos IDs;
- 404 histórico terminal/no disponible;
- 429/5xx bounded retry por infraestructura ya existente;
- volver a verificar después.

### Invariante

```text
AUDIT no reimporta todo
REPAIR no redescubre todo el período
IMPORT no declara cobertura por sí solo
```

## 11. Gap analysis ERP2 F4/F6A

### F4 — conservar

- webhook como señal;
- `order.sync` exacto;
- `orders.reconcile` sobre Work existente;
- `MeliClient` único;
- stale protection por `last_updated`;
- DECIMAL/string para cantidades exactas;
- identidad empresa/cuenta;
- `pack_id` preservado.

### F4 — mejorar después de Spec V3

No implementar todavía, pero V3 debe convertir en tareas RED concretas:

1. congelar contrato de frontera mensual compatible con granularidad real de Orders;
2. separar “recorrido terminado” de “cobertura demostrada”;
3. detectar/impedir falso complete ante total/páginas/IDs inestables;
4. definir semántica de canceladas excluidas por seller search;
5. añadir reparación mínima sólo si falta ID demostrado;
6. probar reauditoría repetible;
7. probar mes límite y transición timezone;
8. decidir si conservar offset original aporta una segunda necesidad real; no guardarlo por precaución.

### F6A — conservar

- period-first;
- `BILL` / `CREDIT_NOTE` aislados;
- `from_id`/`last_id`;
- una página por ejecución;
- detalle remoto idempotente;
- `206` no final;
- Work/MeliClient existentes;
- sin loop histórico order-by-order.

### F6/F6B — definir antes de código

Con fixtures reales determinar el modelo mínimo para:

- detalle ↔ orden(es);
- pack/shipping;
- item/commission;
- impuestos/retenciones;
- shipping buyer contribution vs ML charge vs efecto vendedor;
- estado PROCESSING/PROCESSED;
- neto/total oficial;
- cálculo analítico separado;
- late adjustment/reapertura auditable.

No fijar todavía tablas/columnas adicionales.

## 12. Casos reales que se convierten en fixtures obligatorios

### Fixture A — pack con dos productos

Evidencia recuperada:

```text
Productos: 99.621
Comisión item 1: 11.698
Comisión item 2: 6.732
Envío: 12.200
Impuestos: 3.567
  retención: 1.230
  ReteIVA: 2.337
Neto oficial: 65.424
```

Debe probar:

- comisión por item;
- cargo compartido de envío una sola vez;
- impuestos separados;
- suma analítica auditable;
- neto oficial preservado aunque el cálculo local tenga diferencia de redondeo.

### Fixture B — shipping split

```text
Producto: 19.990
Cargo venta: 3.698
Aporte comprador a Mercado Envíos: +12.300
Cargo Mercado Envíos: -14.900
Efecto neto envío: -2.600
Total oficial: 13.692
```

Debe probar:

- no colapsar shipping en un único dato cuando la fuente distingue componentes;
- aporte comprador y cargo ML separados;
- efecto vendedor derivable/auditable;
- total oficial preservado.

Los fixtures formales serán sanitizados: sin nombre, documento, dirección, teléfono ni otro PII de comprador.

## 13. Semántica provisional de cobertura histórica

No persistir todavía esta máquina de estados; primero validar que se necesita. Conceptualmente, por `company + account + source/domain + period`:

```text
NO_INICIADO
EN_PROCESO
PARCIAL
COMPLETO
NO_DISPONIBLE_API
REQUIERE_FUENTE_EXTERNA
```

Reglas:

- `COMPLETO` siempre nombra la fuente que lo demuestra;
- Sales completo no implica Financial completo;
- Financial completo no implica Billing completo;
- período fuera del horizonte no se convierte en error/retry infinito;
- una captura parcial no se convierte en cero ni en completo;
- nueva evidencia remota válida puede producir revisión/ajuste auditable, no reescritura silenciosa de historia cerrada.

## 14. Roadmap V3 — estado después de este bloque forense

| Bloque | Estado | Evidencia pendiente |
|---|---|---|
| V3-1 ERP1 históricos/Financial/fechas/meses | SUSTANCIALMENTE AUDITADO | cerrar algunos bordes y fixtures |
| V3-2 ERP1 importer vs auditor/repair | SUFICIENTE PARA DISEÑO | ninguna arquitectura adicional requerida |
| V3-3 contrato oficial Sales/orders | SUSTANCIALMENTE AUDITADO | inclusividad exacta de fronteras; comportamiento GET exacto fuera de search horizon |
| V3-4 Billing/history/partial/rate limit | SUSTANCIALMENTE AUDITADO | late adjustments/período cerrado y evidencia MCO |
| V3-5 matriz fecha/timezone | BORRADOR CON EVIDENCIA | congelar regla final por dominio |
| V3-6 ERP2 schema/behavior gap | EN CURSO AVANZADO | convertir gaps en tareas/spec, sin código aún |
| V3-7 casos reales → fixtures | EVIDENCIA DISPONIBLE | crear fixtures sanitizados formales |
| V3-8 write map | PENDIENTE | mantener writes OFF |
| V3-9 snapshot API curado | PENDIENTE | decidir contenido mínimo/versionado |
| V3-10 Especificación Maestra V3 | PENDIENTE | depende de cierre de gaps críticos |
| V3-11 Plan Maestro V3 | PENDIENTE | depende de Spec V3 |
| V3-12 aprobación usuario | PENDIENTE | obligatoria antes de reabrir F6A Task 2 |

## 15. Pendientes inmediatos de auditoría

1. verificar inclusividad exacta de fronteras de filtros Orders y diseñar prueba adversarial de hora/límite mensual;
2. verificar comportamiento/documentación de GET exacto de orden fuera del horizonte de `/orders/search`;
3. cerrar semántica 404 histórica por fuente sin asumir que todo 404 significa lo mismo;
4. cerrar Billing late adjustments / período cerrado / re-sync explícito;
5. obtener o construir evidencia sanitizada MCO de cardinalidades `sales_info`, `shipping_info`, `items_info`;
6. convertir Fixture A/B en fixtures formales para F6B;
7. decidir regla final de período por Sales/Payment/Cash/Billing;
8. terminar matriz ERP1 → ERP2 y matriz API en documentos dedicados o integrarlas en Spec V3 si reduce ruido;
9. auditar mapa de escrituras futuras con `writes OFF`;
10. diseñar snapshot API curado;
11. producir Especificación Maestra V3;
12. producir Plan Maestro V3 por microbloques TDD.

## 16. Regla de congelación

Hasta cerrar esta auditoría y aprobar V3:

- no iniciar F6A Task 2;
- no añadir tablas productivas nuevas por esta auditoría;
- no habilitar escrituras ML;
- no rediseñar Work/MeliClient salvo evidencia que demuestre un defecto real;
- no hacer merge/deploy desde esta rama documental.

## 17. Siguiente punto exacto

Cerrar los bordes todavía no demostrados del contrato actual de Sales/Billing, formalizar fixtures y transformar esta evidencia en **Especificación Maestra V3 + Plan Maestro V3** antes de volver a código productivo.
