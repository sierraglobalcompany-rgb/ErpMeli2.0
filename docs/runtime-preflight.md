# Runtime preflight — Hostinger

Este preflight evita certificar **G8 HOSTING_REALITY** con suposiciones sobre el hosting.

## Ejecutar

Desde el root del proyecto, con las variables reales de producción cargadas:

```bash
php bin/runtime-preflight.php
```

El comando es **read-only**. No crea tablas, no modifica configuración y no llama Mercado Libre.

## Qué verifica automáticamente

- versión PHP requerida por el runtime objetivo;
- SAPI visible;
- extensiones requeridas (`curl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `sodium`);
- conexión MariaDB y `SELECT VERSION()`;
- `sql_mode` y timezone de la sesión;
- escritura en `storage/logs`, `debug`, `cache`, `exports` y `tmp`;
- espacio total/libre visible desde PHP;
- HTTPS en `APP_URL` cuando `APP_ENV=production`.

## Qué NO puede certificar por sí solo

El script devuelve `UNKNOWN` para hechos exclusivos del plan/panel, por ejemplo:

- cuota de disco;
- cuota de base de datos;
- cuota de inodes;
- cantidad de cron jobs disponibles;
- intervalo mínimo permitido por cron.

Esos valores deben comprobarse en hPanel/plan real. `UNKNOWN` nunca se convierte silenciosamente en `PASS`.

## Gate G8 HOSTING_REALITY

G8 sólo puede cerrarse cuando los hard checks del runtime pasan y los datos exclusivos de hPanel quedan conocidos, incluyendo:

```text
RUNTIME_PREFLIGHT_HARD_CHECKS=PASS
HOST_DISK_QUOTA=KNOWN
HOST_DB_QUOTA=KNOWN
HOST_INODE_QUOTA=KNOWN
HOST_CRON_CAPACITY=KNOWN
HOST_MIN_CRON_INTERVAL=KNOWN
WEB_PHP_VERSION=KNOWN
CLI_PHP_VERSION=KNOWN
HTTPS_STATUS=KNOWN
SUBDOMAIN_DOCUMENT_ROOT=KNOWN
```

Un resultado `PARTIAL` es correcto mientras falten hechos que el script no puede observar.

## Seguridad

No adjuntar al diagnóstico:

- `APP_KEY`;
- passwords DB;
- tokens Mercado Libre;
- Client Secret;
- archivos `.env`.

El JSON producido por el preflight no debe incluir esas credenciales.
