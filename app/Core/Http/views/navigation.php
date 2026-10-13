<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string} $company */
/** @var string $csrfToken */
/** @var list<array{id:int,name:string,role:string}> $companies */
?>
<nav aria-label="Navegación del operador">
    <p>Empresa: <?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?></p>
    <a href="/sales">Ventas</a>
    <a href="/catalog">Catálogo</a>
    <?php if ($company['role'] === 'admin'): ?><a href="/settings/system">Configuración</a><?php endif; ?>
    <?php if (count($companies) > 1): ?><a href="/company/select">Cambiar empresa</a><?php endif; ?>
    <form method="post" action="/logout">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Cerrar sesión</button>
    </form>
</nav>
