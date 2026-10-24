# AGENTS.md


## L1 KISS + Ponytail
Antes de editar software, consulta `.agents/skills/kiss-ponytail/SKILL.md`: comprende el flujo/callers, elimina solo lo no requerido, reutiliza primero código existente, biblioteca estándar, capacidad nativa y dependencias actuales. Cambia lo mínimo completo preservando seguridad, datos, validaciones, UX, pruebas y L2–L6. `ponytail-audit` solo cuando se solicite/justifique. No confundir esta integración con el plugin global y sus hooks, que requieren instalación y confianza separadas.

## PingPongLaw L1–L6 — referencia canónica fijada

**Canon para consulta:** https://github.com/sierraglobalcompany-rgb/PingPongLaw/blob/84e961cadf482d234f4c0ee2ca60e8c7e9b388e8/docs/engineering/LEYES_IMPLEMENTACION_1_A_6.md
**Router de skills (fuente, no instalación local):** https://github.com/sierraglobalcompany-rgb/PingPongLaw/blob/84e961cadf482d234f4c0ee2ca60e8c7e9b388e8/.agents/skills/pingponglaw/SKILL.md

Al iniciar una tarea, comprobar la autoridad del repositorio y, si la fuente es accesible, leer realmente el canon fijado y seleccionar únicamente las skills pertinentes. Una URL no significa `SKILL_LOADED`, plugin instalado ni hooks habilitados; si la fuente no puede consultarse, declarar `CANON_READ=BLOCKED` y no fingir validación. La skill local `.agents/skills/kiss-ponytail/SKILL.md` se conserva como adaptador L1 para el trabajo offline, no como canon alternativo.

- **L1 — KISS + Ponytail:** escoger la solución mínima completa, reutilizando owners, plataforma y dependencias sin recortar seguridad, datos ni QA.
- **L2 — Antirruido:** comprobar equivalencia y consumidores antes de unir o retirar; conservar lo incierto.
- **L3 — Antiparches:** identificar causa raíz, corregir al dueño y comprobar regresiones; no apilar remedios.
- **L4 — Diseño humano:** prevalecen SPEC, mockup aprobado y reglas Human-First propias del ERP; las fuentes externas son consultivas.
- **L5 — Verificación independiente:** pruebas atribuibles al SHA/build y aceptación proporcionada al riesgo; QA del autor no equivale a verificador externo.
- **L6 — Antiping-pong:** ChatGPT investiga, decide e implementa lo accesible; Codex recibe solo un lote necesario por límite real, con pruebas y cierre.

**Protección del proyecto:** esta referencia no reemplaza las normas específicas existentes en las ramas activas ni altera políticas de diez pasadas, permisos, multiempresa, cron/Work, QA, seguridad, ramas, F14/F16 o la prohibición de escrituras remotas sin autorización. Si al integrar ramas aparece conflicto, conservar ambas instrucciones y reconciliarlo antes de merge; no sobrescribir el `AGENTS.md` de producto. No instalar ni activar hooks, plugins, CI o skills globales por esta referencia.

**Cierre por tarea:** registrar leyes aplicables, evidencias `PASS/FAIL/BLOCKED/NOT_RUN`, pendientes reales y `NEXT` único o `NINGUNO`. No lanzar auditorías forenses ni diez pasadas por rutina si no las exige la instrucción vigente o un riesgo demostrado.
