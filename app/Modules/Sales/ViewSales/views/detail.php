<?php

declare(strict_types=1);

/** @var array<string,mixed> $order */
/** @var list<array<string,mixed>> $items */
/** @var array<string,mixed> $reconciliation */
/** @var array<string,mixed> $invoiceReview */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Venta <?= htmlspecialchars((string) $order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <?php require dirname(__DIR__, 4) . '/Core/Http/views/navigation.php'; ?>
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
        <section id="reconciliation">
            <h2>Reconciliación</h2>
            <p>Ámbito: <?= htmlspecialchars((string) $reconciliation['scope']['type'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($reconciliation['scope']['pack_id'] !== null): ?>
            <p>Pack: <?= htmlspecialchars((string) $reconciliation['scope']['pack_id'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p>Pedidos del ámbito:</p>
            <ul>
                <?php foreach ($reconciliation['scope']['order_ids'] as $scopeOrderId): ?>
                <li><?= htmlspecialchars((string) $scopeOrderId, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <dl>
                <dt>Comisión operativa</dt><dd><?= htmlspecialchars((string) ($reconciliation['operational']['sale_fee_total'] ?? $reconciliation['coverage']['sale_fee']), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Cobertura de comisión</dt><dd><?= htmlspecialchars((string) $reconciliation['coverage']['sale_fee'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Ítems del ámbito</dt><dd><?= htmlspecialchars((string) $reconciliation['operational']['item_count'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Moneda del ámbito</dt><dd><?= htmlspecialchars((string) ($reconciliation['currency'] ?? $reconciliation['coverage']['currency']), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Cobertura Billing</dt><dd><?= htmlspecialchars((string) $reconciliation['coverage']['billing_identity'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Cobertura envío del receptor</dt><dd><?= htmlspecialchars((string) $reconciliation['coverage']['receiver_shipping'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Envío del vendedor</dt><dd><?= htmlspecialchars((string) ($reconciliation['shipping']['seller_shipping_cost'] ?? $reconciliation['coverage']['seller_shipping']), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Referencia oficial</dt><dd><?= htmlspecialchars((string) $reconciliation['coverage']['official_reference'], ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
            <h3>Hechos facturados Billing</h3>
            <table>
                <thead><tr><th>Detalle</th><th>Asociado</th><th>Documento</th><th>Tipo</th><th>Subtipo</th><th>Importe</th><th>Moneda</th></tr></thead>
                <tbody>
                <?php foreach ($reconciliation['billing']['details'] as $billedDetail): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $billedDetail['external_detail_id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($billedDetail['associated_detail_id'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $billedDetail['document_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $billedDetail['detail_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($billedDetail['detail_sub_type'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $billedDetail['detail_amount'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($billedDetail['currency_id'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h3>Envío del receptor: evidencia</h3>
            <ul>
                <?php foreach ($reconciliation['shipping']['receiver_shipping'] as $shippingEvidence): ?>
                <li>Detalle <?= htmlspecialchars((string) $shippingEvidence['external_detail_id'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars((string) $shippingEvidence['value'], ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section id="invoice-review">
            <h2>Factura / Postventa</h2>
            <dl>
                <dt>Readiness</dt><dd><?= htmlspecialchars((string) $invoiceReview['readiness'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Factura registrada</dt><dd><?= $invoiceReview['original_invoice_artifact_present'] ? 'Sí' : 'No' ?></dd>
                <dt>Nota crédito registrada</dt><dd><?= $invoiceReview['credit_note_artifact_present'] ? 'Sí' : 'No' ?></dd>
                <dt>Postventa</dt><dd><?= htmlspecialchars((string) $invoiceReview['post_sale_status'], ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        </section>
    </main>
</body>
</html>
