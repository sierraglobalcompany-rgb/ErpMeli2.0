<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string} $company */
/** @var string $csrfToken */
/** @var list<array{id:int,name:string,role:string}> $companies */

$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$isActive = static function (string $path) use ($currentPath): string {
    return $currentPath === $path || ($path !== '/' && str_starts_with($currentPath, $path . '/')) ? ' is-active' : '';
};
?>
<link rel="stylesheet" href="/assets/app.css">
<nav class="app-nav" aria-label="Navegación del operador">
    <div class="app-brand">
        <span class="app-brand-mark" aria-hidden="true">EM</span>
        <div>
            <strong>ERP Meli 2.0</strong>
            <small>Operación central</small>
        </div>
    </div>

    <div class="app-context">
        <span>Empresa</span>
        <strong><?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <div class="app-context">
        <span>Usuario</span>
        <strong><?= htmlspecialchars((string) ($_SESSION['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
    </div>

    <div class="app-nav-group">
        <span class="app-nav-label">Operación</span>
        <a class="<?= $isActive('/sales') ?>" href="/sales">Ventas</a>
        <a class="<?= $isActive('/catalog') ?>" href="/catalog">Catálogo</a>
        <a class="<?= $isActive('/billing') ?>" href="/billing">Facturación</a>
        <?php if ($company['role'] === 'admin'): ?>
            <a class="<?= $isActive('/mercadolibre/accounts') ?>" href="/mercadolibre/accounts">Cuentas Mercado Libre</a>
        <?php endif; ?>
    </div>

    <?php if ($_SESSION['is_system_admin'] ?? false): ?>
        <div class="app-nav-group">
            <span class="app-nav-label">Administración</span>
            <a class="<?= $isActive('/system/companies') ?>" href="/system/companies">Empresas</a>
            <a class="<?= $isActive('/system/users') ?>" href="/system/users">Usuarios</a>
            <a class="<?= $isActive('/settings/system') ?>" href="/settings/system">Configuración</a>
            <a class="<?= $isActive('/settings/mercadolibre') ?>" href="/settings/mercadolibre">Mercado Libre</a>
        </div>
    <?php endif; ?>

    <?php if (count($companies) > 1): ?>
        <a class="<?= $isActive('/company/select') ?>" href="/company/select">Cambiar empresa</a>
    <?php endif; ?>

    <form method="post" action="/logout">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Cerrar sesión</button>
    </form>
</nav>
