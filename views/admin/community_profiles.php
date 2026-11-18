<?php $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<section class="nx-panel" style="padding:1.5rem"><h1>Goldensite Community Profiles</h1>
<p>Define hasta 12 campos de texto. Los usuarios eligen quién puede ver cada campo y sus aportes aprobados.</p>
<?php if ($message): ?><p role="status"><?= $e($message) ?></p><?php endif ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
<label>Campos en formato clave=Etiqueta <textarea name="fields" rows="10" maxlength="1000"><?= $e(implode("\n",array_map(static fn($key,$value)=>$key.'='.$value,array_keys($fields),array_values($fields)))) ?></textarea></label>
<button>Guardar campos</button></form>
<a href="/comunidad/mi-perfil">Abrir mi perfil</a></section>
