<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / AGENDA</div><h1>Booking &amp; Appointments</h1><p>Publica disponibilidad y gestiona citas por profesional.</p></div>
<?php if ($active): ?><a class="nx-btn primary" href="/reservas">Abrir reservas para usuarios</a><?php endif; ?></div>
<?php if (!$active): ?><p class="nx-alert">Activa el módulo desde Módulos y extensiones.</p><?php return; endif; ?>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif; ?>
<div class="nx-operation-grid">
<section class="nx-panel"><div class="nx-panel-head"><h2>Profesionales</h2></div>
<form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Nombre público<input name="name" maxlength="100" required></label>
<button class="nx-btn" name="action" value="professional">Añadir profesional</button>
</form></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Publicar disponibilidad</h2></div>
<form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Profesional<select name="professional" required><option value="">Selecciona</option><?php foreach ($professionals as $person): ?><option value="<?= (int)$person['id'] ?>"><?= $e($person['name']) ?></option><?php endforeach; ?></select></label>
<label>Servicio<input name="service" maxlength="150" required></label>
<label>Comienza<input type="datetime-local" name="start" required></label>
<label>Termina<input type="datetime-local" name="end" required></label>
<p class="nx-note">Zona horaria <?= $e(\App\Core\Booking::timezone()->getName()) ?>.</p>
<button class="nx-btn primary" name="action" value="slot">Publicar horario</button>
</form></section>
</div>
<section class="nx-panel" style="margin-top:24px"><div class="nx-panel-head"><h2>Próximos horarios</h2></div>
<div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Profesional y servicio</th><th>Horario</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php foreach ($slots as $slot): ?>
<tr><td><?= $e($slot['professional']) ?> · <?= $e($slot['service']) ?></td>
<td><?= $e(\App\Core\Booking::date($slot['starts_at'])) ?> – <?= $e(\App\Core\Booking::date($slot['ends_at'])) ?></td>
<td><?= $slot['appointment_id'] ? $e($slot['client_name'].' · '.$slot['client_phone']) : 'Disponible' ?></td>
<td><form method="post" action="<?= $e($base) ?>"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<?php if ($slot['appointment_id']): ?><input type="hidden" name="appointment" value="<?= (int)$slot['appointment_id'] ?>"><button class="nx-btn small danger" name="action" value="cancel">Cancelar cita</button>
<?php else: ?><input type="hidden" name="slot" value="<?= (int)$slot['id'] ?>"><button class="nx-btn small" name="action" value="close">Retirar horario</button><?php endif; ?>
</form></td></tr>
<?php endforeach; ?>
<?php if (!$slots): ?><tr><td colspan="4">Todavía no hay horarios publicados.</td></tr><?php endif; ?>
</tbody></table></div></section>
