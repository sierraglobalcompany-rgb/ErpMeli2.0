<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string} $company */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */
/** @var string $fromValue */
/** @var string $toValue */
/** @var array{orders:int,products:int,sales_by_currency:list<array{currency:string,total:string,orders:int}>,account_statuses:array{connected:int,attention:int,reauth_required:int,disabled:int},recent_orders:list<array{external_order_id:string,status:string,date_created:string,total_amount:string,currency_id:string}>} $snapshot */

require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php';
?>
<main>
    <header class="page-header">
        <h1>Inicio / Resumen</h1>
        <p>Vista factual de la operación local de <?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?>.</p>
    </header>

    <form method="get" action="/dashboard" aria-label="Rango del resumen">
        <label>Desde
            <input type="date" name="from" value="<?= htmlspecialchars($fromValue, ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label>Hasta
            <input type="date" name="to" value="<?= htmlspecialchars($toValue, ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <button type="submit">Aplicar</button>
    </form>

    <div class="settings-grid" aria-label="Indicadores principales">
        <section class="panel">
            <span class="eyebrow">Operación</span>
            <h2>Órdenes del período</h2>
            <strong data-dashboard-kpi="orders"><?= $snapshot['orders'] ?></strong>
            <p>Órdenes locales de la empresa activa dentro del rango seleccionado.</p>
        </section>

        <section class="panel">
            <span class="eyebrow">Productos</span>
            <h2>Productos locales</h2>
            <strong data-dashboard-kpi="products"><?= $snapshot['products'] ?></strong>
            <p>Productos registrados para esta empresa.</p>
        </section>

        <section class="panel">
            <span class="eyebrow">Finanzas</span>
            <h2>Ventas por moneda</h2>
            <?php if ($snapshot['sales_by_currency'] === []): ?>
                <p>Sin ventas representables en este período.</p>
            <?php else: ?>
                <dl class="settings-list">
                    <?php foreach ($snapshot['sales_by_currency'] as $currency): ?>
                        <dt data-dashboard-sales-currency="<?= htmlspecialchars($currency['currency'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($currency['currency'], ENT_QUOTES, 'UTF-8') ?></dt>
                        <dd data-dashboard-sales-amount="<?= htmlspecialchars($currency['total'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($currency['total'], ENT_QUOTES, 'UTF-8') ?>
                            <small class="muted"> · <?= $currency['orders'] ?> órdenes</small>
                        </dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>

        <section class="panel">
            <span class="eyebrow">Cobertura financiera</span>
            <h2>Rentabilidad</h2>
            <strong data-dashboard-profitability="UNKNOWN">UNKNOWN</strong>
            <p>El ERP todavía no tiene un owner agregado autorizado para calcular rentabilidad global sin perder semántica factual.</p>
        </section>

        <section class="panel">
            <span class="eyebrow">Mercado Libre</span>
            <h2>Estado de cuentas</h2>
            <dl class="settings-list">
                <dt>Conectadas</dt><dd><strong data-dashboard-account-status="connected"><?= $snapshot['account_statuses']['connected'] ?></strong></dd>
                <dt>Requieren atención</dt><dd><strong data-dashboard-account-status="attention"><?= $snapshot['account_statuses']['attention'] ?></strong></dd>
                <dt>Reautorización</dt><dd><strong data-dashboard-account-status="reauth_required"><?= $snapshot['account_statuses']['reauth_required'] ?></strong></dd>
                <dt>Desconectadas</dt><dd><strong data-dashboard-account-status="disabled"><?= $snapshot['account_statuses']['disabled'] ?></strong></dd>
            </dl>
        </section>

        <section class="panel">
            <span class="eyebrow">Prioridad</span>
            <h2>Requiere atención</h2>
            <?php $needsAttention = $snapshot['account_statuses']['attention'] + $snapshot['account_statuses']['reauth_required']; ?>
            <?php if ($needsAttention > 0): ?>
                <p><span class="status-chip status-attention"><?= $needsAttention ?></span> cuenta(s) de Mercado Libre requieren revisión.</p>
            <?php else: ?>
                <p><span class="status-chip status-connected">Sin alertas de cuenta</span></p>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel">
        <span class="eyebrow">Actividad</span>
        <h2>Ventas recientes del período</h2>
        <?php if ($snapshot['recent_orders'] === []): ?>
            <p>No hay órdenes recientes para mostrar.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Venta</th><th>Estado</th><th>Fecha</th><th>Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($snapshot['recent_orders'] as $order): ?>
                        <tr>
                            <td><a href="/sales/<?= rawurlencode($order['external_order_id']) ?>"><?= htmlspecialchars($order['external_order_id'], ENT_QUOTES, 'UTF-8') ?></a></td>
                            <td><?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($order['date_created'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($order['currency_id'] . ' ' . $order['total_amount'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
