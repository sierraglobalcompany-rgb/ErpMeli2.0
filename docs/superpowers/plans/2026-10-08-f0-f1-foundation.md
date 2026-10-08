# ERP Meli 2.0 — F0/F1 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verificar el runtime real y construir la foundation greenfield mínima de ERP Meli 2.0 sin incorporar todavía cola, OAuth, Sales, Billing, Catalog ni llamadas reales a Mercado Libre.

**Architecture:** Greenfield Modular Monolith + Vertical Slices. F0 sólo congela contratos/runtime verificables. F1 crea PHP 8.5 + Slim 4 + PDO + MariaDB, autenticación/tenancy/configuración mínima, los tres toggles operativos y CI verde. No se porta código de ERP1.

**Tech Stack:** PHP 8.5, Slim 4.x parcheado, slim/psr7, Composer, PDO MySQL/MariaDB, PHPUnit, PHPStan.

**Spec:** `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_FINAL_2026-10-08.md` (documento maestro aprobado fuera de esta rama; debe incorporarse al repo antes de implementar F2).

## Global Constraints

- Filosofía: KISS — simple, optimizado, eficiente, sin sobrearquitectura.
- Un repositorio, una aplicación y una base de datos.
- No Redis, RabbitMQ, Kafka, microservicios, EventBus, CommandBus ni CQRS framework.
- No copiar código de ERP1 salvo decisión explícita posterior basada en conocimiento/invariantes.
- PHP objetivo inicial: 8.5.
- `main` debe permanecer verde.
- `APP_ENV=local|test` nunca puede realizar HTTP real a `api.mercadolibre.com`.
- `storage/` debe quedar fuera del document root público.
- Todas las consultas de negocio futuras deberán estar scoped por `company_id`.
- Los tres toggles son requisitos de producto: sincronización automática, escrituras ML y debug.
- No implementar EmergencyStop/Freno de mano.
- No implementar todavía Work Engine, OAuth, MeliClient, Sales, Billing, Catalog, Inventory, ERP API ni facturación.

## Review Focus

1. **Runtime distinto al supuesto:** si Hostinger no ejecuta PHP 8.5 CLI o la versión MariaDB no soporta lo requerido, F0 debe detener F1 y documentar la corrección.
2. **Cross-tenant access:** un usuario nunca debe poder seleccionar/cambiar una compañía ajena alterando IDs en URL/form.
3. **Settings corruption:** los tres toggles deben tener valores tipados/defaults seguros; valores inválidos no pueden habilitar escrituras.
4. **Secret/storage exposure:** `.env`, claves y `storage/` nunca deben quedar servidos desde `public/`.
5. **Real ML HTTP in test/local:** cualquier intento debe fallar antes del transporte, incluso si alguien hardcodea el host.

---

# F0 — Runtime & Contract Preflight

### Task 1: Registrar autoridad del nuevo repo y runtime objetivo

**Files:**
- Create: `docs/runtime-preflight.md`

**Interfaces:**
- Consumes: acceso read-only al hosting y documentación oficial.
- Produces: matriz `PASS/FAIL/UNKNOWN` usada por todas las fases posteriores.

- [ ] **Step 1: Crear la plantilla de preflight**

Debe contener exactamente estas categorías:

```text
Repository
Hosting plan
Disk quota
Inode quota
Database quota
PHP web version
PHP CLI version
MariaDB VERSION()
sql_mode
DB timezone
cron capability
HTTPS
ext-curl
ext-json
ext-mbstring
ext-openssl
ext-pdo_mysql
ext-sodium
zip/gzip support
```

- [ ] **Step 2: Verificar el repositorio**

Registrar:

```text
REPO=https://github.com/sierraglobalcompany-rgb/ErpMeli2.0
DEFAULT_BRANCH=main
```

- [ ] **Step 3: Verificar runtime Hostinger**

Ejecutar/obtener de forma read-only:

```bash
php -v
php -m
```

y en MariaDB:

```sql
SELECT VERSION();
SELECT @@sql_mode;
SELECT @@time_zone, @@system_time_zone;
```

- [ ] **Step 4: Verificar cron real**

Documentar cantidad de cron jobs disponibles y si ejecutan PHP CLI 8.5.

- [ ] **Step 5: Registrar límites del plan**

Disk, DB e inodes deben quedar como números reales. No inferirlos por nombre del plan si el panel muestra valores distintos.

- [ ] **Step 6: Gate F0-runtime**

F0 runtime pasa sólo si:

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

Si algo falla, corregir la especificación antes de escribir foundation.

- [ ] **Step 7: Commit**

```bash
git add docs/runtime-preflight.md
git commit -m "docs: record ERP2 runtime preflight"
```

---

### Task 2: Congelar contratos Mercado Libre necesarios para F1–F4

**Files:**
- Create: `docs/meli-contracts-2026.md`

**Interfaces:**
- Produces: contratos verificados que F3/F4 consumirán; F1 sólo necesita la hard barrier de host.

- [ ] **Step 1: Registrar OAuth vigente**

Documentar desde fuente oficial:

```text
authorization endpoint
token endpoint
access token lifetime
refresh rotation/single-use
invalid_grant behavior
```

- [ ] **Step 2: Registrar Orders mínimo**

Documentar:

```text
GET exact order
orders search/discovery
pack relation
shipment relation
```

- [ ] **Step 3: Registrar Notifications mínimo**

Documentar:

```text
HTTP 200 deadline
duplicates/out-of-order
missed_feeds retention
```

- [ ] **Step 4: Registrar endpoints que NO se implementan aún**

Billing, Catalog y writes se documentan como `DEFERRED_CONTRACT_GATE`, no como código F1.

- [ ] **Step 5: Gate F0-contract**

Debe existir una tabla:

```text
operation | method | path | classification | official_source | verified_at
```

para OAuth/Orders/Notifications solamente.

- [ ] **Step 6: Commit**

```bash
git add docs/meli-contracts-2026.md
git commit -m "docs: freeze initial Mercado Libre read contracts"
```

---

# F1 — Greenfield Foundation

### Task 3: Crear Composer y dependencias mínimas

**Files:**
- Create: `composer.json`
- Create: `.gitignore`

**Interfaces:**
- Produces: autoload PSR-4 `App\\ => app/` y scripts QA.

- [ ] **Step 1: Escribir test/constraint previo**

Crear una comprobación simple en CI que falle si `composer.json` no exige PHP `^8.5`.

- [ ] **Step 2: Crear `composer.json` mínimo**

Dependencias runtime permitidas en F1:

```text
slim/slim ^4.15.3
slim/psr7
```

Dev:

```text
phpunit/phpunit
phpstan/phpstan
```

No agregar container DI externo, ORM, Twig, dotenv package ni logger framework salvo que una necesidad concreta de F1 lo demuestre.

- [ ] **Step 3: Scripts**

Definir:

```text
composer test
composer analyse
composer qa
```

`qa` debe ejecutar lint/analysis/tests sin aceptar baseline rojo.

- [ ] **Step 4: `.gitignore`**

Excluir:

```text
/vendor/
.env
/storage/logs/*
/storage/debug/*
/storage/cache/*
/storage/exports/*
/storage/tmp/*
```

con `.gitkeep` donde haga falta.

- [ ] **Step 5: Verify**

```bash
composer validate --strict
composer install
composer qa
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock .gitignore
git commit -m "build: establish PHP 8.5 minimal dependencies"
```

---

### Task 4: Crear estructura pública/privada y bootstrap HTTP

**Files:**
- Create: `public/index.php`
- Create: `app/Bootstrap.php`
- Create: `app/Core/Config/AppConfig.php`
- Create: `app/Core/Http/Routes.php`
- Create: `storage/*/.gitkeep`
- Test: `tests/Unit/BootstrapTest.php`

**Interfaces:**
- `Bootstrap::create(): \Slim\App`
- `AppConfig::fromEnvironment(array $env): AppConfig`

- [ ] **Step 1: RED — bootstrap crea app**

Test: `BootstrapTest::testCreatesSlimApplication()`.

Expected initially: class missing.

- [ ] **Step 2: Implementar bootstrap mínimo**

Sin service container genérico. Composition root explícito.

- [ ] **Step 3: Crear `GET /health`**

Respuesta JSON mínima:

```json
{"ok":true}
```

No exponer versión PHP, DB credentials ni secretos.

- [ ] **Step 4: Probar document root**

Test debe comprobar que paths bajo `storage/` no son rutas HTTP.

- [ ] **Step 5: Verify**

```bash
composer test
composer analyse
```

- [ ] **Step 6: Commit**

```bash
git add public app storage tests
git commit -m "feat: add minimal HTTP foundation"
```

---

### Task 5: Configuración y hard barrier contra HTTP ML real

**Files:**
- Create: `.env.example`
- Create: `app/Core/Config/Environment.php`
- Create: `app/Integrations/MercadoLibre/Transport/RemoteHostPolicy.php`
- Test: `tests/Unit/RemoteHostPolicyTest.php`

**Interfaces:**
- `RemoteHostPolicy::assertAllowed(string $url, string $appEnv): void`

- [ ] **Step 1: RED — bloquear ML en test/local**

Assertions:

```text
APP_ENV=test + https://api.mercadolibre.com/... => exception
APP_ENV=local + https://api.mercadolibre.com/... => exception
APP_ENV=production => policy itself does not block
```

- [ ] **Step 2: Implementar allow/deny simple**

No implementar todavía Curl transport.

- [ ] **Step 3: `.env.example`**

Sólo nombres/config sin secretos reales:

```text
APP_ENV
APP_URL
APP_KEY
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
```

- [ ] **Step 4: Verify**

```bash
composer test
```

- [ ] **Step 5: Commit**

```bash
git add .env.example app tests
git commit -m "test: hard block real Mercado Libre HTTP in local and test"
```

---

### Task 6: Migrador SQL mínimo y esquema Core

**Files:**
- Create: `bin/migrate.php`
- Create: `app/Core/Database/Connection.php`
- Create: `app/Core/Database/Migrator.php`
- Create: `database/migrations/001_core.sql`
- Test: `tests/Integration/MigrationsTest.php`

**Interfaces:**
- `Connection::fromConfig(AppConfig $config): PDO`
- `Migrator::migrate(PDO $pdo, string $directory): void`

- [ ] **Step 1: RED — fresh DB**

Test debe crear DB de integración vacía y esperar tablas:

```text
schema_migrations
companies
users
company_users
system_settings
```

- [ ] **Step 2: SQL 001**

`system_settings` es tabla tipada de una fila, no key/value genérico.

Campos:

```text
id=1
automation_enabled false
meli_writes_enabled false
debug_enabled false
debug_retention_days 7
debug_max_mb 100
updated_at
updated_by nullable
```

- [ ] **Step 3: Migrator mínimo**

Sólo:

```text
orden por nombre
checksum opcional sólo si se usa para detectar alteración
apply once
schema_migrations
```

No down migration framework.

- [ ] **Step 4: Test idempotencia**

Ejecutar migrador dos veces; segunda no modifica schema ni duplica versión.

- [ ] **Step 5: Verify**

```bash
composer test
```

- [ ] **Step 6: Commit**

```bash
git add bin app/Core/Database database tests
git commit -m "feat: add minimal SQL migration foundation"
```

---

### Task 7: Auth, sesión, CSRF y tenancy mínimo

**Files:**
- Create: `app/Core/Auth/AuthService.php`
- Create: `app/Core/Auth/PasswordService.php`
- Create: `app/Core/Security/Csrf.php`
- Create: `app/Core/Tenancy/CompanyContext.php`
- Create/Modify: routes needed for login/logout/company context
- Test: `tests/Integration/AuthTenancyTest.php`

**Interfaces:**
- `AuthService::login(string $email, string $password): bool`
- `AuthService::logout(): void`
- `CompanyContext::companyId(): int`
- `Csrf::token(): string`
- `Csrf::assertValid(string $token): void`

- [ ] **Step 1: RED — auth**

Test valid password succeeds; invalid fails.

- [ ] **Step 2: RED — cross tenant**

User A perteneciente sólo a company 1 no puede seleccionar company 2 modificando request/session IDs.

- [ ] **Step 3: Implement minimal auth**

Usar `password_hash/password_verify`; session cookie Secure/HttpOnly/SameSite apropiado.

- [ ] **Step 4: Implement CSRF**

Todas las mutaciones UI F1 requieren token.

- [ ] **Step 5: Implement CompanyContext**

Context sólo puede seleccionar empresas del usuario autenticado.

- [ ] **Step 6: Verify**

```bash
composer test
composer analyse
```

- [ ] **Step 7: Commit**

```bash
git add app tests
git commit -m "feat: add minimal auth csrf and tenant isolation"
```

---

### Task 8: Pantalla Sistema con tres toggles

**Files:**
- Create: `app/Modules/Settings/SystemSettingsRepository.php`
- Create: `app/Modules/Settings/SystemSettingsController.php`
- Create: `app/Modules/Settings/views/system.php`
- Test: `tests/Integration/SystemSettingsTest.php`

**Interfaces:**
- `SystemSettingsRepository::get(): SystemSettings`
- `SystemSettingsRepository::updateOperationalToggles(bool $automation, bool $writes, bool $debug, int $retentionDays, int $debugMaxMb, int $userId): void`

- [ ] **Step 1: RED — defaults seguros**

Fresh DB:

```text
automation=false
writes=false
debug=false
retention=7
debug_max_mb=100
```

- [ ] **Step 2: RED — writes fail closed**

Valor DB inválido/corrupto nunca se interpreta como `true`.

- [ ] **Step 3: Implement repository tipado**

No feature-flag framework.

- [ ] **Step 4: UI simple**

Mostrar:

```text
Sincronización automática ON/OFF
Escrituras Mercado Libre ON/OFF
Debug ON/OFF
Retención debug
Límite debug
```

Aún no implementar historial/limpieza Debug; eso es F5.

- [ ] **Step 5: CSRF + admin authorization**

Sólo admin puede mutar settings.

- [ ] **Step 6: Verify**

```bash
composer test
```

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Settings tests
git commit -m "feat: add three operational system toggles"
```

---

### Task 9: Logger normal mínimo

**Files:**
- Create: `app/Core/Logging/AppLogger.php`
- Create: `app/Core/Logging/LogEvent.php`
- Test: `tests/Unit/AppLoggerTest.php`

**Interfaces:**
- `AppLogger::error(string $event, array $fields = []): void`
- `AppLogger::warning(string $event, array $fields = []): void`

- [ ] **Step 1: RED — secret rejection**

Campos con nombres sensibles (`authorization`, `access_token`, `refresh_token`, `password`, `api_key`) deben ser rechazados/redactados antes de escribir.

- [ ] **Step 2: Implement logger pequeño**

Archivo diario normal bajo `storage/logs`.

No Monolog si no se demuestra necesidad.

- [ ] **Step 3: Test retention boundary helper**

Sólo helper/fecha; cleanup real se completa cuando Debug DVR exista.

- [ ] **Step 4: Verify**

```bash
composer test
```

- [ ] **Step 5: Commit**

```bash
git add app/Core/Logging tests
git commit -m "feat: add minimal safe operational logger"
```

---

### Task 10: CI verde y fresh-install proof

**Files:**
- Create: `.github/workflows/qa.yml`
- Create: `tests/Integration/FreshInstallTest.php`
- Modify: `README.md`

**Interfaces:**
- Produces required GitHub check: `qa`.

- [ ] **Step 1: CI PHP 8.5 + MariaDB**

Workflow ejecuta:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
composer qa
```

- [ ] **Step 2: Fresh install test**

Desde DB vacía:

```text
migrate
seed/create first admin in test fixture
boot app
GET /health
login
read settings
```

- [ ] **Step 3: Assert no real ML HTTP**

CI establece:

```text
APP_ENV=test
REAL_MELI_HTTP=0
```

y el suite demuestra hard block.

- [ ] **Step 4: README**

Documentar sólo:

- requisitos;
- install dev;
- migrate;
- test/qa;
- arquitectura KISS resumida;
- estado F1.

- [ ] **Step 5: Verify locally**

```bash
composer qa
```

Expected: PASS 100%, 0 accepted inherited failures.

- [ ] **Step 6: Commit**

```bash
git add .github tests README.md
git commit -m "ci: require green fresh-install foundation"
```

---

# F0/F1 Exit Gate

No comenzar F2 hasta cumplir TODO:

```text
RUNTIME_PREFLIGHT=PASS
MELI_INITIAL_CONTRACTS=VERIFIED
PHP_TARGET=8.5
MARIADB_VERSION=KNOWN
DISK_DB_INODE_LIMITS=KNOWN

FRESH_INSTALL=PASS
COMPOSER_QA=PASS
PHPSTAN=PASS
TESTS=PASS
REAL_MELI_HTTP=0
MAIN_REQUIRED_QA=ENABLED

TENANT_ISOLATION=PASS
CSRF=PASS
SETTINGS_FAIL_CLOSED=PASS
STORAGE_OUTSIDE_PUBLIC=PASS
```

# Explicitly deferred to F2+

Do **not** implement during this plan:

```text
work_items
GET_LOCK runner
claim_token
MeliClient/Curl
OAuth
webhooks
orders
Billing
Catalog
Inventory
Debug DVR/history/download
/api/v1
invoicing
remote writes
migration ERP1
```

If F1 begins needing any of these to “make the foundation complete”, stop and apply KISS/YAGNI: the foundation is already too large.
