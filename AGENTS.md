# ERP MELI 2.0 — Ley de ingeniería

Esta regla es obligatoria para cualquier persona, IA o agente que diseñe, programe, revise o modifique ERP MELI 2.0.

## Puerta KISS obligatoria

Antes de proponer o implementar cualquier cambio, responder internamente:

1. ¿Es KISS?
2. ¿Es simple?
3. ¿Está optimizado para el problema real?
4. ¿Es eficiente?
5. ¿Se puede mejorar usando menos piezas, menos estado, menos consultas o menos abstracciones?

Si alguna respuesta es no, dudosa o existe una solución claramente más sencilla, no implementar todavía: simplificar y volver a evaluar.

## Reducción de ruido

ERP MELI 2.0 no debe acumular código, archivos, caminos alternos, compatibilidad, historial técnico ni abstracciones que ya no aportan al sistema actual.

Antes de agregar o conservar una pieza:

1. Debe servir hoy para una necesidad real.
2. Debe evaluarse si cabe en una pieza existente.
3. Si duplica lógica, datos, flujo, documentación o responsabilidad, preferir fusionar.
4. Si fue reemplazada u obsoleta, eliminarla tras comprobar referencias, tests y contratos.
5. Git conserva el historial; no mantener código o documentos muertos "por si acaso".
6. Si una pieza no puede borrarse, debe demostrar utilidad actual, auditabilidad necesaria, compatibilidad real o rollback real.

## Regla anti-parches

- No parche sobre parche.
- Si una solución nueva sustituye a otra, revisar en el mismo cambio qué código, tests, configuración o documentación vieja puede retirarse.
- No crear archivos `legacy`, `old`, `v2`, `final`, `new` ni caminos paralelos como forma normal de evolución.
- La compatibilidad temporal debe tener una razón y una condición de eliminación.
- Una eliminación sólo es correcta cuando está respaldada por búsqueda de referencias, QA y revisión de contratos/datos.

## Orden de prioridades

Correcto > Simple > Estable > Mantenible > Eficiente > Escalable.

La escalabilidad futura no justifica complejidad presente sin evidencia.

## Reglas no negociables

- Reutilizar primero `Work`, `MeliClient`, MariaDB y módulos existentes.
- No crear motores, colas, estados, tablas, servicios, capas, dependencias ni abstracciones nuevas si la solución correcta cabe en lo existente.
- No implementar arquitectura especulativa.
- Un cambio pequeño debe producir un diff pequeño y una responsabilidad clara.
- La lógica de negocio no depende de timezone de PHP, MySQL o Hostinger.
- Fechas externas se normalizan determinísticamente desde la fecha fuente remota.
- `created_at`/`updated_at` nunca sustituyen la fecha fuente de Mercado Libre.
- Históricos no generan retries eternos.
- Ejecutar sin error no significa cobertura completa: debe demostrarse.
- Tests antes del GREEN: RED → fallo esperado → GREEN mínimo → QA → checkpoint.
- No mezclar refactors no relacionados.
- No usar float para dinero exacto.
- Remote writes permanecen OFF hasta F16 y autorización explícita.

## Regla de eliminación

Ante dos soluciones igualmente correctas, elegir la que tenga menos código, menos estado, menos consultas, menos componentes, menos dependencias, menos pasos operativos y menos caminos alternos.

Pregunta final obligatoria:

> ¿Podemos resolverlo correctamente dejando el sistema con menos cosas que entender y mantener?
