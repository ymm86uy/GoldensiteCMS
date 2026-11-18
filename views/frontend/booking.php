<link rel="stylesheet" href="/assets/account.css?v=20261104r2">
<section class="nx-account-page">
<div class="nx-account-heading"><div><p class="nx-account-eyebrow">GOLDENSITE / AGENDA</p><h1>Reserva tu cita</h1><p>Elige un profesional y un horario disponible.</p></div></div>
<?php if (!$available): ?><p>La agenda está fuera de servicio.</p><?php else: ?>
<?php if ($notice): ?><p role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if (!$buyer): ?><p><a class="nx-account-action" href="/login?return_to=%2Freservas">Iniciar sesión para reservar</a></p><?php endif; ?>
<div class="nx-account-grid">
<?php foreach ($slots as $slot): ?>
<article class="nx-account-card"><h2><?= $e($slot['service']) ?></h2>
<p><?= $e($slot['professional']) ?><br><?= $e(\App\Core\Booking::date($slot['starts_at'])) ?> – <?= $e(\App\Core\Booking::date($slot['ends_at'])) ?></p>
<?php if ($buyer): ?><form method="post" action="/reservas"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="slot" value="<?= (int)$slot['id'] ?>">
<label>Teléfono de contacto <input type="tel" name="phone" maxlength="30" autocomplete="tel" required></label><br>
<button class="nx-account-action" name="action" value="reserve">Confirmar cita</button></form><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php if (!$slots): ?><p>No hay horarios disponibles en este momento.</p><?php endif; ?>
<?php if ($buyer): ?>
<h2 style="margin-top:28px">Mis citas</h2>
<?php if (!$mine): ?><p>Todavía no tienes citas.</p><?php endif; ?>
<?php foreach ($mine as $appointment): ?><article class="nx-account-card">
<h3><?= $e($appointment['service_requested']) ?> · <?= $e($appointment['professional']) ?></h3>
<p><?= $e(\App\Core\Booking::date($appointment['starts_at'])) ?> · <?= $appointment['status']==='cancelled'?'Cancelada':'Confirmada' ?></p>
<?php if ($appointment['can_cancel']): ?><form method="post" action="/reservas"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="appointment" value="<?= (int)$appointment['id'] ?>"><button class="nx-account-action secondary" name="action" value="cancel">Cancelar cita</button></form><?php endif; ?>
</article><?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
</section>
