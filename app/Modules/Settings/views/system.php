<?php
/** @var \App\Modules\Settings\SystemSettings $settings */
/** @var string $csrfToken */
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
</main>
</body>
</html>
