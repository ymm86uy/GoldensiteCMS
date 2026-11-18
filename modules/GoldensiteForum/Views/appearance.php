<form method="post" enctype="multipart/form-data" class="gs-forum-form gs-forum-card" id="forum-appearance">
<?= $csrf ?>
<h2>Identidad y permisos</h2>
<div class="gs-forum-grid">
<label>Nombre público del foro <input name="forum_name" maxlength="80" required value="<?= $e($branding['name']) ?>"></label>
<label>Descripción breve <input name="forum_description" maxlength="220" value="<?= $e($branding['description']) ?>"></label>
<label>Fuente del cuerpo <select name="font">
<?php foreach (['inherit'=>'Heredar del sitio']+\App\Core\Typography::fonts() as $id=>$label): ?>
<option value="<?= $e($id) ?>" <?= ($branding['type']['font']??'inherit')===$id?'selected':'' ?>><?= $e($label) ?></option>
<?php endforeach ?></select></label>
<label>Fuente de los títulos <select name="heading_font">
<?php foreach (['inherit'=>'Igual al cuerpo']+\App\Core\Typography::fonts() as $id=>$label): ?>
<option value="<?= $e($id) ?>" <?= $branding['heading_font']===$id?'selected':'' ?>><?= $e($label) ?></option>
<?php endforeach ?></select></label>
<label>Tamaño del cuerpo <input name="size" type="number" min="12" max="28" value="<?= (int)($branding['type']['size']??16) ?>"></label>
<label>Tamaño del título <input name="heading" type="number" min="24" max="80" value="<?= (int)($branding['type']['heading']??44) ?>"></label>
<label>Estilo de portada <select name="style">
<?php foreach (\Modules\GoldensiteForum\Appearance::STYLES as $id=>$label): ?>
<option value="<?= $e($id) ?>" <?= $branding['style']===$id?'selected':'' ?>><?= $e($label) ?></option>
<?php endforeach ?></select></label>
<label>Quién puede participar <select name="access">
<option value="members" <?= $branding['access']==='members'?'selected':'' ?>>Todas las cuentas activas</option>
<option value="roles" <?= $branding['access']==='roles'?'selected':'' ?>>Roles con permiso forum.participate</option>
</select></label>
</div>
<p>En modo restringido, asigna el permiso desde Identity &amp; Access. Los moderadores también pueden participar.</p>
<div class="gs-forum-grid">
<?php foreach (['logo'=>'Logo','cover'=>'Imagen de portada'] as $kind=>$label): ?>
<div class="gs-forum-form">
<label><?= $e($label) ?> <input name="<?= $e($kind) ?>" type="file" accept="image/jpeg,image/png,image/webp"></label>
<?php if ($branding[$kind]): ?><img width="160" src="/forum/brand/<?= $e($kind) ?>/<?= $e($branding[$kind]) ?>" alt="<?= $e($label) ?>"><label><input type="checkbox" name="remove_<?= $e($kind) ?>" value="1"> Quitar imagen</label><?php endif ?>
</div>
<?php endforeach ?>
</div>
<p>JPEG, PNG o WebP, hasta 4 MB. La portada y el monograma se generan a partir del nombre y el estilo cuando no hay imágenes subidas.</p>
<button name="action" value="appearance">Guardar identidad del foro</button>
</form>
