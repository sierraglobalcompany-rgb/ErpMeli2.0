<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $companies */
/** @var string $csrfToken */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Empresas — ERP Meli 2.0</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="simple-page administration-companies-workspace" data-design-master-page="companies">
    <nav class="simple-nav" aria-label="Administración global">
        <a href="/">Inicio</a>
        <a href="/company/select">Elegir empresa</a>
        <a href="/system/users">Usuarios</a>
        <a href="/settings/system">Configuración</a>
        <a href="/settings/mercadolibre">Mercado Libre</a>
    </nav>

    <header class="page-header companies-page-header">
        <span class="eyebrow">Administración</span>
        <h1>Empresas</h1>
        <p>Administra las empresas del ERP. Archivar conserva su historia y exige cerrar primero su actividad operativa.</p>
    </header>

    <section class="panel panel-full companies-create-panel" aria-labelledby="create-company-title">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Nueva empresa</span>
                <h2 id="create-company-title">Crear empresa</h2>
            </div>
            <p>Crear una empresa no cambia la empresa seleccionada.</p>
        </div>
        <form class="settings-form" method="post" action="/system/companies">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Nombre <input name="name" maxlength="160" required></label>
            <label>Identificador <input name="slug" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></label>
            <button>Crear empresa</button>
            <p class="muted">Usa letras minúsculas, números y guiones.</p>
        </form>
    </section>

    <div class="companies-list">
        <div class="settings-grid">
            <?php foreach ($companies as $company):
                $id = (int) $company['id'];
                $archived = $company['archived_at'] !== null;
            ?>
            <section class="panel" data-admin-card="company" aria-labelledby="company-<?= $id ?>-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Empresa #<?= $id ?></span>
                        <h2 id="company-<?= $id ?>-title"><?= $escape((string) $company['name']) ?></h2>
                    </div>
                    <span class="status-chip <?= $archived ? 'status-disabled' : 'status-connected' ?>"><?= $archived ? 'Archivada' : 'Activa' ?></span>
                </div>

                <form class="settings-form" method="post" action="/system/companies/<?= $id ?>/update">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label>Nombre <input name="name" value="<?= $escape((string) $company['name']) ?>" maxlength="160" required></label>
                    <label>Identificador <input name="slug" value="<?= $escape((string) $company['slug']) ?>" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></label>
                    <button>Guardar cambios</button>
                </form>

                <div class="row-actions">
                    <form method="post" action="/system/companies/<?= $id ?>/<?= $archived ? 'reactivate' : 'archive' ?>">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <button><?= $archived ? 'Reactivar' : 'Archivar' ?></button>
                    </form>
                </div>

                <?php if ($company['deletable']): ?>
                    <div class="notice">
                        <p>Sólo se puede eliminar una empresa archivada sin uso previo y con tu única membresía admin. No se eliminará tu usuario.</p>
                        <form method="post" action="/system/companies/<?= $id ?>/delete">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <button class="danger">Eliminar definitivamente</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="post" action="/logout">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <button class="button-secondary">Cerrar sesión</button>
    </form>
</main>
</body>
</html>
