# ERP MELI 2.0 — ESPECIFICACIÓN MAESTRA V3

**Fecha:** 2026-10-09  
**Estado:** DRAFT PARA REVISIÓN — no autoriza código productivo todavía.  
**Rama de autoridad:** `audit/v3-forensic-redesign-20261009`  
**Freeze vigente:** F6A Task 2 permanece congelado hasta aprobación explícita de esta especificación + Plan Maestro V3.  

## 1. Objetivo

ERP MELI 2.0 reemplazará progresivamente ERP1 conservando aprendizajes de negocio y eliminando su complejidad accidental.

El producto debe ser:

```text
Correcto
→ Simple
→ Estable
→ Mantenible
→ Eficiente
→ Escalable
```

La solución correcta más pequeña gana.

## 2. Leyes vinculantes

### 2.1 KISS / noise reduction

Antes de añadir tabla, columna, engine, cola, estado, helper, endpoint o flag:

```text
¿se puede eliminar?
→ ¿derivar?
→ ¿fusionar?
→ ¿reutilizar?
→ ¿extender localmente?
→ sólo entonces añadir
```

### 2.2 Arquitectura base

Permanece:

```text
1 aplicación
1 repositorio
1 base de datos
1 Work durable
1 WorkRunner
1 MeliClient
```

No crear microservicios, brokers, una segunda cola, recovery engine, command bus ni framework genérico de remote writes.

### 2.3 Fuente de verdad

Orden de autoridad:

1. contrato/API oficial vigente + evidencia real del negocio;
2. código/Git/tests reales;
3. decisiones recientes aprobadas;
4. documentación;
5. inferencias explícitamente marcadas.

## 3. Fronteras de dominio

V3 separa estrictamente:

```text
Sales
  venta/orden/pack/items y hechos comerciales

Shipping
  shipment y costos/estado propios del envío

Payment/Cash
  aprobación de pago y liberación de dinero

Billing
  cargos/bonificaciones/documentos fiscales de Mercado Libre

Financial
  conciliación entre hechos de Sales/Shipping/Payment/Billing

Historical
  prueba de cobertura + repair mínimo

Invoicing
  factura DIAN + datos fiscales comprador + PDF/XML

Remote Writes
  operaciones preparadas por slice; activación/certificación posterior
```

Ningún módulo puede usar el timestamp o total de otro dominio como sustituto silencioso de su propia fuente.

## 4. Tiempo, fechas y períodos

### 4.1 Regla general

Toda fecha remota:

```text
valor remoto con offset
→ interpretar el instante
→ UTC persistido
→ proyección al timezone de negocio sólo cuando el dominio lo necesite
```

Prohibido:

- decidir mes por timezone de PHP/Hostinger/MySQL;
- usar `created_at`/`updated_at` local como business date remoto;
- eliminar el significado original de `date_created`, `date_closed`, `date_approved`, `money_release_date` o Billing period.

### 4.2 Timezone de negocio actual

Para el alcance actual MCO, la proyección mensual de negocio usa **`America/Bogota`** explícitamente.

No crear todavía una columna timezone por cuenta. Sólo se generaliza cuando un segundo site/timezone real lo requiera.

### 4.3 Sales month

Fuente: `order.date_created`.

Período canónico:

```text
[primer día del mes 00:00 America/Bogota,
 primer día del mes siguiente 00:00 America/Bogota)
```

Persistencia de órdenes: UTC.

Clasificación mensual: proyectar el instante de `date_created` a `America/Bogota` y aplicar el intervalo canónico.

### 4.4 Otros dominios

| Dominio | Fuente temporal |
|---|---|
| Sales occurrence | `order.date_created` |
| Sales close | `order.date_closed` |
| Sales update/stale | `last_updated` / `date_last_updated` |
| Payment | `payment.date_approved` |
| Cash | `money_release_date` |
| Billing | `period_key` + fecha de detalle/documento |
| Work/audit local | UTC operacional; nunca business month |

No existe una función global `businessMonth(timestamp)` válida para todos los dominios.

## 5. Frontera JSON y dinero exacto

### 5.1 Hallazgo V3

Mercado Libre entrega importes como números JSON. `json_decode()` normal de PHP materializa decimales como `float`.

El gate F4.1 existente demuestra precisión cuando el fixture entrega strings, pero no demuestra que un número JSON real atraviese MeliClient sin pérdida.

### 5.2 Invariante obligatorio

```text
REMOTE_NUMBERS_LOSSLESS
```

Ningún valor monetario puede pasar por `float` antes de persistirse o calcularse.

### 5.3 Dirección de diseño

Las operaciones que contienen dinero exacto deben solicitar decodificación que preserve el **lexema numérico** como string.

Primeros casos reales que justifican la capacidad compartida:

- `orders.get` / `orders.search`;
- `billing.period.details`.

Por existir ya dos dominios reales, una utilidad compartida pequeña en la frontera MeliClient está justificada. No se añade una librería/abstracción mayor si una implementación local pequeña y probada reduce complejidad total.

Después de aplicar esta corrección:

- normalizadores monetarios aceptan `string|int` donde corresponda;
- `float` en un campo de dinero se trata como violación interna de contrato;
- redondeo a DECIMAL(18,4) es determinístico;
- cálculos posteriores usan strings/aritmética decimal o enteros escalados, nunca IEEE-754 como verdad financiera.

## 6. Sales — orden exacta

### 6.1 Identidad

Una orden se identifica por:

```text
company_id + account_id + external_order_id
```

`order_id` nunca se elimina aunque exista `pack_id`.

### 6.2 Persistencia base

Conservar:

- status/status_detail;
- `date_created` UTC;
- `date_closed` UTC;
- `last_updated` UTC;
- total_amount exacto;
- currency;
- pack_id;
- items;
- quantity/unit_price exactos;
- SKU si está disponible.

PII del comprador no se conserva salvo necesidad funcional explícita. El `buyer_id` actual debe revisarse cuando Invoicing defina su necesidad; no expandir datos personales en Sales.

### 6.3 Stale protection

Una respuesta con `last_updated` anterior al valor local no puede sobrescribir estado más nuevo.

## 7. Venta visible y packs

### 7.1 Relaciones actuales

Contrato oficial:

```text
Pack 1:N Orders
Pack 0..1 Shipping
Order 1:N Payments
```

Un pack puede contener órdenes de distintos sellers. El caller sólo ve las órdenes de sellers autorizados para su integración.

### 7.2 Sale key lógica

No crear todavía una tabla `sales`.

Derivar:

```text
si pack_id existe:
  sale_key = company + account + pack_id
si pack_id legacy es null:
  sale_key = company + account + order_id
```

`pack_id` nunca es identidad global multi-cuenta.

### 7.3 Regla de cargos compartidos

Un cargo de pack/shipping compartido se aplica una sola vez a la venta visible, no una vez por child order.

Gate:

```text
PACK_NO_DOUBLE_CHARGE
```

## 8. Historical / Sales audit

### 8.1 Objetivo

Demostrar cobertura de una fuente histórica sin construir un segundo sistema de sincronización.

### 8.2 Source contract

Fuente inicial: seller `/orders/search` filtrado por `order.date_created`.

Limitaciones documentadas:

- search conserva/documenta hasta 12 meses de órdenes creadas;
- seller search excluye canceladas;
- seller sort `date_asc/date_desc` usa `date_closed`;
- filtros de fecha sólo respetan la hora y descartan minutos/segundos/ms;
- paginación usa offset;
- inclusividad exacta de `from/to` no se usa como supuesto.

### 8.3 Frontera robusta

Para un mes canónico:

```text
1. construir [local_from, local_to) en America/Bogota
2. convertir a UTC
3. ampliar request remota con guard-band mínimo de 1 hora a ambos lados
4. consultar /orders/search
5. normalizar date_created exacto de cada resultado
6. conservar como expected sólo IDs cuyo date_created pertenezca al período canónico
```

El guard-band protege frente a granularidad/inclusividad remota; nunca cambia el período canónico.

### 8.4 Cobertura no es “loop terminó”

Un run sólo puede considerarse captura válida si:

- cada página HTTP es utilizable;
- el `paging.total` no cambia dentro de la captura;
- offsets avanzan como se espera;
- no hay IDs duplicados entre páginas;
- no hay página intermedia inesperadamente vacía/incompleta;
- unique expected IDs y total remoto son consistentes después del filtro contractual aplicable;
- no hubo 429/5xx sin resolver.

### 8.5 Seller cancellations

Debido a que seller search excluye canceladas:

```text
missing = expected_remote - local_orders
```

Los `local_orders - expected_remote` son informativos; **no invalidan por sí solos la cobertura** y nunca se borran automáticamente.

Una orden conocida localmente que desaparece del search puede haberse cancelado o haber quedado fuera de la semántica actual de la fuente.

### 8.6 Captura estable

Para certificar por primera vez un período:

```text
captura válida A
→ captura válida B independiente
→ mismo conjunto canónico de expected IDs
→ cobertura verificable
```

No realizar doble captura en cada consulta rutinaria.

Después de una baseline verificada:

- una nueva captura idéntica conserva confianza;
- si cambia el conjunto, el cambio se trata como nueva evidencia;
- IDs nuevos se sincronizan si faltan;
- IDs desaparecidos se verifican de forma exacta sólo cuando sea necesario explicar el cambio;
- un conjunto nuevo necesita confirmación antes de convertirse en nueva baseline verificada.

### 8.7 Repair KISS

```text
expected IDs
vs local IDs
→ missing
→ enqueue sólo missing como order.sync
→ verificar
```

No reimportar todo el período.

### 8.8 Persistencia mínima demostrada

La auditoría cruza múltiples páginas y múltiples work items; por tanto, necesita evidencia durable que `orders` y `work_items` actuales no representan por sí solos.

V3 autoriza **como máximo dos tablas específicas de Sales audit**, sujeto al Plan/RED antes de crearlas:

1. `sales_audit_runs`
   - scope company/account;
   - period_key;
   - source contract;
   - state;
   - initial remote total;
   - page/expected counts;
   - canonical set hash;
   - timestamps.
2. `sales_audit_orders`
   - audit_run_id;
   - external_order_id;
   - remote `date_created` UTC;
   - unique `(audit_run_id, external_order_id)`.

No guardar bodies, buyer data, page JSON ni engine state extra.

La segunda tabla permite detectar duplicados, calcular hash ordenado y comparar expected/local sin cargar todo el mes en el payload de Work.

### 8.9 Estados conceptuales

Persistir sólo los necesarios para operar el run:

```text
capturing
repairing
confirming
verified
invalid
unavailable
attention
```

La UI puede derivar `no iniciado` cuando no hay run.

No crear una máquina de estados genérica multi-dominio.

## 9. Reconcile actual de ERP2

`orders.reconcile` se reutiliza; no se crea `HistoricalEngine`.

Debe evolucionar de “pagina y encola” a “captura verificable” manteniendo una página remota por ejecución.

Se reutilizan:

- Work;
- WorkRunner;
- MeliClient;
- `order.sync`;
- cooldown/retry existente.

## 10. Billing F6A — period-first

### 10.1 Identidad

```text
company
+ account
+ period_key
+ document_type (BILL|CREDIT_NOTE)
```

### 10.2 Endpoint primario

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
```

Contrato ERP2:

```text
limit=1000
from_id=<cursor>
sort_by=ID
order_by=ASC
una página por ejecución
```

### 10.3 Semántica de F6A Task 2

Después de aprobación V3, `BillingPeriodSyncHandler` debe:

1. cargar período scopeado;
2. pedir exactamente una página;
3. validar respuesta y cursor;
4. persistir sólo una respuesta 200 utilizable;
5. upsert idempotente por `(billing_period_id, external_detail_id)`;
6. avanzar cursor sólo si `last_id` progresa;
7. encolar/reintentar la siguiente página usando Work existente;
8. considerar el pass `caught_up` sólo después de una página 200 sin nuevos resultados;
9. mantener `caught_up` separado del concepto fiscal “período cerrado/final”.

### 10.4 HTTP 206

Para Billing reports:

```text
206 = parcial/incompleto
```

Regla mínima:

- no avanzar cursor;
- no declarar caught-up/complete;
- no exponer esa página como evidencia financiera final;
- marcar `partial_flag=1`;
- retry bounded en el mismo `from_id` usando Work.

V3 prefiere no persistir una página Billing 206 hasta recibir una respuesta completa, porque evita mezclar evidencia parcial con cálculo financiero.

### 10.5 429 / 5xx / 404

- 429: MeliClient cooldown + retry del mismo work;
- 5xx/transport: retry bounded existente;
- 404/período no disponible: atención/terminal para esa ejecución, no loop infinito;
- auth: flujo OAuth existente.

No crear limiter ni retry engine Billing.

### 10.6 Cursor

Con resultados no vacíos:

- `last_id` debe existir;
- debe ser distinto/progresivo respecto al cursor solicitado según el contrato ID ASC;
- si no progresa, fail/attention sin avanzar.

Para terminar un pass, V3 prefiere una página 200 vacía explícita antes que inferir fin sólo porque `count(results) < limit`. Es una llamada adicional por pass y simplifica la prueba de completitud.

## 11. Billing cache mínimo y contexto financiero

### 11.1 Tablas actuales

Conservar:

- `billing_periods`;
- `billing_details`.

No crear todavía un “Financial engine”.

### 11.2 Expansión mínima previa a F6A Task 2

La evidencia oficial ya demuestra dos necesidades que el esquema actual no conserva:

1. `legal_document_status` (`PROCESSING|PROCESSED|...`);
2. relación allowlisted de detalle con ventas/items/shipping.

Para evitar tres tablas prematuras, V3 propone inicialmente:

- añadir `legal_document_status VARCHAR(...) NULL` a `billing_details`;
- añadir `context_json JSON NULL` **curado**, nunca raw.

`context_json` sólo puede contener allowlist no-PII:

```json
{
  "sales": [
    {
      "order_id": "...",
      "operation_id": "...",
      "sale_date_time": "...",
      "transaction_amount": "..."
    }
  ],
  "shipping": {
    "shipping_id": "...",
    "pack_id": "...",
    "receiver_shipping_cost": "..."
  },
  "items": [
    {
      "item_id": "...",
      "order_id": "...",
      "item_amount": "...",
      "item_price": "..."
    }
  ]
}
```

Todas las cantidades monetarias dentro del JSON curado se serializan como strings exactos.

Excluir expresamente:

- nickname;
- buyer/payer identity;
- dirección;
- teléfono;
- email;
- body remoto completo;
- claves desconocidas.

Normalizar a tablas hijas sólo si F6B demuestra consultas/integridad que el JSON curado no puede resolver limpiamente.

## 12. Billing OPEN/CLOSED y ajustes tardíos

La documentación oficial indica:

- OPEN puede cambiar diariamente;
- CLOSED suele estabilizar;
- existen excepciones posteriores como bonificaciones/devoluciones/documentos.

Reglas:

- `caught_up` local no significa `CLOSED` fiscal;
- `CLOSED` no significa inmutable para siempre;
- no polling agresivo;
- una revalidación explícita de un período reinicia el cursor en 0 y hace upsert idempotente;
- no borrar detalles anteriores porque una consulta nueva devuelva menos;
- un nuevo detail/credit note es nueva evidencia;
- si se demuestra en MCO que un mismo `detail_id` puede mutar materialmente, F6B añadirá la mínima revisión histórica necesaria. No crearla antes de esa evidencia.

## 13. Financial F6B

### 13.1 Principio

Financial no inventa una verdad única. Concilia fuentes.

```text
Sales facts
+ Shipping facts
+ Payment/Cash facts
+ Billing facts
→ vista financiera de venta
```

### 13.2 Oficial vs analítico

Siempre separar:

- valor oficial/remoto cuando exista una fuente demostrada;
- cálculo analítico ERP;
- delta de conciliación.

Nunca sobrescribir el valor oficial con una fórmula local.

Si no existe aún una fuente API demostrada para “neto oficial”, ERP no puede etiquetar su cálculo como oficial.

### 13.3 Comisión

Puede ser item-level.

Nunca distribuir una comisión total inventando proporciones si la fuente entrega la comisión por item.

### 13.4 Shipping

Cuando la fuente distingue:

- aporte comprador;
- cargo ML;
- costo/efecto vendedor;

se conservan como componentes distintos.

Prohibido un único `shipping_cost` ambiguo como modelo financiero final.

### 13.5 Impuestos/retenciones

Conservar los componentes necesarios para reconciliar. No colapsar todo a `tax_total` si la fuente separa conceptos relevantes al negocio.

### 13.6 Ajustes/bonificaciones

Credit notes/bonificaciones son nuevos hechos financieros, no edición destructiva del cargo original.

## 14. Fixtures financieros obligatorios

Fuente auditada:

`docs/fixtures/financial_real_cases_v3.json`

### Caso A

Debe probar:

```text
99621 - 11698 - 6732 - 12200 - 3567 = 65424
1230 + 2337 = 3567
```

Invariantes:

- fee por item;
- shipping compartido una vez;
- impuestos separados;
- official net preservado.

### Caso B

Debe probar:

```text
12300 - 14900 = -2600
19990 - 3698 + 12300 - 14900 = 13692
```

Invariantes:

- buyer contribution y ML charge separados;
- net shipping derivable;
- official net preservado.

Los fixtures no contienen PII y no pretenden ser payloads API.

## 15. Shipping

Shipping se implementará como slice sólo cuando sea necesario para Financial/Inventory/operación.

Fuente propia de Shipping, no Order embebido, para datos que el endpoint de Orders ya no garantiza completos.

No almacenar direcciones por defecto.

## 16. Invoicing

Tres fronteras distintas:

```text
A. Billing Mercado Libre
B. factura electrónica DIAN/proveedor
C. adjunto PDF/XML en la venta de Mercado Libre
```

No compartir estados como si fueran la misma operación.

Flujo futuro:

```text
ERP prepara datos fiscales
→ provider/API/RPA emite DIAN
→ ERP registra resultado
→ acción separada puede adjuntar documento a ML
```

La integración de proveedor debe depender de una interfaz pequeña, no de un vendor en el core.

## 17. Remote Writes

Fuente:

`docs/ERP_MELI_2_0_WRITE_MAP_V3.md`

Global default:

```text
meli_writes_enabled = false
```

F16 certifica/habilita por operación.

No crear `RemoteWriteEngine`.

Cada slice futuro:

```text
validación permiso/intención
→ Work específico
→ MeliClient operation allowlisted
→ resultado
→ persistencia/audit del slice
```

Writes inicialmente identificados:

- precio;
- stock;
- pause/active;
- close/delete;
- adjuntar/eliminar fiscal documents.

Todo permanece OFF hasta su fase/gates.

## 18. Error semantics

### 18.1 Principio

HTTP técnico y significado de negocio son capas distintas.

### 18.2 Reglas

| Señal | Regla V3 |
|---|---|
| 2xx | MeliClient puede devolver respuesta; handler decide completitud |
| 206 Orders | evaluar campos críticos; no fallo global automático |
| 206 Billing | parcial; nunca complete/caught_up |
| 401 | refresh/reauth existente |
| 403 | permiso/alcance; no retry infinito |
| 404 exact historical | clasificar por operación; puede ser terminal/unavailable |
| 409 write | conflicto; nunca retry ciego |
| 429 | cooldown; mismo work, sin fan-out |
| 5xx/transport | retry bounded si la operación es segura |

## 19. Privacidad y observabilidad

- Debug DVR sigue bounded/sanitized.
- No raw API archive permanente.
- No PII en fixtures.
- No PII en `context_json` Billing.
- No tokens/secrets en logs.
- Correlación con work ID existente; no UUID adicional sin necesidad.

## 20. Datos derivados vs persistidos

Derivar primero:

- sale_key;
- totals analíticos;
- estados de UI que resulten de evidencia existente;
- net shipping si sus componentes están presentes.

Persistir sólo cuando la recomputación sea imposible, cara o deba conservar evidencia histórica.

## 21. Gates V3

Ninguna fase puede declararse terminada sólo porque “el código corre”.

```text
REMOTE_NUMBERS_LOSSLESS
MONTH_BOUNDARY_STABLE
DOMAIN_DATE_SOURCE_FIXED
SELLER_SEARCH_SEMANTICS
PAGING_COVERAGE_PROVEN
RECONCILE_REPEATABLE
NO_FALSE_COMPLETE
AUDIT_REPAIR_MINIMAL
NO_ENDLESS_MISSING_RETRY
NO_404_STORM
NO_429_STORM
PACK_NO_DOUBLE_CHARGE
SHIPPING_COMPONENTS_PRESERVED
OFFICIAL_NET_PRESERVED
INVOICE_NET_SEPARATION
PARTIAL_NEVER_COMPLETE
API_CONTRACT_SNAPSHOT_CURRENT
NOISE_REDUCTION_PASS
WRITES_DEFAULT_OFF
```

## 22. Validaciones externas todavía abiertas

No bloquean escribir el Plan, pero sí el gate correspondiente antes de producción:

1. smoke real de frontera `/orders/search` con guard-band;
2. comportamiento real de `GET /orders/{id}` para orden >12 meses si existe caso de prueba;
3. captura MCO sanitizada de Billing para confirmar cardinalidades reales de `sales_info/items_info/shipping_info`;
4. observar si un `detail_id` Billing existente muta o si ajustes aparecen como nuevos detalles/documentos;
5. app OAuth dedicada real + seller authorization;
6. límites reales Hostinger/cron;
7. branch protection antes de integración productiva.

Las incertidumbres externas se registran; no se rellenan con arquitectura especulativa.

## 23. Criterio para reabrir F6A Task 2

Task 2 sólo se reabre cuando:

1. esta Especificación V3 esté revisada/aprobada por el usuario;
2. exista Plan Maestro V3 microbloqueado TDD;
3. `REMOTE_NUMBERS_LOSSLESS` esté colocado antes de cualquier persistencia financiera nueva;
4. el Plan incluya explícitamente 206/cursor/caught-up/404/429;
5. cualquier migración adicional esté justificada por esta especificación.

Reabrir Task 2 **no** equivale a habilitar writes, merge ni deploy.

## 24. Fuentes V3 del repositorio

- `docs/audits/ERP_MELI_2_0_AUDITORIA_FORENSE_V3_2026-10-09.md`
- `docs/ERP_MELI_2_0_MATRIZ_ERP1_ERP2.md`
- `docs/ERP_MELI_2_0_MATRIZ_API_MERCADOLIBRE.md`
- `docs/ERP_MELI_2_0_WRITE_MAP_V3.md`
- `docs/api/MERCADOLIBRE_API_SNAPSHOT_V3_2026-10-09.md`
- `docs/fixtures/financial_real_cases_v3.json`
- corpus `sierraglobalcompany-rgb/ApiMercadolibre@eeb0bc9d944e2fad58c13a47ccc7660413fa6193`

## 25. Decisiones explícitamente NO tomadas

V3 todavía no autoriza:

- tabla `sales`;
- tablas Financial genéricas;
- 3+ tablas de relaciones Billing si `context_json` curado basta;
- nuevo queue/recovery engine;
- scheduler Billing dedicado;
- archivo raw permanente;
- per-account timezone sin segundo caso;
- cálculo etiquetado “neto oficial” sin fuente demostrada;
- remote writes;
- merge/deploy.
