<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $users */
/** @var list<array<string,mixed>> $companies */
/** @var array<int,list<array<string,mixed>>> $memberships */
/** @var string $csrfToken */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$passwordMin = \App\Core\Auth\PasswordService::MIN_LENGTH;
$navigationCompany = null;
$navigationCompanies = [];
$activeCompanyId = $_SESSION['company_id'] ?? null;
if (is_int($activeCompanyId) && $activeCompanyId > 0) {
    foreach ($companies as $candidate) {
        if ((int) $candidate['id'] === $activeCompanyId && $candidate['archived_at'] === null) {
            $navigationCompany = [
                'id' => $activeCompanyId,
                'name' => (string) $candidate['name'],
                'role' => (string) ($_SESSION['role'] ?? ''),
            ];
            $navigationCompanies = [$navigationCompany];
            break;
        }
    }
}
$navigationIsSystemAdmin = true;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios — ERP Meli 2.0</title>
</head>
<body>
<?php require __DIR__ . '/navigation.php'; ?>
<main id="main-content" tabindex="-1" class="administration-users-workspace" data-design-master-page="users">
<header class="page-header users-page-header">
    <span class="eyebrow">Administración</span>
    <h1>Usuarios y permisos</h1>
    <p>Administra identidad, acceso global y membresías. Los roles por empresa son Administrador, Operador y Consulta.</p>
</header>

<div class="settings-grid">
    <section class="panel panel-full users-create-panel" aria-labelledby="create-user-title">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Usuarios</span>
                <h2 id="create-user-title">Crear usuario</h2>
            </div>
            <p>Define identidad, acceso global y una membresía inicial opcional sin crear un modelo de permisos paralelo.</p>
        </div>
        <form class="settings-form" method="post" action="/system/users">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <label>Usuario <input name="username" minlength="3" maxlength="80" pattern="[a-z0-9._-]+" required></label>
            <label>Nombre <input name="display_name" maxlength="191" required></label>
            <label>Correo <input name="email" type="email" maxlength="191" required></label>
            <label>Contraseña inicial <input name="password" type="password" minlength="<?= $passwordMin ?>" maxlength="72" autocomplete="new-password" required></label>
            <label><input type="checkbox" name="is_system_admin" value="1"> Administrador del sistema</label>
            <label>Empresa inicial <select name="company_id"><option value="">Sin empresa</option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>"><?= $e((string)$company['name']) ?><?= $company['archived_at']!==null?' (archivada)':'' ?></option><?php endforeach; ?></select></label>
            <label>Rol inicial <select name="role"><option value="operator">Operador</option><option value="viewer">Consulta</option><option value="admin">Administrador</option></select></label>
            <button>Crear usuario</button>
            <p class="muted">Un usuario normal sin empresa no podrá iniciar sesión hasta recibir una membresía. El acceso de Administrador del sistema es independiente del rol dentro de cada empresa.</p>
        </form>
    </section>

    <section class="panel panel-full" aria-labelledby="users-list-title">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Acceso</span>
                <h2 id="users-list-title">Usuarios registrados</h2>
            </div>
            <p>Gestiona estado, credenciales y membresías desde el owner existente.</p>
        </div>

        <div class="users-list">
        <?php foreach ($users as $user): $id=(int)$user['id']; $active=$user['status']==='active'; $systemAdmin=(int)$user['is_system_admin']===1; $userMemberships=$memberships[$id] ?? []; $membershipCount=count($userMemberships); ?>
            <article class="panel" data-admin-card="user">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow"><?= $active ? 'Activo' : 'Desactivado' ?></span>
                        <h2><?= $e((string)$user['display_name']) ?></h2>
                    </div>
                    <p>Usuario: <strong><?= $e((string)$user['username']) ?></strong> · Administrador del sistema: <strong><?= $systemAdmin?'Sí':'No' ?></strong></p>
                </div>
                <p class="muted">Acceso por empresa: <strong><?= $membershipCount ?></strong> <?= $membershipCount === 1 ? 'empresa' : 'empresas' ?>.</p>

                <details data-user-section="identity">
                    <summary>Identidad</summary>
                    <form class="settings-form" method="post" action="/system/users/<?= $id ?>/update">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <label>Usuario <input name="username" value="<?= $e((string)$user['username']) ?>" minlength="3" maxlength="80" pattern="[a-z0-9._-]+" required></label>
                        <label>Nombre <input name="display_name" value="<?= $e((string)$user['display_name']) ?>" maxlength="191" required></label>
                        <label>Correo <input name="email" type="email" value="<?= $e((string)$user['email']) ?>" maxlength="191" required></label>
                        <button>Guardar identidad</button>
                    </form>
                </details>

                <details data-user-section="security">
                    <summary>Seguridad y acceso global</summary>
                    <form class="settings-form" method="post" action="/system/users/<?= $id ?>/status"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="status" value="<?= $active?'disabled':'active' ?>"><button class="<?= $active?'danger':'' ?>"><?= $active?'Desactivar':'Activar' ?></button></form>

                    <form class="settings-form" method="post" action="/system/users/<?= $id ?>/password"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><label>Nueva contraseña <input name="password" type="password" minlength="<?= $passwordMin ?>" maxlength="72" autocomplete="new-password" required></label><button>Restablecer contraseña</button></form>

                    <?php if ($systemAdmin): ?>
                        <details data-user-destructive-action="remove-system-admin">
                            <summary>Quitar administración del sistema</summary>
                            <p class="muted">Se retirarán los permisos globales de administrador del sistema de este usuario. Sus accesos por empresa no se eliminan. Si retira sus propios permisos, perderá acceso a la administración global.</p>
                            <form class="settings-form" method="post" action="/system/users/<?= $id ?>/system-admin">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="enabled" value="0">
                                <label><input type="checkbox" name="confirm_remove_system_admin" value="1" required> Confirmo que deseo retirar la administración del sistema.</label>
                                <button class="danger" type="submit">Confirmar retiro de administración</button>
                            </form>
                        </details>
                    <?php else: ?>
                        <form class="settings-form" method="post" action="/system/users/<?= $id ?>/system-admin">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="enabled" value="1">
                            <button type="submit">Hacer Administrador del sistema</button>
                        </form>
                    <?php endif; ?>
                </details>

                <details data-user-section="memberships">
                    <summary>Acceso por empresa</summary>
                    <?php if ($userMemberships === []): ?><p class="empty-state">Sin membresías.</p><?php endif; ?>

                    <?php foreach ($userMemberships as $membership): $companyId=(int)$membership['company_id']; ?>
                        <form class="settings-form" method="post" action="/system/users/<?= $id ?>/membership">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>">
                            <strong><?= $e((string)$membership['name']) ?><?= $membership['archived_at']!==null?' (archivada)':'' ?></strong>
                            <select name="role"><option value="admin" <?= $membership['role']==='admin'?'selected':'' ?>>Administrador</option><option value="operator" <?= in_array($membership['role'],['operator','member'],true)?'selected':'' ?>>Operador</option><option value="viewer" <?= $membership['role']==='viewer'?'selected':'' ?>>Consulta</option></select><button>Actualizar rol</button>
                        </form>
                        <form class="settings-form" method="post" action="/system/users/<?= $id ?>/remove-membership"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>"><button class="danger">Quitar acceso a <?= $e((string)$membership['name']) ?></button></form>
                    <?php endforeach; ?>

                    <form class="settings-form" method="post" action="/system/users/<?= $id ?>/membership"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><label>Añadir empresa <select name="company_id" required><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>"><?= $e((string)$company['name']) ?><?= $company['archived_at']!==null?' (archivada)':'' ?></option><?php endforeach; ?></select></label><label>Rol <select name="role"><option value="operator">Operador</option><option value="viewer">Consulta</option><option value="admin">Administrador</option></select></label><button>Añadir o actualizar acceso</button></form>
                </details>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
</div>
</main>
</body>
</html>
