<?php
$scope=$entity==='page'?'pages':'posts';
$reviewState=$workflow['state']??null;
$actions=match($reviewState) {
    'review'=>['approve_review','review','Aprobar borrador'],
    'approved'=>['schedule_publish','publish','Programar publicación'],
    'scheduled'=>['cancel_schedule','publish','Cancelar programación'],
    default=>['request_review','write','Enviar a revisión'],
};
[$nextAction,$permission,$buttonLabel]=$actions;
$canAdvance=\App\Core\IdentityAccess::allows($scope.'.'.$permission);
?>
<section class="nx-panel-body nx-stack" aria-labelledby="editorial-workflow-title">
    <h3 id="editorial-workflow-title">Revisión y publicación</h3>
    <p role="status"><?= $e(match($reviewState){'review'=>'Pendiente de revisión.','approved'=>'Borrador aprobado.','scheduled'=>'Programado para '.gmdate('d/m/Y H:i',(int)$workflow['publish_at']).' UTC.','stale'=>'El borrador cambió. Vuelve a enviarlo a revisión.',default=>'Borrador guardado. Puedes enviarlo a revisión.'}) ?></p>
    <?php if ($canAdvance): ?>
        <form method="post" class="nx-stack">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="page_id" value="<?= (int)$selected['id'] ?>">
            <input type="hidden" name="revision" value="<?= $e(\App\Core\EditorialHistory::fingerprint($selected)) ?>">
            <?php if ($nextAction==='schedule_publish'): ?><label>Fecha y hora UTC <input name="publish_at" type="datetime-local" required></label><?php endif ?>
            <div class="nx-actions"><button class="nx-btn primary" name="action" value="<?= $e($nextAction) ?>"><?= $e($buttonLabel) ?></button></div>
        </form>
    <?php endif ?>
</section>
