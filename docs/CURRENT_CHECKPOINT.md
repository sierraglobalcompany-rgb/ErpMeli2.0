# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-1 — RED VERIFICADO PARA CSS COMPARTIDO**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Código UI checkpoint | `cbcc44ede76c9c32a1fe6a0ce8e87dd2fcea3891` |
| Hostinger | ERP2 instalado; smoke login/empresas/usuarios/configuración/ventas PASS |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | GREEN mínimo para `/assets/app.css` y STOP |

## Cerrado antes de este checkpoint

- L1–L10 local readiness cerrado.
- RC L9 certificado y desplegado manualmente en Hostinger.
- Login y pantallas principales abren sin error.
- `/` sin sesión: causa raíz identificada y hotfix TDD certificado por Actions #767.
- PR #18 contiene el rediseño UI aislado; stable todavía no recibe estos cambios.

## UI-1 actual

Se creó un sistema visual KISS con un único CSS compartido, sin framework frontend nuevo. Se trabajaron navegación, Ventas, Catálogo, Facturación, Empresas, Usuarios, Configuración y selector de empresa.

Actions #768 encontró sólo una incompatibilidad textual (`Uso debug`); se corrigió preservando el contrato existente.

Durante la auditoría se detectó una causa raíz adicional: las vistas apuntan a `/assets/app.css`, pero el runtime no sirve todavía esa ruta. Se añadió una prueba RED específica.

Actions #770 confirma el RED esperado en PHP 8.3/8.4/8.5:

```text
GET /assets/app.css
EXPECTED=200 text/css
ACTUAL=404
```

No hay que tocar tests para hacerlo pasar. El siguiente bloque debe implementar la ruta estática mínima y segura para ese único CSS compartido, manteniendo bloqueado `/public` y el source privado.

## 3 reglas

```text
KISS: camino mínimo y arquitectura simple
ANTI-RUIDO: reutilizar/unir/simplificar; no duplicar soluciones
ANTI-PARCHE: corregir owner/causa raíz; no workaround sobre workaround
```

## Guardrails

```text
no merge a stable todavía
no deploy UI todavía
no OAuth real
no API Mercado Libre real
remote writes=OFF
no F15/F16
```

## NEXT — un solo microbloque

**Implementar GREEN mínimo para `/assets/app.css`, ejecutar QA y crear checkpoint. STOP.**

No avanzar login/instalador ni más rediseño dentro del mismo bloque.
