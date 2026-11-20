<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / SALUD DEL SITIO</div><h1>Comprueba antes de publicar.</h1><p>Requisitos observados, alcance real de cada módulo y estado de tareas. Activar un módulo no demuestra que un proveedor externo esté funcionando.</p></div><a class="nx-btn" href="?export=json">Exportar diagnóstico</a></div>
<?php if ($notice): ?><div class="nx-alert" role="alert"><?= $e($notice) ?></div><?php endif ?>
<section class="nx-panel" id="capas-alojamiento" aria-labelledby="capas-alojamiento-titulo">
    <div class="nx-panel-head"><h2 id="capas-alojamiento-titulo">Capas del alojamiento</h2><span class="nx-note"><?= $e($hosting['checked_at']) ?></span></div>
    <div class="nx-panel-body nx-stack">
        <p class="nx-note">Cada estado describe la observación indicada. Los controles locales y la protección del proveedor se comprueban por separado. Esta consulta no cambia la configuración.</p>
        <?php foreach ($hosting['checks'] as $layer): ?>
        <article class="nx-form-field" data-hosting-check="<?= $e($layer['id']) ?>">
            <div class="nx-check"><strong><?= $e($layer['name']) ?></strong><span class="nx-pill <?= $layer['state']==='checked'?'good':'warn' ?>"><?= $e(['checked'=>'Comprobado','warning'=>'Revisar','unknown'=>'Sin comprobar'][$layer['state']]) ?></span></div>
            <p><?= $e($layer['detail']) ?></p><p class="nx-note"><?= $e($layer['fix']) ?></p>
        </article>
        <?php endforeach ?>
        <a class="nx-btn small" href="<?= $e(\App\Core\AdminPath::url('/admin/waf')) ?>">Abrir centro de seguridad</a>
    </div>
</section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Respaldo y recuperación</h2></div><div class="nx-panel-body nx-stack">
    <p><strong>Último respaldo local</strong><br><?= $backup?$e(date('Y-m-d H:i T',$backup['time']).' · '.$backup['file']):'Sin respaldo local disponible.' ?></p>
    <p><strong>Última restauración comprobada</strong><br><?= $recovery?$e(date('Y-m-d H:i T',$recovery['time']).' · '.$recovery['backup']):'Todavía no hay un ensayo aislado registrado.' ?></p>
    <?php if ($recovery): ?><p>Base de datos restaurada, archivos del ZIP verificados y acceso al panel comprobado.</p><?php endif ?>
</div></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Requisitos locales</h2></div><div class="nx-panel-body nx-stack"><?php foreach ($checks as $check): ?><div class="nx-check"><div><strong><?= $e($check['name']) ?></strong><p><?= $e($check['detail']) ?></p></div><span class="nx-pill <?= $check['ok']?'good':'warn' ?>"><?= $check['ok']?'Comprobado':'Revisar' ?></span></div><?php endforeach ?></div></section>
<section class="nx-panel" id="correo-y-tareas" aria-labelledby="correo-y-tareas-titulo" style="margin-top:24px;scroll-margin-top:5rem">
    <div class="nx-panel-head"><h2 id="correo-y-tareas-titulo">Correo y trabajador de tareas</h2></div>
    <div class="nx-panel-body nx-stack">
        <?php require __DIR__.'/mail_health.php'; ?>
        <div class="nx-check"><div><strong>Estado de las colas</strong><p><?= (int)$queueHealth['due'] ?> pendientes de ejecución · <?= (int)$queueHealth['failed'] ?> fallidas · <?= (int)$queueHealth['uncertain'] ?> inciertas.</p></div><span class="nx-pill <?= $queueHealth['ok']?'good':'warn' ?>"><?= $queueHealth['ok']?'Sin alertas de cola':'Revisar' ?></span></div>
        <?php if ($queueHealth['due']>0 && !$queueHealth['worker_recent']): ?><p role="status">Hay tareas vencidas y no se registró una ejecución del trabajador en los últimos 15 minutos. Revisa su programación.</p><?php endif ?>

        <div class="nx-check"><div><strong>Correo transaccional</strong><p>El servidor SMTP y el remitente se usan para enviar las confirmaciones de Goldensite Love Dating.</p></div><span class="nx-pill <?= $smtpConfigured?'good':'warn' ?>"><?= $smtpConfigured?'Configurado':'Revisar configuración' ?></span></div>
        <a class="nx-btn small" href="<?= $e(\App\Core\AdminPath::url('/admin/settings#correo')) ?>">Abrir configuración de correo</a>
        <div class="nx-check"><div><strong>Trabajador de tareas</strong><p><?= $workerSeen>0?'Última ejecución registrada · '. $e(gmdate('Y-m-d H:i',$workerSeen)).' UTC':'Todavía no hay una ejecución registrada.' ?></p></div></div>
        <h3>Colas privadas</h3>
        <?php foreach ($jobs as $job): ?><p><?= $e(\App\Core\ModuleCatalog::get($job['module_id'])['name']??$job['module_id']) ?> · <?= $e($job['state']) ?>: <?= (int)$job['count'] ?></p><?php endforeach ?>
        <?php if (!$jobs): ?><p class="nx-note">Aún no hay tareas registradas.</p><?php endif ?>
        <p class="nx-note">Las tareas interrumpidas durante una operación externa quedan en estado incierto para revisión. Comprueba si el proveedor las recibió antes de reintentarlas.</p>
    </div>
</section>
<section class="nx-panel" style="margin-top:24px"><div class="nx-panel-head"><h2>Alcance de los <?= count($modules) ?> módulos</h2></div><div class="nx-panel-body nx-stack"><?php foreach ($modules as $module): ?><article class="nx-form-field" id="<?= $e($module['id']) ?>"><h3><?= $e($module['name']) ?></h3><span class="nx-pill"><?= $module['tier'] ?></span> <span class="nx-pill <?= $module['enabled']?'good':'' ?>"><?= $module['enabled']?'Activado':'Sin activar' ?></span><p style="margin-top:14px"><?= $e($module['scope']) ?></p><?php foreach ($module['requirements'] as $requirement): ?><p class="nx-note">• <?= $e($requirement) ?></p><?php endforeach ?></article><?php endforeach ?></div></section>
<section class="nx-panel" style="margin-top:24px"><div class="nx-panel-head"><h2>Revisar tareas recientes</h2></div><div class="nx-panel-body nx-stack"><?php foreach ($recentJobs as $job): ?><details class="nx-form-field"><summary><?= $e(\App\Core\ModuleCatalog::get($job['module_id'])['name']??$job['module_id']) ?> · <?= $e($job['state']) ?> · <?= (int)$job['attempts'] ?> intentos</summary><p class="nx-code nx-small"><?= $e($job['id']) ?></p><?php if (in_array($job['state'],['queued','retry','failed','uncertain'],true)): ?><form method="post" class="nx-stack"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="job_id" value="<?= $e($job['id']) ?>"><label>Contraseña actual<input type="password" name="current_password" required maxlength="72" autocomplete="current-password"></label><?php if ($job['state']==='uncertain'): ?><label><input type="checkbox" name="duplicate_risk" value="1"> He revisado el proveedor y entiendo que reintentar podría duplicar una entrega anterior.</label><?php endif ?><div class="nx-actions"><?php if (in_array($job['state'],['failed','uncertain'],true)): ?><button class="nx-btn" name="job_action" value="retry">Reintentar con nuevo presupuesto</button><?php endif ?><button class="nx-btn danger" name="job_action" value="cancel">Cancelar tarea</button></div></form><?php endif ?></details><?php endforeach ?></div></section>

