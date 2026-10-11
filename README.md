# ERP MELI 2.0

ERP MELI 2.0 es el ERP greenfield de Sierra Global para operar y auditar Mercado Libre con una arquitectura deliberadamente pequeña, auditable y recuperable.

**Stack soportado:** PHP 8.3 / 8.4 / 8.5 + Slim 4 + PDO/MariaDB + PHPUnit + PHPStan.  
**Estado:** pre-release; remote Mercado Libre writes OFF.  
**Rama de trabajo actual:** ver `docs/CURRENT_CHECKPOINT.md`.

## Leer en este orden

Para continuar el proyecto sin reconstruir conversaciones antiguas:

1. verificar branch y HEAD reales;
2. leer `AGENTS.md` — leyes de ingeniería y KISS;
3. leer `docs/CURRENT_CHECKPOINT.md` — estado actual, SHAs, QA, gates y siguiente microbloque;
4. leer `docs/ERP2_AUTHORITY.md` — contratos durables de arquitectura y dominio;
5. abrir sólo la documentación de evidencia específica que el microbloque necesite;
6. auditar únicamente el delta desde el checkpoint si HEAD cambió.

No usar planes, checkpoints o handoffs históricos como autoridad. Git conserva la historia.

## Separación de responsabilidades documentales

| Archivo | Responsabilidad |
|---|---|
| `AGENTS.md` | Leyes de ingeniería, reducción de ruido, TDD, KISS y límites no negociables. |
| `docs/CURRENT_CHECKPOINT.md` | Verdad transitoria: branch/HEAD, último QA, trabajo cerrado, gate actual y siguiente microbloque. |
| `docs/ERP2_AUTHORITY.md` | Contratos durables que deben sobrevivir a varios checkpoints. No debe contener changelog ni roadmap transitorio. |
| `README.md` | Índice de entrada. No duplica contratos detallados ni historial. |

### Precedencia para saber qué existe hoy

```text
código/schema real del branch activo
> tests/CI del SHA relevante
> docs/CURRENT_CHECKPOINT.md
> docs/ERP2_AUTHORITY.md
> README/documentación de apoyo
> historia/inferencias
```

Una decisión explícita reciente del usuario gobierna el alcance y la autorización de la siguiente acción; después debe quedar reflejada en checkpoint/authority si cambia un contrato durable.

## Producto y límites

ERP2 reemplaza progresivamente ERP1, pero ERP1 es fuente de evidencia y aprendizaje, no blueprint arquitectónico.

El núcleo actual mantiene una sola aplicación PHP/Slim, una MariaDB, un `Work` durable, un `WorkRunner`, un `MeliClient`, OAuth centralizado, rate safety, Sales operational truth, Sales Audit y tooling C0 de Billing. No se agregan queues, schedulers, engines, estados, tablas o capas por dominio sin evidencia real.

Reglas de seguridad permanentes durante esta etapa:

```text
REAL_MELI_HTTP normal = 0
remote writes = OFF
no merge sin autorización explícita
no deploy sin autorización explícita
no mutación de producción DB/OAuth sin autorización explícita
```

## Documentación de apoyo vigente

Estos documentos son evidencia o guías operativas específicas; no reemplazan checkpoint/authority:

- `docs/meli-contracts-2026.md` — contratos externos Mercado Libre verificados contra documentación oficial;
- `docs/mercadolibre-app-erp2.md` — aplicación/OAuth dedicado de ERP2;
- `docs/runtime-preflight.md` — preflight de runtime;
- `docs/hostinger-runtime-evidence.md` — evidencia de hosting/runtime.

## Forma de trabajo

Cada cambio se ejecuta como microbloque:

```text
problema/evidencia
-> DELETE/SIMPLIFY/REUSE/MERGE
-> RED
-> confirmar fallo correcto
-> GREEN mínimo
-> QA completa
-> noise audit
-> checkpoint
-> STOP
```

El punto exacto de continuidad siempre está en `docs/CURRENT_CHECKPOINT.md`.
