# Mercado Libre application — ERP Meli 2.0

**Cutoff:** 2026-10-08

ERP Meli 2.0 will use a **new Mercado Libre application dedicated to ERP2**. ERP1 keeps its existing application and credentials untouched during development, shadow operation and migration.

## Canonical ERP2 URL

```text
https://erpmeli.bodegadigitalmedellin.com/
```

Physical Hostinger path:

```text
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

The physical folder is deployment detail; it is not part of the public OAuth/webhook URLs.

## Planned Mercado Libre URLs

OAuth callback:

```text
https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
```

Notification callback:

```text
https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
```

These paths are reserved now to avoid later redirect/callback drift. The routes themselves are implemented only in their planned phases.

## Official Mercado Libre requirements verified at this cutoff

Official application documentation:

- https://developers.mercadolibre.com.co/es_co/crea-una-aplicacion-en-mercado-libre-es
- https://developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion
- https://developers.mercadolibre.com.co/es_co/gestion-de-identidades-y-accesos-oauth-y-tokens

Confirmed requirements/recommendations relevant to ERP2:

1. The application receives its own Client ID and Secret Key.
2. HTTPS is required for the redirect URI when creating/configuring the application.
3. The `redirect_uri` used by OAuth must match exactly one configured in the application and must not contain variable data.
4. Dynamic state belongs in the OAuth `state` parameter, not in the redirect URI.
5. PKCE is supported and recommended; ERP2 plans to use PKCE S256 when OAuth is implemented.
6. Mercado Libre notification topics and the notification callback URL are configured in the application.
7. The app Client ID, Client Secret, access tokens and refresh tokens are secrets and must never be committed to Git or emitted in logs/debug.
8. Since 2026 Mercado Libre and Mercado Pago applications must be separated by business unit. ERP2's Mercado Libre app must not be used as a substitute for a future Mercado Pago app.

## New application instead of reusing ERP1

Decision:

```text
ERP1 APP != ERP2 APP
```

Reasons:

- ERP1 remains operational while ERP2 is developed/tested;
- callback URLs are isolated;
- permissions/scopes can be kept minimal for ERP2;
- ERP2 credentials can be rotated independently;
- migration does not risk invalidating ERP1 tokens;
- ERP2 sellers explicitly authorize the new application.

Existing ERP1 access/refresh tokens are **not** migrated into the new application. Accounts will authorize ERP2 through its own OAuth flow.

## Initial permission posture

ERP2 V1 is read-oriented.

At app creation/configuration time, prefer the minimum permissions needed for the implemented phases. Do not enable remote-write behavior merely because the application can request write-capable scopes.

The ERP-level setting remains an independent safety requirement:

```text
meli_writes_enabled = false
```

Remote business writes are a later phase and require a fresh endpoint audit.

## App creation checklist

In Mercado Libre DevCenter:

- [ ] create a new application owned by the appropriate legal/business owner account;
- [ ] use a clear unique application name for ERP Meli 2.0;
- [ ] configure HTTPS redirect URI exactly as:

```text
https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
```

- [ ] enable PKCE if available/appropriate for the server-side flow;
- [ ] configure only notification topics actually used by implemented modules;
- [ ] configure notification callback exactly as:

```text
https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
```

- [ ] store Client ID/Secret outside Git;
- [ ] never paste Client Secret, access token or refresh token into issues/PRs/chat logs;
- [ ] verify callback URLs from the real HTTPS deployment before connecting production seller accounts.

## Environment variables reserved for F3

Do **not** add real values to Git.

When F3 OAuth begins, runtime configuration is expected to include names equivalent to:

```text
MELI_CLIENT_ID=
MELI_CLIENT_SECRET=
MELI_REDIRECT_URI=https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback
MELI_WEBHOOK_URL=https://erpmeli.bodegadigitalmedellin.com/webhooks/mercadolibre
```

The exact config names are frozen only when the F3 implementation starts. They are documented here now solely to preserve the canonical URLs.

## Gate

The new Mercado Libre application may be created during F0/F1, but its credentials are not required to merge foundation code.

Before F3 OAuth can close:

```text
MELI_APP_CREATED=YES
HTTPS_CALLBACK_REACHABLE=YES
REDIRECT_URI_MATCH=YES
CLIENT_SECRET_STORED_OUTSIDE_GIT=YES
```
