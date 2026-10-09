# ERP Meli 2.0

Greenfield ERP for Mercado Libre built with a KISS architecture.

## Ley de ingeniería obligatoria

Antes de diseñar, programar o revisar cualquier cambio, leer y cumplir [`AGENTS.md`](AGENTS.md).

Todo cambio debe pasar esta puerta: **¿Es KISS? ¿Es simple? ¿Está optimizado para el problema real? ¿Es eficiente? ¿Se puede mejorar con menos piezas, estado, consultas o abstracciones?** Si alguna respuesta es no o dudosa, se simplifica antes de implementar.

## F1 stack

- PHP 8.5
- Slim 4
- PDO / MariaDB
- PHPUnit
- PHPStan

## Architecture

```text
Modular Monolith
+ Vertical Slices
1 application
1 database
```

F1 intentionally does **not** contain Work Engine, OAuth integration, Sales, Billing, Catalog, Inventory, Debug DVR, external API or remote writes.

## Canonical deployment target

```text
Public URL:
https://erpmeli.bodegadigitalmedellin.com/

Hostinger physical path:
/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

ERP2 uses a dedicated Mercado Libre application separate from ERP1. Reserved integration URLs and creation requirements are documented in `docs/mercadolibre-app-erp2.md`.

## Development

```bash
composer install
cp .env.example .env
php bin/migrate.php
composer qa
```

Configure the web server document root as `public/`. `storage/` must remain outside the public document root.

## Environment

See `.env.example`.

Tests and CI run with:

```text
APP_ENV=test
REAL_MELI_HTTP=0
```

The foundation contains a hard policy that rejects real `api.mercadolibre.com` traffic in `local` and `test`.

## Runtime preflight

See:

- `docs/runtime-preflight.md`
- `docs/hostinger-runtime-evidence.md`
- `docs/meli-contracts-2026.md`
- `docs/mercadolibre-app-erp2.md`

Hostinger-specific database/disk/inode/runtime values must be measured before deployment. Development does not convert UNKNOWN production facts into assumptions.
