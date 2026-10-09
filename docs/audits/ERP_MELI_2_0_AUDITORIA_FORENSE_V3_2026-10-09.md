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

### H8 — Riesgo de fechas aún abierto

ERP2 normaliza fechas remotas a UTC en Sales, pero V3 debe verificar de extremo a extremo rangos mensuales, sesión DB, parámetros enviados a ML, selección del campo fuente y comparación con Financial antes de declarar resuelto el problema histórico de ERP1.

## 7. Matriz de decisiones — borrador vivo

| Área ERP1/ERP2 | Valor | Problema | Decisión V3 provisional |
|---|---|---|---|
| Webhook-first Sales ERP2 | Alto | no cubre histórico por sí solo | CONSERVAR |
| `orders.reconcile` ERP2 | Alto | auditar fechas/paginación/cobertura | MEJORAR SIN OTRO ENGINE |
| Billing period-first ERP2 | Alto | falta modelo de cobertura/Financial | CONSERVAR Y COMPLETAR |
| Billing order-by-order ERP1 | Bajo como ingestión | amplificación / 429 | NO PORTAR; dejar sólo reparación puntual |
| Pack grouping ERP1 | Alto | implementación legacy compleja | RESCATAR CONCEPTO, SIMPLIFICAR |
| Financial revisions ERP1 | Medio/alto | auditar necesidad exacta | RESCATAR PROPIEDAD, NO MAQUINARIA |
| QueueCore/QueueV4 | Bajo para ERP2 | sobrearquitectura | NO PORTAR |
| múltiples retry/budget/rhythm engines | Bajo | contención de síntomas | NO PORTAR |
| auditor/reparador histórico | Alto | ERP1 poco humano/complicado | REDISEÑAR KISS SOBRE Work existente |
| raw payload archive permanente | Bajo | ruido/storage | NO PORTAR |

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

## 9. Pendientes inmediatos de auditoría

1. reconstruir en ERP1 el flujo exacto de históricos/Financial/meses;
2. localizar cómo ERP1 generaba rangos y timezone;
3. comparar importación vs auditoría ERP1;
4. verificar contrato actual de `/orders/search` para fechas/paginación/errores/horizonte;
5. verificar contrato Billing actual por período y horizonte disponible;
6. mapear `date_created`, `date_closed`, `last_updated`, fechas de pago/envío y fechas Financial;
7. auditar esquema ERP2 F4/F6A y decidir cambios mínimos;
8. convertir casos reales adjuntos en fixtures funcionales de V3;
9. consolidar mapa de escrituras útiles ML;
10. diseñar snapshot curado `ApiMercadolibre` → ERP2;
11. producir Especificación Maestra V3;
12. producir Plan Maestro V3 por microbloques TDD.

## 10. Regla de congelación

Hasta cerrar esta auditoría y aprobar V3:

- no iniciar F6A Task 2;
- no añadir tablas productivas nuevas por esta auditoría;
- no habilitar escrituras ML;
- no rediseñar Work/MeliClient salvo evidencia que demuestre un defecto real;
- no hacer merge/deploy desde esta rama documental.

## 11. Siguiente punto exacto

**Auditar ERP1 histórico/Financial/fechas** y documentar evidencia concreta antes de diseñar el reparador o modificar F6.
