# ERP Meli 2.0

Greenfield ERP for Mercado Libre built with a KISS architecture.

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
- `docs/meli-contracts-2026.md`

Hostinger-specific database/disk/inode/runtime values must be measured before deployment. Development does not convert UNKNOWN production facts into assumptions.
