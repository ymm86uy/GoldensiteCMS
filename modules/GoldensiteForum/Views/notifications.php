<section class="gs-forum">
<p><a href="/forum">Volver al foro</a></p><h1>Notificaciones</h1>
<form method="post" action="/forum/notifications/preferences"><?= $csrf ?>
<label><input type="checkbox" name="direct_email" value="1" <?= $directEmail?'checked':'' ?>> Recibir correo cuando me mencionen o respondan a mis hilos</label>
<button>Guardar preferencia</button></form>
<?php if (!$rows): ?><p>Aún no tienes notificaciones.</p><?php else: ?>
<form method="post" action="/forum/notifications/read"><?= $csrf ?><button>Marcar todas como leídas</button></form>
<ul class="gs-forum-list">
<?php foreach ($rows as $row): ?><li><a href="<?= $e(\Modules\GoldensiteForum\Models\TopicModel::url(['id'=>$row['topic_id'],'slug'=>$row['slug']])) ?>"><?= $e($row['title']) ?></a>
<small><?= $e(['mention'=>'Te mencionaron','reply'=>'Respondieron a tu hilo','follow'=>'Actividad en un hilo seguido'][$row['reason']]) ?><?= $row['read_at']?'':' · Nueva' ?></small></li><?php endforeach ?>
</ul><?php endif ?>
</section>
