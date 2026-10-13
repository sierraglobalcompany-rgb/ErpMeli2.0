<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $accounts */
/** @var list<array<string,mixed>> $periods */
/** @var array{account_id:int,period_key:string,document_type:string}|null $selection */
/** @var array<string,mixed>|null $progress */
/** @var array{id:int,status:string}|null $requestState */
/** @var string|null $error */
/** @var array{id:int,name:string,role:string} $company */
/** @var string $csrfToken */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facturación</title>
</head>
<body>
    <?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
    <main>
        <h1>Facturación</h1>
        <?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($company['role'] === 'admin'): ?>
        <form method="post" action="/billing/syncs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Cuenta <select name="account_id" required>
                <?php foreach ($accounts as $account): ?>
                <option value="<?= (int) $account['id'] ?>" <?= $selection !== null && $selection['account_id'] === (int) $account['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $account['external_user_id'] . ' — ' . (string) ($account['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Período (AAAA-MM-01) <input name="period_key" type="text" pattern="[0-9]{4}-[0-9]{2}-01" placeholder="2026-09-01" value="<?= htmlspecialchars($selection['period_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
            <label>Tipo documental <select name="document_type">
                <option value="BILL">BILL</option>
                <option value="CREDIT_NOTE" <?= ($selection['document_type'] ?? '') === 'CREDIT_NOTE' ? 'selected' : '' ?>>CREDIT_NOTE</option>
            </select></label>
            <button type="submit">Solicitar sincronización</button>
        </form>
        <p>La solicitud registra trabajo pendiente. No consulta Mercado Libre desde esta página.</p>
        <?php endif; ?>

        <?php if ($selection !== null): ?>
        <h2>Solicitud seleccionada</h2>
        <p>Cuenta <?= $selection['account_id'] ?> · <?= htmlspecialchars($selection['period_key'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($selection['document_type'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (($requestState['status'] ?? null) === 'failed'): ?>
            <p>Requiere revisión. Una nueva solicitud debe ser explícita.</p>
        <?php elseif (in_array($requestState['status'] ?? null, ['pending', 'running'], true)): ?>
            <p><?= $progress !== null && $progress['sync_state'] === 'pending' ? 'Sincronizando' : 'Solicitud registrada. En espera de progreso confirmado.' ?></p>
        <?php elseif ($progress === null): ?>
            <p>Sin solicitud ni progreso confirmados.</p>
        <?php endif; ?>
        <?php if ($progress !== null): ?>
        <dl>
            <dt>Progreso persistido</dt><dd><?= $progress['sync_state'] === 'caught_up' ? 'Al día con la fuente consultada' : 'Progreso registrado' ?></dd>
            <dt>Cursor confirmado</dt><dd><?= htmlspecialchars((string) $progress['cursor_last_id'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Última lectura persistida (UTC)</dt><dd><?= htmlspecialchars((string) ($progress['last_synced_at'] ?? 'Sin lectura confirmada'), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
        <?php endif; ?>
        <?php endif; ?>

        <h2>Períodos registrados</h2>
        <p>Hasta 100 períodos. Actualice la página para consultar el progreso persistido.</p>
        <table>
            <thead><tr><th>Cuenta</th><th>Período</th><th>Tipo</th><th>Progreso persistido</th><th>Última lectura (UTC)</th></tr></thead>
            <tbody>
            <?php foreach ($periods as $period): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $period['external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a href="/billing?<?= htmlspecialchars(http_build_query(['account_id' => (int) $period['account_id'], 'period_key' => $period['period_key'], 'document_type' => $period['document_type']], '', '&', PHP_QUERY_RFC3986), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $period['period_key'], ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars((string) $period['document_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $period['sync_state'] === 'caught_up' ? 'Al día con la fuente consultada' : 'Progreso registrado' ?></td>
                    <td><?= htmlspecialchars((string) ($period['last_synced_at'] ?? 'Sin lectura confirmada'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
