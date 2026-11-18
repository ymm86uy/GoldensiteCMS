<?php $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); $notice = $_SESSION['automation_status'] ?? null; unset($_SESSION['automation_status']); ?>
<h1>Goldensite Automation Hub</h1>
<p style="margin:1rem 0">Reglas para artículos, páginas, registros, contactos y formularios. Los envíos externos y las demoras usan la cola privada. El trabajador de módulos debe ejecutarse en el servidor. Máximo 200 reglas.</p>
<?php if ($notice): ?><p role="status"><?= $e($notice['msg']) ?></p><?php endif ?>
<form method="post" style="padding:20px;background:#1e293b;border-radius:12px;margin:20px 0;display:grid;gap:12px">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="create"><label>Demora en segundos (máximo 7 días)<input type="number" name="execution_delay" value="0" min="0" max="604800" required></label>
<label>Nombre <input name="name" required maxlength="150" style="color:#111"></label>
<label>Evento <select name="trigger_event" style="color:#111"><option value="post_published">Artículo publicado</option><option value="page_published">Página publicada</option><option value="form_submitted">Formulario recibido</option><option value="user_registered">Usuario registrado</option><option value="contact_received">Contacto recibido</option></select></label>
<label>Campo de condición (vacío = siempre) <input name="condition_field" maxlength="100" placeholder="title, email, subject..." style="color:#111"></label>
<label>Condición <select name="condition_operator" style="color:#111"><option value="equals">Igual a</option><option value="contains">Contiene</option><option value="greater_than">Mayor que</option><option value="not_empty">No vacío</option></select></label>
<label>Valor de condición <input name="condition_value" maxlength="100" style="color:#111"></label>
<label>Acción <select name="action_type" style="color:#111"><option value="record_event">Registrar evento local</option><option value="send_email">Solicitar envío de correo</option><option value="call_webhook">Webhook saliente HTTPS</option></select></label>
<label>Destino (correo o webhook) <input name="target_value" maxlength="255" style="color:#111"></label>
<button style="background:#38bdf8;color:#111;padding:10px">Crear regla</button></form>
<p class="nx-note">Pausar o eliminar una regla evita ejecutar sus tareas pendientes. La validación es local y no envía mensajes. Las acciones externas se prueban desde el proveedor y el trabajador, con trazabilidad real.</p><h2>Reglas</h2><table style="width:100%;text-align:left"><thead><tr><th>Nombre</th><th>Evento / acción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($automations as $rule): ?><tr><td><?= $e($rule['name']) ?></td><td><?= $e($rule['trigger_event'] . ' / ' . $rule['action_type']) ?></td><td><?= $rule['is_active'] ? 'Activa' : 'Pausada' ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button name="action" value="test_trigger">Validar configuración</button> · <button name="action" value="toggle">Activar / pausar</button> · <button name="action" value="delete" onclick="return confirm('¿Eliminar regla?')">Eliminar</button></form></td></tr><?php endforeach ?>
</tbody></table>
<h2 style="margin-top:20px">Últimas 100 ejecuciones y validaciones</h2><table style="width:100%;text-align:left"><thead><tr><th>Regla</th><th>Estado</th><th>Detalle</th><th>Fecha</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr><td><?= $e($log['automation_name']) ?></td><td><?= $e($log['status']) ?></td><td><?= $e($log['details']) ?></td><td><?= $e($log['created_at']) ?></td></tr><?php endforeach ?></tbody></table>
