# Runtime preflight — Hostinger

Este preflight existe para evitar declarar `F0=PASS` con suposiciones sobre el hosting.

## Ejecutar

Desde el root del proyecto, con las variables reales de producción cargadas:

```bash
php bin/runtime-preflight.php
```

El comando es **read-only**. No crea tablas, no modifica configuración y no llama Mercado Libre.

## Qué verifica automáticamente

- PHP >= 8.5;
- SAPI visible;
- extensiones requeridas (`curl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `sodium`);
- conexión MariaDB y `SELECT VERSION()`;
- `sql_mode` y timezone de la sesión;
- escritura en `storage/logs`, `debug`, `cache`, `exports` y `tmp`;
- espacio total/libre visible desde PHP;
- HTTPS en `APP_URL` cuando `APP_ENV=production`.

## Qué NO puede certificar por sí solo

El script devuelve `UNKNOWN` para:

- cuota de disco del plan;
- cuota de base de datos;
- cuota de inodes;
- cantidad de cron jobs disponibles;
- intervalo mínimo permitido por cron.

Esos valores deben comprobarse en hPanel/plan real. `UNKNOWN` nunca se convierte silenciosamente en `PASS`.

## Gate F0

F0 queda completamente cerrado únicamente cuando:

```text
RUNTIME_PREFLIGHT_HARD_CHECKS=PASS
HOST_DISK_QUOTA=KNOWN
HOST_DB_QUOTA=KNOWN
HOST_INODE_QUOTA=KNOWN
HOST_CRON_CAPACITY=KNOWN
HOST_MIN_CRON_INTERVAL=KNOWN
```

Un resultado `PARTIAL` es esperado mientras falten los datos exclusivos del panel.

## Seguridad

No adjuntar al diagnóstico:

- `APP_KEY`;
- passwords DB;
- tokens Mercado Libre;
- client secret;
- archivos `.env`.

El JSON producido por el preflight no incluye esas credenciales.
