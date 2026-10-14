<?php

declare(strict_types=1);

/** @var string $productIdInput */
/** @var array{id:int,company_id:int,internal_sku:string,name:string}|null $selectedProduct */
/** @var int|null $quantity */
/** @var string $error */
/** @var string $internalSku */
/** @var string $name */
/** @var string $csrfToken */
/** @var list<array{id:int,internal_sku:string,name:string}> $products */
/** @var bool $canOperate */
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Catálogo local</title></head>
<body>
<?php require dirname(__DIR__, 3) . '/Core/Http/views/navigation.php'; ?>
<main>
    <h1>Catálogo local</h1>
    <?php if ($error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($canOperate): ?>
    <section aria-labelledby="create-product-title">
        <h2 id="create-product-title">Crear producto</h2>
        <form method="post" action="/catalog">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>SKU interno <input name="internal_sku" maxlength="120" value="<?= htmlspecialchars($internalSku, ENT_QUOTES, 'UTF-8') ?>" required></label>
            <label>Nombre <input name="name" maxlength="255" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required></label>
            <button type="submit">Crear producto</button>
        </form>
    </section>
    <?php endif; ?>
    <h2>Productos recientes (máximo 100)</h2>
    <?php if ($products === []): ?><p>No hay productos en esta empresa.</p><?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>SKU interno</th><th>Nombre</th></tr></thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr><td><a href="/catalog?product_id=<?= $product['id'] ?>"><?= $product['id'] ?></a></td>
                <td><?= htmlspecialchars($product['internal_sku'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <section aria-labelledby="select-product-title">
        <h2 id="select-product-title">Seleccionar producto por ID</h2>
        <form method="get" action="/catalog">
            <label>ID exacto <input name="product_id" inputmode="numeric" pattern="[1-9][0-9]*" value="<?= htmlspecialchars($productIdInput, ENT_QUOTES, 'UTF-8') ?>" required></label>
            <button type="submit">Seleccionar</button>
            <a href="/catalog">Quitar selección</a>
        </form>
    </section>
    <?php if ($selectedProduct !== null): ?>
    <section aria-labelledby="selected-product-title">
        <h2 id="selected-product-title">Producto seleccionado</h2>
        <dl>
            <dt>ID</dt><dd><?= $selectedProduct['id'] ?></dd>
            <dt>SKU interno</dt><dd><?= htmlspecialchars($selectedProduct['internal_sku'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Nombre</dt><dd><?= htmlspecialchars($selectedProduct['name'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Cantidad local</dt><dd><output id="local-quantity"><?= $quantity === null ? 'Sin definir' : $quantity ?></output></dd>
        </dl>
        <?php if ($canOperate): ?>
        <form method="post" action="/catalog/<?= $selectedProduct['id'] ?>/inventory">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Cantidad local (entero >= 0) <input name="quantity" inputmode="numeric" pattern="[0-9]+" value="<?= $quantity === null ? '' : $quantity ?>" required></label>
            <button type="submit">Establecer cantidad local</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
