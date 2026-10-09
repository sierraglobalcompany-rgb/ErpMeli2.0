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
- Catalog `/items/bulk`;
- Billing Info;
- User Products;
- writes remotos.

Cada contrato diferido se revalida contra documentación oficial al comenzar su fase.
