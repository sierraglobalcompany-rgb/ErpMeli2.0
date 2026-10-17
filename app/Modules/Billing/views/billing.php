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
$documentLabels = ['BILL' => 'Factura', 'CREDIT_NOTE' => 'Nota crédito'];
$accountLabels = [];
foreach ($periods as $period) {
    $accountLabels[(int) $period['account_id']] = (string) $period['external_user_id'];
}
foreach ($accounts as $account) {
    $nickname = trim((string) ($account['nickname'] ?? ''));
    $accountLabels[(int) $account['id']] = $nickname !== '' ? $nickname : (string) $account['external_user_id'];
}
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
    <main id="main-content" tabindex="-1">
        <header class="page-header billing-page-header">
            <span class="eyebrow">Finanzas</span>
            <h1>Facturación</h1>
            <p>Controla solicitudes de sincronización y consulta el progreso persistido por cuenta y período.</p>
        </header>

        <?php if ($error !== null): ?><p class="notice" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

        <div class="billing-workspace">
            <div class="settings-grid">
                <?php if ($company['role'] === 'admin'): ?>
                <section class="panel billing-sync-panel" aria-labelledby="billing-sync-title">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Sincronización</span>
                            <h2 id="billing-sync-title">Solicitar período</h2>
                        </div>
                    </div>
                    <form class="settings-form" method="post" action="/billing/syncs">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label>Cuenta <select name="account_id" required>
                            <?php foreach ($accounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>" <?= $selection !== null && $selection['account_id'] === (int) $account['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $account['external_user_id'] . ' — ' . (string) ($account['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Período (AAAA-MM-01) <input name="period_key" type="text" pattern="[0-9]{4}-[0-9]{2}-01" placeholder="2026-09-01" value="<?= htmlspecialchars($selection['period_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
                        <label>Tipo documental <select name="document_type">
                            <option value="BILL">Factura</option>
                            <option value="CREDIT_NOTE" <?= ($selection['document_type'] ?? '') === 'CREDIT_NOTE' ? 'selected' : '' ?>>Nota crédito</option>
                        </select></label>
                        <button type="submit">Solicitar sincronización</button>
                    </form>
                    <p class="notice">La solicitud registra trabajo pendiente. No consulta Mercado Libre desde esta página.</p>
                </section>
                <?php endif; ?>

                <?php if ($selection !== null): ?>
                <section class="panel billing-selection-panel" aria-labelledby="billing-selection-title">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Período seleccionado</span>
                            <h2 id="billing-selection-title">Solicitud seleccionada</h2>
                        </div>
                    </div>
                    <p><?= htmlspecialchars($accountLabels[$selection['account_id']] ?? 'Cuenta seleccionada', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($selection['period_key'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($documentLabels[$selection['document_type']] ?? $selection['document_type'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (($requestState['status'] ?? null) === 'failed'): ?>
                        <p class="notice">Requiere revisión. Una nueva solicitud debe ser explícita.</p>
                    <?php elseif (in_array($requestState['status'] ?? null, ['pending', 'running'], true)): ?>
                        <p class="notice"><?= $progress !== null && $progress['sync_state'] === 'pending' ? 'Sincronizando' : 'Solicitud registrada. En espera de progreso confirmado.' ?></p>
                    <?php elseif ($progress === null): ?>
                        <p class="empty-state">Sin solicitud ni progreso confirmados.</p>
                    <?php endif; ?>
                    <?php if ($progress !== null): ?>
                    <dl class="settings-list">
                        <dt>Progreso persistido</dt><dd><?= $progress['sync_state'] === 'caught_up' ? 'Al día con la fuente consultada' : 'Progreso registrado' ?></dd>
                        <dt>Última lectura persistida (UTC)</dt><dd><?= htmlspecialchars((string) ($progress['last_synced_at'] ?? 'Sin lectura confirmada'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </dl>
                    <details class="billing-technical-details">
                        <summary>Detalles técnicos</summary>
                        <dl class="settings-list">
                            <dt>Cursor confirmado</dt><dd><?= htmlspecialchars((string) $progress['cursor_last_id'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </dl>
                    </details>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section class="panel panel-full billing-periods-panel" aria-labelledby="billing-periods-title">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Historial local</span>
                            <h2 id="billing-periods-title">Períodos registrados</h2>
                        </div>
                        <p>Hasta 100 períodos. Actualiza la página para consultar el progreso persistido.</p>
                    </div>
                    <?php if ($periods === []): ?>
                        <p class="empty-state">No hay períodos registrados para esta empresa.</p>
                    <?php else: ?>
                    <div class="table-wrap">
                        <table data-responsive-table="billing-periods">
                            <thead><tr><th>Cuenta</th><th>Período</th><th>Tipo</th><th>Progreso persistido</th><th>Última lectura (UTC)</th></tr></thead>
                            <tbody>
                            <?php foreach ($periods as $period): ?>
                                <tr data-mobile-card="billing-period-row">
                                    <td data-label="Cuenta"><?= htmlspecialchars($accountLabels[(int) $period['account_id']] ?? (string) $period['external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Período"><a class="primary-record-link" href="/billing?<?= htmlspecialchars(http_build_query(['account_id' => (int) $period['account_id'], 'period_key' => $period['period_key'], 'document_type' => $period['document_type']], '', '&', PHP_QUERY_RFC3986), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $period['period_key'], ENT_QUOTES, 'UTF-8') ?></a></td>
                                    <td data-label="Tipo"><?= htmlspecialchars($documentLabels[(string) $period['document_type']] ?? (string) $period['document_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Progreso persistido"><?= $period['sync_state'] === 'caught_up' ? 'Al día con la fuente consultada' : 'Progreso registrado' ?></td>
                                    <td data-label="Última lectura (UTC)"><?= htmlspecialchars((string) ($period['last_synced_at'] ?? 'Sin lectura confirmada'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
