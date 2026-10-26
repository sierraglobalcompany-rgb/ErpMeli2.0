<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string} $company */
/** @var list<array{id:int,seller_id:string,site_id:string,nickname:?string}> $accounts */
/** @var array{id:int,seller_id:string,site_id:string,nickname:?string}|null $selectedAccount */
/** @var array{account_id:int,company_id:int,seller_id:string,site_id:string}|null $selectedSeller */
/** @var list<array{id:string,title:?string,identity:string,status:?string,price:?string,currency_id:?string,public_quantity_reference:?string}> $fixtures */
/** @var list<array{id:string,title:?string,identity:string,status:?string,price:?string,currency_id:?string,public_quantity_reference:?string}> $pageRows */
/** @var list<array{id:string,title:?string,identity:string,status:?string,price:?string,currency_id:?string,public_quantity_reference:?string}> $visibleRows */
/** @var string $filter */
/** @var string $error */
/** @var int $responseStatus */
/** @var int $offset */
/** @var int|null $nextOffset */
/** @var int|null $previousOffset */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$previewPath = \App\Modules\Settings\MeliListingPreviewController::PATH;
$selectedAccountId = $selectedSeller['account_id'] ?? null;
$visibleStart = $visibleRows === [] ? 0 : $offset + 1;
$visibleEnd = $offset + count($pageRows);
$buildPageUrl = static function (int $targetOffset) use ($selectedAccountId, $filter, $previewPath): string {
    $parameters = ['account_id' => $selectedAccountId, 'offset' => $targetOffset];
    if ($filter !== '') {
        $parameters['q'] = $filter;
    }
    return $previewPath . '?' . http_build_query($parameters);
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vista previa de publicaciones · ERP MELI 2.0</title>
</head>
<body>
<?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
<main id="main-content" data-m02-fixture-preview tabindex="-1">
    <header class="page-header">
        <span class="eyebrow">M02 · solo entorno de prueba</span>
        <h1>Vista previa de publicaciones</h1>
        <p>Revisa cómo se presentan publicaciones de ejemplo asociadas a una cuenta conectada de esta empresa.</p>
    </header>

    <section class="panel" aria-labelledby="fixture-account-title">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Empresa activa</span>
                <h2 id="fixture-account-title">Cuenta vendedora</h2>
            </div>
        </div>
        <?php if ($accounts === []): ?>
            <p class="empty-state">Esta empresa no tiene cuentas conectadas. La vista de ejemplo no crea ni conecta cuentas.</p>
        <?php else: ?>
            <form class="toolbar m02-fixture-account-form" method="get" action="<?= $escape($previewPath) ?>">
                <label for="listing-account">Cuenta conectada
                    <select id="listing-account" name="account_id" required>
                        <option value="">Selecciona una cuenta</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= $account['id'] ?>" <?= $selectedAccountId === $account['id'] ? 'selected' : '' ?>>
                                <?= $escape($account['nickname'] ?? 'Cuenta sin nombre') ?> · vendedor <?= $escape($account['seller_id']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Ver datos de prueba</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="fixture-notice-title" data-fixture-notice>
        <div class="section-heading">
            <div>
                <span class="eyebrow">Modo de prueba</span>
                <h2 id="fixture-notice-title">Datos de prueba</h2>
            </div>
        </div>
        <p>Las filas con IDs <code>MUESTRA-…</code> son ficticias. Esta pantalla no consulta Mercado Libre ni sincroniza publicaciones.</p>
        <p>La identidad que coincide en el ejemplo no confirma título, precio, estado ni cantidad. La cantidad pública es referencial; no representa inventario físico.</p>
    </section>

    <?php if ($error !== ''): ?>
        <p class="notice" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <?php if ($selectedSeller !== null && $selectedAccount !== null): ?>
        <section class="panel" aria-labelledby="fixture-list-title" data-page-offset="<?= $offset ?>">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Cuenta seleccionada</span>
                    <h2 id="fixture-list-title"><?= $escape($selectedAccount['nickname'] ?? 'Cuenta sin nombre') ?></h2>
                    <p>Vendedor <?= $escape($selectedSeller['seller_id']) ?> · sitio <?= $escape($selectedSeller['site_id']) ?></p>
                </div>
            </div>

            <?php if ($fixtures === []): ?>
                <p class="empty-state">No hay una fixture de prueba para esta cuenta. No se mostrarán publicaciones del proveedor.</p>
            <?php else: ?>
                <form class="toolbar" method="get" action="<?= $escape($previewPath) ?>" role="search">
                    <input type="hidden" name="account_id" value="<?= $selectedSeller['account_id'] ?>">
                    <input type="hidden" name="offset" value="0">
                    <label for="listing-filter">Filtrar por título o ID (solo esta página)
                        <input id="listing-filter" type="search" name="q" maxlength="80" value="<?= $escape($filter) ?>" autocomplete="off">
                    </label>
                    <button type="submit">Filtrar esta página</button>
                    <?php if ($filter !== ''): ?>
                        <a class="button-secondary" href="<?= $escape($previewPath) ?>?account_id=<?= $selectedSeller['account_id'] ?>">Limpiar filtro</a>
                    <?php endif; ?>
                </form>
                <p class="muted">
                    <?php if ($filter !== ''): ?>Filtro activo solo en esta página. <?php endif; ?>
                    <?= $visibleRows === [] && $pageRows !== [] && $filter !== ''
                        ? 'Esta página no contiene coincidencias para el filtro.'
                        : ($pageRows === [] ? 'No hay ejemplos en esta página.' : 'Filas ficticias ' . $visibleStart . '–' . $visibleEnd . ' de ' . count($fixtures) . '.') ?>
                </p>

                <?php if ($visibleRows !== []): ?>
                    <div class="table-wrap">
                        <table data-responsive-table="m02-fixtures">
                            <thead>
                                <tr>
                                    <th scope="col">ID de muestra</th>
                                    <th scope="col">Título de muestra</th>
                                    <th scope="col">Identidad en el ejemplo</th>
                                    <th scope="col">Estado de muestra</th>
                                    <th scope="col">Precio de muestra</th>
                                    <th scope="col">Cantidad pública de referencia</th>
                                    <th scope="col">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($visibleRows as $row): ?>
                                <?php
                                $identityLabel = $row['identity'] === 'matched'
                                    ? 'Coincide en el ejemplo'
                                    : 'Sin información suficiente';
                                $priceLabel = $row['price'] === null
                                    ? 'Sin información'
                                    : trim(($row['currency_id'] ?? '') . ' ' . $row['price']);
                                $quantityLabel = $row['public_quantity_reference'] ?? 'Sin información';
                                ?>
                                <tr data-mobile-card="m02-fixture-row" data-fixture-row="<?= $escape($row['id']) ?>">
                                    <td data-label="ID de muestra"><span><?= $escape($row['id']) ?></span></td>
                                    <td data-label="Título de muestra"><?= $escape($row['title'] ?? 'Sin información') ?></td>
                                    <td data-label="Identidad en el ejemplo"><?= $escape($identityLabel) ?></td>
                                    <td data-label="Estado de muestra"><?= $escape($row['status'] ?? 'Sin información') ?></td>
                                    <td data-label="Precio de muestra"><?= $escape($priceLabel) ?></td>
                                    <td data-label="Cantidad pública de referencia"><span data-fixture-quantity="<?= $row['public_quantity_reference'] === null ? 'unknown' : $escape($row['public_quantity_reference']) ?>"><?= $escape($quantityLabel) ?></span></td>
                                    <td data-label="Detalle">
                                        <details>
                                            <summary>Ver detalle</summary>
                                            <p>Este registro es ficticio y no se consultó al proveedor.</p>
                                            <p>La identidad del ejemplo no implica que sus otros campos existan o estén confirmados.</p>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($pageRows === []): ?>
                    <p class="empty-state">No hay ejemplos en esta página. Vuelve a la página anterior o selecciona otra cuenta.</p>
                <?php else: ?>
                    <p class="empty-state">Esta página no contiene coincidencias. Cambia el filtro o límpialo para revisar sus ejemplos.</p>
                <?php endif; ?>

                <?php if ($previousOffset !== null || $nextOffset !== null): ?>
                    <nav class="toolbar" aria-label="Paginación de los datos de prueba">
                        <?php if ($previousOffset !== null): ?><a class="button-secondary" rel="prev" href="<?= $escape($buildPageUrl($previousOffset)) ?>">Página anterior</a><?php endif; ?>
                        <?php if ($nextOffset !== null): ?><a class="button-secondary" rel="next" href="<?= $escape($buildPageUrl($nextOffset)) ?>">Página siguiente</a><?php endif; ?>
                        <span class="muted">Máximo 20 filas por página; ventana de prueba acotada a 1000.</span>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
