<?php

declare(strict_types=1);

/** @var array<string,mixed> $order */
/** @var list<array<string,mixed>> $items */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Venta <?= htmlspecialchars((string) $order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <main>
        <p><a href="/sales">← Ventas</a></p>
        <h1>Venta <?= htmlspecialchars((string) $order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></h1>
        <dl>
            <dt>Cuenta</dt><dd><?= htmlspecialchars((string) $order['account_external_user_id'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Estado</dt><dd><?= htmlspecialchars((string) $order['status'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Total</dt><dd><?= htmlspecialchars((string) $order['total_amount'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $order['currency_id'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Creada</dt><dd><?= htmlspecialchars((string) ($order['date_created'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Actualizada</dt><dd><?= htmlspecialchars((string) ($order['last_updated'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>

        <h2>Productos</h2>
        <table>
            <thead>
                <tr>
                    <th>Artículo</th>
                    <th>SKU</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($item['seller_sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $item['unit_price'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $item['currency_id'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
