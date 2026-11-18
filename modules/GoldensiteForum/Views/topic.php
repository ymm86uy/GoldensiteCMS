<?php
use Modules\GoldensiteForum\{Markdown,Reputation};
use Modules\GoldensiteForum\Models\TopicModel;
$url=TopicModel::url($topic);
$members=Reputation::summary(array_column([$topic,...$replies],'author_id'));
?>
<section class="gs-forum">
<nav aria-label="Ruta del foro"><a href="/forum">Foro</a> / <a href="/forum/board/<?= $e($board['slug']) ?>"><?= $e($board['name']) ?></a></nav>
<header><h1><?= $e($topic['title']) ?></h1><p><?= $e(TopicModel::KINDS[$topic['kind']]) ?><?= $topic['closed']?' · Cerrado':'' ?><?= $topic['pinned']?' · Fijado':'' ?></p></header>
<?php require __DIR__.'/inbox-link.php'; ?>
<?php if ($tags): ?><ul class="gs-forum-tags"><?php foreach ($tags as $tag): ?><li><a href="/forum/board/<?= $e($board['slug']) ?>?tag=<?= rawurlencode($tag['name']) ?>"><?= $e($tag['name']) ?></a></li><?php endforeach ?></ul><?php endif ?>
<article class="gs-forum-card">
<?php $post=$topic; require __DIR__.'/author.php'; ?>
<div class="gs-forum-message"><?= Markdown::render($topic['body']) ?></div>
<?php $files=$attachments[0]??[]; require __DIR__.'/attachments.php'; ?>
</article>
<?php if ($solution): ?><aside class="gs-forum-card gs-forum-solution"><h2>Solución elegida</h2><?= Markdown::render($solution['body']) ?></aside><?php endif ?>
<?php if ($options): ?><section class="gs-forum-card"><h2>Encuesta</h2><ul class="gs-forum-list">
<?php foreach ($options as $option): ?><li><span><?= $e($option['label']) ?> · <?= (int)$option['votes'] ?> votos</span>
<?php if ($participant && !$voted && !$topic['closed']): ?><form method="post" action="<?= $e($url) ?>/action"><?= $csrf ?><input type="hidden" name="action" value="vote"><button name="target" value="<?= (int)$option['id'] ?>">Votar</button></form><?php endif ?></li><?php endforeach ?>
</ul><?php if ($voted): ?><p>Tu voto está registrado.</p><?php endif ?></section><?php endif ?>
<?php if ($user): ?><form class="gs-forum-actions" method="post" action="<?= $e($url) ?>/action"><?= $csrf ?><input type="hidden" name="action" value="follow">
<label>Seguir hilo <select name="target"><option value="0">Sin seguimiento</option><option value="1" <?= $follow && !$follow['email_enabled']?'selected':'' ?>>Avisos en el foro</option><option value="2" <?= $follow && $follow['email_enabled']?'selected':'' ?>>Avisos y correo</option></select></label><button>Guardar preferencia</button></form><?php endif ?>
<h2>Respuestas</h2>
<?php if (!$replies): ?><p>No hay respuestas en esta página.</p><?php endif ?>
<?php foreach ($replies as $post): ?><article class="gs-forum-card" id="reply-<?= (int)$post['id'] ?>">
<?php require __DIR__.'/author.php'; ?>
<div class="gs-forum-message"><?= Markdown::render($post['body']) ?></div>
<?php $files=$attachments[(int)$post['id']]??[]; require __DIR__.'/attachments.php'; ?>
<p>Me gusta <?= (int)$post['likes'] ?> · Útil <?= (int)$post['helpful'] ?></p>
<div class="gs-forum-actions">
<?php if ($participant && !$topic['closed'] && $post['author_id'] && (int)$post['author_id']!==$user): ?><form class="gs-forum-actions" method="post" action="<?= $e($url) ?>/react"><?= $csrf ?><input type="hidden" name="reply" value="<?= (int)$post['id'] ?>"><button name="reaction" value="like">Me gusta</button><button name="reaction" value="helpful">Útil</button><button name="reaction" value="remove">Quitar reacción</button></form><?php endif ?>
<?php if ($participant && $canSolve && $topic['kind']==='question' && !$topic['closed']): ?><form method="post" action="<?= $e($url) ?>/action"><?= $csrf ?><input type="hidden" name="action" value="solution"><button name="target" value="<?= (int)$post['id'] ?>"><?= (int)$topic['solution_id']===(int)$post['id']?'Quitar solución':'Elegir solución' ?></button></form><?php endif ?>
</div></article><?php endforeach ?>
<?php if ($next): ?><p><a href="?after=<?= $next ?>">Más respuestas</a> · <a href="<?= $e($url) ?>">Primera página</a></p><?php elseif (!empty($_GET['after'])): ?><p><a href="<?= $e($url) ?>">Primera página</a></p><?php endif ?>
<?php if ($topic['closed']): ?><p>El hilo está cerrado.</p>
<?php elseif ($participant): ?><form class="gs-forum-form" method="post" enctype="multipart/form-data" action="<?= $e($url) ?>/reply"><h2>Responder</h2><?= $csrf ?><?php require __DIR__.'/editor.php'; ?><button>Publicar respuesta</button></form>
<?php elseif ($user): ?><p>Tu cuenta tiene acceso de lectura.</p>
<?php else: ?><p><a href="/login?return_to=<?= rawurlencode($url) ?>">Inicia sesión para responder</a></p><?php endif ?>
</section>
