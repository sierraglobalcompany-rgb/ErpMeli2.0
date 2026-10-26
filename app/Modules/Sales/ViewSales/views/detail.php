<?php

declare(strict_types=1);

/** @var array<string,mixed> $order */
/** @var list<array<string,mixed>> $items */
/** @var array<string,mixed> $reconciliation */
/** @var array<string,mixed> $invoiceReview */

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$orderStatusRaw = strtolower((string) $order['status']);
$orderStatusLabel = match ($orderStatusRaw) {
    'paid' => 'Pagada',
    'cancelled' => 'Cancelada',
    default => ucfirst(str_replace('_', ' ', $orderStatusRaw)),
};

$humanFact = static function (mixed $value): string {
    $raw = (string) $value;
    return match ($raw) {
        'UNKNOWN' => 'Sin información suficiente',
        'CONFLICT' => 'Requiere revisión',
        default => $raw,
    };
};

$commissionRaw = (string) ($reconciliation['operational']['sale_fee_total'] ?? $reconciliation['coverage']['sale_fee']);
$commissionCoverageRaw = (string) $reconciliation['coverage']['sale_fee'];
$scopeCurrencyRaw = (string) ($reconciliation['currency'] ?? $reconciliation['coverage']['currency']);
$billingCoverageRaw = (string) $reconciliation['coverage']['billing_identity'];
$sellerShippingRaw = (string) ($reconciliation['shipping']['seller_shipping_cost'] ?? $reconciliation['coverage']['seller_shipping']);
$officialReferenceRaw = (string) $reconciliation['coverage']['official_reference'];

$hasFinancialConflict = in_array('CONFLICT', [
    $commissionRaw,
    $commissionCoverageRaw,
    $scopeCurrencyRaw,
    $billingCoverageRaw,
], true);

$readinessRaw = (string) $invoiceReview['readiness'];
$readinessLabel = match ($readinessRaw) {
    'ORDER_READY' => 'Lista para facturación',
    'PACK_MEMBERSHIP_UNVERIFIED' => 'Membresía del paquete sin verificar',
    'CANCELLED' => 'Venta cancelada',
    default => 'Estado disponible',
};

$postSaleRaw = (string) $invoiceReview['post_sale_status'];
$postSaleLabel = match ($postSaleRaw) {
    'NONE' => 'Sin postventa registrada',
    'CANCELLED_BEFORE_INVOICE' => 'Cancelada antes de facturar',
    'CREDIT_NOTE_REVIEW_REQUIRED' => 'Requiere revisar nota crédito',
    'CREDIT_NOTE_RECORDED' => 'Nota crédito registrada',
    default => 'Estado de postventa disponible',
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Venta <?= $escape($order['external_order_id']) ?></title>
</head>
<body>
    <?php require dirname(__DIR__, 4) . '/Core/Http/views/navigation.php'; ?>
    <main id="main-content" tabindex="-1">
        <header class="page-header sale-detail-header">
            <p class="back-link"><a href="/sales">← Ventas</a></p>
            <span class="eyebrow">Detalle de venta</span>
            <h1>Venta <?= $escape($order['external_order_id']) ?></h1>
            <div class="sale-hero-meta" aria-label="Resumen rápido de la venta">
                <span class="status-chip <?= $orderStatusRaw === 'paid' ? 'status-connected' : 'status-neutral' ?>"><?= $escape($orderStatusLabel) ?></span>
                <strong class="numeric-value"><?= $escape($order['total_amount']) ?> <?= $escape($order['currency_id']) ?></strong>
                <span class="muted">Cuenta <?= trim((string) ($order['account_nickname'] ?? '')) !== '' ? $escape($order['account_nickname']) . ' · ' : '' ?>ID vendedor: <?= $escape($order['account_external_user_id']) ?></span>
            </div>
        </header>

        <?php if ($hasFinancialConflict): ?>
        <section class="sale-attention" aria-labelledby="sale-attention-title">
            <span class="status-chip status-attention">Requiere revisión</span>
            <h2 id="sale-attention-title">Hay datos financieros incompatibles</h2>
            <p>Hay monedas diferentes dentro del mismo ámbito de la venta. El ERP no consolida esos importes ni los sustituye por cero.</p>
        </section>
        <?php endif; ?>

        <div class="sales-detail-grid">
            <section class="panel sales-summary" aria-labelledby="sale-summary-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Operación</span>
                        <h2 id="sale-summary-title">Resumen de la venta</h2>
                    </div>
                </div>
                <dl class="settings-list">
                    <dt>Estado</dt><dd><span class="status-chip <?= $orderStatusRaw === 'paid' ? 'status-connected' : 'status-neutral' ?>"><?= $escape($orderStatusLabel) ?></span></dd>
                    <dt>Total</dt><dd class="numeric-value"><?= $escape($order['total_amount']) ?> <?= $escape($order['currency_id']) ?></dd>
                    <dt>Cuenta</dt><dd><?= trim((string) ($order['account_nickname'] ?? '')) !== '' ? $escape($order['account_nickname']) . ' · ' : '' ?>ID vendedor: <?= $escape($order['account_external_user_id']) ?></dd>
                    <?php if (trim((string) ($order['date_created'] ?? '')) !== ''): ?>
                    <dt>Creada</dt><dd><?= $escape($order['date_created']) ?></dd>
                    <?php endif; ?>
                    <?php if (trim((string) ($order['last_updated'] ?? '')) !== ''): ?>
                    <dt>Actualizada</dt><dd><?= $escape($order['last_updated']) ?></dd>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="panel sales-products" aria-labelledby="sale-products-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Productos</span>
                        <h2 id="sale-products-title">Productos de la venta</h2>
                    </div>
                    <p><?= count($items) ?> ítem<?= count($items) === 1 ? '' : 's' ?>.</p>
                </div>
                <?php if ($items === []): ?>
                    <p class="empty-state">No hay productos locales asociados a esta venta.</p>
                <?php else: ?>
                    <div class="sale-product-list">
                        <?php foreach ($items as $item): ?>
                        <article class="panel sale-product-item" data-sale-product="product-row">
                            <div class="section-heading">
                                <div>
                                    <strong><?= $escape($item['title']) ?></strong>
                                    <?php if (trim((string) ($item['seller_sku'] ?? '')) !== ''): ?>
                                    <p class="muted">SKU <?= $escape($item['seller_sku']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <p class="numeric-value"><strong><?= $escape($item['unit_price']) ?> <?= $escape($item['currency_id']) ?></strong></p>
                            </div>
                            <p><strong>Cantidad:</strong> <?= $escape($item['quantity']) ?></p>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel sales-detail-wide financial-human-summary" aria-labelledby="financial-summary-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Finanzas</span>
                        <h2 id="financial-summary-title">Resumen financiero</h2>
                    </div>
                    <p>Primero mostramos lo necesario para entender la venta. Los datos técnicos siguen disponibles abajo.</p>
                </div>

                <div class="settings-grid financial-summary-grid">
                    <div class="panel fact-summary-card">
                        <span class="eyebrow">Comisión</span>
                        <h3><?= $escape($humanFact($commissionRaw)) ?></h3>
                        <?php if ($commissionRaw === 'CONFLICT'): ?><p>Hay monedas incompatibles; no se calculó un total.</p><?php endif; ?>
                    </div>
                    <div class="panel fact-summary-card">
                        <span class="eyebrow">Moneda</span>
                        <h3><?= $escape($humanFact($scopeCurrencyRaw)) ?></h3>
                    </div>
                    <div class="panel fact-summary-card">
                        <span class="eyebrow">Facturación asociada</span>
                        <h3><?= $escape($humanFact($billingCoverageRaw)) ?></h3>
                    </div>
                    <div class="panel fact-summary-card">
                        <span class="eyebrow">Envío del vendedor</span>
                        <h3><?= $escape($humanFact($sellerShippingRaw)) ?></h3>
                    </div>
                </div>

                <div class="notice">
                    <details class="technical-disclosure">
                        <summary>Ver conciliación técnica</summary>
                        <div class="technical-disclosure-body" id="reconciliation">
                            <h3>Reconciliación técnica</h3>
                            <p class="muted">Los códigos técnicos se conservan aquí para auditoría y soporte. UNKNOWN significa que la evidencia no permite afirmar un valor.</p>

                            <div class="reconciliation-scope">
                                <span class="fact-pill">Ámbito: <?= $escape($reconciliation['scope']['type']) ?></span>
                                <?php if ($reconciliation['scope']['pack_id'] !== null): ?>
                                    <span class="fact-pill">Pack: <?= $escape($reconciliation['scope']['pack_id']) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="compact-label">Pedidos del ámbito</p>
                            <ul class="inline-fact-list">
                                <?php foreach ($reconciliation['scope']['order_ids'] as $scopeOrderId): ?>
                                <li><?= $escape($scopeOrderId) ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <dl class="settings-list reconciliation-facts">
                                <dt>Comisión operativa</dt><dd><?= $escape($commissionRaw) ?></dd>
                                <dt>Cobertura de comisión</dt><dd><?= $escape($commissionCoverageRaw) ?></dd>
                                <dt>Ítems del ámbito</dt><dd><?= $escape($reconciliation['operational']['item_count']) ?></dd>
                                <dt>Moneda del ámbito</dt><dd><?= $escape($scopeCurrencyRaw) ?></dd>
                                <dt>Cobertura Billing</dt><dd><?= $escape($billingCoverageRaw) ?></dd>
                                <dt>Cobertura envío del receptor</dt><dd><?= $escape($reconciliation['coverage']['receiver_shipping']) ?></dd>
                                <dt>Envío del vendedor</dt><dd><?= $escape($sellerShippingRaw) ?></dd>
                                <dt>Referencia oficial</dt><dd><?= $escape($officialReferenceRaw) ?></dd>
                            </dl>

                            <h3>Hechos facturados Billing</h3>
                            <?php if ($reconciliation['billing']['details'] === []): ?>
                                <p class="empty-state"><strong>Sin detalles de facturación</strong><br>Todavía no hay información de Billing asociada a este ámbito.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                    <table class="responsive-table billing-details-table" data-responsive-table="billing-details">
                                        <thead><tr><th>Detalle</th><th>Asociado</th><th>Documento</th><th>Tipo</th><th>Subtipo</th><th>Importe</th><th>Moneda</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($reconciliation['billing']['details'] as $billedDetail): ?>
                                            <tr data-mobile-card="billing-row">
                                                <td><?= $escape($billedDetail['external_detail_id']) ?></td>
                                                <td data-label="Asociado"><?= $escape($billedDetail['associated_detail_id'] ?? 'UNKNOWN') ?></td>
                                                <td data-label="Documento"><?= $escape($billedDetail['document_type']) ?></td>
                                                <td data-label="Tipo"><?= $escape($billedDetail['detail_type']) ?></td>
                                                <td data-label="Subtipo"><?= $escape($billedDetail['detail_sub_type'] ?? 'UNKNOWN') ?></td>
                                                <td><?= $escape($billedDetail['detail_amount']) ?></td>
                                                <td data-label="Moneda"><?= $escape($billedDetail['currency_id'] ?? 'UNKNOWN') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <h3>Envío del receptor: evidencia</h3>
                            <?php if ($reconciliation['shipping']['receiver_shipping'] === []): ?>
                                <p class="empty-state">Sin evidencia disponible de envío del receptor.</p>
                            <?php else: ?>
                                <ul class="evidence-list">
                                    <?php foreach ($reconciliation['shipping']['receiver_shipping'] as $shippingEvidence): ?>
                                    <li><strong>Detalle <?= $escape($shippingEvidence['external_detail_id']) ?>: <?= $escape($shippingEvidence['value']) ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </section>

            <section id="invoice-review">
                <div class="panel invoice-review-panel">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Facturación / postventa</span>
                            <h2 id="invoice-review-title">Factura / Postventa</h2>
                        </div>
                    </div>

                    <div class="settings-grid invoice-summary-grid">
                        <div class="panel fact-summary-card">
                            <span class="eyebrow">Preparación</span>
                            <h3><?= $escape($readinessLabel) ?></h3>
                        </div>
                        <div class="panel fact-summary-card">
                            <span class="eyebrow">Factura</span>
                            <h3><?= $invoiceReview['original_invoice_artifact_present'] ? 'Registrada' : 'Sin registrar' ?></h3>
                        </div>
                        <div class="panel fact-summary-card">
                            <span class="eyebrow">Nota crédito</span>
                            <h3><?= $invoiceReview['credit_note_artifact_present'] ? 'Registrada' : 'Sin registrar' ?></h3>
                        </div>
                        <div class="panel fact-summary-card">
                            <span class="eyebrow">Postventa</span>
                            <h3><?= $escape($postSaleLabel) ?></h3>
                        </div>
                    </div>

                    <div class="notice">
                        <details class="technical-disclosure invoice-technical-disclosure">
                            <summary>Ver estados técnicos</summary>
                            <div class="technical-disclosure-body">
                                <dl class="settings-list">
                                    <dt>Readiness</dt><dd><?= $escape($readinessRaw) ?></dd>
                                    <dt>Factura registrada</dt><dd><?= $invoiceReview['original_invoice_artifact_present'] ? 'Sí' : 'No' ?></dd>
                                    <dt>Nota crédito registrada</dt><dd><?= $invoiceReview['credit_note_artifact_present'] ? 'Sí' : 'No' ?></dd>
                                    <dt>Postventa</dt><dd><?= $escape($postSaleRaw) ?></dd>
                                </dl>
                            </div>
                        </details>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
