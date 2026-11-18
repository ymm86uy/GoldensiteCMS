<section class="gs-forum">
<header><h1>Goldensite Forum</h1><p><a href="/forum">Abrir foro público</a></p></header>
<?php if ($error): ?><p role="alert"><?= $e($error) ?></p><?php endif ?>
<?php if (\App\Core\IdentityAccess::superuser() || \App\Core\IdentityAccess::allows('appearance.write')) require __DIR__.'/appearance.php'; ?>
<div class="gs-forum-grid">
<form method="post" class="gs-forum-form gs-forum-card"><?= $csrf ?><h2>Categorías y subforos</h2>
<label>Nombre de categoría <input name="category" maxlength="100" required></label>
<label>Nombre del subforo <input name="board" maxlength="100" required></label>
<button name="action" value="board">Añadir subforo</button></form>
<form method="post" class="gs-forum-form gs-forum-card"><?= $csrf ?><h2>Términos restringidos</h2>
<label>Un término por línea <textarea name="words" maxlength="2000" rows="3"><?= $e($words) ?></textarea></label>
<button name="action" value="filter">Guardar filtro</button></form>
</div>
<h2>Hilos y papelera</h2>
<?php if ($topics): ?><form method="post" class="gs-forum-form">
<?= $csrf ?><fieldset><legend>Selecciona hasta 25 hilos</legend>
<ul class="gs-forum-list">
<?php foreach ($topics as $row): ?><li><label><input type="checkbox" name="topics[]" value="<?= (int)$row['id'] ?>"> #<?= (int)$row['id'] ?> · <?= $e($row['title']) ?> · <?= $e($row['board']) ?><?= $row['merged_into']?' · Fusionado':($row['deleted_at']?' · Papelera':($row['closed']?' · Cerrado':'')) ?><?= $row['pinned']?' · Fijado':'' ?></label>
<a href="<?= $e($url) ?>?replies=<?= (int)$row['id'] ?>#gs-forum-replies">Respuestas</a></li><?php endforeach ?>
</ul></fieldset>
<label>Acción <select name="action"><?php foreach (['move'=>'Mover','close'=>'Cerrar','open'=>'Reabrir','pin'=>'Fijar','unpin'=>'Desfijar','merge'=>'Fusionar','split'=>'Dividir desde una respuesta','delete'=>'Enviar a papelera','restore'=>'Restaurar hilo'] as $key=>$label): ?><option value="<?= $e($key) ?>"><?= $e($label) ?></option><?php endforeach ?></select></label>
<label>ID de subforo para mover o ID de hilo para fusionar <input name="target" type="number" min="1" list="gs-forum-boards"></label>
<datalist id="gs-forum-boards"><?php foreach ($boards as $board): if (!$board['id']) continue; ?><option value="<?= (int)$board['id'] ?>"><?= $e($board['name']) ?></option><?php endforeach ?></datalist>
<label>ID de respuesta para dividir <input name="reply" type="number" min="1"></label>
<label>Título del hilo nuevo al dividir <input name="title" maxlength="160"></label>
<button>Aplicar moderación</button>
</form><?php else: ?><p>Aún no hay hilos.</p><?php endif ?>
<?php if ($next): ?><p><a href="?before=<?= $next ?>">Más hilos</a></p><?php endif ?>
<?php if ($topicId): ?><section id="gs-forum-replies"><h2>Respuestas del hilo #<?= $topicId ?></h2>
<?php if (!$replies): ?><p>No hay respuestas.</p><?php endif ?>
<?php foreach ($replies as $row): ?><article class="gs-forum-card">
<p>#<?= (int)$row['id'] ?><?= $row['deleted_at']?' · Papelera':'' ?></p><p><?= $e(mb_substr($row['body'],0,160)) ?></p>
<form method="post"><?= $csrf ?><input type="hidden" name="topics[]" value="<?= $topicId ?>"><input type="hidden" name="reply" value="<?= (int)$row['id'] ?>">
<button name="action" value="<?= $row['deleted_at']?'restore_reply':'delete_reply' ?>"><?= $row['deleted_at']?'Restaurar respuesta':'Enviar a papelera' ?></button></form></article><?php endforeach ?>
<?php if ($replyNext): ?><p><a href="?replies=<?= $topicId ?>&amp;reply_before=<?= $replyNext ?>#gs-forum-replies">Más respuestas</a></p><?php endif ?>
</section><?php endif ?>
</section>
