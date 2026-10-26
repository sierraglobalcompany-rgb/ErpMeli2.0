<?php

declare(strict_types=1);

/** @var string $productIdInput */
/** @var array{id:int,company_id:int,internal_sku:string,name:string}|null $selectedProduct */
/** @var int|null $quantity */
/** @var string $error */
/** @var string $internalSku */
/** @var string $name */
/** @var string $csrfToken */
/** @var string $search */
/** @var bool $searchInvalid */
/** @var list<array{id:int,internal_sku:string,name:string}> $products */
/** @var bool $canOperate */
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Productos e inventario local</title></head>
<body>
<?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
<main id="main-content" tabindex="-1">
    <header class="page-header catalog-page-header">
        <span class="eyebrow">Productos</span>
        <h1>Productos e inventario local</h1>
        <p>Administra productos e inventario local de la empresa seleccionada desde una sola superficie.</p>
    </header>

    <?php if ($error !== ''): ?><p class="notice" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <div class="catalog-workspace">
        <div class="settings-grid">
            <?php if ($canOperate): ?>
            <section class="panel panel-full" aria-labelledby="create-product-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Productos locales</span>
                        <h2 id="create-product-title">Crear producto</h2>
                    </div>
                    <p>Registra la identidad local del producto sin mezclarla con sincronizaciones remotas.</p>
                </div>
                <form class="settings-form" method="post" action="/catalog">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label>SKU interno <input name="internal_sku" maxlength="120" value="<?= htmlspecialchars($internalSku, ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Nombre <input name="name" maxlength="255" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <button type="submit">Crear producto</button>
                </form>
            </section>
            <?php endif; ?>

            <section class="panel panel-full" aria-labelledby="catalog-products-title">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Productos locales</span>
                        <h2 id="catalog-products-title"><?= $search !== '' ? 'Resultados de búsqueda (máximo 100)' : 'Productos recientes (máximo 100)' ?></h2>
                    </div>
                    <p>Busca en todos los productos internos por SKU o nombre; abre uno para consultar su inventario local.</p>
                </div>
                <form class="settings-form" method="get" action="/catalog" role="search">
                    <label>Buscar producto interno por SKU o nombre
                        <input name="q" type="search" maxlength="120" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="SKU o nombre" autocomplete="off">
                    </label>
                    <div class="row-actions">
                        <button type="submit">Buscar productos</button>
                        <?php if ($search !== ''): ?><a class="button-secondary" href="/catalog">Limpiar búsqueda</a><?php endif; ?>
                    </div>
                </form>
                <?php if ($products === []): ?>
                    <p class="empty-state"><?= $searchInvalid ? 'Corrige la búsqueda para consultar productos.' : ($search !== '' ? 'No se encontraron productos internos con ese SKU o nombre.' : 'No hay productos en esta empresa.') ?></p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table data-responsive-table="catalog-products">
                            <thead><tr><th>ID</th><th>SKU interno</th><th>Nombre</th></tr></thead>
                            <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr data-mobile-card="product-row">
                                    <td data-label="ID"><?= $product['id'] ?></td>
                                    <td data-label="SKU interno"><a class="primary-record-link" href="/catalog?product_id=<?= $product['id'] ?>"><?= htmlspecialchars($product['internal_sku'], ENT_QUOTES, 'UTF-8') ?></a></td>
                                    <td data-label="Nombre"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <details class="catalog-advanced-selection">
                <summary>Seleccionar producto por ID</summary>
                <p class="muted">Opción avanzada para abrir un producto cuando conoces su ID interno exacto.</p>
                <form class="settings-form" method="get" action="/catalog">
                    <label>ID exacto <input name="product_id" inputmode="numeric" pattern="[1-9][0-9]*" value="<?= htmlspecialchars($productIdInput, ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <button type="submit">Seleccionar</button>
                    <a class="button-secondary" href="/catalog">Quitar selección</a>
                </form>
            </details>

            <?php if ($selectedProduct !== null): ?>
            <section class="catalog-inventory-card" aria-labelledby="selected-product-title">
                <div class="panel">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Inventario local</span>
                            <h2 id="selected-product-title">Producto seleccionado</h2>
                        </div>
                    </div>
                    <dl class="settings-list">
                        <dt>ID</dt><dd><?= $selectedProduct['id'] ?></dd>
                        <dt>SKU interno</dt><dd><?= htmlspecialchars($selectedProduct['internal_sku'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt>Nombre</dt><dd><?= htmlspecialchars($selectedProduct['name'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt>Cantidad local</dt><dd><output id="local-quantity"><?= $quantity === null ? 'Sin definir' : $quantity ?></output></dd>
                    </dl>
                    <?php if ($canOperate): ?>
                    <form class="settings-form" method="post" action="/catalog/<?= $selectedProduct['id'] ?>/inventory">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label>Cantidad local (entero &gt;= 0) <input name="quantity" inputmode="numeric" pattern="[0-9]+" value="<?= $quantity === null ? '' : $quantity ?>" required></label>
                        <button type="submit">Establecer cantidad local</button>
                    </form>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
