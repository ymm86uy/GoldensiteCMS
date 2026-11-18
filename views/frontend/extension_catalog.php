<section style="max-width:1000px;margin:32px auto;padding:20px">
    <h1>Extensiones oficiales de GoldenSiteCMS</h1>
    <p>En tu instalación, abre Módulos y extensiones y pulsa Buscar extensiones oficiales para descargar los paquetes con su firma verificada.</p>
    <p><a href="/extension-catalog/index.json">Consultar índice firmado</a></p>
    <?php if (!$offers): ?>
        <p role="status">Todavía no hay extensiones publicadas.</p>
    <?php endif ?>
    <?php foreach ($offers as $offer): $module=$offer['module']; ?>
        <article style="padding:24px 0;border-bottom:1px solid var(--primary)">
            <h2><?= $e($module['name']) ?></h2>
            <p>Versión <?= $e($module['version']) ?> · <?= $module['pro']?'PRO':'FREE' ?></p>
            <p><?= $e($module['description']) ?></p>
            <p>Requiere GoldenSiteCMS <?= $e($module['min_core']) ?> o posterior.</p>
            <a href="/extension-catalog/package/<?= $e($offer['sha256']) ?>">Descargar ZIP</a>
        </article>
    <?php endforeach ?>
</section>
