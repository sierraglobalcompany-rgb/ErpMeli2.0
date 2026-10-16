<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string}|null $company */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Seleccionar empresa</title><?php if ($company === null): ?><link rel="stylesheet" href="/assets/app.css"><?php endif; ?></head>
<body>
<?php if ($company !== null) { require __DIR__ . '/navigation.php'; } ?>
<main class="<?= $company === null ? 'simple-page company-selector-workspace' : 'company-selector-workspace' ?>">
    <header class="page-header company-select-page-header">
        <span class="eyebrow">Contexto de trabajo</span>
        <h1>Seleccionar empresa</h1>
        <p>Elige la empresa con la que vas a trabajar en esta sesión.</p>
    </header>

    <section class="panel company-select-panel" aria-labelledby="company-select-title">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Empresa activa</span>
                <h2 id="company-select-title">Cambiar contexto</h2>
            </div>
            <p>Tu rol y los datos visibles se ajustan a la empresa seleccionada.</p>
        </div>

        <form class="settings-form company-select-form" method="post" action="/company/select">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Empresa
                <select name="company_id" required>
                    <?php foreach ($companies as $option): ?>
                        <option value="<?= $option['id'] ?>" <?= $company !== null && $option['id'] === $company['id'] ? 'selected' : '' ?>><?= htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Trabajar con esta empresa</button>
        </form>
    </section>
</main>
</body>
</html>
