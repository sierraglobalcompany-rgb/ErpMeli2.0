# UI-DM8 — Auditoría de completitud ERP MELI 2.0

Fecha: 2026-10-16  
Rama: `ui/design-master-v1-20261015`  
PR: #19 — DRAFT / NO MERGE  
Base visual: Design Master V1 aprobado  
Alcance: completitud local antes de QA final, demo y Hostinger.

## Regla

No convertir cada clase backend en una pantalla. Una capacidad sólo requiere superficie humana cuando existe una acción, lectura o decisión que un operador/admin deba ejecutar. API de máquina, CLI, workers, webhooks y preflight conservan su owner técnico salvo evidencia de una necesidad humana real.

Clasificación: `KEEP / MERGE / OPTIMIZE / DEFER / TECHNICAL_ONLY`.

## 1. Superficies humanas actuales

| Área | Owner / ruta | Estado DM8 | Decisión |
|---|---|---|---|
| Login | `/login` | Shared `auth-page/auth-card`, CSS común, CSRF | KEEP |
| Inicio / Resumen | `/dashboard` | Design Master factual, local/read-only | KEEP |
| Ventas | `/sales` | Design Master + búsqueda exacta + auditoría visible | KEEP |
| Detalle venta | `/sales/{order_id}` | Design Master + Financial/PostSale/Invoice factual | KEEP |
| Productos / Inventario | `/catalog` | Un único owner humano y backend; ausencia stock = `Sin definir` | KEEP/MERGE |
| Facturación | `/billing` | Productor manual explícito + estado Work/Billing | KEEP |
| Cuentas MELI | `/mercadolibre/accounts` | Lifecycle humano + OAuth actions | KEEP |
| App MELI global | `/settings/mercadolibre` | System-admin, secretos protegidos | KEEP |
| Empresas | `/system/companies` | Design Master, lifecycle existente | KEEP |
| Usuarios / permisos | `/system/users` | Design Master, roles/guards existentes | KEEP |
| Sistema / diagnóstico | `/settings/system` | Design Master, debug/automation sin writes MELI | KEEP |
| Selector empresa | `/company/select` | Seguro y funcional; dos ajustes de coherencia bajo auditoría | OPTIMIZE |
| Instalador | `/install` | Flujo fuera del shell operativo; visual shell ya probado | KEEP |

## 2. Capacidades que NO necesitan nueva pantalla

| Capacidad | Owner | Clasificación | Motivo |
|---|---|---|---|
| Work runner | `app/Work/*`, `bin/work.php` | TECHNICAL_ONLY | Ejecutor background; no task engine UI nuevo |
| Runtime preflight | `RuntimePreflight`, CLI | TECHNICAL_ONLY | Certificación runtime/deploy posterior |
| ERP API | `ErpApiController` | TECHNICAL_ONLY | API de integración, no CRUD paralelo de UI |
| Webhook MELI | `/webhooks/mercadolibre` | TECHNICAL_ONLY | Entrada background; health UI sólo con hechos reales |
| Invoicing bridge | API `/api/integrations/v1/invoicing/*` | TECHNICAL_ONLY | Consumer de máquina; revisión humana ya aparece en venta donde aplica |
| Financial engine | `FinancialReadModel` | MERGE | Se consume en detalle/dashboard; no crear ledger o `/financial` paralelo |
| Inventory repository | `InventoryRepository` | MERGE | Se presenta bajo `/catalog`; no crear `/inventory` duplicado |
| Billing C0 | CLI/probe | TECHNICAL_ONLY | Probe/certificación, no producto operador |
| F13 rehearsal | CLI/docs | TECHNICAL_ONLY | Migración/cutover separado |
| F14 READ helpers | MELI client/contracts | DEFER LIVE | Certificación proveedor real posterior; no inventar UI de health |

## 3. Reconciliación con LC0 histórico

LC0 marcó cuatro faltantes P1 locales. En el código actual:

### LC-P1-1 — operación / tenant / Sales

`CLOSED CURRENT`:
- navegación real;
- selector tenant;
- acceso exacto a ventas fuera de últimos 100;
- estados de auditoría visibles;
- Home factual `/dashboard`.

### LC-P1-2 — Catalog / Inventory / Financial / PostSale

`CLOSED CURRENT`:
- Catalog e Inventory operables desde navegador;
- un solo owner de producto/inventario;
- Financial factual visible sin motor paralelo;
- Invoice/PostSale visible desde detalle de venta.

### LC-P1-3 — productor inicial Billing

`CLOSED CURRENT`:
- `POST /billing/syncs` existe;
- admin + CSRF + tenant;
- cuenta conectada validada y bloqueada `FOR UPDATE`;
- dedupe por estado lógico Work;
- enqueue explícito de `billing.period.sync`;
- no requiere scheduler para uso manual.

### LC-P1-4 — provisión inicial

`CLOSED CURRENT` por la familia Installer / bootstrap-admin / migrations ya certificada en fases locales anteriores. No crear otro stack de instalación.

## 4. Hallazgos DM8 activos

### DM8-A1 — selector empresa única vuelve a Sales

**Estado:** CONFIRMED / NOT FIXED YET.

`GET /company/select` con una única empresa ya activa redirige a `/sales`, mientras:
- `/` con empresa activa redirige a `/dashboard`;
- `POST /company/select` redirige a `/`, luego `/dashboard`;
- Design Master define Inicio/Resumen como home.

**Decisión:** `OPTIMIZE`. Cerrar por TDD en microbloque separado. No cambia tenant ni autorización.

### DM8-A2 — presentación selector de empresa

**Estado:** AUDIT NEXT.

La vista usa CSS común y shell cuando corresponde, pero aún no está formalmente fijada por un contrato Design Master propio. Auditar después de A1; no crear componente nuevo si `page-header/panel/settings-form` bastan.

### DM8-A3 — Settings invalid input histórico

**Estado:** REPRODUCE BEFORE FIX.

LC0 documentó que retention/max inválidos podían terminar en 500. El código actual sigue delegando validación de rango al repository mediante `InvalidArgumentException`; `SystemSettingsController::update()` no la captura explícitamente.

**Decisión:** no afirmar bug cerrado/abierto sin RED reproducible. Próximo microbloque funcional tras selector: prueba 422, luego corrección mínima si RED confirma 500.

### DM8-A4 — OAuth callback destination

**Estado:** REVIEW ONLY.

El callback exitoso redirige actualmente a `/sales`. No cambiar todavía. Auditar contra UX de cuentas MELI y tests existentes en un microbloque separado. Puede ser KEEP si no produce un flujo incorrecto.

## 5. No-hallazgos / ruido evitado

No abrir ahora:
- Logística sin owner factual suficiente;
- Postventa como módulo paralelo si el review factual ya vive en Sales detail;
- Vinculación de catálogo sin contrato real;
- rentabilidad inventada;
- Salud API porcentual ficticia;
- pantalla Work/queues;
- pantalla Runtime/Hostinger;
- scheduler/cron;
- remote writes MELI.

## 6. Gates que siguen posteriores

```text
UI-DM8 gap closure
→ UI-DM9 full automated QA
→ UI-DM10 final browser/responsive QA
→ ERP COMPLETE gate
→ demo local
→ manual E2E
→ user approval
→ Hostinger
→ runtime certification
→ cron/Work Runner
→ real MELI READ-only gates
```

## 7. Próximo microbloque

`NEXT=DM8-A1 COMPANY-SELECT HOME CONSISTENCY`

Disciplina:
1. RED explícito `/dashboard`;
2. certificar fallo esperado;
3. cambio mínimo de ruta;
4. alinear sólo tests obsoletos relacionados;
5. full QA 8.3/8.4/8.5;
6. checkpoint antes de DM8-A2/A3.

## 8. No-go

`REMOTE_WRITES=OFF`. No demo, Hostinger, cron, MELI real, F15/F16 ni merge PR #19 durante DM8.
