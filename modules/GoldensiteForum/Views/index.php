<section class="gs-forum">
<header class="gs-forum-hero">
<?php if ($branding['cover']): ?><img class="gs-forum-cover" src="/forum/brand/cover/<?= $e($branding['cover']) ?>" alt=""><?php endif ?>
<div class="gs-forum-hero-copy">
<?php if ($branding['logo']): ?><img class="gs-forum-emblem" src="/forum/brand/logo/<?= $e($branding['logo']) ?>" alt=""><?php else: ?><span class="gs-forum-emblem" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($branding['name'],0,1))) ?></span><?php endif ?>
<h1><?= $e($branding['name']) ?></h1>
<?php if ($branding['description']!==''): ?><p><?= $e($branding['description']) ?></p><?php endif ?>
</div></header>
<?php require __DIR__.'/inbox-link.php'; ?>
<?php if (!$boards): ?><p>La comunidad todavía no tiene subforos.</p><?php endif ?>
<?php $previous=null; foreach ($boards as $board): ?>
<?php if ($previous!==$board['category_slug']): if ($previous!==null): ?></ul><?php endif ?>
<h2 id="<?= $e($board['category_slug']) ?>"><?= $e($board['category']) ?></h2><ul class="gs-forum-list">
<?php $previous=$board['category_slug']; endif ?>
<?php if ($board['id']): ?><li><a href="/forum/board/<?= $e($board['slug']) ?>"><?= $e($board['name']) ?></a></li><?php endif ?>
<?php endforeach; if ($previous!==null): ?></ul><?php endif ?>
</section>
