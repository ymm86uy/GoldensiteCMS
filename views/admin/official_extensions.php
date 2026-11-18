<section class="nx-panel" id="official-extensions" style="margin-bottom:24px">
    <div class="nx-panel-head"><h2>Canal oficial de extensiones</h2></div>
    <div class="nx-panel-body nx-stack">
        <p><a href="<?= $e(\App\Core\ExtensionPackages::CHANNEL) ?>" target="_blank" rel="noopener">Abrir catálogo oficial <?= $e(\App\Core\ExtensionPackages::CHANNEL) ?></a></p>
        <?php if ($ownerEdition): ?>
            <p>Instala y publica un paquete firmado para que esté disponible en GoldenSiteCMS Comercial.</p>
            <?php if ($ownerUnlocked): ?>
                <form method="post" enctype="multipart/form-data" action="<?= $e($catalogUrl) ?>" class="nx-stack">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <label>Paquete ZIP <input type="file" name="extension_zip" accept=".zip,application/zip" required></label>
                    <label>Firma de la extensión <textarea name="extension_signature" required maxlength="2048" rows="2"></textarea></label>
                    <label>Contraseña administrativa actual <input type="password" name="current_password" autocomplete="current-password" maxlength="72" required></label>
                    <button class="nx-btn primary" name="extension_action" value="publish">Instalar y publicar extensión</button>
                </form>
            <?php else: ?>
                <a class="nx-btn" href="<?= $e(\App\Core\AdminPath::url('/admin/owner')) ?>">Desbloquear Owner</a>
            <?php endif ?>
        <?php else: ?>
            <section class="nx-form-field nx-stack" aria-labelledby="official-core-updates">
                <h3 id="official-core-updates">Actualizaciones del CMS</h3>
                <p>Versión instalada <?= $e($coreVersion) ?>. Consulta los parches firmados para esta instalación.</p>
                <p><a href="<?= $e(\App\Core\CommercialUpdateChannel::BASE) ?>" target="_blank" rel="noopener">Canal oficial <?= $e(\App\Core\CommercialUpdateChannel::BASE) ?></a></p>
                <div class="nx-actions"><a class="nx-btn" href="<?= $e($catalogUrl) ?>?channel=updates#official-core-updates">Buscar actualizaciones del CMS</a><a class="nx-btn" href="<?= $e(\App\Core\AdminPath::url('/admin/backups')) ?>">Abrir Release &amp; Backup</a></div>
                <?php if ($coreChannelMessage!==''): ?><p class="nx-note" role="status"><?= $e($coreChannelMessage) ?></p><?php endif ?>
                <?php if ($coreOffer!==null): ?><p role="status">Disponible <?= $e($coreOffer['version']) ?>. <a href="<?= $e($coreOffer['url']) ?>">Descargar ZIP firmado</a></p><p class="nx-note">Verifica e instala el paquete desde Release &amp; Backup.</p><?php endif ?>
            </section>
            <h3>Extensiones y módulos</h3>
            <p><a href="<?= $e(\App\Core\ExtensionPackages::CHANNEL) ?>index.json" target="_blank" rel="noopener">Catálogo oficial <?= $e(\App\Core\ExtensionPackages::CHANNEL) ?></a></p>
            <div class="nx-actions"><a class="nx-btn" href="<?= $e($catalogUrl) ?>?channel=official#official-extensions">Buscar extensiones oficiales</a></div>
            <?php if (($_GET['channel']??'')==='official' && !$extensionOffers && $errorMsg===''): ?>
                <p class="nx-note">El canal aún no tiene extensiones publicadas.</p>
            <?php endif ?>
            <?php foreach ($extensionOffers as $id=>$offer):
                $module=$offer['module'];
                $current=($installedExtensions[$id]['use_bundled']??false)?null:($installedExtensions[$id]['module']['version']??null);
                $compatible=version_compare($coreVersion,$module['min_core'],'>=');
                $available=$current===null || version_compare($module['version'],$current,'>');
            ?>
                <section class="nx-form-field nx-stack">
                    <strong><?= $e($module['name']) ?> · <?= $e($module['version']) ?> · <?= $module['pro']?'PRO':'FREE' ?></strong>
                    <p><?= $e($module['description']) ?></p>
                    <?php if (($module['native']??null)==='forum'): ?><p class="nx-note">Actualiza el módulo Forum existente y conserva su configuración y activación.</p><?php endif ?>
                    <?php if ($module['database']??false): ?><p class="nx-note">Este paquete añade tablas o columnas propias de la extensión.</p><?php endif ?>
                    <?php if (!$compatible): ?>
                        <p class="nx-note">Requiere GoldenSiteCMS <?= $e($module['min_core']) ?> o posterior.</p>
                    <?php elseif (!$available): ?>
                        <p class="nx-note">Versión instalada <?= $e($current) ?>.</p>
                    <?php else: ?>
                        <form method="post" action="<?= $e($catalogUrl) ?>" class="nx-actions">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="extension_signature" value="<?= $e($offer['code']) ?>">
                            <label>Contraseña administrativa actual <input type="password" name="current_password" autocomplete="current-password" maxlength="72" required></label>
                            <button class="nx-btn primary" name="extension_action" value="install"><?= $current?'Actualizar extensión':'Descargar e instalar' ?></button>
                        </form>
                    <?php endif ?>
                </section>
            <?php endforeach ?>
        <?php endif ?>
        <?php if ($installedExtensions): ?>
            <p class="nx-note">Versiones de extensiones instaladas</p>
            <?php foreach ($installedExtensions as $id=>$item): ?>
                <section class="nx-form-field nx-stack">
                    <strong><?= $e($item['module']['name'].' · '.$item['module']['version']) ?></strong>
                    <?php if ($item['use_bundled']??false): ?><p>Se está usando el Forum incluido en el núcleo.</p><?php endif ?>
                    <?php if (($item['module']['native']??null)==='forum' && !($item['use_bundled']??false) && (!$ownerEdition || $ownerUnlocked)): ?>
                        <details>
                            <summary>Volver al Forum incluido</summary>
                            <form method="post" action="<?= $e($catalogUrl) ?>" class="nx-stack">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="extension_current" value="<?= $e($item['sha256']) ?>">
                                <p>Se conserva la configuración, los temas, las respuestas y los adjuntos.</p>
                                <label>Contraseña administrativa actual <input type="password" name="current_password" autocomplete="current-password" maxlength="72" required></label>
                                <div><button class="nx-btn" name="extension_action" value="bundled_forum">Confirmar retorno</button></div>
                            </form>
                        </details>
                    <?php endif ?>
                    <?php if ($ownerEdition): ?><p><?= $item['published']?'Publicado en el canal oficial.':'Instalado localmente. Publicación retirada.' ?></p><?php endif ?>
                    <?php if ($ownerUnlocked && $item['published']): ?>
                        <details>
                            <summary>Retirar del canal</summary>
                            <form method="post" action="<?= $e($catalogUrl) ?>" class="nx-stack">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="extension_id" value="<?= $e($id) ?>">
                                <input type="hidden" name="extension_current" value="<?= $e($item['sha256']) ?>">
                                <p>Los sitios que ya tienen la extensión conservan su instalación. El canal deja de ofrecerla.</p>
                                <label>Contraseña administrativa actual <input type="password" name="current_password" autocomplete="current-password" maxlength="72" required></label>
                                <div><button class="nx-btn" name="extension_action" value="withdraw">Confirmar retirada</button></div>
                            </form>
                        </details>
                    <?php endif ?>
                    <?php if (isset($item['previous']) && $item['previous']['module']['pro']===$item['module']['pro'] && (!$ownerEdition || $ownerUnlocked)): ?>
                        <details>
                            <summary>Recuperar versión <?= $e($item['previous']['module']['version']) ?></summary>
                            <form method="post" action="<?= $e($catalogUrl) ?>" class="nx-stack">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="extension_id" value="<?= $e($id) ?>">
                                <input type="hidden" name="extension_current" value="<?= $e($item['sha256']) ?>">
                                <p>Se restauran los archivos de la versión anterior y se conservan todas las tablas y sus datos. <?= $ownerEdition?'La extensión se retirará del canal hasta que vuelvas a publicar.':'' ?></p>
                                <label>Contraseña administrativa actual <input type="password" name="current_password" autocomplete="current-password" maxlength="72" required></label>
                                <div><button class="nx-btn" name="extension_action" value="rollback">Confirmar recuperación</button></div>
                            </form>
                        </details>
                    <?php endif ?>
                </section>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</section>
