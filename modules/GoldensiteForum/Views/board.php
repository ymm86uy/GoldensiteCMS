<section class="gs-forum">
<nav aria-label="Ruta del foro"><a href="/forum">Foro</a> / <a href="/forum#<?= $e($board['category_slug']) ?>"><?= $e($board['category']) ?></a></nav>
<h1><?= $e($board['name']) ?></h1>
<?php require __DIR__.'/inbox-link.php'; ?>
<?php if ($participant): ?><p><a class="gs-forum-button" href="/forum/board/<?= $e($board['slug']) ?>/new">Crear hilo</a></p>
<?php elseif ($csrf): ?><p>Tu cuenta tiene acceso de lectura.</p>
<?php else: ?><p><a href="/login?return_to=<?= rawurlencode('/forum/board/'.$board['slug']) ?>">Inicia sesión para participar</a></p><?php endif ?>
<?php if ($tag!==''): ?><p>Etiqueta <?= $e($tag) ?> · <a href="/forum/board/<?= $e($board['slug']) ?>">Ver todos</a></p><?php endif ?>
<ul class="gs-forum-list">
<?php foreach ($topics as $row): ?><li><a href="<?= $e(\Modules\GoldensiteForum\Models\TopicModel::url($row)) ?>"><?= $e($row['title']) ?></a>
<small><?= $e(\Modules\GoldensiteForum\Models\TopicModel::KINDS[$row['kind']]) ?><?= $row['pinned']?' · Fijado':'' ?><?= $row['closed']?' · Cerrado':'' ?></small></li><?php endforeach ?>
</ul>
<?php if (!$topics): ?><p>No hay hilos en esta lista.</p><?php endif ?>
<?php if ($next): ?><a href="?<?= $e($next) ?>">Más hilos</a><?php endif ?>
</section>
