<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $users */
/** @var list<array<string,mixed>> $companies */
/** @var array<int,list<array<string,mixed>>> $memberships */
/** @var string $csrfToken */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$passwordMin = \App\Core\Auth\PasswordService::MIN_LENGTH;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios — ERP Meli 2.0</title>
<link rel="stylesheet" href="/assets/app.css">
</head><body><main class="simple-page">
<nav class="simple-nav"><a href="/">Inicio</a><a href="/system/companies">Empresas</a><a href="/system/users">Usuarios</a><a href="/settings/system">Configuración</a><a href="/settings/mercadolibre">Mercado Libre</a></nav>
<h1>Usuarios</h1>
<p>Administra identidad, acceso global y membresías. Los roles por empresa son Administrador, Operador y Consulta.</p>
<section>
<h2>Crear usuario</h2>
<form method="post" action="/system/users">
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
<?php foreach ($users as $user): $id=(int)$user['id']; $active=$user['status']==='active'; $systemAdmin=(int)$user['is_system_admin']===1; ?>
<section>
<h2><?= $e((string)$user['display_name']) ?></h2>
<p>Usuario: <strong><?= $e((string)$user['username']) ?></strong> · Estado: <strong><?= $active?'Activo':'Desactivado' ?></strong> · Administrador del sistema: <strong><?= $systemAdmin?'Sí':'No' ?></strong></p>
<form method="post" action="/system/users/<?= $id ?>/update">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Usuario <input name="username" value="<?= $e((string)$user['username']) ?>" minlength="3" maxlength="80" pattern="[a-z0-9._-]+" required></label>
<label>Nombre <input name="display_name" value="<?= $e((string)$user['display_name']) ?>" maxlength="191" required></label>
<label>Correo <input name="email" type="email" value="<?= $e((string)$user['email']) ?>" maxlength="191" required></label>
<button>Guardar identidad</button>
</form>
<form method="post" action="/system/users/<?= $id ?>/status"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="status" value="<?= $active?'disabled':'active' ?>"><button class="<?= $active?'danger':'' ?>"><?= $active?'Desactivar':'Activar' ?></button></form>
<form method="post" action="/system/users/<?= $id ?>/system-admin"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="enabled" value="<?= $systemAdmin?'0':'1' ?>"><button><?= $systemAdmin?'Quitar administración del sistema':'Hacer Administrador del sistema' ?></button></form>
<form method="post" action="/system/users/<?= $id ?>/password"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><label>Nueva contraseña <input name="password" type="password" minlength="<?= $passwordMin ?>" maxlength="72" autocomplete="new-password" required></label><button>Restablecer contraseña</button></form>
<h3>Acceso por empresa</h3>
<?php if (($memberships[$id] ?? []) === []): ?><p>Sin membresías.</p><?php endif; ?>
<?php foreach (($memberships[$id] ?? []) as $membership): $companyId=(int)$membership['company_id']; ?>
<form method="post" action="/system/users/<?= $id ?>/membership">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>">
<strong><?= $e((string)$membership['name']) ?><?= $membership['archived_at']!==null?' (archivada)':'' ?></strong>
<select name="role"><option value="admin" <?= $membership['role']==='admin'?'selected':'' ?>>Administrador</option><option value="operator" <?= in_array($membership['role'],['operator','member'],true)?'selected':'' ?>>Operador</option><option value="viewer" <?= $membership['role']==='viewer'?'selected':'' ?>>Consulta</option></select><button>Actualizar rol</button>
</form>
<form method="post" action="/system/users/<?= $id ?>/remove-membership"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>"><button class="danger">Quitar acceso a <?= $e((string)$membership['name']) ?></button></form>
<?php endforeach; ?>
<form method="post" action="/system/users/<?= $id ?>/membership"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><label>Añadir empresa <select name="company_id" required><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>"><?= $e((string)$company['name']) ?><?= $company['archived_at']!==null?' (archivada)':'' ?></option><?php endforeach; ?></select></label><label>Rol <select name="role"><option value="operator">Operador</option><option value="viewer">Consulta</option><option value="admin">Administrador</option></select></label><button>Añadir o actualizar acceso</button></form>
</section>
<?php endforeach; ?>
<form method="post" action="/logout"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><button>Cerrar sesión</button></form>
</main></body></html>
