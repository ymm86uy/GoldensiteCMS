<link rel="stylesheet" href="/assets/account.css?v=20261104r2">
<section class="nx-account-page"><div class="nx-account-heading"><div><p class="nx-account-eyebrow">GOLDENSITE / EVENTOS</p><h1>Próximos eventos</h1></div></div>
<?php if (!$active): ?><p>La agenda de eventos está fuera de servicio.</p><?php else: ?>
<?php if ($notice): ?><p role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if (!$actor): ?><a class="nx-account-action" href="/login?return_to=%2Feventos">Iniciar sesión para obtener entradas</a><?php endif; ?>
<div class="nx-account-grid">
<?php foreach ($events as $event): ?><article class="nx-account-card"><h2><?= $e($event['title']) ?></h2><p><?= $e($event['venue']) ?> · <?= $e(\App\Core\Booking::date($event['starts_at'])) ?></p>
<?php if ($actor): ?><form method="post" action="/eventos"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="event" value="<?= (int)$event['id'] ?>"><button class="nx-account-action" name="action" value="issue">Obtener entrada</button></form><?php endif; ?></article><?php endforeach; ?>
</div>
<?php if (!$events): ?><p>Todavía no hay eventos próximos.</p><?php endif; ?>
<?php if ($actor): ?><h2 style="margin-top:24px">Mis entradas</h2>
<?php foreach ($tickets as $ticket): ?><article class="nx-account-card"><h3><?= $e($ticket['title']) ?></h3><p><?= $e($ticket['venue']) ?> · <?= $e(\App\Core\Booking::date($ticket['starts_at'])) ?></p>
<?php if (!$ticket['active']): ?><p>Evento cerrado.</p>
<?php elseif ($ticket['state']==='checked'): ?><p>Entrada utilizada.</p>
<?php elseif (strtotime($ticket['ends_at'].' UTC')<=time()): ?><p>Evento finalizado.</p>
<?php else: $code=\App\Core\SecretStore::open($ticket['sealed_code']); ?>
<div data-event-qr="<?= $e(rtrim(\App\Core\Environment::get('APP_URL'),'/').'/admin/events#'.$code) ?>" style="background:white;padding:12px;width:max-content"></div>
<p>Código <code style="overflow-wrap:anywhere"><?= $e($code) ?></code></p>
<?php if (strtotime($ticket['starts_at'].' UTC')>time()): ?><form method="post" action="/eventos"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="event" value="<?= (int)$ticket['event_id'] ?>"><button class="nx-account-action secondary" name="action" value="cancel">Cancelar entrada</button></form><?php endif; ?>
<?php endif; ?></article><?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
</section><script src="/js/qrcode.min.js" defer></script><script src="/assets/events-qr.js" defer></script>
