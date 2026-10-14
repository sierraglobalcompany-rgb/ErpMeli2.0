# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=L9 LOCAL RELEASE CANDIDATE — PACKAGE BUILD PENDING**

| Campo | Estado actual |
|---|---|
| Fecha | 2026-10-14 |
| Rama activa | `impl/l9-local-release-candidate-chatgpt` |
| Functional source HEAD certificado | `ea4fddab7d4d6439583dc5061daa5936eb97cc1f` |
| Stable separado | `impl/v3-b-sales-audit-20261010` — no merge autorizado |
| Último QA código | Actions `#754` — PASS PHP 8.3 / 8.4 / 8.5 |
| PHP 8.3 | 766 tests / 6448 assertions / 0 failures |
| LOCAL_CORE_CONTRACTS_READY | YES |
| LOCAL_PRODUCT_READY | NO |
| L1 Web Installer | CLOSED |
| L2 Company Management | CLOSED |
| L3 Users/Roles/Memberships | CLOSED |
| L4 Meli App Settings | CLOSED |
| L5 Canonical Seller + Account Management | CLOSED |
| L6 Local OAuth / MLSIM | CLOSED |
| L7 Account Lifecycle | CLOSED |
| L8 Human Local E2E | CLOSED / GREEN |
| L9 Local Release Candidate | IN PROGRESS |
| HOSTINGER | DEFERRED |
| F14 LIVE | DEFERRED |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| NEXT | Construir y probar un paquete local autocontenido desde el functional source HEAD |

## L8 cerrado

L8-A certificó instalación local real desde base vacía, login y navegación humana. L8-B certificó configuración MELI sintética, OAuth local MLSIM, Seller `100` / `MCO`, una sola cuenta canónica, disconnect y reauthorization sobre el mismo `account_id`, con dos ciclos `oauth.token → users.me` y cero requests al proveedor real.

La evidencia visual L8-B quedó validada en cuatro PNG. La aparición de `Eliminar` en una cuenta `disabled` recién desconectada es compatible con el contrato: hard-delete depende de historia persistida asociada a la cuenta; una cuenta OAuth puede continuar prístina si no existen Work/orders/audits/billing/webhook/account-scoped usage.

## L9 — decisión KISS

No se añadirá un framework de releases ni otro stack de packaging al producto.

El paquete local debe construirse **fuera del árbol de producto** desde el functional source HEAD certificado y demostrar que el artefacto, no sólo el repositorio, instala y arranca.

### Forma de construcción

1. materializar exactamente `ea4fddab7d4d6439583dc5061daa5936eb97cc1f` en un worktree limpio;
2. crear staging desechable fuera del repo;
3. copiar sólo contenido versionado necesario;
4. instalar dependencias de producción en staging con Composer `--no-dev --prefer-dist --optimize-autoloader`;
5. retirar del staging ruido no-runtime: `.git*`, `.github`, `tests`, docs de desarrollo, caches y herramientas QA;
6. conservar runtime real: `app`, `bin`, `config`, `database`, `public`, `storage` skeleton, `.htaccess`, `composer.json`, `composer.lock` y `vendor` de producción;
7. no incluir `.env`, `storage/private/runtime.json`, `installed.lock`, credenciales, logs, debug, cache, exports, invoices ni tmp generados;
8. crear un único ZIP RC + SHA-256;
9. extraer el ZIP en otra carpeta limpia;
10. verificar `vendor/autoload.php`, permisos/skeleton de storage y ausencia de secretos;
11. ejecutar el Web Installer desde el ZIP extraído contra una DB nueva y dedicada;
12. smoke mínimo: instalación → login → `/sales` → `/system/companies` → `/system/users`;
13. `REAL_MELI_HTTP=0`, remote writes OFF, no Hostinger/F14/LIVE.

`RuntimePreflight::installerChecks()` exige `vendor/autoload.php`; por eso un `git archive` sin dependencias no cumple L9 como paquete instalable.

## Criterio de cierre L9

L9 sólo puede cerrar si existe evidencia de:

```text
PACKAGE_BUILD=PASS
PACKAGE_SELF_CONTAINED=YES
PRODUCTION_VENDOR_PRESENT=YES
DEV_VENDOR_ABSENT=YES
SECRETS_IN_PACKAGE=NO
PACKAGE_SHA256=KNOWN
EXTRACTED_INSTALLER=PASS
EXTRACTED_LOGIN=PASS
EXTRACTED_SALES=PASS
EXTRACTED_COMPANIES=PASS
EXTRACTED_USERS=PASS
REAL_MELI_HTTP=0
REMOTE_WRITES=OFF
PRODUCT_CODE_CHANGED=NO
```

El ZIP no se despliega. Hostinger/F14 siguen cerrados hasta terminar L9 y luego tomar una decisión explícita L10.

## Guardrails permanentes

```text
KISS: simple, eficiente, optimizado, eficaz; sin arquitectura innecesaria
NOISE: eliminar/simplificar/unir antes de añadir
ANTI-PATCH: corregir causa raíz, no workaround sobre workaround
REAL_MELI_HTTP=0 durante L9
remote writes=OFF
no Hostinger / no F14 / no LIVE
no merge a stable/main sin autorización explícita
```

## NEXT — un solo microbloque

Construir el RC local en Windows y probar una instalación completamente nueva **desde el ZIP extraído**. Codex sólo participa porque requiere filesystem, Composer, ZIP, MariaDB y navegador local. Si el paquete falla por defecto de producto, no parchear localmente: volver al owner raíz con RED → GREEN mínimo → QA → checkpoint.

La continuidad adicional está en Issue #16. Los contratos durables siguen en [`ERP2_AUTHORITY.md`](ERP2_AUTHORITY.md).
