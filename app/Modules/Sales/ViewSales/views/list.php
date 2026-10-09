<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $orders */
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
