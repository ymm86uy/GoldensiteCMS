<?php
declare(strict_types=1);
$cspSummary=\App\Security\CspReports::summary();
$cspEnabled=\App\Security\CspReports::enabled();
$cspEscape=\App\Core\ConsoleView::escape(...);
$cspAreas=['public'=>'Sitio público','admin'=>'Administración','account'=>'Acceso y cuenta'];
$cspKinds=['inline'=>'Código integrado','eval'=>'Evaluación dinámica','self'=>'Recurso del sitio','external'=>'Recurso externo','other'=>'Otro recurso'];
?>
<section class="nx-panel" id="gs-csp-observation" style="margin-bottom:22px" aria-labelledby="gs-csp-heading">
    <div class="nx-panel-head"><h2 id="gs-csp-heading">Observación CSP</h2><span class="nx-pill"><?= $cspEnabled?'ACTIVADA':'DESACTIVADA' ?></span></div>
    <div class="nx-panel-body nx-stack">
        <p>Revisa los tipos de scripts y recursos que necesitarían autorización con una política más estricta. La observación no impide su ejecución; las protecciones actuales siguen aplicándose.</p>
        <p class="nx-note">Avisos agregados de los últimos 14 días. Son datos enviados por navegadores y pueden ser incompletos o falsificados: no confirman ataques ni bloquean direcciones IP. Para identificar el script concreto, revisa la consola del navegador en el área indicada.</p>
        <?php if (!$cspSummary['available']): ?><p role="alert">No se pudo consultar el almacenamiento de informes. Revisa la migración y el registro del servidor.</p>
        <?php elseif (!$cspSummary['rows']): ?><p>No hay avisos registrados en este período. Esto no demuestra compatibilidad con una política más estricta.</p>
        <?php else: ?>
            <div style="overflow-x:auto"><table><thead><tr><th>Área</th><th>Directiva</th><th>Tipo de recurso</th><th>Avisos</th><th>Último aviso (UTC)</th></tr></thead><tbody>
                <?php foreach ($cspSummary['rows'] as $cspRow): ?><tr><td><?= $cspEscape($cspAreas[$cspRow['area']]??'Otro') ?></td><td><code><?= $cspEscape($cspRow['directive']) ?></code></td><td><?= $cspEscape($cspKinds[$cspRow['blocked_kind']]??'Otro') ?></td><td><?= (int)$cspRow['events'] ?></td><td><?= $cspEscape($cspRow['last_seen']) ?></td></tr><?php endforeach ?>
            </tbody></table></div>
        <?php endif ?>
        <p class="nx-note">El registro CSP conserva solo área, directiva, tipo de recurso, contador y fecha. Descarta URL, consultas, IP, usuario, remitente y muestras de código del informe. Los registros habituales del servidor web y del WAF se gestionan por separado. Se aplican límites de recepción para contener la carga.</p>
        <?php if (\App\Core\IdentityAccess::superuser()): ?>
            <form method="post" action="<?= $cspEscape(\App\Core\AdminPath::url('/admin/waf')) ?>" class="nx-stack">
                <input type="hidden" name="csrf_token" value="<?= $cspEscape(\App\Core\Csrf::generate()) ?>">
                <input type="hidden" name="action" value="save_csp_observation">
                <label><input type="checkbox" name="csp_observe" value="1" <?= $cspEnabled?'checked':'' ?>> Activar observación CSP local</label>
                <label>Contraseña administrativa actual <input type="password" name="current_password" required autocomplete="current-password" maxlength="72"></label>
                <div class="nx-actions"><button class="nx-btn" type="submit">Guardar observación CSP</button></div>
            </form>
        <?php endif ?>
    </div>
</section>
