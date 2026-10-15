<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $orders */
/** @var list<array<string,mixed>> $auditRuns */
/** @var string $orderId */
/** @var bool $canStartAudit */
/** @var list<array<string,mixed>> $auditAccounts */
/** @var string $csrfToken */
/** @var string $lastClosedPeriodKey */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ventas</title>
</head>
<body>
    <?php require dirname(__DIR__, 4) . '/Core/Http/views/navigation.php'; ?>
    <main>
        <h1>Ventas</h1>
        <p>Consulta las ventas almacenadas localmente y controla las auditorías históricas de cada cuenta.</p>
        <form method="get" action="/sales">
            <label>ID exacto de venta <input name="order_id" inputmode="numeric" pattern="[0-9]{1,32}" value="<?= htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') ?>"></label>
            <button type="submit">Buscar venta</button>
            <a href="/sales">Ver ventas recientes</a>
        </form>
        <?php if ($orders === []): ?>
            <p><?= $orderId === '' ? 'No hay ventas locales.' : 'No se encontró la venta en esta empresa.' ?></p>
        <?php endif; ?>

        <?php if ($canStartAudit): ?>
            <section aria-labelledby="sales-audit-title">
                <h2 id="sales-audit-title">Auditoría histórica</h2>
                <?php if ($auditAccounts === []): ?>
                    <p>No hay cuentas de Mercado Libre conectadas para auditar.</p>
                <?php else: ?>
                    <form method="post" action="/sales/audits">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label>
                            Cuenta
                            <select name="account_id" required>
                                <?php foreach ($auditAccounts as $account): ?>
                                    <?php
                                    $nickname = trim((string) ($account['nickname'] ?? ''));
                                    $sellerId = (string) ($account['external_user_id'] ?? '');
                                    $label = $nickname === '' ? $sellerId : $nickname . ' (' . $sellerId . ')';
                                    ?>
                                    <option value="<?= htmlspecialchars((string) $account['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Mes a auditar
                            <input type="month" name="period_key" max="<?= htmlspecialchars(substr($lastClosedPeriodKey, 0, 7), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(substr($lastClosedPeriodKey, 0, 7), ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <button type="submit">Iniciar auditoría</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section aria-labelledby="audit-results-title">
            <h2 id="audit-results-title">Estado y resultados de auditoría (últimas 25)</h2>
            <p>CAPTURING, REPAIRING y CONFIRMING indican auditoría pendiente o en curso. VALID, ATTENTION y UNAVAILABLE son los estados finales registrados. Actualiza la página para consultar cambios.</p>
            <?php if ($auditRuns === []): ?>
                <p>No hay auditorías para esta empresa.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Cuenta</th><th>Período</th><th>Estado</th><th>Inicio (UTC)</th><th>Fin (UTC)</th><th>Ventas locales</th><th>Ventas remotas</th></tr></thead>
                    <tbody>
                    <?php foreach ($auditRuns as $run): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $run['external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $run['period_key'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(strtoupper((string) $run['status']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $run['started_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($run['completed_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($run['canonical_count'] ?? 'Sin determinar'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($run['remote_total'] ?? 'Sin determinar'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Venta</th>
                    <th>Cuenta</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><a href="/sales/<?= rawurlencode((string) $order['external_order_id']) ?>"><?= htmlspecialchars((string) $order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars((string) $order['account_external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $order['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $order['total_amount'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $order['currency_id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($order['date_created'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>