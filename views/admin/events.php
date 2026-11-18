<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / EVENTOS</div><h1>Events &amp; Tickets</h1><p>Aforo, emisión y control de acceso.</p></div><?php if ($active): ?><a class="nx-btn primary" href="/eventos">Ver eventos</a><?php endif; ?></div>
<?php if (!$active): ?><p class="nx-alert">Activa el módulo desde Módulos y extensiones.</p><?php return; endif; ?>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif; ?>
<div class="nx-operation-grid">
<section class="nx-panel"><div class="nx-panel-head"><h2>Publicar evento</h2></div><form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Nombre<input name="title" maxlength="150" required></label><label>Lugar<input name="venue" maxlength="150" required></label>
<label>Comienza<input type="datetime-local" name="start" required></label><label>Termina<input type="datetime-local" name="end" required></label>
<label>Aforo<input type="number" name="capacity" min="1" max="10000" required></label>
<button class="nx-btn primary" name="action" value="create">Publicar evento</button>
</form></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Validar entrada</h2></div><form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<p>Escanea el QR con el equipo de acceso o escribe el código. La validación está disponible desde dos horas antes del comienzo.</p>
<label>Código<input name="code" data-event-checkin pattern="[a-f0-9]{48}" maxlength="48" required autocomplete="off"></label>
<button class="nx-btn primary" name="action" value="checkin">Registrar entrada</button>
</form></section></div>
<section class="nx-panel" style="margin-top:24px"><div class="nx-panel-head"><h2>Eventos publicados</h2></div><div class="nx-table-wrap"><table class="nx-table">
<thead><tr><th>Evento</th><th>Comienzo</th><th>Entradas</th><th>Accesos</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr><td><?= $e($event['title'].' · '.$event['venue']) ?></td><td><?= $e(\App\Core\Booking::date($event['starts_at'])) ?></td><td><?= (int)$event['reserved'] ?> / <?= (int)$event['capacity'] ?></td><td><?= (int)$event['checked'] ?></td><td>
<?php if ($event['active']): ?><form method="post" action="<?= $e($base) ?>"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="event" value="<?= (int)$event['id'] ?>"><button class="nx-btn small danger" name="action" value="close">Cerrar evento e invalidar accesos pendientes</button></form><?php else: ?>Cerrado<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section><script src="/assets/events-qr.js" defer></script>
