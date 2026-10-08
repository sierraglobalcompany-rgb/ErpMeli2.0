# ERP Meli 2.0 — Contratos Mercado Libre iniciales

Fecha de verificación: 2026-10-08

Autoridad: documentación oficial `developers.mercadolibre.com.co`.

## Operaciones F3/F4

| operation | method | path | classification | official_source | verified_at |
|---|---|---|---|---|---|
| oauth.token | POST | `/oauth/token` | AUTH | https://developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion | 2026-10-08 |
| users.me | GET | `/users/me` | READ | https://developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion | 2026-10-08 |
| orders.get | GET | `/orders/{order_id}` | READ | https://developers.mercadolibre.com.co/en_us/api-docs/manage-sales | 2026-10-08 |
| orders.search | GET | `/orders/search?seller={seller_id}` | READ | https://developers.mercadolibre.com.co/pt_br/usuarios-e-aplicativos/pedidos-e-opinioes | 2026-10-08 |
| packs.get | GET | `/packs/{pack_id}` | READ | https://developers.mercadolibre.com.co/gestion-packs | 2026-10-08 |
| shipments.get | GET | `/shipments/{shipment_id}` | READ | https://developers.mercadolibre.com.co/es_ar/metricas/envios | 2026-10-08 |
| missed_feeds | GET | `/missed_feeds` | READ | https://developers.mercadolibre.com.co/es_co/primeros-pasos-in-house | 2026-10-08 |

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

## Deferred contract gates

No se implementan en F1:

- Billing;
- Catalog `/items/bulk`;
- Billing Info;
- User Products;
- writes remotos.

Cada uno se revalida contra documentación oficial al comenzar su fase.
