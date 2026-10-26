# ERP Meli 2.0 — Contratos Mercado Libre iniciales

Fecha de verificación: 2026-10-09

Autoridad: documentación oficial `developers.mercadolibre.com.co`.

## Operaciones verificadas

| operation | method | path | classification | official_source | verified_at |
|---|---|---|---|---|---|
| oauth.token | POST | `/oauth/token` | AUTH | https://developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion | 2026-10-08 |
| users.me | GET | `/users/me` | READ | https://developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion | 2026-10-08 |
| orders.get | GET | `/orders/{order_id}` | READ | https://developers.mercadolibre.com.co/gestiona-ventas | 2026-10-09 |
| orders.search | GET | `/orders/search` | READ | https://developers.mercadolibre.com.co/gestiona-ventas | 2026-10-09 |
| billing.period.details | GET | `/billing/integration/periods/key/{period_key}/group/ML/details` | READ | https://developers.mercadolibre.com.co/provisiones | 2026-10-09 |
| users.items.search | GET | `/users/{user_id}/items/search` | READ | https://developers.mercadolibre.com.co/es_ar/api-docs-es/items-y-busquedas | 2026-10-24 |
| items.bulk | GET | `/items/bulk` | READ | https://developers.mercadolibre.com.co/es_ar/api-docs-es/items-y-busquedas | 2026-10-24 |

## Items del vendedor — M02 (contrato offline; no certificación F14)

- `users.items.search`: `GET /users/{user_id}/items/search`, `READ`; `user_id` decimal positivo de la cuenta `meli_accounts` perteneciente a la empresa autorizada. Parámetros permitidos: `offset` entero >=0 y `limit` entero 1–50; se limita la ventana común a 1000. No se implementa `scan` ni sincronización automática.
- `items.bulk`: `GET /items/bulk?ids=...`, `READ`, máximo 20 IDs distintos y validados por solicitud. Usar contrato nuevo (`status_code`, `id` raíz, `body`) en vez de `/items?ids=`; preservar decimales sin float mediante `preserve_numbers`.
- No equiparar HTTP 200 con éxito de cada entrada. `sellerBulkReadState()` sólo admite datos del `seller_id` esperado y deja los IDs ajenos, faltantes o fallidos como `UNVERIFIED` sin título/precio filtrado; respuesta `contentMissing` o entradas duplicadas también quedan sin verificar.
- El vendedor esperado debe provenir de `meli_accounts.external_user_id` después de validar `company_id`, `account_id`, estado y permisos en el **consumidor que todavía no se ha implementado**. El cliente/registro no sustituyen esta autorización.
- `available_quantity` pública puede ser un valor referencial por rango; `public_quantity_reference` no es inventario físico ERP2. Datos sin valor confirmado son `null`, nunca cero inferido.
- Estas operaciones están registradas para integración simulada. Sin consumidor activo, ruta/UI, persistencia ni autorizaciones de API real. `REAL_MELI_HTTP=0`, F14 pendiente, escrituras F16 OFF.
- Fuente de referencia (verificada 2026-10-24): https://developers.mercadolibre.com.co/es_ar/api-docs-es/items-y-busquedas. La deprecación de `/items?ids=` está anunciada para 2026-10-25. Revalidar el contrato al habilitar F14.

## OAuth

- Token endpoint: `POST https://api.mercadolibre.com/oauth/token`.
- Access token: 6 horas según guía vigente.
- Cada refresh devuelve un refresh token nuevo.
- Sólo el último refresh token generado es válido y es de un solo uso.
- `invalid_grant` requiere recuperación/reautorización; no retry ciego.
- Credenciales/tokens deben almacenarse cifrados y no registrarse.

## Orders / Packs / Shipments

- Orden exacta: `GET /orders/{ORDER_ID}`.
- Discovery vendedor: `GET /orders/search?seller={SELLER_ID}`.
- La búsqueda de órdenes conserva hasta 12 meses según documentación vigente; **no se usa para reconstruir historia ilimitada**.
- `pack_id` relaciona una orden con un pack.
- `GET /packs/{PACK_ID}` devuelve órdenes del pack y puede devolver `shipment.id`.
- Para detalle de envío se consulta el recurso `/shipments/{SHIPMENT_ID}`; no asumir que el JSON nuevo de Order contiene todos los datos de Shipping.

## Notifications

- Callback debe responder HTTP 200 en máximo 500 ms.
- Validar lo mínimo, persistir/encolar y procesar asíncronamente.
- Notificaciones pueden repetirse o llegar fuera de orden.
- `missed_feeds` conserva hasta 2 días y es mecanismo de recuperación, no sustituto de reconciliación/discovery.

## Billing period details

- Flujo financiero principal: `GET /billing/integration/periods/key/{period_key}/group/ML/details`.
- `period_key` mensual se usa como `YYYY-MM-01`.
- Procesar `document_type=BILL` y `document_type=CREDIT_NOTE` como streams separados.
- Página máxima aprobada: `limit=1000`.
- Paginación secuencial: primera página `from_id=0`; continuar usando el `last_id` recibido.
- Orden estable: `sort_by=ID&order_by=ASC`.
- HTTP 206 indica información parcial/en proceso: no tratar como éxito final; reintentar en un ciclo posterior.
- Cachear localmente la información normalizada y evitar consultas repetitivas order-by-order.
- `/billing/integration/group/ML/order/details` queda reservado para reparación/investigación puntual y no se registra como flujo masivo F6A.
- Billing es conciliación fiscal/financiera, no fuente operacional de ventas.

## Deferred contract gates

No están habilitados todavía:

- Billing per-order repair;
- Uso real de `/items/bulk` y lectura/renovación OAuth contra Mercado Libre (gate externo F14); el mero registro READ en M02 no lo autoriza.
- Billing Info;
- User Products;
- writes remotos.

Cada contrato diferido se revalida contra documentación oficial al comenzar su fase.
