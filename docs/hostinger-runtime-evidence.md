# Hostinger runtime evidence — ERP Meli 2.0

Este documento separa capacidades públicas de Hostinger de hechos específicos de la cuenta que todavía requieren hPanel/runtime real.

## Target de despliegue

```text
PUBLIC_URL=https://erpmeli.bodegadigitalmedellin.com/
DOCUMENT_ROOT=/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2
```

Reglas:

- la URL pública es el subdominio anterior;
- el folder físico `erpmeli2` no se expone como requisito de URL;
- el servidor debe exponer sólo `public/` o una configuración equivalente segura;
- `storage/`, `.env`, metadata Composer y source de aplicación no deben quedar accesibles directamente por web.

La configuración real de hPanel debe verificarse antes de certificar G8.

## Capacidades públicas conocidas

La documentación pública de Hostinger soporta PHP moderno hasta 8.5 en planes compatibles. El proyecto mantiene compatibilidad PHP 8.3 / 8.4 / 8.5, pero la versión web y CLI reales deben medirse en la cuenta ERP2.

Hostinger documenta límites de cron dependientes del plan. ERP2 requiere sólo dos entradas por diseño:

```text
bin/work.php
bin/cleanup.php
```

Las cuotas de CPU/RAM/disk/inodes/database también varían por plan y versión comercial; una tabla pública no constituye evidencia de la cuenta real.

Fuentes públicas de referencia:

- `hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/`
- `hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/`
- `hostinger.com/support/1583765-how-many-cron-jobs-can-you-set-up-in-hostinger/`
- `hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/`
- `hostinger.com/support/10717644-new-web-and-cloud-hosting-limits-at-hostinger/`
- `hostinger.com/support/1583210-what-is-the-inode-limit-and-what-happens-when-it-is-reached/`

## Evidencia de cuenta requerida

Ejecutar en el runtime real:

```bash
php bin/runtime-preflight.php
```

Y confirmar en hPanel:

```text
HOST_DISK_QUOTA
HOST_DB_QUOTA
HOST_INODE_QUOTA
HOST_CRON_CAPACITY
HOST_MIN_CRON_INTERVAL
WEB_PHP_VERSION
CLI_PHP_VERSION
HTTPS_STATUS
SUBDOMAIN_DOCUMENT_ROOT
```

`SUBDOMAIN_DOCUMENT_ROOT` debe corresponder al target ERP2 y mantener el source/storage fuera del web root efectivo.

## Gate G8 HOSTING_REALITY

```text
official Hostinger docs + CI green != G8 PASS
```

G8 requiere evidencia del runtime y de la cuenta real. Hasta entonces permanece `NOT CERTIFIED` aunque GitHub Actions esté verde.

## Consecuencia KISS

El diseño sólo presupone:

```text
2 cron jobs
1 MariaDB
PHP compatible 8.3-8.5
filesystem storage acotado
1 subdominio HTTPS dedicado
```

Si la cuenta real no cumple esos mínimos, se corrige ambiente/configuración antes de agregar complejidad a la aplicación.
