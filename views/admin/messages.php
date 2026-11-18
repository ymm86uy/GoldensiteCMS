<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / CONVERSACIONES</div><h1><?= $desk ? "Goldensite Support Desk" : "Conversaciones que importan." ?></h1><p>Solicitudes del formulario de contacto, seguimiento y respuestas con estado de envío.</p></div><a href="/contacto" target="_blank" rel="noopener" class="nx-btn">Formulario público ↗</a></div>
<?php if ($status): ?><div class="nx-alert" role="status"><?= $e($status) ?></div><?php endif ?>
<form method="get" class="nx-filter nx-panel-body"><input name="q" value="<?= $e($q) ?>" placeholder="Buscar" aria-label="Buscar"><select name="state" aria-label="Estado"><?php foreach ([''=>'Todos','unread'=>'Sin leer','read'=>'Leídos','replied'=>'Respondidos','archived'=>'Archivados'] as $key=>$label): ?><option value="<?= $key ?>" <?= $filter===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select><?php if ($desk): ?><select name="ticket" aria-label="Estado del ticket"><option value="">Todos los tickets</option><?php foreach (\App\Core\SupportDesk::STATES as $key=>$label): ?><option value="<?= $key ?>" <?= $ticketFilter===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select><?php endif; ?><button class="nx-btn">Buscar</button></form>
<div class="nx-inbox"><section class="nx-panel"><div class="nx-panel-head"><h2>Mensajes</h2><span class="nx-small"><?= $total ?></span></div><div class="nx-message-list"><?php foreach ($messages as $message): ?><a class="nx-message-item <?= $message['status']==='unread'?'unread':'' ?>" href="?id=<?= $message['id'] ?>&amp;state=<?= $e($filter) ?>&amp;ticket=<?= $e($ticketFilter) ?>&amp;q=<?= rawurlencode($q) ?>"><span class="nx-small"><?= $e($message['name']) ?> · <?= $e(substr($message['created_at'],0,10)) ?></span><strong data-nx-content><?= $e($message['subject']) ?></strong><span class="nx-code nx-small"><?= $e($message['email']) ?></span><span class="nx-pill"><?= $e($message['status']) ?></span><?php if ($desk): ?><span class="nx-pill"><?= $e(\App\Core\SupportDesk::STATES[$message['ticket_state']]??'') ?> · <?= $e(\App\Core\SupportDesk::PRIORITIES[$message['ticket_priority']]??'') ?></span><?php endif; ?></a><?php endforeach ?><?php if (!$messages): ?><p class="nx-empty">No hay resultados.</p><?php endif ?></div><div class="nx-table-foot"><?php if ($page>1): ?><a class="nx-btn small" href="?page=<?= $page-1 ?>&amp;q=<?= rawurlencode($q) ?>&amp;state=<?= $e($filter) ?>&amp;ticket=<?= $e($ticketFilter) ?>">Anterior</a><?php endif ?><?php if ($page*20<$total): ?><a class="nx-btn small" href="?page=<?= $page+1 ?>&amp;q=<?= rawurlencode($q) ?>&amp;state=<?= $e($filter) ?>&amp;ticket=<?= $e($ticketFilter) ?>">Siguiente</a><?php endif ?></div></section><section class="nx-panel"><div class="nx-panel-head"><h2>Detalle de conversación</h2><?= \App\Core\GoldensiteIcon::svg('messages') ?></div><div class="nx-panel-body nx-stack"><?php if ($selected): ?><h2 data-nx-content><?= $e($selected['subject']) ?></h2><p class="nx-small"><?= $e($selected['name'].' <'.$selected['email'].'>') ?></p><div class="nx-actions"><span class="nx-pill"><?= $e($selected['inquiry_type']??'general') ?></span><?php if (!empty($selected['module_id'])): ?><span class="nx-pill"><?= $e(\App\Core\ModuleCatalog::get($selected['module_id'])['name']??$selected['module_id']) ?></span><?php endif ?></div><div class="nx-message-content" data-nx-content><?= nl2br($e($selected['message'])) ?></div>
<?php if ($desk): ?>
<section class="nx-panel nx-panel-body nx-stack">
<h3>Ticket #<?= (int)$selected['id'] ?></h3>
<?php if (\App\Core\IdentityAccess::allows('messages.write')): ?>
<form method="post" class="nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="message_id" value="<?= (int)$selected['id'] ?>">
<label>Estado del ticket<select name="ticket_state"><?php foreach (\App\Core\SupportDesk::STATES as $key=>$label): ?><option value="<?= $key ?>" <?= $selected['ticket_state']===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
<label>Prioridad<select name="ticket_priority"><?php foreach (\App\Core\SupportDesk::PRIORITIES as $key=>$label): ?><option value="<?= $key ?>" <?= $selected['ticket_priority']===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
<label>Responsable<select name="ticket_assignee"><option value="0">Sin asignar</option><?php foreach ($team as $person): ?><option value="<?= (int)$person['id'] ?>" <?= (int)$selected['ticket_assignee']===(int)$person['id']?'selected':'' ?>><?= $e($person['username']) ?></option><?php endforeach; ?></select></label>
<label>Nota interna<textarea name="ticket_note" rows="3" maxlength="4000"></textarea></label>
<button class="nx-btn primary" name="action" value="ticket">Guardar seguimiento</button>
</form>
<?php endif; ?>
<details><summary>Historial de atención</summary>
<?php if (!$history): ?><p>El ticket todavía no tiene movimientos.</p><?php endif; ?>
<?php foreach ($history as $entry): ?>
<article class="nx-message-reply"><small><?= $e($entry['created_at']) ?> · <?= $e($entry['actor']??'Cuenta retirada') ?></small>
<p><?= $e(\App\Core\SupportDesk::STATES[$entry['state']]??'') ?> · <?= $e(\App\Core\SupportDesk::PRIORITIES[$entry['priority']]??'') ?> · <?= $e($entry['responsible']??'Sin responsable activo') ?></p>
<?php if ($entry['note']): ?><p data-nx-content><?= nl2br($e($entry['note'])) ?></p><?php endif; ?>
</article>
<?php endforeach; ?>
</details>
</section>
<?php endif; ?>

<?php if (\App\Core\IdentityAccess::allows('messages.write')): ?><form method="post" class="nx-actions"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="message_id" value="<?= $selected['id'] ?>"><button name="action" value="mark_read" class="nx-btn small">Marcar leído</button><button name="action" value="archive" class="nx-btn small">Archivar</button><button name="action" value="unread" class="nx-btn small">Sin leer</button></form><form method="post" class="nx-stack"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="message_id" value="<?= $selected['id'] ?>"><label>Destinatario<input readonly value="<?= $e($selected['email']) ?>"></label><label>Respuesta<textarea name="reply_message" rows="7" maxlength="20000" required></textarea></label><div class="nx-actions"><button name="action" value="draft" class="nx-btn">Guardar borrador</button><button name="action" value="reply" class="nx-btn primary">Enviar por SMTP</button></div><p class="nx-note">El destinatario se toma del mensaje original. Un envío fallido nunca se marca como respondido.</p></form><?php endif ?>
<?php if (\App\Core\IdentityAccess::allows('messages.write')): ?>
<details>
    <summary class="nx-btn danger small">Eliminar conversación</summary>
    <form method="post" class="nx-stack" style="padding-top:12px">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <input type="hidden" name="message_id" value="<?= (int)$selected['id'] ?>">
        <p class="nx-note">Se eliminarán el mensaje, sus respuestas guardadas y el historial del ticket. Esta acción es permanente.</p>
        <label>Tu contraseña administrativa actual<input type="password" name="current_password" maxlength="72" required autocomplete="current-password"></label>
        <button class="nx-btn danger" name="action" value="delete">Eliminar definitivamente</button>
    </form>
</details>
<?php endif ?>
<?php foreach ($replies as $reply): ?><article class="nx-message-reply"><span class="nx-pill <?= $reply['delivery_state']==='sent'?'good':'' ?>"><?= $e($reply['delivery_state']) ?></span><small><?= $e($reply['created_at']) ?></small><p data-nx-content><?= nl2br($e($reply['body'])) ?></p></article><?php endforeach ?><?php else: ?><p class="nx-empty">Selecciona un mensaje para ver la conversación.</p><?php endif ?></div></section></div>
