<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $orders */
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
    <main>
        <h1>Ventas</h1>

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