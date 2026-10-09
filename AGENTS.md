# ERP MELI 2.0 — Ley de ingeniería

Esta regla es **obligatoria** para cualquier persona, IA o agente que diseñe, programe, revise o modifique ERP MELI 2.0.

## Puerta KISS obligatoria

Antes de proponer o implementar cualquier cambio, responder internamente:

1. **¿Es KISS?**
2. **¿Es simple?**
3. **¿Está optimizado para el problema real?**
4. **¿Es eficiente?**
5. **¿Se puede mejorar usando menos piezas, menos estado, menos consultas o menos abstracciones?**

Si alguna respuesta es **no**, **dudosa** o existe una solución claramente más sencilla, **no implementar todavía**: simplificar y volver a evaluar hasta cumplir la puerta.

## Orden de prioridades

**Correcto > Simple > Estable > Mantenible > Eficiente > Escalable**

La escalabilidad futura no justifica complejidad presente sin evidencia.

## Reglas no negociables

- Reutilizar primero lo que ya existe: módulos, base de datos, `Work Engine`, `MeliClient` y patrones aprobados.
- No crear motores, colas, estados, tablas, servicios, capas, dependencias ni abstracciones nuevas si la solución correcta cabe en lo existente.
- No implementar arquitectura especulativa ni preparar infraestructura para problemas que todavía no existen.
- Un cambio pequeño debe producir un diff pequeño y una responsabilidad clara.
- La lógica de negocio no puede depender de la zona horaria local de PHP, MySQL o del servidor Hostinger.
- Las fechas externas deben conservar su instante/origen y normalizarse de forma determinística antes de decidir día, mes o período contable.
- `created_at`, `updated_at` o la hora de descarga nunca sustituyen la fecha fuente de Mercado Libre para clasificar una venta.
- Los procesos históricos no pueden generar reintentos eternos sobre recursos que la API ya no ofrece.
- Un proceso ejecutado sin error no significa que un período esté completo; la cobertura debe poder demostrarse.
- Tests antes del GREEN cuando cambia comportamiento: **RED → fallo esperado → GREEN mínimo → QA → checkpoint**.
- No mezclar refactors, mejoras estéticas o arquitectura adicional dentro de una corrección concreta.

## Regla de eliminación

Ante dos soluciones igualmente correctas, elegir la que tenga:

- menos código,
- menos estado,
- menos consultas,
- menos componentes,
- menos dependencias,
- menos pasos operativos,
- y sea más fácil de explicar y reparar.

Si una solución necesita una explicación arquitectónica larga para resolver un problema pequeño, debe revisarse.
