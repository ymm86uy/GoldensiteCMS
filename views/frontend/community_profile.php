<?php
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$options=static function(string $selected): void {
    foreach (['off'=>'Privado','member'=>'Cuentas activas','public'=>'Público'] as $mode=>$label) {
        echo '<option value="'.$mode.'"'.($mode===$selected?' selected':'').'>'.$label.'</option>';
    }
};
?>
<section style="max-width:760px;margin:2rem auto;padding:1rem">
<?php if (!empty($missing)): ?><h1>Perfil no disponible</h1>
<?php elseif (!empty($edit)): ?><h1>Goldensite Community Profiles</h1>
<?php if ($message): ?><p role="status"><?= $e($message) ?></p><?php endif ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
<p><label>Nombre visible <input name="display_name" maxlength="80" value="<?= $e($label) ?>"></label></p>
<p><label>Visibilidad del perfil <select name="visibility"><?php $options($visibility) ?></select></label></p>
<?php foreach ($fields as $key=>$name): ?><fieldset><legend><?= $e($name) ?></legend><label><?= $e($name) ?> <textarea name="field[<?= $e($key) ?>]" maxlength="600"><?= $e($values[$key]['value']??'') ?></textarea></label> <label>Privacidad <select name="privacy[<?= $e($key) ?>]"><?php $options($values[$key]['mode']??'off') ?></select></label></fieldset><?php endforeach ?>
<p><label>Historial de aportes aprobados <select name="history_mode"><?php $options($history) ?></select></label></p>
<button>Guardar perfil</button> <a href="/comunidad/perfil/<?= (int)$profileId ?>">Ver perfil</a></form>
<?php else: ?><h1><?= $e($label) ?></h1>
<?php foreach ($visible as $name=>$value): ?><h2><?= $e($name) ?></h2><p><?= nl2br($e($value)) ?></p><?php endforeach ?>
<?php if ($entries): ?><h2>Aportes aprobados</h2><ul><?php foreach ($entries as $entry): ?><li><a href="/biblioteca/ficha/<?= (int)$entry['source_id'] ?>"><?= $e($entry['title']) ?></a></li><?php endforeach ?></ul><?php endif ?>
<?php endif ?></section>
