# Mercado Libre application — ERP Meli 2.0

ERP Meli 2.0 usa una **aplicación Mercado Libre dedicada a ERP2**. ERP1 conserva su aplicación y credenciales separadas durante desarrollo, operación paralela y migración.

## URLs canónicas

Public URL:

```text
https://erpmeli.bodegadigitalmedellin.com/
```

OAuth callback:

```text
https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
```

Notification callback:

```text
https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
```

Physical Hostinger path:

```text
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

El path físico es detalle de despliegue; no forma parte de las URLs OAuth/webhook públicas.

## Contrato de aplicación

```text
ERP1 APP != ERP2 APP
```

Razones durables:

- aislar callbacks y credenciales;
- evitar invalidar tokens de ERP1;
- permitir scopes mínimos para ERP2;
- rotar credenciales de ERP2 de forma independiente;
- exigir autorización explícita de las cuentas seller en la app nueva.

Los access/refresh tokens de ERP1 **no se migran** a ERP2.

## OAuth y secretos

La implementación actual usa el núcleo OAuth de ERP2 con PKCE/state y tokens cifrados. La configuración real de la aplicación y la alcanzabilidad HTTPS siguen requiriendo evidencia externa antes de certificar producción.

Reglas:

1. `redirect_uri` debe coincidir exactamente con la configurada en Mercado Libre.
2. Estado dinámico pertenece a OAuth `state`, no a la redirect URI.
3. Client ID, Client Secret, access tokens y refresh tokens nunca se commitean ni se imprimen en logs/debug.
4. Refresh tokens pertenecen exclusivamente a la app ERP2 que los emitió.
5. Remote business writes siguen deshabilitados aunque la app pueda tener scopes más amplios.

Variables de runtime esperadas:

```text
MELI_CLIENT_ID=
MELI_CLIENT_SECRET=
MELI_REDIRECT_URI=https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
MELI_WEBHOOK_URL=https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
```

Los valores reales no pertenecen a Git.

## Checklist de realidad externa

Antes de certificar OAuth/notifications en producción:

```text
MELI_APP_CREATED=YES
HTTPS_CALLBACK_REACHABLE=YES
REDIRECT_URI_MATCH=YES
CLIENT_SECRET_STORED_OUTSIDE_GIT=YES
NOTIFICATION_CALLBACK_REACHABLE=YES
```

La existencia de rutas/tests locales no prueba estos hechos externos.

## Fuentes oficiales

Revalidar contra documentación oficial vigente al cambiar permisos, callbacks o flujos OAuth:

- `developers.mercadolibre.com.co/es_co/crea-una-aplicacion-en-mercado-libre-es`
- `developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion`
- `developers.mercadolibre.com.co/es_co/gestion-de-identidades-y-accesos-oauth-y-tokens`
