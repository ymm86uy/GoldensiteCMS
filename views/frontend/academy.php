<link rel="stylesheet" href="/assets/account.css?v=20261104r2">
<section class="nx-account-page"><div class="nx-account-heading"><div><p class="nx-account-eyebrow">GOLDENSITE / ACADEMIA</p><h1><?= $verification?'Verificación de certificado':'Academia' ?></h1></div></div>
<?php if ($verification): ?>
<article class="nx-account-card">
<?php if ($certificate): ?><h2>Certificado verificado</h2><p data-nx-content><?= $e($certificate['certificate_name']) ?> completó <?= $e($certificate['title']) ?>.</p><p><?= $e(\App\Core\Booking::date($certificate['completed_at'])) ?></p>
<?php else: ?><p>El certificado solicitado no está disponible.</p><?php endif; ?>
</article>
<?php elseif (!$active): ?><p>La academia está fuera de servicio.</p>
<?php else: ?>
<?php if ($notice): ?><p role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if ($course): ?>
<h2 data-nx-content><?= $e($course['title']) ?></h2><p data-nx-content><?= nl2br($e($course['description'])) ?></p>
<?php if (!$actor): ?><a class="nx-account-action" href="/login?return_to=%2Facademia">Iniciar sesión</a>
<?php elseif (!$enrollment): ?>
<form method="post" action="/academia"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="course" value="<?= (int)$course['id'] ?>"><button class="nx-account-action" name="action" value="enroll">Inscribirme</button></form>
<?php else: ?>
<p><?= count(array_filter($lessons,static fn($lesson)=>$lesson['completed_at']!==null)) ?> de <?= count($lessons) ?> lecciones completadas</p>
<?php if ($enrollment['certificate']): ?><p><a class="nx-account-action" href="/academia/certificado/<?= $e($enrollment['certificate']) ?>">Ver certificado</a></p><p>Quien tenga ese enlace puede verificar tu nombre, curso y fecha.</p><?php endif; ?>
<?php foreach ($lessons as $lesson): ?><article class="nx-account-card" style="margin:16px 0">
<h3 data-nx-content><?= $e($lesson['title']) ?></h3><p data-nx-content><?= nl2br($e($lesson['body'])) ?></p>
<?php if ($lesson['completed_at']): ?><p>Lección completada.</p>
<?php else: ?><form method="post" action="/academia?course=<?= (int)$course['id'] ?>">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="course" value="<?= (int)$course['id'] ?>"><input type="hidden" name="lesson" value="<?= (int)$lesson['id'] ?>">
<?php if ($lesson['question']): ?><fieldset><legend data-nx-content><?= $e($lesson['question']) ?></legend><?php foreach (json_decode($lesson['answers'],true) as $index=>$answer): ?><label><input type="radio" name="answer" value="<?= $index+1 ?>" required> <?= $e($answer) ?></label><br><?php endforeach; ?></fieldset><?php endif; ?>
<button class="nx-account-action" name="action" value="complete"><?= $lesson['question']?'Comprobar respuesta':'Completar lección' ?></button></form><?php endif; ?>
</article><?php endforeach; ?>
<?php endif; ?><p><a href="/academia">Volver a los cursos</a></p>
<?php else: ?><div class="nx-account-grid">
<?php foreach ($courses as $entry): ?><article class="nx-account-card"><h2 data-nx-content><?= $e($entry['title']) ?></h2><p data-nx-content><?= nl2br($e($entry['description'])) ?></p><a class="nx-account-action" href="/academia?course=<?= (int)$entry['id'] ?>">Ver curso</a></article><?php endforeach; ?>
</div><?php if (!$courses): ?><p>Todavía no hay cursos publicados.</p><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</section>
