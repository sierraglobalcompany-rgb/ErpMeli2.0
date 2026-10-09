# ERP MELI 2.0 — WRITE MAP V3

**Fecha de verificación:** 2026-10-09  
**Estado:** DISEÑO/CONTRATOS — todas las escrituras remotas permanecen OFF.  
**Regla:** F16 certifica/habilita contratos ya conocidos; no crea un framework genérico de writes.

## 1. Principio

Toda operación remota se clasifica por efecto real:

```text
READ
MUTATION
ACTION
FINANCIAL
DESTRUCTIVE
```

`meli_writes_enabled=false` sigue siendo el default obligatorio.

No registrar una operación WRITE en `meli_operations.php` hasta que el módulo correspondiente esté aprobado y exista test de contrato + guard de autorización.

## 2. Writes que sí tienen una necesidad futura demostrable

| Dominio | Operación oficial | Efecto | Clasificación V3 | Fase propietaria | Estado |
|---|---|---|---|---|---|
| Catalog | `PUT /items/{item_id}` con `price` | cambia precio publicado | MUTATION | F7/F16 | CONTRATO CONOCIDO, OFF |
| Inventory | `PUT /items/{item_id}` con `available_quantity` | cambia stock remoto; 0 puede pausar por out_of_stock | MUTATION | F8/F16 | CONTRATO CONOCIDO, OFF |
| Catalog | `PUT /items/{item_id}` con `status=paused|active` | pausa/reactiva publicación | ACTION | F7/F16 | CONTRATO CONOCIDO, OFF |
| Catalog | `PUT /items/{item_id}` con `status=closed` | finaliza publicación; no se reactiva | DESTRUCTIVE | F7/F16 | CONTRATO CONOCIDO, OFF |
| Catalog | `PUT /items/{item_id}` con `deleted=true` después de closed | elimina publicación | DESTRUCTIVE | F7/F16 | CONTRATO CONOCIDO, OFF |
| Invoicing | `POST /packs/{pack_id}/fiscal_documents` | adjunta factura PDF/XML a la venta | FINANCIAL | F10/F16 | CONTRATO CONOCIDO, OFF |
| Invoicing | `DELETE /packs/{pack_id}/fiscal_documents` | elimina documentos fiscales adjuntos | DESTRUCTIVE | F10/F16 | CONTRATO CONOCIDO, OFF |

## 3. Writes que NO se incorporan todavía

No registrar por anticipación:

- preguntas/respuestas;
- mensajes posventa;
- tags de órdenes;
- promociones;
- reclamos/mediaciones;
- devoluciones/refunds;
- edición de imágenes/descripciones/títulos;
- cambios de logística;
- cualquier acción Mercado Pago;
- acciones administrativas destructivas adicionales.

Motivo: no existe todavía un caso de negocio aprobado que justifique contrato, UI, permisos, audit trail y gates. F12 PostSale continúa condicionado a necesidad real.

## 4. Reglas comunes de certificación F16

Cada write futuro debe demostrar antes de habilitarse:

1. **owner module:** sólo un slice es dueño de la operación;
2. **tenancy:** company/account del usuario coincide con el recurso;
3. **RBAC:** rol explícito para la acción;
4. **CSRF:** cualquier disparo web mutante está protegido;
5. **explicit intent:** ninguna mutación ocurre por GET, redirect, page load o simple lectura;
6. **idempotency:** reintentar no debe producir un efecto duplicado cuando el contrato lo permita;
7. **precondition/read-before-write:** cuando sea necesario para evitar overwrites stale;
8. **safe retry:** 429/5xx sólo se reintentan si repetir es seguro;
9. **conflict policy:** 409/optimistic-locking no se convierte en loop agresivo;
10. **audit:** guardar quién/qué/cuándo/resultado sin PII/raw innecesario;
11. **dry/preview where useful:** mostrar cambio antes de operación destructiva o financiera;
12. **global kill switch:** `meli_writes_enabled=false` bloquea la salida remota;
13. **operation allowlist:** sólo operaciones certificadas pueden cruzar el cliente;
14. **real smoke:** test real controlado antes de producción;
15. **rollback/recovery:** documentar qué puede revertirse y qué es irreversible.

## 5. Catalog / Inventory

### 5.1 Precio

Fuente oficial actual: sincronización/modificación de publicaciones permite `PUT /items/{id}` con `price`.

Regla de diseño:

- F7 podrá preparar intención de precio;
- F16 habilita envío remoto;
- no mezclar el estado local deseado con confirmación remota;
- después del PUT, GET remoto o webhook debe confirmar el valor observado cuando el caso lo requiera.

### 5.2 Stock

`available_quantity` es un write de inventario con efectos secundarios documentados:

- `0` puede pausar por `out_of_stock`;
- volver a >0 puede reactivar cuando el subestado es `out_of_stock`.

Por tanto, no es una simple asignación numérica sin semántica de estado.

Regla:

- Inventory calcula stock local;
- el write remoto se emite sólo cuando F16 esté habilitado;
- dedupe por `account + item/variation + desired_quantity`;
- no crear un segundo queue engine: usar Work.

### 5.3 Pausar/reactivar

`status=paused|active` se clasifica ACTION, no simple MUTATION, porque cambia disponibilidad comercial.

Debe exigir intención explícita/permiso y mantener auditoría.

### 5.4 Cerrar/eliminar

`closed` es irreversible como publicación activa; `deleted=true` elimina después del cierre.

Reglas adicionales:

- confirmación reforzada;
- nunca reintento ciego de una secuencia parcialmente ejecutada;
- recuperar estado remoto antes de decidir el segundo paso;
- no automatizar eliminación como mantenimiento rutinario.

## 6. Invoicing: documentos fiscales en Mercado Libre

Contrato oficial actual:

```text
POST /packs/{pack_id}/fiscal_documents
GET  /packs/{pack_id}/fiscal_documents
GET  /packs/{pack_id}/fiscal_documents/{fiscal_document_id}
DELETE /packs/{pack_id}/fiscal_documents
```

Para orden legacy con `pack_id=null`, la documentación indica usar el `order_id` como identificador en el recurso `/packs/{id}`.

La carga admite PDF y XML según contrato vigente; el tamaño/formato deberá revalidarse al implementar F10.

### Separación obligatoria

```text
DIAN/provider genera documento
        ↓
ERP conserva referencia/estado
        ↓
acción separada: adjuntar a Mercado Libre
```

Subir a ML no significa emitir DIAN y emitir DIAN no significa que el adjunto ML se haya cargado.

### Delete fiscal document

DELETE elimina los documentos que el vendedor cargó en el pack y es **DESTRUCTIVE**.

Debe requerir:

- permiso alto;
- confirmación reforzada;
- identificación exacta del pack/sale scope;
- evidencia previa de documentos existentes;
- audit log;
- no retry automático ante resultado incierto.

## 7. Lo que F16 NO debe construir

No crear:

- `RemoteWriteEngine` genérico;
- otra cola;
- otro scheduler;
- saga framework;
- command bus;
- policy DSL;
- rollback engine universal.

Patrón esperado:

```text
vertical slice
→ valida permiso/intención
→ encola work específico
→ MeliClient operation allowlisted
→ resultado exacto
→ persistencia/audit del slice
```

Generalizar sólo cuando dos writes reales compartan código y extraerlo reduzca complejidad.

## 8. Gates de F16 derivados

```text
WRITES_DEFAULT_OFF
WRITE_OPERATION_ALLOWLIST
WRITE_TENANCY_PASS
WRITE_RBAC_PASS
WRITE_CSRF_PASS
WRITE_IDEMPOTENCY_PROVEN
WRITE_RETRY_SAFETY_PROVEN
WRITE_AUDIT_SAFE
DESTRUCTIVE_CONFIRMATION_PASS
REAL_WRITE_SMOKE_CONTROLLED
REMOTE_RESULT_RECONCILED
```

Estos gates son diseño, no están PASS todavía.

## 9. Fuentes oficiales verificadas

- `https://developers.mercadolibre.com.co/es_co/producto-sincroniza-modifica-publicaciones`
- `https://developers.mercadolibre.com.co/es_ar/es_ar/cargar-factura`

Revalidar las páginas oficiales inmediatamente antes de implementar cada write, porque restricciones por site/categoría pueden cambiar.
