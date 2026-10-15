# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=L9 CLOSED/GREEN — LOCAL PRODUCT READY — INTEGRATION CANDIDATE**

| Campo | Estado actual |
|---|---|
| Fecha | 2026-10-14 |
| Rama candidata | `integrate/l9-local-product-ready-20261014` |
| Functional source certificado | `f05377cb17afc3971997f686102ddf29dfbe6bc0` |
| Stable actual | `impl/v3-b-sales-audit-20261010` @ `7dd666552ad0e81b3a4953cffd41393b1cedc1a1` — intacto |
| Último QA código | Actions `#758` — PASS PHP 8.3 / 8.4 / 8.5 |
| PHP 8.3 | 767 tests / 6450 assertions / 0 failures |
| LOCAL_CORE_CONTRACTS_READY | YES |
| LOCAL_PRODUCT_READY | YES |
| L1–L7 | CLOSED |
| L8 Human Local E2E | CLOSED / GREEN |
| L9 Local Release Candidate | CLOSED / GREEN |
| L10 External-gate decision | NOT STARTED — requiere autorización explícita |
| HOSTINGER | DEFERRED |
| F14 LIVE | DEFERRED |
| F15 | PAUSED |
| F16 | UNAUTHORIZED |
| REMOTE_WRITES | OFF |
| NEXT | Aprobar o rechazar integración del candidato a stable; no deploy |

## L8 cerrado

L8-A certificó instalación local real desde base vacía, login y navegación humana. L8-B certificó configuración MELI sintética, OAuth local MLSIM, Seller `100` / `MCO`, una sola cuenta canónica, disconnect y reauthorization sobre el mismo `account_id`, con dos ciclos `oauth.token → users.me` y cero requests al proveedor real.

La evidencia visual L8-B quedó validada en cuatro PNG. La aparición de `Eliminar` en una cuenta `disabled` recién desconectada es compatible con el contrato: hard-delete depende de historia persistida asociada a la cuenta; una cuenta OAuth puede continuar prístina si no existen Work/orders/audits/billing/webhook/account-scoped usage.

## L9 cerrado

L9 detectó y corrigió en causa raíz una contradicción de política de contraseña: `AuthService::bootstrapAdmin()` conservaba un mínimo literal de 12 mientras `PasswordService::MIN_LENGTH` ya definía 6. Se añadió cobertura RED para bootstrap, se eliminó la duplicación y el owner real reutiliza la constante canónica.

Certificación del código:

```text
HEAD=f05377cb17afc3971997f686102ddf29dfbe6bc0
Actions #758=PASS PHP 8.3 / 8.4 / 8.5
PHP 8.3=767 tests / 6450 assertions / 0 failures
PHPStan=PASS
syntax=PASS
```

El RC se construyó fuera del árbol del producto, con `vendor` productivo y sin dependencias dev ni secretos. Se extrajo en limpio y se instaló contra una DB MariaDB nueva. Web Installer, login, `/sales`, `/system/companies` y `/system/users` pasaron con `meli_writes_enabled=0`, `REAL_MELI_HTTP=0` y cero requests al proveedor real.

Artefacto certificado:

```text
ERP_MELI_2_0_RC_L9_f05377cb.zip
SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
PACKAGE_SELF_CONTAINED=YES
SECRETS_IN_PACKAGE=NO
```

## Auditoría de integración

Stable y L9 divergen desde `b26db6a62683791f9c1d7b00c6e59f8964cfeec9`.

La rama stable tiene tres commits exclusivos posteriores a ese punto:

- `4b6e51eb...`: sólo modifica `README.md` para describir candidatos aislados mientras L6–L9 estaban pendientes;
- `70bc2940...`: commit vacío, mismo árbol;
- `7dd66655...`: commit vacío, mismo árbol.

Ese README temporal quedó obsoleto tras cerrar L8/L9. Conforme a KISS, anti-ruido y anti-parche, **no se arrastran esos tres commits mediante merge**. La rama candidata de integración parte directamente del HEAD funcional L9 certificado y refresca únicamente la documentación de continuidad al estado real.

Delta del candidato sobre el source funcional:

```text
README.md                   docs-only
docs/CURRENT_CHECKPOINT.md  docs-only
PRODUCT_CODE_DELTA=0
```

No se ha movido stable ni main. No hubo deploy.

## Guardrails permanentes

```text
KISS: simple, eficiente, optimizado, eficaz; sin arquitectura innecesaria
NOISE: eliminar/simplificar/unir antes de añadir
ANTI-PATCH: corregir causa raíz, no workaround sobre workaround
remote writes=OFF
no Hostinger / no F14 / no LIVE sin decisión L10 explícita
no merge/movimiento de stable o main sin autorización explícita
```

## NEXT — un solo microbloque

Revisar este candidato de integración y, **sólo con autorización explícita del usuario**, mover `impl/v3-b-sales-audit-20261010` al HEAD candidato usando una operación controlada con lease. Después de integrar stable, detenerse antes de Hostinger/F14/LIVE y abrir L10 únicamente mediante una decisión explícita separada.

Los contratos durables siguen en [`ERP2_AUTHORITY.md`](ERP2_AUTHORITY.md).