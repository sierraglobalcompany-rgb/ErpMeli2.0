<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $orders */
/** @var list<array<string,mixed>> $auditRuns */
/** @var string $orderId */
/** @var bool $canStartAudit */
/** @var list<array<string,mixed>> $auditAccounts */
/** @var string $csrfToken */
/** @var string $lastClosedPeriodKey */

$saleStatusLabels = [
    'confirmed' => 'Confirmada',
    'payment_required' => 'Pago pendiente',
    'payment_in_process' => 'Pago en proceso',
    'partially_paid' => 'Pago parcial',
    'paid' => 'Pagada',
    'cancelled' => 'Cancelada',
    'invalid' => 'No válida',
];
$auditStatusLabels = [
    'capturing' => 'Recopilando datos',
    'repairing' => 'Completando datos',
    'confirming' => 'Verificando resultados',
    'valid' => 'Sin diferencias',
    'attention' => 'Requiere revisión',
    'unavailable' => 'No disponible',
];
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
    <main id="main-content" tabindex="-1">
        <header class="page-header sales-page-header">
            <span class="eyebrow">Operación</span>
            <h1>Ventas</h1>
            <p>Consulta y revisa las ventas de esta empresa.</p>
        </header>

        <form class="toolbar sales-search-toolbar" method="get" action="/sales">
            <label>ID exacto de venta <input name="order_id" inputmode="numeric" pattern="[0-9]{1,32}" value="<?= htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 200000000101"></label>
            <button type="submit">Buscar venta</button>
            <a class="button-secondary" href="/sales">Ver ventas recientes</a>
        </form>

        <?php if ($orders === []): ?>
            <p class="empty-state"><?= $orderId === '' ? 'No hay ventas locales.' : 'No se encontró la venta en esta empresa.' ?></p>
        <?php endif; ?>

        <section class="panel sales-orders-panel" aria-labelledby="sales-orders-title">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Actividad</span>
                    <h2 id="sales-orders-title"><?= $orderId === '' ? 'Ventas recientes' : 'Resultado de búsqueda' ?></h2>
                </div>
                <p><?= $orders === [] ? 'Sin resultados para mostrar.' : 'Hasta 100 ventas locales, ordenadas de la más reciente a la más antigua.' ?></p>
            </div>

            <?php if ($orders !== []): ?>
                <div class="table-wrap">
                    <table class="responsive-table" data-responsive-table="sales-orders">
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
                            <?php
                            $rawSaleStatus = strtolower(trim((string) ($order['status'] ?? '')));
                            $saleStatusLabel = $saleStatusLabels[$rawSaleStatus] ?? 'Estado no reconocido';
                            ?>
                            <tr data-mobile-card="sale-row">
                                <td data-label="Venta"><a class="primary-record-link" href="/sales/<?= rawurlencode((string) $order['external_order_id']) ?>"><?= htmlspecialchars((string) $order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></a></td>
                                <td data-label="Cuenta"><?= htmlspecialchars((string) $order['account_external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Estado"><span class="status-chip status-neutral" data-sale-status="<?= htmlspecialchars($rawSaleStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($saleStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td data-label="Total" class="numeric-value"><?= htmlspecialchars((string) $order['total_amount'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $order['currency_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Fecha"><?= htmlspecialchars((string) ($order['date_created'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <details class="panel sales-audit-disclosure">
            <summary class="sales-audit-summary">Auditoría histórica</summary>
            <p>Compara meses cerrados con la fuente autorizada sin alterar las ventas de esta empresa.</p>

            <?php if ($canStartAudit): ?>
                <section class="sales-audit-start" aria-labelledby="sales-audit-start-title">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Control</span>
                            <h2 id="sales-audit-start-title">Iniciar auditoría</h2>
                        </div>
                        <p>Selecciona una cuenta y un mes ya cerrado.</p>
                    </div>
                    <?php if ($auditAccounts === []): ?>
                        <p class="empty-state">No hay cuentas de Mercado Libre conectadas para auditar.</p>
                    <?php else: ?>
                        <form class="sales-audit-form" method="post" action="/sales/audits">
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

            <section class="sales-audit-results" aria-labelledby="audit-results-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Evidencia</span>
                        <h2 id="audit-results-title">Resultados de auditoría</h2>
                    </div>
                    <p>Se muestran las últimas 25 auditorías registradas para esta empresa.</p>
                </div>
                <?php if ($auditRuns === []): ?>
                    <p class="empty-state">No hay auditorías para esta empresa.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="responsive-table" data-responsive-table="sales-audits">
                            <thead><tr><th>Cuenta</th><th>Período</th><th>Estado</th><th>Inicio (UTC)</th><th>Fin (UTC)</th><th>Ventas locales</th><th>Ventas remotas</th></tr></thead>
                            <tbody>
                            <?php foreach ($auditRuns as $run): ?>
                                <?php
                                $rawAuditStatus = strtolower(trim((string) ($run['status'] ?? '')));
                                $auditStatusLabel = $auditStatusLabels[$rawAuditStatus] ?? 'Estado no reconocido';
                                ?>
                                <tr data-mobile-card="audit-row">
                                    <td data-label="Cuenta"><?= htmlspecialchars((string) $run['external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Período"><?= htmlspecialchars((string) $run['period_key'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Estado"><span class="status-chip status-neutral" data-audit-status="<?= htmlspecialchars($rawAuditStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($auditStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Inicio (UTC)"><?= htmlspecialchars((string) $run['started_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Fin (UTC)"><?= htmlspecialchars((string) ($run['completed_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Ventas locales"><?= htmlspecialchars((string) ($run['canonical_count'] ?? 'Sin determinar'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Ventas remotas"><?= htmlspecialchars((string) ($run['remote_total'] ?? 'Sin determinar'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </details>
    </main>
</body>
</html>
