<div class="nx-stack">
    <header class="nx-page-head"><div><h1>Goldensite ADS</h1><p>Publica campañas de patrocinadores y afiliados en las páginas de tu sitio.</p></div><a class="nx-btn" href="<?= $e(\App\Core\AdminPath::url('/admin/plugins')) ?>">Módulos y extensiones</a></header>
    <?php if ($notice !== ''): ?><p role="status" class="nx-note"><?= $e($notice) ?></p><?php endif; ?>
    <section class="nx-panel"><div class="nx-panel-head"><h2>Campañas</h2><span><?= count($campaigns) ?> de 30</span></div><div class="nx-panel-body nx-stack">
    <?php if (!$active): ?><p>Activa el módulo para publicar y administrar campañas.</p><?php else: ?>
        <p class="nx-note">Las cuentas VIP con sesión iniciada navegan sin anuncios. Si varias campañas activas tienen la misma prioridad y coinciden con una ubicación, se elige una al azar.</p>
        <p class="nx-note">Para mostrar publicidad dentro de una página, usa <code>[goldensite_ads]</code> en un párrafo separado y elige «Dentro del contenido». La imagen es opcional. Usa su ruta relativa a <code>/uploads/</code>.</p>
        <?php $blank=['title'=>'','sponsor'=>'','copy'=>'','destination'=>'','image'=>'','placement'=>'top','page'=>'','priority'=>0,'enabled'=>false,'starts'=>null,'ends'=>null]; $now=time(); ?>
        <?php foreach (['new'=>$blank]+$campaigns as $id=>$ad): ?>
            <?php $new=$id==='new'; $state=match (true) { !$ad['enabled']=>'Pausada', $ad['starts']!==null && $ad['starts']>$now=>'Programada', $ad['ends']!==null && $ad['ends']<=$now=>'Finalizada', default=>'En circulación' }; ?>
            <details <?= $new?'open':'' ?>><summary><?= $new?'Nueva campaña':$e($ad['title'].' · '.$state) ?></summary>
                <form method="post" class="nx-stack">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= $new?'':$e($id) ?>">
                    <div class="nx-field-grid">
                        <label>Título<input required name="title" maxlength="100" value="<?= $e($ad['title']) ?>"></label>
                        <label>Anunciante<input required name="sponsor" maxlength="80" value="<?= $e($ad['sponsor']) ?>"></label>
                        <label>Enlace de destino<input required name="destination" maxlength="512" placeholder="https://patrocinador.example/oferta" value="<?= $e($ad['destination']) ?>"></label>
                        <label>Imagen de la biblioteca<input name="image" maxlength="220" placeholder="banners/oferta.webp" value="<?= $e($ad['image']) ?>"></label>
                        <label>Ubicación<select name="placement"><?php foreach (\App\Core\Ads::POSITIONS as $key=>$label): ?><option value="<?= $e($key) ?>" <?= $ad['placement']===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
                        <label>Limitar a una página<input name="page" maxlength="160" placeholder="/blog o vacío para todas" value="<?= $e($ad['page']) ?>"></label>
                        <label>Inicio (hora del sitio)<input type="datetime-local" name="starts" value="<?= $ad['starts']===null?'':$e(date('Y-m-d\TH:i',$ad['starts'])) ?>"></label>
                        <label>Fin (hora del sitio)<input type="datetime-local" name="ends" value="<?= $ad['ends']===null?'':$e(date('Y-m-d\TH:i',$ad['ends'])) ?>"></label>
                        <label>Prioridad<input type="number" name="priority" min="0" max="99" required value="<?= (int)$ad['priority'] ?>"></label>
                    </div>
                    <label>Texto del anuncio<input name="copy" maxlength="240" value="<?= $e($ad['copy']) ?>"></label>
                    <label><input type="checkbox" name="enabled" value="1" <?= $ad['enabled']?'checked':'' ?>> Activar campaña</label>
                    <div class="nx-actions"><button class="nx-btn primary" name="action" value="save">Guardar campaña</button><?php if (!$new): ?><button class="nx-btn danger" name="action" value="delete" formnovalidate>Eliminar campaña</button><?php endif; ?></div>
                </form>
            </details>
        <?php endforeach; ?>
    <?php endif; ?></div></section>
    <section class="nx-panel"><div class="nx-panel-head"><h2>Resultados por campaña y página</h2></div>
        <div class="nx-panel-body"><p class="nx-note">Hasta 100 combinaciones ordenadas por clics. Los conteos incluyen clics repetidos y se guardan sin identificadores de visitantes.</p>
        <?php if (!$results): ?><p>Todavía no hay clics registrados.</p><?php else: ?>
        <div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Campaña</th><th>Página</th><th>Clics</th></tr></thead><tbody>
        <?php foreach ($results as $row): ?><tr><td><?= $e($campaigns[$row['campaign_id']]['title']??'Campaña eliminada') ?></td><td><?= $e($row['page_path']) ?></td><td><?= (int)$row['clicks'] ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></div>
    </section>
</div>
