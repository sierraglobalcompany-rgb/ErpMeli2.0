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
    <title>Sistema — ERP Meli 2.0</title>
</head>
<body>
<main>
    <h1>Sistema</h1>
    <form method="post" action="/settings/system">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <label>
            <input type="checkbox" name="automation_enabled" value="1" <?= $settings->automationEnabled ? 'checked' : '' ?>>
            Sincronización automática
        </label><br>

        <label>
            <input type="checkbox" name="meli_writes_enabled" value="1" <?= $settings->meliWritesEnabled ? 'checked' : '' ?>>
            Escrituras Mercado Libre
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
        </label><br>

        <label>
            Límite debug (MB)
            <input type="number" min="10" max="10240" name="debug_max_mb" value="<?= $settings->debugMaxMb ?>">
        </label><br>

        <button type="submit">Guardar</button>
    </form>

    <section>
        <h2>Uso debug</h2>
        <p><?= $debugUsage['total_bytes'] ?> bytes</p>

        <?php if ($debugCapReached): ?>
            <p role="alert">Límite de almacenamiento debug alcanzado</p>
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
            <button type="submit">Limpiar debug</button>
        </form>
    </section>
</main>
</body>
</html>
