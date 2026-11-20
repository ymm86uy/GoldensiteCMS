<div class="nx-stack" data-mail-health>
    <h3>Últimas comprobaciones de correo</h3>
    <?php foreach (['Conexión SMTP y TLS'=>$mailConnection,'Mensaje de prueba'=>$mailDelivery] as $label=>$result): ?>
    <div class="nx-form-field"><strong><?= $e($label) ?></strong>
    <?php if (!$result): ?><p>Sin comprobar.</p>
    <?php else: ?><p><?= $e(gmdate('Y-m-d H:i',$result['time']).' UTC') ?> · <?= !$result['current']?'Configuración cambiada: repite la prueba.':($result['ok']?'Prueba aceptada.':'La prueba no se completó.') ?></p>
        <?php if ($result['reference']!==''): ?><p>Referencia: <code><?= $e($result['reference']) ?></code></p><?php endif ?>
        <?php if ($result['current'] && $result['confirmed_at']!==null): ?><p>Recepción confirmada por el administrador · <?= $e(gmdate('Y-m-d H:i',$result['confirmed_at']).' UTC') ?></p><?php endif ?>
    <?php endif ?>
    </div>
    <?php endforeach ?>
    <p class="nx-note">La conexión comprueba TLS. La aceptación SMTP indica que el proveedor aceptó la prueba; comprueba también el buzón y la carpeta de correo no deseado. Estos son resultados históricos y no una supervisión permanente.</p>
</div>
