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

## Ley obligatoria de reducción de ruido

ERP MELI 2.0 no debe acumular código, archivos, caminos alternos, compatibilidad, historial técnico ni abstracciones que ya no aportan al sistema actual.

Antes de **agregar** una pieza y antes de **conservar** una pieza existente, responder:

1. **¿Sirve hoy para una necesidad real?**
2. **¿Se puede mejorar o simplificar en el mismo lugar en vez de añadir otra capa?**
3. **¿Duplica lógica, datos, flujo, documentación o responsabilidad existente?** Si sí, preferir fusionar.
4. **¿Ya fue reemplazada o quedó obsoleta?** Si sí, evaluar eliminarla.
5. **¿Se puede eliminar sin afectar otra área?** Verificar referencias, contratos, tests, datos y ejecución antes de borrar.
6. **¿Debe conservarse por una razón real de auditoría, migración, rollback o compatibilidad?** Si no hay una razón demostrable, no conservar por costumbre.
7. **¿Puede aislarse del runtime principal si debe existir pero no participa en la operación normal?**
8. Después de la decisión: **¿el resultado final tiene menos ruido y es más fácil de entender, probar, operar y reparar?**

### Regla anti-parches

- **No parche sobre parche.** Antes de añadir una corrección alrededor de otra corrección, revisar si ambas pueden reemplazarse por una sola solución correcta.
- Un bugfix no debe dejar dos caminos permanentes para resolver el mismo problema salvo una transición temporal demostrablemente necesaria.
- Si una implementación nueva reemplaza una anterior, evaluar en el mismo cambio qué código, pruebas, configuración o documentación vieja puede retirarse con seguridad.
- La compatibilidad temporal debe tener una razón y una condición clara de eliminación; no convertirla en arquitectura permanente por inercia.
- No crear archivos `legacy`, `old`, `v2`, `final`, `new`, copias paralelas o implementaciones alternativas como forma normal de evolucionar el sistema.
- No conservar código muerto “por si acaso”. Git ya conserva el historial recuperable.
- No eliminar a ciegas: una eliminación se considera correcta cuando puede demostrarse mediante búsqueda de referencias, tests/QA y revisión de contratos o datos afectados.
- No borrar historial que siga siendo necesario para reproducir instalaciones, migraciones de datos, auditoría o cumplimiento. El objetivo es eliminar **ruido inútil**, no evidencia necesaria.

### Objetivo de complejidad neta

Para correcciones, refactors y reemplazos, buscar que la complejidad final sea **igual o menor** que antes. Si el cambio añade una pieza, debe justificar qué necesidad real cubre y por qué no cabe correctamente en las piezas existentes.

La pregunta final obligatoria es:

> **¿Podemos resolverlo correctamente dejando el sistema con menos cosas que entender y mantener?**

Si la respuesta es sí, ésa es la opción preferida.

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
- menos caminos alternos,
- menos historial técnico inútil,
- y sea más fácil de explicar y reparar.

Si una solución necesita una explicación arquitectónica larga para resolver un problema pequeño, debe revisarse.
