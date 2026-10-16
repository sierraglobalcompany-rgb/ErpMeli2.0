<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string}|null $company */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */

$currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$isSystemAdmin = (bool) ($_SESSION['is_system_admin'] ?? false);
$isCompanyAdmin = (($company['role'] ?? '') === 'admin');
$displayName = trim((string) ($_SESSION['display_name'] ?? 'Usuario'));
$companyName = trim((string) ($company['name'] ?? 'Sin empresa'));
$companyRole = (string) ($company['role'] ?? '');
$roleLabel = match ($companyRole) {
    'admin' => 'Administrador',
    'operator', 'member' => 'Operador',
    'viewer' => 'Consulta',
    default => 'Usuario',
};
$meliHref = $isCompanyAdmin
    ? '/mercadolibre/accounts'
    : ($isSystemAdmin ? '/settings/mercadolibre' : null);

$icons = [
    'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9h13v-9"/><path d="M9.5 19v-5h5v5"/></svg>',
    'sales' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19H2"/></svg>',
    'products' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5"/><path d="M12 12v9"/></svg>',
    'billing' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2Z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>',
    'meli' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5c2-3 5-4.5 8-4.5s6 1.5 8 4.5"/><path d="M4 15.5c2 3 5 4.5 8 4.5s6-1.5 8-4.5"/><path d="M7 12h10"/><circle cx="7" cy="12" r="2"/><circle cx="17" cy="12" r="2"/></svg>',
    'apps' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="5" height="5" rx="1"/><rect x="15" y="4" width="5" height="5" rx="1"/><rect x="4" y="15" width="5" height="5" rx="1"/><rect x="15" y="15" width="5" height="5" rx="1"/></svg>',
    'company' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V7l8-4 8 4v14"/><path d="M8 10h2M14 10h2M8 14h2M14 14h2"/><path d="M9 21v-4h6v4"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 20c.5-4 2.5-6 5.5-6s5 2 5.5 6"/><path d="M16 5.5a3 3 0 0 1 0 5"/><path d="M17 14c2.2.5 3.5 2.5 3.5 5"/></svg>',
    'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.6-2-3.4-2.4 1A7 7 0 0 0 15 6l-.4-2.6h-4L10 6a7 7 0 0 0-1.5 1L6 6 4 9.4 6.1 11a7 7 0 0 0 0 2L4 14.6 6 18l2.5-1a7 7 0 0 0 1.5 1l.5 2.6h4L15 18a7 7 0 0 0 1.5-1l2.5 1 2-3.4-2.1-1.6c.1-.3.1-.7.1-1Z"/></svg>',
    'switch' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h14"/><path d="m15 4 3 3-3 3"/><path d="M20 17H6"/><path d="m9 14-3 3 3 3"/></svg>',
];

$isActive = static fn (string $path): bool => $path === '/'
    ? $currentPath === '/'
    : ($currentPath === $path || str_starts_with($currentPath, $path . '/'));
?>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>

<div class="app-shell-chrome">
    <header class="app-topbar">
        <div class="topbar-mobile-brand">
            <span class="app-brand-mark" aria-hidden="true">EM</span>
            <span><strong>ERP MELI 2.0</strong><small><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></small></span>
        </div>
        <div class="topbar-context">
            <span class="topbar-company"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="topbar-separator" aria-hidden="true"></span>
            <span class="topbar-user">
                <span class="topbar-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                <span><strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></small></span>
            </span>
        </div>
    </header>

    <aside class="app-sidebar" aria-label="Navegación principal">
        <div class="app-brand">
            <span class="app-brand-mark" aria-hidden="true">EM</span>
            <div>
                <strong>ERP MELI 2.0</strong>
                <small>Centro de operación</small>
            </div>
        </div>

        <div class="sidebar-company">
            <span>Empresa activa</span>
            <strong><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (count($companies) > 1): ?>
                <a href="/company/select"><?= $icons['switch'] ?><span>Cambiar empresa</span></a>
            <?php endif; ?>
        </div>

        <nav class="sidebar-nav" aria-label="Áreas del ERP">
            <div class="sidebar-group">
                <span class="sidebar-label">General</span>
                <a class="<?= $isActive('/dashboard') ? 'is-active' : '' ?>" href="/dashboard"><?= $icons['home'] ?><span>Inicio / Resumen</span></a>
            </div>

            <div class="sidebar-group">
                <span class="sidebar-label">Operación</span>
                <a class="<?= $isActive('/sales') ? 'is-active' : '' ?>" href="/sales"><?= $icons['sales'] ?><span>Ventas</span></a>
            </div>

            <div class="sidebar-group">
                <span class="sidebar-label">Productos</span>
                <a class="<?= $isActive('/catalog') ? 'is-active' : '' ?>" href="/catalog"><?= $icons['products'] ?><span>Catálogo e inventario</span></a>
            </div>

            <div class="sidebar-group">
                <span class="sidebar-label">Finanzas</span>
                <a class="<?= $isActive('/billing') ? 'is-active' : '' ?>" href="/billing"><?= $icons['billing'] ?><span>Facturación</span></a>
            </div>

            <?php if ($meliHref !== null): ?>
                <div class="sidebar-group">
                    <span class="sidebar-label">Mercado Libre</span>
                    <a class="<?= $isActive($meliHref) ? 'is-active' : '' ?>" href="<?= htmlspecialchars($meliHref, ENT_QUOTES, 'UTF-8') ?>"><?= $icons['meli'] ?><span><?= $isCompanyAdmin ? 'Cuentas' : 'Configuración MELI' ?></span></a>
                </div>
            <?php endif; ?>

            <?php if ($isSystemAdmin): ?>
                <div class="sidebar-group">
                    <span class="sidebar-label">Administración</span>
                    <a class="<?= $isActive('/system/companies') ? 'is-active' : '' ?>" href="/system/companies"><?= $icons['company'] ?><span>Empresas</span></a>
                    <a class="<?= $isActive('/system/users') ? 'is-active' : '' ?>" href="/system/users"><?= $icons['users'] ?><span>Usuarios y permisos</span></a>
                    <a class="<?= $isActive('/settings/system') ? 'is-active' : '' ?>" href="/settings/system"><?= $icons['settings'] ?><span>Sistema</span></a>
                    <a class="<?= $isActive('/settings/mercadolibre') ? 'is-active' : '' ?>" href="/settings/mercadolibre"><?= $icons['meli'] ?><span>Mercado Libre</span></a>
                </div>
            <?php endif; ?>
        </nav>

        <form class="sidebar-logout" method="post" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Cerrar sesión</button>
        </form>
    </aside>

    <nav class="mobile-bottom-nav" aria-label="Navegación móvil">
        <a data-mobile-nav-item="inicio" class="<?= $isActive('/dashboard') ? 'is-active' : '' ?>" href="/dashboard">
            <span class="mobile-nav-icon"><?= $icons['home'] ?></span><span>Inicio</span>
        </a>
        <a data-mobile-nav-item="operacion" class="<?= $isActive('/sales') ? 'is-active' : '' ?>" href="/sales">
            <span class="mobile-nav-icon"><?= $icons['sales'] ?></span><span>Operación</span>
        </a>
        <button data-mobile-nav-item="apps" class="mobile-apps-button" type="button" data-apps-launcher-open aria-controls="apps-launcher" aria-expanded="false">
            <span class="mobile-apps-orb"><?= $icons['apps'] ?></span><span>Apps</span>
        </button>
        <a data-mobile-nav-item="productos" class="<?= $isActive('/catalog') ? 'is-active' : '' ?>" href="/catalog">
            <span class="mobile-nav-icon"><?= $icons['products'] ?></span><span>Productos</span>
        </a>
        <?php if ($meliHref !== null): ?>
            <a data-mobile-nav-item="mercado-libre" class="<?= $isActive($meliHref) ? 'is-active' : '' ?>" href="<?= htmlspecialchars($meliHref, ENT_QUOTES, 'UTF-8') ?>">
                <span class="mobile-nav-icon"><?= $icons['meli'] ?></span><span>Mercado Libre</span>
            </a>
        <?php else: ?>
            <span data-mobile-nav-item="mercado-libre" class="is-disabled" aria-disabled="true">
                <span class="mobile-nav-icon"><?= $icons['meli'] ?></span><span>Mercado Libre</span>
            </span>
        <?php endif; ?>
    </nav>

    <div class="apps-launcher" id="apps-launcher" data-apps-launcher hidden>
        <button class="apps-launcher-backdrop" type="button" tabindex="-1" data-apps-launcher-close aria-label="Cerrar aplicaciones"></button>
        <section class="apps-launcher-sheet" role="dialog" aria-modal="true" aria-labelledby="apps-launcher-title" tabindex="-1">
            <div class="apps-launcher-header">
                <div>
                    <span class="eyebrow">Navegación</span>
                    <h2 id="apps-launcher-title">Apps</h2>
                </div>
                <button class="apps-launcher-close" type="button" data-apps-launcher-close aria-label="Cerrar">×</button>
            </div>
            <div class="apps-grid">
                <a href="/sales"><?= $icons['sales'] ?><span>Ventas</span><small>Operación</small></a>
                <a href="/catalog"><?= $icons['products'] ?><span>Productos</span><small>Catálogo e inventario</small></a>
                <a href="/billing"><?= $icons['billing'] ?><span>Facturación</span><small>Finanzas</small></a>
                <?php if ($isCompanyAdmin): ?>
                    <a href="/mercadolibre/accounts"><?= $icons['meli'] ?><span>Cuentas MELI</span><small>Conexiones</small></a>
                <?php endif; ?>
                <?php if ($isSystemAdmin): ?>
                    <a href="/system/companies"><?= $icons['company'] ?><span>Empresas</span><small>Administración</small></a>
                    <a href="/system/users"><?= $icons['users'] ?><span>Usuarios</span><small>Permisos</small></a>
                    <a href="/settings/system"><?= $icons['settings'] ?><span>Sistema</span><small>Configuración</small></a>
                    <a href="/settings/mercadolibre"><?= $icons['meli'] ?><span>Mercado Libre</span><small>Configuración global</small></a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
