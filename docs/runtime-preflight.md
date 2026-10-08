# ERP Meli 2.0 — Runtime Preflight

Fecha: 2026-10-08

## Estado

`F0_RUNTIME=PARTIAL`

El repositorio y los requisitos de software están verificados. Los valores específicos del plan Hostinger deben medirse en el hosting real antes de cualquier deploy productivo; no se infieren desde documentación comercial.

| Check | Estado | Valor / nota |
|---|---|---|
| Repository | PASS | https://github.com/sierraglobalcompany-rgb/ErpMeli2.0 |
| Default branch | PASS | `main` |
| Hosting plan | UNKNOWN | medir en panel/cuenta real |
| Disk quota | UNKNOWN | medir en panel/cuenta real |
| Inode quota | UNKNOWN | medir en panel/cuenta real |
| Database quota | UNKNOWN | medir en panel/cuenta real |
| PHP web version | TARGET | 8.5 |
| PHP CLI version | UNKNOWN | verificar `php -v` en hosting |
| MariaDB VERSION() | UNKNOWN | ejecutar `SELECT VERSION()` |
| sql_mode | UNKNOWN | ejecutar `SELECT @@sql_mode` |
| DB timezone | UNKNOWN | ejecutar `SELECT @@time_zone, @@system_time_zone` |
| cron capability | DOCS PASS / ACCOUNT UNKNOWN | diseño usa máximo 2 crons |
| HTTPS | REQUIRED | confirmar dominio final |
| ext-curl | REQUIRED | verificar `php -m` |
| ext-json | REQUIRED | PHP 8.5 core |
| ext-mbstring | REQUIRED | verificar `php -m` |
| ext-openssl | REQUIRED | verificar `php -m` |
| ext-pdo_mysql | REQUIRED | verificar `php -m` |
| ext-sodium | REQUIRED | verificar `php -m` |
| zip/gzip support | REQUIRED | verificar runtime real |

## Gate antes de deploy

Debe convertirse a PASS:

```text
PHP_WEB>=8.5
PHP_CLI>=8.5
PDO_MYSQL=YES
SODIUM=YES
CURL=YES
HTTPS=YES
CRON>=2
MARIADB_VERSION=KNOWN
DISK_QUOTA=KNOWN
INODE_QUOTA=KNOWN
DB_QUOTA=KNOWN
```

El desarrollo greenfield puede continuar contra CI reproducible; **deploy y cutover permanecen bloqueados** hasta completar los UNKNOWN.
