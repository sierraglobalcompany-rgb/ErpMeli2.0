<?php
/** @var \App\Modules\Settings\SystemSettings $settings */
/** @var string $csrfToken */
/** @var array{total_bytes:int,days:list<array{day:string,bytes:int,compressed:bool}>} $debugUsage */
/** @var bool $debugCapReached */
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
<main class="simple-page">
    <nav class="simple-nav"><a href="/">Inicio</a><a href="/system/companies">Empresas</a><a href="/system/users">Usuarios</a><a href="/settings/mercadolibre">Mercado Libre</a></nav>
    <h1>Configuración del sistema</h1>
    <p>Controla sólo los ajustes operativos globales del ERP.</p>
    <section>
        <h2>Operación</h2>
        <form method="post" action="/settings/system">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label>
                <input type="checkbox" name="automation_enabled" value="1" <?= $settings->automationEnabled ? 'checked' : '' ?>>
                Sincronización automática
            </label><br>

            <label>
                <input type="checkbox" name="debug_enabled" value="1" <?= $settings->debugEnabled ? 'checked' : '' ?>>
                Debug
            </label><br>

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

    <section>
        <h2>Uso debug</h2>
        <p><?= $debugUsage['total_bytes'] ?> bytes almacenados.</p>

        <?php if ($debugCapReached): ?>
            <p role="alert">Límite de almacenamiento debug alcanzado.</p>
        <?php endif; ?>

        <?php if ($debugUsage['days'] !== []): ?>
            <ul>
                <?php foreach ($debugUsage['days'] as $day): ?>
                    <li>
                        <?= htmlspecialchars($day['day'], ENT_QUOTES, 'UTF-8') ?> —
                        <?= $day['bytes'] ?> bytes<?= $day['compressed'] ? ' — gzip' : '' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Sin historial debug.</p>
        <?php endif; ?>

        <form method="post" action="/settings/system/debug/export">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                Desde UTC
                <input type="date" name="debug_start_date">
            </label>
            <label>
                Hasta UTC
                <input type="date" name="debug_end_date">
            </label>
            <p>Para un solo día usa la misma fecha. Semana o mes se exportan como rango.</p>
            <button type="submit">Exportar debug ZIP</button>
        </form>

        <form method="post" action="/settings/system/debug/clear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="danger" type="submit">Limpiar debug</button>
        </form>
    </section>
</main>
</body>
</html>
