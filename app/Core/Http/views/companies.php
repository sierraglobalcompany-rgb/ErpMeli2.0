<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $companies */
/** @var string $csrfToken */
$escape = static fn (string $s): string => htmlspecialchars($s,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Empresas — ERP Meli 2.0</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:960px;margin:32px auto;padding:16px;color:#172839}section{border:1px solid #ccd5dd;border-radius:8px;padding:20px;margin:20px 0}label{display:inline-block;margin:8px}input,button{font:inherit;padding:8px}form{margin:10px 0}.danger{color:#9b241b}nav{display:flex;gap:20px;flex-wrap:wrap}</style></head><body><main>
<nav><a href="/">Inicio</a><a href="/company/select">Elegir empresa</a><a href="/system/users">Usuarios</a><a href="/settings/system">Configuración del sistema</a></nav>
<h1>Empresas</h1><p>La administración del sistema gestiona empresas. Archivar conserva su historia y exige cerrar primero su actividad operativa.</p>
<section><h2>Crear empresa</h2><form method="post" action="/system/companies">
<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
<label>Nombre <input name="name" maxlength="160" required></label><label>Identificador <input name="slug" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></label><button>Crear</button>
<p>Usa letras minúsculas, números y guiones. Crear no cambia la empresa que tienes seleccionada.</p></form></section>
<?php foreach ($companies as $company): $id=(int)$company['id']; $archived=$company['archived_at']!==null; ?>
<section><h2><?= $escape((string)$company['name']) ?></h2><p>Estado: <?= $archived?'Archivada':'Activa' ?></p>
<form method="post" action="/system/companies/<?= $id ?>/update"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
<label>Nombre <input name="name" value="<?= $escape((string)$company['name']) ?>" maxlength="160" required></label>
<label>Identificador <input name="slug" value="<?= $escape((string)$company['slug']) ?>" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></label><button>Editar</button></form>
<form method="post" action="/system/companies/<?= $id ?>/<?= $archived?'reactivate':'archive' ?>"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><button><?= $archived?'Reactivar':'Archivar' ?></button></form>
<?php if ($company['deletable']): ?><form method="post" action="/system/companies/<?= $id ?>/delete"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><p>Sólo se puede eliminar una empresa archivada sin uso previo y con tu única membresía admin. No se eliminará tu usuario.</p><button class="danger">Eliminar definitivamente</button></form><?php endif; ?>
</section><?php endforeach; ?>
<form method="post" action="/logout"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><button>Cerrar sesión</button></form>
</main></body></html>
