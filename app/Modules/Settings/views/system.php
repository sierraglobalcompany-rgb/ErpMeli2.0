<?php
/** @var \App\Modules\Settings\SystemSettings $settings */
/** @var string $csrfToken */
/** @var array{total_bytes:int,days:list<array{day:string,bytes:int,compressed:bool}>} $debugUsage */
/** @var bool $debugCapReached */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema — ERP Meli 2.0</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="simple-page administration-system-workspace" data-design-master-page="system-settings">
    <nav class="simple-nav"><a href="/">Inicio</a><a href="/system/companies">Empresas</a><a href="/system/users">Usuarios</a><a href="/settings/mercadolibre">Mercado Libre</a></nav>

    <header class="page-header system-settings-page-header">
        <span class="eyebrow">Administración</span>
        <h1>Configuración del sistema</h1>
        <p>Controla sólo los ajustes operativos globales y el diagnóstico local del ERP.</p>
    </header>

    <div class="settings-grid">
        <section class="panel panel-full system-operation-panel" aria-labelledby="system-operation-title">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Sistema</span>
                    <h2 id="system-operation-title">Operación</h2>
                </div>
                <p>Ajustes globales operativos. Las escrituras remotas de Mercado Libre permanecen fuera de esta superficie.</p>
            </div>

            <form class="settings-form" method="post" action="/settings/system">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

                <label>
                    <input type="checkbox" name="automation_enabled" value="1" <?= $settings->automationEnabled ? 'checked' : '' ?>>
                    Sincronización automática
                </label>

                <label>
                    <input type="checkbox" name="debug_enabled" value="1" <?= $settings->debugEnabled ? 'checked' : '' ?>>
                    Debug
                </label>

                <label>
                    Retención debug
                    <select name="debug_retention_days">
                        <?php foreach ([1, 3, 7, 14, 30, 90] as $days): ?>
                            <option value="<?= $days ?>" <?= $settings->debugRetentionDays === $days ? 'selected' : '' ?>><?= $days ?> días</option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Límite debug (MB)
                    <input type="number" min="10" max="10240" name="debug_max_mb" value="<?= $settings->debugMaxMb ?>">
                </label>

                <button type="submit">Guardar configuración</button>
            </form>
        </section>

        <section class="panel panel-full system-debug-panel" aria-labelledby="system-debug-title">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Diagnóstico</span>
                    <h2 id="system-debug-title">Uso debug</h2>
                </div>
                <p><?= $debugUsage['total_bytes'] ?> bytes almacenados.</p>
            </div>

            <?php if ($debugCapReached): ?>
                <p class="notice" role="alert">Límite de almacenamiento debug alcanzado.</p>
            <?php endif; ?>

            <?php if ($debugUsage['days'] !== []): ?>
                <dl class="settings-list">
                    <?php foreach ($debugUsage['days'] as $day): ?>
                        <dt><?= $e($day['day']) ?></dt>
                        <dd><?= $day['bytes'] ?> bytes<?= $day['compressed'] ? ' — gzip' : '' ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php else: ?>
                <p class="empty-state">Sin historial debug.</p>
            <?php endif; ?>

            <form class="settings-form" method="post" action="/settings/system/debug/export">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <label>
                    Desde UTC
                    <input type="date" name="debug_start_date">
                </label>
                <label>
                    Hasta UTC
                    <input type="date" name="debug_end_date">
                </label>
                <p class="muted">Para un solo día usa la misma fecha. Semana o mes se exportan como rango.</p>
                <button type="submit">Exportar debug ZIP</button>
            </form>

            <form class="settings-form" method="post" action="/settings/system/debug/clear">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <button class="danger" type="submit">Limpiar debug</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
