<?php

declare(strict_types=1);

/** @var array{id:int,name:string,role:string}|null $company */
/** @var list<array{id:int,name:string,role:string}> $companies */
/** @var string $csrfToken */
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Seleccionar empresa</title></head>
<body>
<?php if ($company !== null) { require __DIR__ . '/navigation.php'; } ?>
<main>
<h1>Seleccionar empresa</h1>
<form method="post" action="/company/select">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <label>Empresa <select name="company_id" required>
        <?php foreach ($companies as $option): ?>
            <option value="<?= $option['id'] ?>" <?= $company !== null && $option['id'] === $company['id'] ? 'selected' : '' ?>><?= htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select></label>
    <button type="submit">Seleccionar</button>
</form>
</main>
</body>
</html>
