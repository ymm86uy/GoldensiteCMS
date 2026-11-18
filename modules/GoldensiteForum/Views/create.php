<section class="gs-forum">
<nav aria-label="Ruta del foro"><a href="/forum">Foro</a> / <a href="/forum/board/<?= $e($board['slug']) ?>"><?= $e($board['name']) ?></a></nav>
<h1>Crear hilo</h1>
<form method="post" enctype="multipart/form-data" class="gs-forum-form">
<?= $csrf ?>
<label>Título <input name="title" maxlength="160" required></label>
<label>Tipo <select name="kind" data-forum-kind>
<?php foreach (\Modules\GoldensiteForum\Models\TopicModel::KINDS as $kind=>$label): if ($kind==='announcement' && !\App\Core\IdentityAccess::allows('forum.moderate')) continue; ?>
<option value="<?= $e($kind) ?>"><?= $e($label) ?></option><?php endforeach ?>
</select></label>
<label data-forum-poll>Opciones para encuestas, una por línea <textarea name="options" maxlength="1210"></textarea></label>
<label>Etiquetas, separadas por comas <input name="tags" maxlength="205" list="gs-forum-tags" data-forum-tags><datalist id="gs-forum-tags"></datalist></label>
<?php require __DIR__.'/editor.php'; ?>
<button>Publicar hilo</button>
</form>
</section>
