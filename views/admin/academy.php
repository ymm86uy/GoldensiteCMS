<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / FORMACIÓN</div><h1>Academy</h1><p>Cursos, lecciones, evaluación y certificados verificables.</p></div><?php if ($active): ?><a class="nx-btn primary" href="/academia">Ver academia</a><?php endif; ?></div>
<?php if (!$active): ?><p class="nx-alert">Activa el módulo desde Módulos y extensiones.</p><?php return; endif; ?>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif; ?>
<div class="nx-operation-grid">
<section class="nx-panel"><div class="nx-panel-head"><h2>Nuevo curso</h2></div><form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Título<input name="title" maxlength="150" required></label><label>Descripción<textarea name="description" rows="4" maxlength="3000"></textarea></label>
<button class="nx-btn primary" name="action" value="course">Crear borrador</button>
</form></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Añadir lección</h2></div><form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Curso<select name="course" required><option value="">Selecciona un borrador</option><?php foreach ($courses as $course): if ($course['published']) continue; ?><option value="<?= (int)$course['id'] ?>"><?= $e($course['title']) ?></option><?php endforeach; ?></select></label>
<label>Título<input name="title" maxlength="150" required></label><label>Contenido<textarea name="body" rows="6" maxlength="20000" required></textarea></label>
<label>Pregunta de evaluación (opcional)<input name="question" maxlength="250"></label>
<label>Opciones, una por línea<textarea name="answers" rows="3" maxlength="1200"></textarea></label>
<label>Número de la respuesta correcta<input type="number" name="correct" min="1" max="6"></label>
<button class="nx-btn primary" name="action" value="lesson">Añadir lección</button></form></section>
</div>
<section class="nx-panel" style="margin-top:24px"><div class="nx-panel-head"><h2>Cursos</h2></div><div class="nx-panel-body nx-stack">
<p>La publicación fija las lecciones del curso para conservar la validez de sus evaluaciones y certificados.</p>
<?php foreach ($courses as $course): ?><div class="nx-actions"><strong><?= $e($course['title']) ?></strong><span><?= (int)$course['lessons'] ?> lecciones</span>
<?php if ($course['published']): ?><a class="nx-btn small" href="/academia?course=<?= (int)$course['id'] ?>">Ver curso publicado</a>
<?php elseif ($course['lessons']): ?><form method="post" action="<?= $e($base) ?>"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="course" value="<?= (int)$course['id'] ?>"><button class="nx-btn small" name="action" value="publish">Publicar curso</button></form>
<?php else: ?><span>Borrador</span><?php endif; ?></div><?php endforeach; ?>
<?php if (!$courses): ?><p>Todavía no hay cursos.</p><?php endif; ?>
</div></section>
