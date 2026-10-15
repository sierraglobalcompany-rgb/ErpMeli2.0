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
        <header class="page-header">
            <p class="back-link"><a href="/sales">← Ventas</a></p>
            <span class="eyebrow">Detalle de venta</span>
            <h1>Venta <?= htmlspecialchars((string) $order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Detalle local de la venta, sus productos, conciliación financiera y estado de facturación/postventa.</p>
        </header>

        <div class="sales-detail-grid">
            <section class="panel sales-summary" aria-labelledby="sale-summary-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Operación</span>
                        <h2 id="sale-summary-title">Resumen de venta</h2>
                    </div>
                </div>
                <dl class="settings-list">
                    <dt>Cuenta</dt><dd><?= htmlspecialchars((string) $order['account_external_user_id'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt>Estado</dt><dd><span class="status-chip status-neutral"><?= htmlspecialchars((string) $order['status'], ENT_QUOTES, 'UTF-8') ?></span></dd>
                    <dt>Total</dt><dd class="numeric-value"><?= htmlspecialchars((string) $order['total_amount'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $order['currency_id'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt>Creada</dt><dd><?= htmlspecialchars((string) ($order['date_created'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt>Actualizada</dt><dd><?= htmlspecialchars((string) ($order['last_updated'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                </dl>
            </section>

            <section class="panel sales-products" aria-labelledby="sale-products-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Productos</span>
                        <h2 id="sale-products-title">Productos de la venta</h2>
                    </div>
                    <p><?= count($items) ?> ítem<?= count($items) === 1 ? '' : 's' ?> local<?= count($items) === 1 ? '' : 'es' ?>.</p>
                </div>
                <?php if ($items === []): ?>
                    <p class="empty-state">No hay productos locales asociados a esta venta.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="responsive-table" data-responsive-table="sale-products">
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
                                <tr data-mobile-card="product-row">
                                    <td data-label="Artículo"><strong><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td data-label="SKU"><?= htmlspecialchars((string) ($item['seller_sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Cantidad"><?= htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Precio" class="numeric-value"><?= htmlspecialchars((string) $item['unit_price'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $item['currency_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel sales-detail-wide" id="reconciliation" aria-labelledby="reconciliation-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Finanzas</span>
                        <h2 id="reconciliation-title">Reconciliación</h2>
                    </div>
                    <p>Hechos locales disponibles para el ámbito de esta venta. UNKNOWN significa que la evidencia no permite afirmar un valor.</p>
                </div>

                <div class="reconciliation-scope">
                    <span class="fact-pill">Ámbito: <?= htmlspecialchars((string) $reconciliation['scope']['type'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($reconciliation['scope']['pack_id'] !== null): ?>
                        <span class="fact-pill">Pack: <?= htmlspecialchars((string) $reconciliation['scope']['pack_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <p class="compact-label">Pedidos del ámbito</p>
                <ul class="inline-fact-list">
                    <?php foreach ($reconciliation['scope']['order_ids'] as $scopeOrderId): ?>
                    <li><?= htmlspecialchars((string) $scopeOrderId, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>

                <dl class="settings-list reconciliation-facts">
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
                <?php if ($reconciliation['billing']['details'] === []): ?>
                    <p class="empty-state">No hay detalles Billing representables para este ámbito.</p>
                <?php endif; ?>
                <div class="table-wrap">
                    <table class="responsive-table billing-details-table" data-responsive-table="billing-details">
                        <thead><tr><th>Detalle</th><th>Asociado</th><th>Documento</th><th>Tipo</th><th>Subtipo</th><th>Importe</th><th>Moneda</th></tr></thead>
                        <tbody>
                        <?php foreach ($reconciliation['billing']['details'] as $billedDetail): ?>
                            <tr data-mobile-card="billing-row">
                                <td><?= htmlspecialchars((string) $billedDetail['external_detail_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Asociado"><?= htmlspecialchars((string) ($billedDetail['associated_detail_id'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Documento"><?= htmlspecialchars((string) $billedDetail['document_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Tipo"><?= htmlspecialchars((string) $billedDetail['detail_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Subtipo"><?= htmlspecialchars((string) ($billedDetail['detail_sub_type'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $billedDetail['detail_amount'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Moneda"><?= htmlspecialchars((string) ($billedDetail['currency_id'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3>Envío del receptor: evidencia</h3>
                <?php if ($reconciliation['shipping']['receiver_shipping'] === []): ?>
                    <p class="empty-state">No hay evidencia representable de envío del receptor.</p>
                <?php else: ?>
                    <ul class="evidence-list">
                        <?php foreach ($reconciliation['shipping']['receiver_shipping'] as $shippingEvidence): ?>
                        <li><strong>Detalle <?= htmlspecialchars((string) $shippingEvidence['external_detail_id'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars((string) $shippingEvidence['value'], ENT_QUOTES, 'UTF-8') ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section id="invoice-review">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Facturación / postventa</span>
                        <h2 id="invoice-review-title">Factura / Postventa</h2>
                    </div>
                </div>
                <dl class="settings-list">
                    <dt>Readiness</dt><dd><?= htmlspecialchars((string) $invoiceReview['readiness'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt>Factura registrada</dt><dd><?= $invoiceReview['original_invoice_artifact_present'] ? 'Sí' : 'No' ?></dd>
                    <dt>Nota crédito registrada</dt><dd><?= $invoiceReview['credit_note_artifact_present'] ? 'Sí' : 'No' ?></dd>
                    <dt>Postventa</dt><dd><?= htmlspecialchars((string) $invoiceReview['post_sale_status'], ENT_QUOTES, 'UTF-8') ?></dd>
                </dl>
            </section>
        </div>
    </main>
</body>
</html>
