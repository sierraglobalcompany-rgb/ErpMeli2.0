<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string} $company */
/** @var list<array{id:int|string,external_user_id:string,site_id:string,nickname:?string,status:string,updated_at:string,can_hard_delete:bool}> $accounts */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */
/** @var bool $appConfigured */
/** @var bool $systemAdmin */

$statusLabels = [
    'connected' => 'Conectada',
    'attention' => 'Requiere revisión',
    'reauth_required' => 'Debes volver a autorizar',
    'disabled' => 'Desconectada',
];
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cuentas Mercado Libre</title></head>
<body>
<?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
<main>
    <h1>Cuentas Mercado Libre</h1>
    <p>Una empresa puede tener varias cuentas Mercado Libre.</p>
    <p>Cada cuenta Mercado Libre sólo puede estar vinculada a una empresa a la vez. El nickname de Mercado Libre no es la razón social de la empresa.</p>

    <?php if ($appConfigured): ?>
        <p><a href="/oauth/mercadolibre/connect">Conectar otra cuenta Mercado Libre</a></p>
    <?php else: ?>
        <p role="status">La aplicación Mercado Libre aún no está configurada. Contacta al Administrador del sistema.</p>
        <?php if ($systemAdmin): ?><p><a href="/settings/mercadolibre">Configurar aplicación Mercado Libre</a></p><?php endif; ?>
    <?php endif; ?>

    <?php if ($accounts === []): ?>
        <p>No hay cuentas Mercado Libre vinculadas a esta empresa.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Cuenta</th><th>Seller ID</th><th>Sitio</th><th>Estado</th><th>Actualizada</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <?php $accountId = (int) $account['id']; ?>
                <tr>
                    <td><?= htmlspecialchars($account['nickname'] ?? 'Sin nickname', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($account['external_user_id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($account['site_id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($statusLabels[$account['status']] ?? 'Estado desconocido', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($account['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($account['status'] === 'connected'): ?>
                            <form method="post" action="/mercadolibre/accounts/<?= $accountId ?>/disconnect">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Desconectar</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($appConfigured && in_array($account['status'], ['attention', 'reauth_required', 'disabled'], true)): ?>
                            <form method="post" action="/mercadolibre/accounts/<?= $accountId ?>/reauthorize">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Reautorizar</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($account['status'] === 'disabled' && $account['can_hard_delete']): ?>
                            <form method="post" action="/mercadolibre/accounts/<?= $accountId ?>/delete">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>
